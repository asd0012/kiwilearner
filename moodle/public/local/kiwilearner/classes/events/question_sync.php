<?php
namespace local_kiwilearner\events;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use core_customfield\handler;
use local_kiwilearner\customfields\question_fields_manager;
use local_kiwilearner\utils\xp_sync_helper;

class question_sync {

    /**
     * Called when a question is created or updated.
     */
    public static function handle_question_saved(\core\event\base $event) {
        global $DB;

        $questionid = self::extract_questionid_from_event($event);

        error_log('[KiwiLearner] question_sync: Extract Question id:' . $questionid);
        if (empty($questionid)) {
            debugging(
                '[KiwiLearner] question_sync: Unable to resolve questionid',
                DEBUG_DEVELOPER
            );
            error_log('[KiwiLearner] question_sync: Unable to resolve questionid'
                . ' | event=' . get_class($event)
                . ' | objectid=' . $event->objectid
                . ' | other=' . json_encode($event->other ?? [])
                . ' | contextid=' . ($event->contextid ?? 'null')
            );
            return true;
        }

        // Check whether customfield_data rows already exist at event time
        $exists = $DB->record_exists_sql("
            SELECT 1
            FROM {customfield_data} d
            JOIN {customfield_field} f ON f.id = d.fieldid
            WHERE d.instanceid = :qid
            AND f.shortname IN ('kiwi_xp_participation', 'kiwi_xp_correct', 'kiwi_xp_enabled')
            AND d.component = 'qbank_customfields'
            AND d.area = 'question'
            AND d.itemid = 0
        ", ['qid' => $questionid]);

        error_log(
            '[KiwiLearner] question_sync: customfield rows exist at event time? '
            . ($exists ? 'YES' : 'NO')
            . ' | questionid=' . $questionid
        );

        // Queue an ad-hoc task so customfield_data definitely exists.
        $task = new \local_kiwilearner\task\sync_question_xp_task();
        $task->set_custom_data((object)[
            'questionid' => (int)$questionid,
        ]);
        \core\task\manager::queue_adhoc_task($task);

        error_log('[KiwiLearner] question_sync: queued XP sync task for questionid=' . (int)$questionid);
        return true;

    }

    /**
     * Resolve courseid using Option A:
     * The course where the question category belongs.
     */
    public static function resolve_course_from_question(int $questionid): ?int {
        global $DB;

        // Detect if the legacy column exists.
        $columns = $DB->get_columns('question');

        if (isset($columns['category'])) {
            // Legacy schema: question.category -> question_categories.id
            $question = $DB->get_record('question', ['id' => $questionid], 'id, category', MUST_EXIST);
            $qcat = $DB->get_record('question_categories', ['id' => $question->category], 'id, contextid', MUST_EXIST);

            $ctx = \context::instance_by_id($qcat->contextid);
            if ($ctx instanceof \context_course) {
                return $ctx->instanceid;
            }
            return null;
        }

        // New schema: question_versions -> question_bank_entries -> question_categories
        // Find the current question's bank entry & its category.
        $sql = "
            SELECT qc.contextid
            FROM {question_versions} qv
            JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            JOIN {question_categories} qc     ON qc.id = qbe.questioncategoryid
            WHERE qv.questionid = :qid
            ORDER BY qv.version DESC
        ";

        $rec = $DB->get_record_sql($sql, ['qid' => $questionid], IGNORE_MULTIPLE);
        if (!$rec || empty($rec->contextid)) {
            return null;
        }

        $ctx = \context::instance_by_id((int)$rec->contextid);

        if ($ctx instanceof \context_course) {
            // CONTEXT_COURSE (50)
            return (int)$ctx->instanceid;
        }

        if ($ctx instanceof \context_module) {
            // CONTEXT_MODULE (70): instanceid = course_modules.id (cmid)
            $cmid = (int)$ctx->instanceid;
            $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
            return (int)$cm->course;
        }

        // System / course category / user / etc. → not a single course owner.
        return null;
        
    }

    private static function extract_questionid_from_event(\core\event\base $event): ?int {
        global $DB;

        // Most reliable: if "other" explicitly contains questionid.
        $other = $event->other ?? [];
        if (is_array($other) && !empty($other['questionid'])) {
            return (int)$other['questionid'];
        }

        // If objectid directly matches an existing question id, use it.
        $oid = (int)$event->objectid;
        if ($oid > 0 && $DB->record_exists('question', ['id' => $oid])) {
            return $oid;
        }

        // Some flows use question_versions.id as objectid; map it back.
        if ($DB->get_manager()->table_exists('question_versions')
            && $DB->record_exists('question_versions', ['id' => $oid])) {

            $qv = $DB->get_record('question_versions', ['id' => $oid], 'id, questionid', MUST_EXIST);
            if (!empty($qv->questionid) && $DB->record_exists('question', ['id' => $qv->questionid])) {
                return (int)$qv->questionid;
            }
        }

        // Otherwise, we cannot safely determine a question id.
        return null;
    }

}
