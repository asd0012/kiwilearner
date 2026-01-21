<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for local_kiwilearner.
 */
function xmldb_local_kiwilearner_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2025-12-03 – previous step you already had.
    if ($oldversion < 2025120300) {
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

        upgrade_plugin_savepoint(true, 2025120300, 'local', 'kiwilearner');
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

    // 2025 12 13 02: Automatically add fields for question
    if ($oldversion < 2025121303) {
        \local_kiwilearner\customfields\question_fields_manager::ensure_fields_exist();

        // Mark this upgrade step as successful.
        upgrade_plugin_savepoint(true, 2025121303, 'local', 'kiwilearner');
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

        upgrade_plugin_savepoint(true, 2025121301, 'local', 'kiwilearner');

    }

    // 2026-01-19 01: Fix KiwiLearner XP customfield defaults
    if ($oldversion < 2026011901) {

        $defaults = [
            'kiwi_xp_participation' => 0,
            'kiwi_xp_correct'       => 1,
            'kiwi_xp_enabled'       => 1,
        ];

        foreach ($defaults as $shortname => $defaultvalue) {
            $field = $DB->get_record('customfield_field', ['shortname' => $shortname], '*', IGNORE_MISSING);
            if (!$field) {
                continue;
            }

            $config = [];
            if (!empty($field->configdata)) {
                $decoded = json_decode($field->configdata, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }

            // Set the real default used by customfields.
            $config['defaultvalue'] = (int)$defaultvalue;

            // Optional cleanup: remove legacy key if present.
            if (array_key_exists('checkbydefault', $config)) {
                unset($config['checkbydefault']);
            }

            $field->configdata = json_encode($config);
            $DB->update_record('customfield_field', $field);
        }

        // default all questions' KiwiLearner XP for consistency (As previous versions are just for testing)
        $DB->execute("UPDATE {local_kiwilearner_question_xp} SET xp_participation = 1");
        $DB->execute("UPDATE {local_kiwilearner_question_xp} SET xp_correct = 1");
        $DB->execute("UPDATE {local_kiwilearner_question_xp} SET enabled = 1");

        upgrade_plugin_savepoint(true, 2026011901, 'local', 'kiwilearner');
    }

    // 2026-01-12 01: Add streak fields to goal table.
    if ($oldversion < 2026011201) {
        $table = new xmldb_table('local_kiwilearner_goal');

        // currentstreak
        $field = new xmldb_field(
            'currentstreak',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            0,
            'timemodified' // place after timemodified
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // beststreak
        $field = new xmldb_field(
            'beststreak',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            0,
            'currentstreak' // place after currentstreak
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // laststreakdaystart (stores today's midnight timestamp when streak was last updated)
        $field = new xmldb_field(
            'laststreakdaystart',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            0,
            'beststreak' // place after beststreak
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026011201, 'local', 'kiwilearner');
    }

    // 2026-01-21 01: Seed global default XP settings if missing.
    if ($oldversion < 2026012103) {

        // default_xp_participation
        if (!$DB->record_exists('config_plugins', [
            'plugin' => 'local_kiwilearner',
            'name'   => 'default_xp_participation',
        ])) {
            set_config('default_xp_participation', '0', 'local_kiwilearner');
        }

        // default_xp_correct
        if (!$DB->record_exists('config_plugins', [
            'plugin' => 'local_kiwilearner',
            'name'   => 'default_xp_correct',
        ])) {
            set_config('default_xp_correct', '1', 'local_kiwilearner');
        }

        // default_xp_enabled
        if (!$DB->record_exists('config_plugins', [
            'plugin' => 'local_kiwilearner',
            'name'   => 'default_xp_enabled',
        ])) {
            set_config('default_xp_enabled', '1', 'local_kiwilearner');
        }

        // correct_fraction_threshold (1.0 = fully correct only)
        if (!$DB->record_exists('config_plugins', [
            'plugin' => 'local_kiwilearner',
            'name'   => 'correct_fraction_threshold',
        ])) {
            set_config('correct_fraction_threshold', '1.0', 'local_kiwilearner');
        }

        upgrade_plugin_savepoint(true, 2026012103, 'local', 'kiwilearner');
    }


    return true;
}
