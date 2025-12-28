<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

/**
 * Business logic for daily XP goal status + streaks.
 */
class goal_status {

    public const STATUS_ACHIEVED = 'achieved';
    public const STATUS_MISSED   = 'missed';
    public const STATUS_UNKNOWN  = 'unknown';

    public static function compute_status(
        ?\stdClass $goal,
        ?int $todayxp
    ): string {
        if (!$goal) {
            return self::STATUS_UNKNOWN;
        }

        $target = isset($goal->xp_target) ? (int)$goal->xp_target : null;

        if ($target === null || $todayxp === null) {
            return self::STATUS_UNKNOWN;
        }

        return ($todayxp >= $target)
            ? self::STATUS_ACHIEVED
            : self::STATUS_MISSED;
    }

    public static function update_streaks(
        string $status,
        int $currentstreak,
        int $beststreak
    ): array {

        $current = max(0, $currentstreak);
        $best    = max(0, $beststreak);

        switch ($status) {
            case self::STATUS_ACHIEVED:
                $current++;
                if ($current > $best) {
                    $best = $current;
                }
                break;

            case self::STATUS_MISSED:
                $current = 0;
                break;

            case self::STATUS_UNKNOWN:
            default:
                // no change
                break;
        }

        return [
            'current' => $current,
            'best'    => $best,
        ];
    }
}

