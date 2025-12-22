<?php
// This file handles upgrades for the kiwilearner_dailyquiz block plugin.
defined('MOODLE_INTERNAL') || die();

function xmldb_block_kiwilearner_dailyquiz_upgrade($oldversion) {
    global $DB;

    // Check if this upgrade is needed (check if version is lower than new version)
    if ($oldversion < 2025121201) {
        // Rename old table to the new table name
        if ($DB->table_exists('block_dailyquiz_data')) {
            $DB->execute('ALTER TABLE {block_dailyquiz_data} RENAME TO {block_kiwilearner_dailyquiz_data}');
        }

        // If you need to rename other tables or make changes, do it here.
        // Example: Rename an additional table or perform a migration.
        // $DB->execute('ALTER TABLE {block_dailyquiz_some_table} RENAME TO {block_kiwilearner_dailyquiz_some_table}');

        // Update the version to signal that the upgrade is complete.
        upgrade_block_savepoint(true, 2025121201, 'kiwilearner_dailyquiz');
    }

    return true;
}
