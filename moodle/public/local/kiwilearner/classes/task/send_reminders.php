<?php
namespace local_kiwilearner\task;

defined('MOODLE_INTERNAL') || die();

class send_reminders extends \core\task\scheduled_task {

    public function get_name(): string {
        // Shows up in admin UI > Scheduled tasks.
        return get_string('task_send_reminders', 'local_kiwilearner');
    }

    public function execute() {
        global $CFG;

        mtrace('KiwiLearner: send_reminders task starting');

        // 1) Decide which slot this run represents (for now based on hour).
        $hour = (int) date('G'); // 0-23 server time

        if ($hour === 9) {
            $slot = 'morning';
        } else if ($hour === 14) {
            $slot = 'afternoon';
        } else if ($hour === 19) {
            $slot = 'evening';
        } else {
            // Shouldn't really happen but be defensive.
            $slot = 'unknown';
        }

        // 2) Delegate the actual logic to a service class (easy to change later).
        $service = new \local_kiwilearner\reminder_service();
        $service->send_for_slot($slot);

        mtrace('KiwiLearner: send_reminders task finished');
    }
}

