<?php
namespace local_kiwilearner\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use local_kiwilearner\goal;

class get_daily_goal extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]); // no params for GET
    }

    public static function execute(): array {
        global $USER, $DB;

        // validate (even if empty – Moodle convention)
        self::validate_parameters(self::execute_parameters(), []);

        $rec = $DB->get_record('local_kiwilearner_goal', ['userid' => $USER->id], '*', IGNORE_MISSING);

        return [
            'goal_type' => $rec ? (int)$rec->goal_type : 1,
            'xp_target' => $rec ? (int)$rec->xp_target : 0,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'goal_type' => new external_value(PARAM_INT, '1=lesson,2=xp'),
            'xp_target' => new external_value(PARAM_INT, 'daily XP target'),
        ]);
    }
}

