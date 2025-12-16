<?php
namespace local_kiwilearner\events;

defined('MOODLE_INTERNAL') || die();

use local_kiwilearner\utils\xp_engine;

class xp_award {

    /**
     * Award participation XP when the quiz attempt is submitted.
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        $attemptid = $event->objectid;
        $userid    = $event->userid;
        $courseid  = $event->courseid;

        xp_engine::award_participation_xp($userid, $courseid, $attemptid);
    }

    /**
     * Award XP for correctness when a question is graded.
     */
    public static function question_graded(\question\event\question_graded $event) {
        $questionid = $event->objectid;
        $userid     = $event->relateduserid;
        $courseid   = $event->courseid;
        $attemptid  = $event->other['attemptid'];

        $grade      = $event->other['newfraction']; // 0..1

        xp_engine::award_correct_xp($userid, $courseid, $questionid, $attemptid, $grade);
    }
}
