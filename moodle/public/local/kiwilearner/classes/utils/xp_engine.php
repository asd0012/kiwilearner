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
    public static function award_participation_xp(int $userid, int $courseid, int $attemptid): void {
        global $DB;

        error_log("KIWI XP award_participation_xp fire");

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

        // Only award when finished.
        if (empty($attempt->timefinish) || $attempt->state !== 'finished') {
            error_log("Attempt not finished, cancel awarding.");
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
            self::apply_xp_for_event($userid, $courseid, $questionid, $attemptid, 'participation');
        }
    }

    /**
     * Award XP for correctness after question_graded.
     * $grade is usually 0..1 (fraction).
     */
    public static function award_correct_xp(int $userid, int $courseid, int $questionid, int $attemptid, float $grade): void {
        if ($userid <= 0 || $courseid <= 0 || $questionid <= 0 || $attemptid <= 0) {
            return;
        }
        if ($grade <= 0) {
            return; // No XP if incorrect.
        }

        self::apply_xp_for_event($userid, $courseid, $questionid, $attemptid, 'correct');
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
    private static function apply_xp_for_event(int $userid, int $courseid, int $questionid, int $attemptid, string $type): void {
        global $DB;

        // Load XP config.
        $cfg = $DB->get_record('local_kiwilearner_question_xp', [
            'questionid' => $questionid,
            'courseid'   => $courseid,
        ], '*', IGNORE_MISSING);

        if (!$cfg || empty($cfg->enabled)) {
            return;
        }

        $xp = ($type === 'participation') ? (int)$cfg->xp_participation : (int)$cfg->xp_correct;
        if ($xp === 0) {
            return;
        }

        // Idempotency guard (prevents double awarding).
        $reason = 'kiwilearner:' . $type;
        $already = $DB->record_exists('local_kiwilearner_xp_event', [
            'userid'     => $userid,
            'courseid'   => $courseid,
            'questionid' => $questionid,
            'attemptid'  => $attemptid,
            'reason'     => $reason,
        ]);
        if ($already) {
            return;
        }

        $now = time();

        // Write ledger row.
        $DB->insert_record('local_kiwilearner_xp_event', (object)[
            'userid'      => $userid,
            'courseid'    => $courseid,
            'questionid'  => $questionid,
            'attemptid'   => $attemptid,
            'xpdelta'     => $xp,
            'reason'      => $reason,
            'createdby'   => null,
            'timecreated' => $now,
        ]);

        // Update daily summary.
        self::update_daily_summary($userid, $courseid, $xp, $now);
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
        int $xpdelta = 1
    ): bool {
        global $DB;

        $subcontentid = trim($subcontentid);
        if ($userid <= 0 || $courseid <= 0 || $subcontentid === '' || $xpdelta === 0) {
            return false;
        }

        $now = time();
        $daystart = usergetmidnight($now);
        $dayend   = $daystart + DAYSECS;

        $reason = 'h5piv_correct:' . $subcontentid;

        // Enforce once/day per user per interaction.
        $exists = $DB->record_exists_select(
            'local_kiwilearner_xp_event',
            'userid = ? AND courseid = ? AND reason = ? AND timecreated >= ? AND timecreated < ?',
            [$userid, $courseid, $reason, $daystart, $dayend]
        );

        if ($exists) {
            return false;
        }

        // Insert ledger.
        $DB->insert_record('local_kiwilearner_xp_event', (object)[
            'userid'      => $userid,
            'courseid'    => $courseid,
            'questionid'  => null, // H5P interaction is not Moodle question.id
            'attemptid'   => null,
            'xpdelta'     => $xpdelta,
            'reason'      => $reason,
            'createdby'   => null,
            'timecreated' => $now,
        ]);

        // Update daily summary (same-day bucket).
        self::update_daily_summary($userid, $courseid, $xpdelta, $now);

        return true;
    }

    /**
     * Update daily XP summary table.
     * Uses Moodle's user-midnight, not server timezone midnight.
     */
    private static function update_daily_summary(int $userid, int $courseid, int $xp, int $now): void {
        global $DB;

        $daystart = usergetmidnight($now);

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
}
