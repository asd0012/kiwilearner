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
            'goal_type' => new external_value(PARAM_INT, '1=XP, 2=LESSON'),
            'xp_target' => new external_value(PARAM_INT, 'XP per day (1–999); only used when goal_type=XP'),
        ]);
    }

    public static function execute(int $goal_type, int $xp_target): array {
        global $USER;

        $p = self::validate_parameters(self::execute_parameters(), [
            'goal_type' => $goal_type,
            'xp_target' => $xp_target,
        ]);
	$gt = (int)$p['goal_type'];
	$xp = (int)$p['xp_target'];

	if ($gt === \local_kiwilearner\goal::TYPE_XP) {
		if ($xp < 1 || $xp > 999) {
			throw new invalid_parameter_exception('xp_target must be between 1 and 999');
		}
	} else {
		// not an XP goal → xp_target is meaningless; normalize it.
		$xp = 0;
	}

	\local_kiwilearner\goal::upsert($USER->id, $gt, $xp);
	$r = \local_kiwilearner\goal::get($USER->id, $gt);
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

