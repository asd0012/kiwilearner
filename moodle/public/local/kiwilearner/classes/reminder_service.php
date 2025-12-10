<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

class reminder_service {

    public function send_for_slot(string $slot): void {
        global $DB;

        mtrace("KiwiLearner: reminder_service::send_for_slot({$slot})");

        // Real logic will go here in the next step.
    }
}

