<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for local_kiwilearner.
 */
function xmldb_local_kiwilearner_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2025-12-03 – add goal_type & migrate from legacy "type".
    if ($oldversion < 2025120300) {

        $table = new xmldb_table('local_kiwilearner_goal');

        // 1) Add new column goal_type AFTER userid.
        $goaltype = new xmldb_field(
            'goal_type',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'userid'   // after userid
        );

        if (!$dbman->field_exists($table, $goaltype)) {
            $dbman->add_field($table, $goaltype);
        }

        // 2) If old "type" column exists, copy data and drop it.
        $legacytype = new xmldb_field('type');
        if ($dbman->field_exists($table, $legacytype)) {

            // Copy old values.
            $DB->execute("UPDATE {local_kiwilearner_goal} SET goal_type = type");

            // Old unique index: [userid, type].
            $oldidx = new xmldb_index(
                'uniq_user_type',
                XMLDB_INDEX_UNIQUE,
                ['userid', 'type']
            );
            if ($dbman->index_exists($table, $oldidx)) {
                $dbman->drop_index($table, $oldidx);
            }

            // New unique index: [userid, goal_type].
            $newidx = new xmldb_index(
                'uniq_user_goaltype',
                XMLDB_INDEX_UNIQUE,
                ['userid', 'goal_type']
            );
            if (!$dbman->index_exists($table, $newidx)) {
                $dbman->add_index($table, $newidx);
            }

            // Drop legacy column.
            $dbman->drop_field($table, $legacytype);

        } else {
            // Fresh install, just ensure new unique index exists.
            $newidx = new xmldb_index(
                'uniq_user_goaltype',
                XMLDB_INDEX_UNIQUE,
                ['userid', 'goal_type']
            );
            if (!$dbman->index_exists($table, $newidx)) {
                $dbman->add_index($table, $newidx);
            }
        }

        // Tell Moodle we reached this version.
        upgrade_plugin_savepoint(true, 2025120300, 'local', 'kiwilearner');
    }

    return true;
}

