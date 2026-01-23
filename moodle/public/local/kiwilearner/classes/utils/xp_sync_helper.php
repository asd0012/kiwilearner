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

        foreach ($fields as $f) {
            error_log('[KiwiLearner] field shortname=' . $f->get('shortname')
                . ' type=' . $f->get('type')
                . ' id=' . $f->get('id')
            );
        }

        if (empty($fields)) {
            return null;
        }

        // Customfield data instanceid is question.id (as per your customfield_data rows).
        $data = api::get_instance_fields_data($fields, $questionid);
        error_log("[KiwiLearner] xp_sync_helper: reading customfields for questionid={$questionid}");

        // Question bank entry id solution
        // IMPORTANT: in Moodle 4/5 question bank, the customfields are stored against question bank entry id.
        // $qbeid = self::resolve_questionbankentryid($questionid);
        // if (empty($qbeid)) {
        //     error_log('[KiwiLearner] xp_sync_helper: cannot resolve questionbankentryid for questionid=' . $questionid);
        //     return null;
        // }
        // $data = api::get_instance_fields_data($fields, $qbeid);
        // error_log("[KiwiLearner] xp_sync_helper: qid={$questionid} qbeid={$qbeid}");

        // Defaults if fields exist but values are empty.
        $result = [
            'xp_participation' => 0,
            'xp_correct'       => 1,
            'enabled'          => 1,
        ];

        $foundany = false;

        foreach ($data as $d) {
            $shortname = $d->get_field()->get('shortname');
            $value = $d->get_value();

            // debugging(
            //     '[KiwiLearner] xp_sync_helper: get_field:' . $shortname
            //     .' get_value:' . $value,
            //     DEBUG_DEVELOPER
            // );

            if ($shortname === question_fields_manager::FIELD_XP_PARTICIPATION) {
                if ($value !== null && $value !== '') {
                    $result['xp_participation'] = (int)$value;
                    $foundany = true;
                }
            } else if ($shortname === question_fields_manager::FIELD_XP_CORRECT) {
                if ($value !== null && $value !== '') {
                    $result['xp_correct'] = (int)$value;
                    $foundany = true;
                }
            } else if ($shortname === question_fields_manager::FIELD_XP_ENABLED) {
                // Checkbox: treat ''/null as "not set"
                if ($value !== null && $value !== '') {
                    // Moodle checkboxes typically come as '0'/'1' (string) or int.
                    $result['enabled'] = ((string)$value === '1' || (int)$value === 1) ? 1 : 0;
                    $foundany = true;
                }
            }
        }

        // debugging(
            // '[KiwiLearner] xp_sync_helper: field result:'
            // . " xp_participation:". $result['xp_participation']
            // . " xp_correct:". $result['xp_correct']
            // . " enabled:". $result['enabled'],
            // DEBUG_DEVELOPER
        // );
// 
        return $foundany ? $result : null;
    }

    /**
     * Upsert into local_kiwilearner_question_xp.
     *
     * @param int   $questionid Moodle question id
     * @param int   $courseid   owning course id (resolved from context)
     * @param array $xp         from get_xp_from_customfields()
     */
    public static function upsert_question_xp(int $questionid, int $courseid, array $xp): void {
        global $DB;

        $existing = $DB->get_record(
            'local_kiwilearner_question_xp',
            ['questionid' => $questionid, 'courseid' => $courseid],
            '*',
            IGNORE_MISSING
        );

        $record = (object)[
            'questionid'       => $questionid,
            'courseid'         => $courseid,
            'xp_participation' => (int)($xp['xp_participation'] ?? 0), // TODO: may customize default value
            'xp_correct'       => (int)($xp['xp_correct'] ?? 1), // TODO: may customize default value
            'enabled'          => !empty($xp['enabled']) ? 1 : 0, // TODO: may customize default value
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

    // private static function resolve_questionbankentryid(int $questionid): ?int {
    //     global $DB;

    //     if (!$DB->get_manager()->table_exists('question_versions')) {
    //         return null;
    //     }

    //     $sql = "
    //         SELECT qv.questionbankentryid
    //         FROM {question_versions} qv
    //         WHERE qv.questionid = :qid
    //     ORDER BY qv.version DESC
    //     ";

    //     $rec = $DB->get_record_sql($sql, ['qid' => $questionid], IGNORE_MULTIPLE);
    //     if (!$rec || empty($rec->questionbankentryid)) {
    //         return null;
    //     }

    //     return (int)$rec->questionbankentryid;
    // }

}
