<?php
namespace local_kiwilearner\utils;

defined('MOODLE_INTERNAL') || die();

use core_customfield\api;
use core_customfield\handler;
use core_customfield\field_controller;
use qbank_customfields\customfield\question_handler;
use local_kiwilearner\customfields\question_fields_manager;

class xp_sync_helper {

    /**
     * Read customfield XP data for a question.
     * Returns array: ['participation' => int, 'correct' => int, 'enabled' => bool]
     * or null if no custom fields found.
     */
    public static function get_xp_from_customfields(int $questionid): ?array {
        global $DB;

        $handler = question_handler::create();

        // IMPORTANT: api::get_instance_fields_data expects an array of field controllers.
        $fields = $handler->get_fields();
        if (empty($fields)) {
            return null;
        }

        $data = api::get_instance_fields_data($fields, $questionid);

        // Defaults if fields exist but values are empty.
        $result = array(
            'xp_participation' => 0,
            'xp_correct'       => 1,
            'enabled'          => 1,
        );


        $foundany = false;

        foreach ($data as $d) {
            $shortname = $d->get_field()->get('shortname');
            $value = $d->get_value();

            if ($shortname === question_fields_manager::FIELD_XP_PARTICIPATION) {
                $result['xp_participation'] = (int)$value;
                $foundany = true;
            } else if ($shortname === question_fields_manager::FIELD_XP_CORRECT) {
                $result['xp_correct'] = (int)$value;
                $foundany = true;
            } else if ($shortname === question_fields_manager::FIELD_XP_ENABLED) {
                // checkbox fields can come back as '0'/'1' or '' depending on state
                $result['enabled'] = (int)!empty($value);
                $foundany = true;
            }
        }

        return $foundany ? $result : null;
    }

    /**
     * Insert/update XP configuration into KiwiLearner XP table.
     */
    public static function upsert_question_xp(int $questionid, int $courseid, array $xp): void {
        global $DB;

        $existing = $DB->get_record('local_kiwilearner_question_xp', [
            'questionid' => $questionid,
            'courseid'   => $courseid,
        ]);

        $record = array(
            'questionid'       => $questionid,
            'courseid'         => $courseid,
            'xp_participation' => $xp['xp_participation'],
            'xp_correct'       => $xp['xp_correct'],
            'enabled'          => $xp['enabled'] ? 1 : 0,
            'timemodified'     => time(),
        );

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_kiwilearner_question_xp', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_kiwilearner_question_xp', $record);
        }
    }
}
