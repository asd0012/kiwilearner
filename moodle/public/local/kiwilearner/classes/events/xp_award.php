<?php
namespace local_kiwilearner\events;

defined('MOODLE_INTERNAL') || die();

use context_course;
use local_kiwilearner\utils\xp_engine;


/**
 * Event observer callbacks (thin wrappers).
 *
 * Keep this file focused on:
 *  - extracting ids from events
 *  - calling xp_engine
 *
 * Put all DB + awarding logic in xp_engine.
 */
class xp_award {

    /**
     * Observer: \mod_quiz\event\attempt_submitted
     *
     * Award participation XP for each question in the submitted attempt.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
        public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        // For attempt_submitted, objectid is usually the quiz_attempts.id.
        $attemptid = (int)$event->objectid;
        $userid    = (int)$event->userid;
        $courseid  = (int)$event->courseid;

        if ($attemptid <= 0 || $userid <= 0 || $courseid <= 0) {
            return;
        }

        xp_engine::award_participation_xp($userid, $courseid, $attemptid);
    }

    /**
     * Observer: \question\event\question_graded
     *
     * Award correctness XP after a question is graded.
     *
     * NOTE: Moodle event payloads can vary by question engine / context.
     * This method tries several common keys and bails out safely if it can't.
     *
     * @param \question\event\question_graded $event
     */
    public static function question_graded(\question\event\question_graded $event): void {
        $userid   = (int)$event->userid;
        $courseid = (int)$event->courseid;

        if ($userid <= 0 || $courseid <= 0) {
            return;
        }

        // Try to resolve questionid.
        $questionid = 0;
        if (!empty($event->other['questionid'])) {
            $questionid = (int)$event->other['questionid'];
        } else if (!empty($event->other['question'])) {
            $questionid = (int)$event->other['question'];
        } else {
            // Some events use objectid, but that may also be a questionattempt id.
            $questionid = (int)$event->objectid;
        }

        // Try to resolve attemptid (quiz attempt id).
        $attemptid = 0;
        foreach (['attemptid', 'quizattemptid'] as $k) {
            if (!empty($event->other[$k])) {
                $attemptid = (int)$event->other[$k];
                break;
            }
        }

        // Grade/fraction: commonly available as "fraction" or "grade" in other[].
        $grade = null;
        foreach (['fraction', 'grade', 'mark'] as $k) {
            if (isset($event->other[$k])) {
                $grade = (float)$event->other[$k];
                break;
            }
        }

        if ($questionid <= 0 || $attemptid <= 0 || $grade === null) {
            // If you want to debug: temporarily add debugging() / error_log here.
            return;
        }

        xp_engine::award_correct_xp($userid, $courseid, $questionid, $attemptid, (float)$grade);
    }

}
