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
    public static function handle_question_saved(\core\event\question_created $event) {
        global $DB;

        $questionid = $event->objectid;

        // Resolve courseid from the context of the question category (Option A).
        $courseid = self::resolve_course_from_question($questionid);
        if (!$courseid) {
            // Not a course-level question (system or category context).
            return true;
        }

        // Read custom field XP values.
        $xp = xp_sync_helper::get_xp_from_customfields($questionid);

        // If nothing set at all, skip.
        if ($xp === null) {
            return true;
        }

        // Upsert into KiwiLearner XP table.
        xp_sync_helper::upsert_question_xp($questionid, $courseid, $xp);

        return true;
    }

    /**
     * Resolve courseid using Option A:
     * The course where the question category belongs.
     */
    private static function resolve_course_from_question(int $questionid): ?int {
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

}
