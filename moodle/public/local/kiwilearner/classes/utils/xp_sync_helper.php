<?php
namespace local_kiwilearner\utils;

defined('MOODLE_INTERNAL') || die();

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

        // Load custom field handler for questions.
        $handler = question_handler::create();

        // Get custom field data for this question instance.
        $instance = $handler->get_instance($questionid);
        if (!$instance) {
            return null;
        }

        $data = $handler->export_instance_data_object($instance);

        $p = $data->{'customfield_' . question_fields_manager::FIELD_XP_PARTICIPATION} ?? null;
        $c = $data->{'customfield_' . question_fields_manager::FIELD_XP_CORRECT} ?? null;
        $e = $data->{'customfield_' . question_fields_manager::FIELD_XP_ENABLED} ?? null;

        if ($p === null && $c === null && $e === null) {
            return null;
        }

        return [
            'participation' => (int)($p ?? 0),
            'correct'       => (int)($c ?? 1),
            'enabled'       => (bool)($e ?? true),
        ];
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

        $record = (object)[
            'questionid'       => $questionid,
            'courseid'         => $courseid,
            'xp_participation' => $xp['participation'],
            'xp_correct'       => $xp['correct'],
            'enabled'          => $xp['enabled'] ? 1 : 0,
            'timemodified'     => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_kiwilearner_question_xp', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_kiwilearner_question_xp', $record);
        }
    }
}
