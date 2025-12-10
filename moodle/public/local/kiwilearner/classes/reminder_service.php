<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple service class for sending daily goal reminders.
 *
 * For now it just logs which slot ran.
 */
class reminder_service {

    /**
     * Send reminders for a given time slot: 'morning', 'afternoon', 'evening', 'unknown'.
     */
    public function send_for_slot(string $slot): void {
        mtrace("KiwiLearner: reminder_service::send_for_slot({$slot})");
        // TODO: later - query users + goals + XP, call message API, etc.
    }
}

