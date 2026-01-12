<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Declares which Moodle features this module supports.
 */
function kiwivideo_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;

        case FEATURE_SHOW_DESCRIPTION:
            return true;

        case FEATURE_BACKUP_MOODLE2:
            return true;

        default:
            return null;
    }
}


/**
 * Add instance.
 */
function kiwivideo_add_instance($data, $mform) {
    global $DB;

    $record = new stdClass();
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->h5pcontentid = $data->h5pcontentid ?? null;
    $record->timecreated = time();
    $record->timemodified = time();

    return $DB->insert_record('kiwivideo', $record);
}

/**
 * Update instance.
 */
function kiwivideo_update_instance($data, $mform) {
    global $DB;

    $record = $DB->get_record('kiwivideo', ['id' => $data->instance], '*', MUST_EXIST);
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->h5pcontentid = $data->h5pcontentid ?? null;
    $record->timemodified = time();

    return $DB->update_record('kiwivideo', $record);
}

/**
 * Delete instance.
 */
function kiwivideo_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('kiwivideo', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('kiwivideo', ['id' => $id]);
    return true;
}
