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

        error_log('[KIWI XP] attempt_submitted fired: ' . json_encode($event->get_data()));
        xp_engine::award_participation_xp_for_quiz_attempts($userid, $courseid, $attemptid);
    }

    /**
     * Observer: \mod_quiz\event\attempt_graded
     *
     * Award correctness XP for each question in the attempt whose FINAL fraction
     * meets your threshold (e.g. fraction >= 1.0).
     *
     * IMPORTANT:
     * - This event can fire multiple times (manual grading updates, regrades).
     * - xp_engine::apply_xp_for_event idempotency must prevent double-awards.
     *
     * @param \mod_quiz\event\attempt_graded $event
     */
    public static function attempt_graded(\mod_quiz\event\attempt_graded $event): void {
        $attemptid = (int)$event->objectid; // quiz_attempts.id
        $userid    = (int)$event->userid;
        $courseid  = (int)$event->courseid;

        if ($attemptid <= 0 || $userid <= 0 || $courseid <= 0) {
            return;
        }

        error_log('[KIWI XP] attempt_graded fired: ' . json_encode($event->get_data()));
        xp_engine::award_correct_xp_for_quiz_attempt($userid, $courseid, $attemptid);
    }
}
