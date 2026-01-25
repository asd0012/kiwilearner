<?php
namespace block_kiwilearner_chatbot\local;

defined('MOODLE_INTERNAL') || die();

class context_helper {
    public static function greeting(int $courseid, int $cmid): string {
        global $PAGE;

        if ($PAGE->pagetype === 'my-index') {
            return "You're on your Dashboard.";
        }

        if (!empty($cmid) && !empty($PAGE->cm)) {
            $name = $PAGE->cm->name ?? 'this activity';
            $modname = $PAGE->cm->modname ?? 'activity';
            return "You're viewing the {$modname}: <b>" . format_string($name) . "</b>.";
        }

        if (!empty($courseid) && !empty($PAGE->course) && $PAGE->course->id == $courseid && $courseid > 1) {
            return "You're in the course: <b>" . format_string($PAGE->course->fullname) . "</b>.";
        }

        return "You're on the site home.";
    }
}
