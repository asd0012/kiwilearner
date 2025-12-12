<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for local_kiwilearner.
 */
function xmldb_local_kiwilearner_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2024-12-03 – previous step you already had.
    if ($oldversion < 2024120300) {
        $table = new xmldb_table('local_kiwilearner_goal');

        // 1) Add courseid column with default 0.
        if (!$dbman->field_exists($table, new xmldb_field('courseid'))) {
            $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'userid');
            $dbman->add_field($table, $field);
        }

        // 2) Drop old unique index on (userid, goal_type).
        $oldindex = new xmldb_index('usegoa_uix', XMLDB_INDEX_UNIQUE, ['userid', 'goal_type']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        // 3) Add new unique index on (userid, courseid, goal_type).
        $newindex = new xmldb_index('usercoursegoal_uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'goal_type']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2024120300, 'local', 'kiwilearner');
    }

    // 2025-12-10 02– switch to XP-only, per-course goals.
    if ($oldversion < 2025121002) {
        $table = new xmldb_table('local_kiwilearner_goal');

        // 1) Drop unique index that still references goal_type.
        $oldindex = new xmldb_index('usercoursegoal_uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'goal_type']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        // 2) Drop goal_type column if it still exists.
        $field = new xmldb_field('goal_type');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // 3) Drop lesson_target column if it still exists.
        $field = new xmldb_field('lesson_target');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

		// Drop legacy goalxp column if it still exists.
		$goalxpfield = new xmldb_field('goalxp');
		if ($dbman->field_exists($table, $goalxpfield)) {
			$dbman->drop_field($table, $goalxpfield);
		}

        // 4) Ensure xp_target exists and is NOT NULL with a sensible default.
        $field = new xmldb_field('xp_target', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'courseid');

        if (!$dbman->field_exists($table, $field)) {
            // If this is a fresh-ish DB that never had xp_target, add it.
            $dbman->add_field($table, $field);
        } else {
            // Tighten NOT NULL + default for existing installs.
            $dbman->change_field_default($table, $field);
            $dbman->change_field_notnull($table, $field);
        }

        // 5) Add new unique index on (userid, courseid) – XP-only per course.
        $newindex = new xmldb_index('usercourse_uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        upgrade_plugin_savepoint(true, 2025121002, 'local', 'kiwilearner');
    }

    // 2025 12 13 01: seperate xpvalue to xp_participation/xp_correct
    if ($oldversion < 2025121301) {
        $table = new xmldb_table('local_kiwilearner_question_xp');

        // 1) Add xp_participation (INT, NOT NULL, DEFAULT 0) if missing.
        $xppart = new xmldb_field(
            'xp_participation',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            0,
            'questionid' // place after questionid
        );

        if (!$dbman->field_exists($table, $xppart)) {
            $dbman->add_field($table, $xppart);
        } else {
            // Ensure default + notnull are correct if field already exists.
            $dbman->change_field_default($table, $xppart);
            $dbman->change_field_notnull($table, $xppart);
        }

        // 2) Add xp_correct (INT, NOT NULL, DEFAULT 1) if missing.
        $xpcorrect = new xmldb_field(
            'xp_correct',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            1,
            'xp_participation' // place after xp_participation
        );

        if (!$dbman->field_exists($table, $xpcorrect)) {
            $dbman->add_field($table, $xpcorrect);
        } else {
            $dbman->change_field_default($table, $xpcorrect);
            $dbman->change_field_notnull($table, $xpcorrect);
        }

        // 3) Migrate old xpvalue → xp_correct, if xpvalue still exists.
        $xpvalue = new xmldb_field('xpvalue');
        if ($dbman->field_exists($table, $xpvalue)) {
            // Copy existing xpvalue into xp_correct (only where xp_correct is still default).
            $DB->execute("
                UPDATE {local_kiwilearner_question_xp}
                SET xp_correct = xpvalue
                WHERE xpvalue IS NOT NULL
            ");
            // 4) Drop the legacy xpvalue column.
            $dbman->drop_field($table, $xpvalue);
        }

    }

    // 2025 12 13 02: Automatically add fields for question
    if ($oldversion < 2025121303) {
        \local_kiwilearner\customfields\question_fields_manager::ensure_fields_exist();

        // Mark this upgrade step as successful.
        upgrade_plugin_savepoint(true, 2025121303, 'local', 'kiwilearner');
    }

    return true;
}
