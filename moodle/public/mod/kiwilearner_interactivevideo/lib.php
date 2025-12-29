<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add instance.
 */
function kiwilearner_interactivevideo_add_instance($data, $mform) {
    global $DB;

    $record = new stdClass();
    $record->course = $data->course;
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->h5pcontentid = $data->h5pcontentid ?? null;
    $record->timecreated = time();
    $record->timemodified = time();

    return $DB->insert_record('kiwilearner_interactivevideo', $record);
}

/**
 * Update instance.
 */
function kiwilearner_interactivevideo_update_instance($data, $mform) {
    global $DB;

    $record = $DB->get_record('kiwilearner_interactivevideo', ['id' => $data->instance], '*', MUST_EXIST);
    $record->name = $data->name;
    $record->intro = $data->intro ?? '';
    $record->introformat = $data->introformat ?? FORMAT_HTML;
    $record->h5pcontentid = $data->h5pcontentid ?? null;
    $record->timemodified = time();

    return $DB->update_record('kiwilearner_interactivevideo', $record);
}

/**
 * Delete instance.
 */
function kiwilearner_interactivevideo_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('kiwilearner_interactivevideo', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('kiwilearner_interactivevideo', ['id' => $id]);
    return true;
}
