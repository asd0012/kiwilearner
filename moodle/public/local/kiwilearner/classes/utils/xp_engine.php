<?php
namespace local_kiwilearner\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * XP engine (business logic).
 *
 * Responsibilities:
 *  - load XP config
 *  - enforce idempotency (prevent double-awards)
 *  - write ledger (local_kiwilearner_xp_event)
 *  - maintain daily summary (local_kiwilearner_xp_summary_day)
 *  - H5P IV once/day awarding
 */
class xp_engine {

    /**
     * Award participation XP for each question in a submitted attempt.
     */
    public static function award_participation_xp_for_quiz_attempts(int $userid, int $courseid, int $attemptid): void {
        global $DB;

        error_log("KIWI XP award_participation_xp_for_quiz_attempts fire");

        if ($userid <= 0 || $courseid <= 0 || $attemptid <= 0) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid],
            'id, quiz, userid, uniqueid, state, timefinish, preview',
            IGNORE_MISSING
        );

        error_log("Get quiz attempts:". json_encode($attempt));

        if (!$attempt) {
            return;
        }
        if ((int)$attempt->userid !== $userid) {
            return;
        }


        $usageid = (int)$attempt->uniqueid;
        error_log("Get usage id:{$usageid}");

        if ($usageid <= 0) {
            return;
        }

        // question_attempts rows for this usage = all questions in this attempt.
        $qas = $DB->get_records('question_attempts', ['questionusageid' => $usageid], '', 'id, questionid');
        if (!$qas) {
            return;
        }
        error_log("Get question attempts:". json_encode($qas));

        foreach ($qas as $qa) {
            $questionid = (int)$qa->questionid;
            if ($questionid <= 0) {
                continue;
            }
            error_log("Call apply_xp_for_event by question attempt:". json_encode($qa));
            $reason = 'quiz_question_participate:' . $questionid;
            self::apply_xp_for_event(
                $userid,
                $courseid,
                $questionid,
                $attemptid,
                'quiz_participation',
                $reason,
                null,
                true,
                null
            );
        }
    }


    /**
     * Award "correct" XP for each question in a quiz attempt whose FINAL fraction
     * meets the global threshold.
     *
     * Intended trigger: \mod_quiz\event\attempt_graded
     *
     * Uses question_attempt_steps to read the last step fraction for each question attempt.
     */
    public static function award_correct_xp_for_quiz_attempt(int $userid, int $courseid, int $attemptid): void {
        global $DB;

        if ($userid <= 0 || $courseid <= 0 || $attemptid <= 0) {
            return;
        }

        // Threshold for "correct" (0.0 .. 1.0)
        $threshold = get_config('local_kiwilearner', 'correct_fraction_threshold');
        $threshold = ($threshold === null || $threshold === '') ? 1.0 : (float)$threshold;

        if ($threshold < 0.0) {
            $threshold = 0.0;
        } else if ($threshold > 1.0) {
            $threshold = 1.0;
        }

        // Load the quiz attempt to get the usageid (questionusageid).
        $attempt = $DB->get_record(
            'quiz_attempts',
            ['id' => $attemptid],
            'id, userid, uniqueid, preview',
            IGNORE_MISSING
        );

        if (!$attempt) {
            return;
        }
        if ((int)$attempt->userid !== $userid) {
            return;
        }
        // Skip teacher preview attempts.
        if (!empty($attempt->preview)) {
            return;
        }

        $usageid = (int)$attempt->uniqueid;
        if ($usageid <= 0) {
            return;
        }

        // Fetch each question attempt's final fraction in one query.
        // We take the last step (max sequencenumber) for each questionattemptid.
        $sql = "
            SELECT
                qa.id AS qaid,
                qa.questionid AS questionid,
                qas.fraction AS fraction
            FROM {question_attempts} qa
            JOIN (
                SELECT questionattemptid, MAX(sequencenumber) AS maxseq
                FROM {question_attempt_steps}
                GROUP BY questionattemptid
            ) last
            ON last.questionattemptid = qa.id
            JOIN {question_attempt_steps} qas
            ON qas.questionattemptid = qa.id
            AND qas.sequencenumber = last.maxseq
            WHERE qa.questionusageid = :usageid
        ";

        $rows = $DB->get_records_sql($sql, ['usageid' => $usageid]);
        if (!$rows) {
            return;
        }

        foreach ($rows as $r) {
            $questionid = (int)$r->questionid;
            if ($questionid <= 0) {
                continue;
            }

            // Fraction may be NULL for "gaveup"/incomplete.
            if ($r->fraction === null) {
                continue;
            }

            $fraction = (float)$r->fraction;
            if ($fraction + 1e-9 < $threshold) {
                continue;
            }

            // Award correct XP for this question in this attempt.
            // Use a unique reason per question (and attemptid is included in idempotency).
            $reason = 'quiz_question_correct:' . $questionid;

            self::apply_xp_for_event(
                $userid,
                $courseid,
                $questionid,
                $attemptid,
                'quiz_correct',   // any non-'participation' type uses correct XP in your apply_xp_for_event()
                $reason,
                null,
                true,
                null
            );
        }
    }


    /**
     * Insert XP ledger rows and update daily summaries.
     *
     * Idempotency:
     * Prevent double-awards by checking if we already wrote an xp_event row for
     * (userid, courseid, questionid, attemptid, reason).
     *
     * NOTE: This idempotency uses "reason" as the type marker (no schema changes required).
     */
    private static function apply_xp_for_event(
        int $userid,
        int $courseid,
        ?int $questionid,
        ?int $attemptid,
        string $type,
        ?string $reasonoverride = null,
        ?int $xpoverride = null,
        bool $onceperday = false,
        ?int $ts = null
    ): bool {
        global $DB;

        // Basic sanity.
        if ($userid <= 0 || $courseid <= 0) {
            error_log("[KIWI XP] apply_xp_for_event: invalid ids userid=$userid courseid=$courseid");
            return false;
        }

        $reason = $reasonoverride ?? ('kiwilearner:' . $type);

        // Determine XP.
        $xp = null;

        $defaultpart = (int)(get_config('local_kiwilearner', 'default_xp_participation') ?? 0);
        $defaultcorrect = (int)(get_config('local_kiwilearner', 'default_xp_correct') ?? 1);
        $defaultenabled = (int)(get_config('local_kiwilearner', 'default_xp_enabled') ?? 1);
        $defaultenabled = $defaultenabled ? 1 : 0;

        if ($xpoverride !== null) {
            $xp = (int)$xpoverride;
        } else {
            // For Moodle-question based awarding, config is required.
            if (empty($questionid) || $questionid <= 0) {
                error_log("[KIWI XP] apply_xp_for_event: missing questionid for type=$type reason=$reason");
                return false;
            }

            // get record from question_xp table
            // right now, xp config load by questionid only

            $cfgs = $DB->get_records('local_kiwilearner_question_xp',
                ['questionid' => $questionid],
                'timemodified DESC, id DESC',
                '*',
                0,
                1
            );
            $cfg = $cfgs ? reset($cfgs) : null;
            $count = $DB->count_records('local_kiwilearner_question_xp', ['questionid' => $questionid]);
            if ($count > 1) {
                error_log("[KIWI XP] Multiple config rows for questionid=$questionid; using newest.");
            }

            // $cfg = $DB->get_record('local_kiwilearner_question_xp', [
            //     'questionid' => (int)$questionid,
            //     'courseid'   => (int)$courseid,
            // ], '*', IGNORE_MISSING);

            $isparticipation = in_array($type, ['participation', 'quiz_participation'], true);

            if (!$cfg) {
                if (!$defaultenabled) {
                    error_log("[KIWI XP] apply_xp_for_event: no per-question config and defaults disabled questionid=$questionid courseid=$courseid");
                    return false;
                }
                $xp = $isparticipation ? $defaultpart : $defaultcorrect;
                error_log("[KIWI XP] apply_xp_for_event: using DEFAULT xp=$xp (no config row) questionid=$questionid courseid=$courseid");

            } else {
                if (empty($cfg->enabled)) {
                    error_log("[KIWI XP] apply_xp_for_event: config disabled questionid=$questionid courseid=$courseid");
                    return false;
                }
                $xp = $isparticipation ? (int)$cfg->xp_participation : (int)$cfg->xp_correct;
                error_log("[KIWI XP] apply_xp_for_event: get xp=$xp");

            }

        }

        if ((int)$xp === 0) {
            error_log("[KIWI XP] apply_xp_for_event: xp=0, skipping reason=$reason questionid=$questionid attemptid=$attemptid");
            return false;
        }

        $now = $ts ?? time();

        if ($onceperday) {
            $daystart = self::daystart_for_user($userid, $now); // or site policy
            $dayend   = $daystart + DAYSECS;

            // Once-per-day guard: same user+course+reason within day window.
            // reason carries questionid
            $already = $DB->record_exists_select(
                'local_kiwilearner_xp_event',
                'userid = ? AND courseid = ? AND reason = ? AND timecreated >= ? AND timecreated < ?',
                [$userid, $courseid, $reason, $daystart, $dayend]
            );
        } else {
            // existing idempotency:
            $already = $DB->record_exists('local_kiwilearner_xp_event', [
                'userid'     => $userid,
                'courseid'   => $courseid,
                'questionid' => $questionid,
                'attemptid'  => $attemptid,
                'reason'     => $reason,
            ]);
        }

        if ($already) {
            error_log("[KIWI XP] apply_xp_for_event: already awarded reason=$reason questionid=$questionid attemptid=$attemptid");
            return false;
        }

        // Write ledger row.
        $DB->insert_record('local_kiwilearner_xp_event', (object)[
            'userid'      => $userid,
            'courseid'    => $courseid,
            'questionid'  => $questionid,
            'attemptid'   => $attemptid,
            'xpdelta'     => (int)$xp,
            'reason'      => $reason,
            'createdby'   => null,
            'timecreated' => $now,
        ]);

        // Update daily summary.
        self::update_daily_summary($userid, $courseid, (int)$xp, $now);

        error_log("[KIWI XP] apply_xp_for_event: INSERTED xp=$xp reason=$reason questionid=$questionid attemptid=$attemptid");

        return true;
    }


    /**
     * Award XP for a correct H5P Interactive Video interaction once per day.
     *
     * We store the H5P "question id" (subContentId) inside reason:
     *   h5piv_correct:<subcontentid>
     *
     * @return bool true if awarded, false if already awarded today
     */
    public static function award_h5p_correct_once_per_day(
        int $userid,
        int $courseid,
        string $subcontentid,
        ?int $xpdelta = null
    ): bool {
        global $DB;
        
        // use default_xp_correct if xp delta is not provided
        if ($xpdelta === null || $xpdelta <= 0) {
            $xpdelta = (int)get_config('local_kiwilearner', 'default_xp_correct') ?: 1;
        }

        $subcontentid = trim($subcontentid);
        if ($userid <= 0 || $courseid <= 0 || $subcontentid === '' || $xpdelta === 0) {
            return false;
        }

        $reason = 'h5piv_correct:' . $subcontentid;

        return self::apply_xp_for_event(
            $userid,
            $courseid,
            null,      // questionid
            null,      // attemptid
            'h5piv',   // type label (not used)
            $reason,   // reasonoverride
            $xpdelta,   // xpoverride
            true
        );
        
    }

    /**
     * Update daily XP summary table.
     * Uses Moodle's user-midnight, not server timezone midnight.
     */
    private static function update_daily_summary(int $userid, int $courseid, int $xp, int $now): void {
        global $DB;

        $daystart = self::daystart_for_user($userid, $now);

        $existing = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ], '*', IGNORE_MISSING);

        if ($existing) {
            $existing->xptotal = (int)$existing->xptotal + (int)$xp;
            $existing->timemodified = $now;
            $DB->update_record('local_kiwilearner_xp_summary_day', $existing);
        } else {
            $DB->insert_record('local_kiwilearner_xp_summary_day', (object)[
                'userid'       => $userid,
                'courseid'     => $courseid,
                'daystart'     => $daystart,
                'xptotal'      => (int)$xp,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * IMPORTANT:
     * - timecreated is always stored as UTC timestamp
     * - daystart is derived using USER timezone
     * - Never mix site/user timezone within the same table
     */
    private static function daystart_for_user(int $userid, int $ts): int {
        global $DB;

        // Get the user's timezone (fallback to Moodle/site default).
        $user = $DB->get_record('user', ['id' => $userid], 'id,timezone', MUST_EXIST);

        // usergetmidnight can take a user object in most Moodle versions.
        return usergetmidnight($ts, $user);
    }

    private static function daystart_for_site(int $ts): int {
        $tzname = \core_date::get_server_timezone(); // site/server tz in Moodle terms
        $tz = new \DateTimeZone($tzname);

        $dt = new \DateTime('@' . $ts);
        $dt->setTimezone($tz);
        $dt->setTime(0, 0, 0);
        return $dt->getTimestamp();
    }


    public static function record_dailyquiz_correct(
        int $userid,
        int $courseid,
        int $questionid,
        int $daystart,
        int $xpvalue = 1
    ): void {
        global $DB;

        if ($userid <= 0 || $courseid <= 0 || $questionid <= 0 || $daystart <= 0 || $xpvalue <= 0) {
            return;
        }

        $attemptid = $daystart;
        $reason = 'kiwilearner:dailyquiz_correct';

        if ($DB->record_exists('local_kiwilearner_xp_event', [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => $questionid,
            'attemptid' => $attemptid,
            'reason' => $reason,
        ])) {
            return;
        }

        $now = time();

        $DB->insert_record('local_kiwilearner_xp_event', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => $questionid,
            'attemptid' => $attemptid,
            'xpdelta' => $xpvalue,
            'reason' => $reason,
            'createdby' => null,
            'timecreated' => $now,
        ]);

        self::update_daily_summary($userid, $courseid, $xpvalue, $now);
    }
}
