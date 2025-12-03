<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

class goal {
    public const TYPE_XP      = 0;
    public const TYPE_LESSON  = 1;

    /**
     * Get the single goal row for this user (we keep at most one row per user).
     */
    public static function get(int $userid, int $goaltype): ?\stdClass {
        global $DB;

        // We ignore $goaltype here – there is only one row per user.
        $record = $DB->get_record(
            'local_kiwilearner_goal',
            ['userid' => $userid],
            '*',
            IGNORE_MISSING
        );

        return $record ?: null;
    }

    /**
     * Insert or update the goal row for this user.
     *
     * - First call: inserts one row.
     * - Later calls: always update that same row (no more duplicate key).
     *
     * $xptarget / $lessontarget:
     *   null means "do not change this column".
     */
    public static function upsert(
        int $userid,
        int $goaltype,
        ?int $xptarget,
        ?int $lessontarget
    ): void {
        global $DB;

        $now = time();

        // Normalise type value just in case.
        if (!in_array($goaltype, [self::TYPE_XP, self::TYPE_LESSON], true)) {
            $goaltype = self::TYPE_XP;
        }

        // Look up by *userid only* – there should be at most one row per user.
        $existing = $DB->get_record(
            'local_kiwilearner_goal',
            ['userid' => $userid],
            '*',
            IGNORE_MISSING
        );

        if ($existing) {
            // --- UPDATE PATH ---
            $existing->goal_type = $goaltype;

            // Only overwrite targets when caller actually sent a value.
            if ($xptarget !== null) {
                $existing->xp_target = $xptarget;
            }
            if ($lessontarget !== null) {
                $existing->lesson_target = $lessontarget;
            }

            $existing->timemodified = $now;
            $DB->update_record('local_kiwilearner_goal', $existing);

        } else {
            // --- INSERT PATH (first ever goal for this user) ---
            $record = (object) [
                'userid'        => $userid,
                'goal_type'     => $goaltype,
                'xp_target'     => $xptarget,
                'lesson_target' => $lessontarget,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ];

            $DB->insert_record('local_kiwilearner_goal', $record);
        }
    }
}

