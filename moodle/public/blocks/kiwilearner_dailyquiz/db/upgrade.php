<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_kiwilearner_dailyquiz_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026010606) {
        $table = new xmldb_table('block_kiwilearner_dailyquiz_temp');

        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('daykey', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('usercourse_day_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'daykey']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026010606, 'kiwilearner_dailyquiz');
    }

    return true;
}
