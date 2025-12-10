<?php
declare(strict_types=1);

namespace local_kiwilearner\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_kiwilearner\goal; 
use core\exception\invalid_parameter_exception;  

final class update_daily_goal extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'xp_target' => new external_value(PARAM_INT, 'XP per day (1–999)'),
        ]);
    }

    public static function execute(int $goal_type, int $xp_target): array {
        global $USER;

        $p = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'xp_target' => $xp_target,
        ]);

        $cid = (int)$p['courseid'];
        $xp = (int)$p['xp_target'];

        // Validate XP range
        if ($xp < 1 || $xp > 999) {
            throw new invalid_parameter_exception('xp_target must be between 1 and 999');
        }

        // Persist goal
        goal::upsert($USER->id, $gt, $xp);

        // Fetch the saved record for return payload
        $r = goal::get($USER->id, $gt);
        return [
            'goal_type'    => (int)$r->goal_type,
            'xp_target'    => (int)$r->xp_target,
            'timemodified' => (int)$r->timemodified,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid'    => new external_value(PARAM_INT, 'course ID'),
            'xp_target'    => new external_value(PARAM_INT, 'XP target per day (1–999)'),
            'timemodified' => new external_value(PARAM_INT, 'Unix timestamp of last update'),
        ]);
    }
}

