<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for local_kiwilearner.
 */
function xmldb_local_kiwilearner_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2025-11-21 — add goal_type and migrate from legacy type.
    if ($oldversion < 2025112106) {
        $table = new xmldb_table('local_kiwilearner_goal');

        // 1) Add new column goal_type AFTER userid.
        $goaltype = new xmldb_field('goal_type', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $goaltype)) {
            $dbman->add_field($table, $goaltype);
        }

        // 2) If legacy 'type' exists, copy data over.
        $legacytype = new xmldb_field('type');
        if ($dbman->field_exists($table, $legacytype)) {
            $DB->execute('UPDATE {local_kiwilearner_goal} SET goal_type = type');

            // Replace unique index: [userid, type] -> [userid, goal_type].
            $oldidx = new xmldb_index('uniq_user_type', XMLDB_INDEX_UNIQUE, ['userid', 'type']);
            if ($dbman->index_exists($table, $oldidx)) {
                $dbman->drop_index($table, $oldidx);
            }
            $newidx = new xmldb_index('uniq_user_goaltype', XMLDB_INDEX_UNIQUE, ['userid', 'goal_type']);
            if (!$dbman->index_exists($table, $newidx)) {
                $dbman->add_index($table, $newidx);
            }

            // Optional: drop legacy column.
            $dbman->drop_field($table, $legacytype);
        } else {
            // Fresh install: ensure the new unique index exists.
            $newidx = new xmldb_index('uniq_user_goaltype', XMLDB_INDEX_UNIQUE, ['userid', 'goal_type']);
            if (!$dbman->index_exists($table, $newidx)) {
                $dbman->add_index($table, $newidx);
            }
        }

        upgrade_plugin_savepoint(true, 2025112106, 'local', 'kiwilearner');
    }

    return true;
}

