<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_kiwilearner_chatbot_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026012405) {

        $table = new xmldb_table('block_kiwi_pdfsurvey');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_field('state', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('read_status', XMLDB_TYPE_CHAR, '10', null, null, null, '');
        $table->add_field('completion', XMLDB_TYPE_CHAR, '10', null, null, null, '');

        $table->add_field('about_text', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('takeaways_text', XMLDB_TYPE_TEXT, null, null, null, null, null);

        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uniq_user_cmid', XMLDB_KEY_UNIQUE, ['userid', 'cmid']);
        $table->add_index('idx_course_cmid', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026012405, 'kiwilearner_chatbot');
    }

    // Step 4.5: PDF cache table (PDF -> extracted text).
    if ($oldversion < 2026012605) {

        $table = new xmldb_table('block_kiwi_pdfcache');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('extractedtext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cmid_uk', XMLDB_KEY_UNIQUE, ['cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026012405, 'kiwilearner_chatbot');
    }

    return true;
}
