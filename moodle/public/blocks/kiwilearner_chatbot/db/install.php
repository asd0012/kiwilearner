<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_kiwilearner_chatbot_install() {
    global $DB;

    $syscontext = context_system::instance();
    $now=time();

    $instance = new stdClass();
    $instance->blockname        = 'kiwilearner_chatbot';
    $instance->parentcontextid  = $syscontext->id;
    $instance->showinsubcontexts = 1;
    $instance->pagetypepattern  = '*';
    $instance->defaultregion    = 'side-pre';
    $instance->defaultweight    = 0;
    $instance->timecreated      = $now;
    $instance->timemodified     = $now;

    if (!$DB->record_exists('block_instances', ['blockname' => 'kiwilearner_chatbot'])) {
        $DB->insert_record('block_instances', $instance);
    }
    return true;
}
