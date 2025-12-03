<?php
declare(strict_types=1);

namespace local_kiwilearner\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class get_daily_goal extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER, $DB;

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

