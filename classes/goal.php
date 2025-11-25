<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

class goal {
    // Types.
    const TYPE_XP     = 1;
    const TYPE_LESSON = 2;

    // Change if your table is named differently.
    const TABLE = 'local_kiwilearner_goal';

    /** Return the goal row for a user+type, or null. */
    public static function get(int $userid, int $type): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid, 'goal_type' => $type], '*', IGNORE_MISSING);
    }

    /** Insert or update the goal row for a user+type. */
    public static function upsert(int $userid, int $type, int $target): \stdClass {
        global $DB;
        $now = time();

        if ($rec = $DB->get_record(self::TABLE, ['userid' => $userid, 'goal_type' => $type])) {
            $rec->xp_target    = $target;
            $rec->timemodified = $now;
            $DB->update_record(self::TABLE, $rec);
            return $rec;
        }

        $rec = (object)[
            'userid'       => $userid,
            'goal_type'    => $type,
            'xp_target'    => $target,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $rec->id = $DB->insert_record(self::TABLE, $rec);
        return $rec;
    }
}

