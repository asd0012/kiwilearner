<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

class goal {
    public const TYPE_XP      = 0;

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
        int $courseid,
        int $xp_target,

    ): void {
        global $DB;

        // Safety check
        if ($xp_target < 1 || $xp_target > 999) {
            throw new \moodle_exception('invalidxptarget', 'local_kiwilearner',
                '', null, 'XP target must be between 1 and 999.');
        }

        $now = time();

        $data = (object)[
            'userid'       => $userid,
            'courseid'     => $courseid,
            'xp_target'    => $xp_target,
            'timemodified' => $now,
        ];

        // Look up by *userid only* – there should be at most one row per user.
        $existing = $DB->get_record(
            'local_kiwilearner_goal',
            ['userid' => $userid , 'courseid' => $courseid],
            '*',
            IGNORE_MISSING
        );

        if ($existing) {
            $data->id          = $existing->id;
            $data->timecreated = $existing->timecreated;
            $DB->update_record('local_kiwilearner_goal', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('local_kiwilearner_goal', $data);
        }
    }
}

