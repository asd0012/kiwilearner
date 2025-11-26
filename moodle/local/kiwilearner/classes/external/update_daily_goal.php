<?php
namespace local_kiwilearner\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use local_kiwilearner\goal;

final class update_daily_goal extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goal_type' => new external_value(PARAM_INT, '1=XP, 2=LESSON'),
            'xp_target' => new external_value(PARAM_INT, 'XP per day (10/20/30); only used when goal_type=XP'),
        ]);
    }

    public static function execute($goal_type, $xp_target): array {
        global $USER;

        $p = self::validate_parameters(self::execute_parameters(), [
            'goal_type' => $goal_type,
            'xp_target' => $xp_target,
        ]);

        if ((int)$p['goal_type'] === goal::TYPE_XP) {
            $allowed = [10, 20, 30];
            if (!in_array((int)$p['xp_target'], $allowed, true)) {
                throw new invalid_parameter_exception('xp_target must be one of 10, 20, 30');
            }
        }

        goal::upsert($USER->id, (int)$p['goal_type'], (int)$p['xp_target']);
        $r = goal::get($USER->id, (int)$p['goal_type']);

        return [
            'goal_type'    => (int)$r->goal_type,
            'xp_target'    => (int)$r->xp_target,
            'timemodified' => (int)$r->timemodified,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'goal_type'    => new external_value(PARAM_INT, '1=XP, 2=LESSON'),
            'xp_target'    => new external_value(PARAM_INT, 'XP per day'),
            'timemodified' => new external_value(PARAM_INT, 'Unix ts'),
        ]);
    }
}

