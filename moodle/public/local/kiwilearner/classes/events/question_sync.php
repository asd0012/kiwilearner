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

        // Get question category.
        $question = $DB->get_record('question', ['id' => $questionid], 'id, category', MUST_EXIST);
        $category = $DB->get_record('question_categories', ['id' => $question->category], 'id, contextid', MUST_EXIST);

        $ctx = context::instance_by_id($category->contextid);

        if ($ctx instanceof context_course) {
            return $ctx->instanceid;
        }

        // Question category is system-level or course-category-level → skip.
        return null;
    }
}
