<?php
namespace local_kiwilearner\utils;

defined('MOODLE_INTERNAL') || die();

use context_course;

class xp_engine {

    /**
     * Award participation XP for each question in a submitted attempt.
     */
    public static function award_participation_xp(int $userid, int $courseid, int $attemptid): void {
        global $DB;

        // Get all question usages from this attempt.
        $slots = $DB->get_records('quiz_attempts', ['id' => $attemptid], '', 'uniqueid');
        if (!$slots) {
            return;
        }

        $usageid = reset($slots)->uniqueid;

        // From question_usage_steps, get question ids used in this attempt.
        $stepqs = $DB->get_records('question_attempts', ['questionusageid' => $usageid], '', 'questionid, id');

        foreach ($stepqs as $qa) {
            $questionid = $qa->questionid;
            self::apply_xp_for_event($userid, $courseid, $questionid, $attemptid, 'participation');
        }
    }

    /**
     * Award XP for correctness after question_graded.
     */
    public static function award_correct_xp(int $userid, int $courseid, int $questionid, int $attemptid, float $grade): void {
        if ($grade <= 0) {
            return; // No XP if incorrect.
        }

        self::apply_xp_for_event($userid, $courseid, $questionid, $attemptid, 'correct');
    }

    /**
     * Internal helper for inserting XP ledger rows and updating daily summaries.
     */
    private static function apply_xp_for_event(int $userid, int $courseid, int $questionid, int $attemptid, string $type): void {
        global $DB;

        // Load XP config (source of truth).
        $cfg = $DB->get_record('local_kiwilearner_question_xp', [
            'questionid' => $questionid,
            'courseid'   => $courseid,
        ]);

        if (!$cfg || !$cfg->enabled) {
            return; // XP disabled for this question.
        }

        // Determine XP amount.
        $xp = ($type === 'participation') ? $cfg->xp_participation : $cfg->xp_correct;
        if ($xp == 0) {
            return;
        }

        // Insert into XP event ledger.
        $event = (object)[
            'userid'      => $userid,
            'courseid'    => $courseid,
            'questionid'  => $questionid,
            'attemptid'   => $attemptid,
            'xpdelta'     => $xp,
            'reason'      => "XP awarded for {$type}",
            'createdby'   => null,
            'timecreated' => time(),
        ];
        $DB->insert_record('local_kiwilearner_xp_event', $event);

        // Update daily summary table.
        self::update_daily_summary($userid, $courseid, $xp);
    }

    /**
     * Update daily XP summary table.
     */
    private static function update_daily_summary(int $userid, int $courseid, int $xp): void {
        global $DB;

        $daystart = strtotime('today midnight');

        $existing = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ]);

        if ($existing) {
            $existing->xptotal += $xp;
            $existing->timemodified = time();
            $DB->update_record('local_kiwilearner_xp_summary_day', $existing);
        } else {
            $record = (object)[
                'userid'       => $userid,
                'courseid'     => $courseid,
                'daystart'     => $daystart,
                'xptotal'      => $xp,
                'timecreated'  => time(),
                'timemodified' => time(),
            ];
            $DB->insert_record('local_kiwilearner_xp_summary_day', $record);
        }
    }
}
