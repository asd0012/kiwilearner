<?php
// AJAX endpoint for Kiwilearner Chatbot.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_login();

defined('MOODLE_INTERNAL') || die();

$action   = required_param('action', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$query    = optional_param('query', '', PARAM_TEXT);

$PAGE->set_context(context_system::instance());

switch ($action) {
    case 'homepage_greeting':
        echo kiwilearner_chatbot_homepage_greeting($USER);
        break;

    case 'course_greeting':
        echo kiwilearner_chatbot_course_greeting($USER, $courseid);
        break;

    case 'quiz':
        echo kiwilearner_chatbot_quiz_message($USER, $courseid);
        break;

    case 'chat':
    default:
        echo kiwilearner_chatbot_answer_query($USER, $courseid, $query);
        break;
    case 'dashboard_feed':
        echo kiwilearner_chatbot_dashboard_feed($USER, $days, 10);
        break;
}

/**
 * Simple homepage greeting (AC2 stub).
 */
function kiwilearner_chatbot_homepage_greeting(stdClass $user): string {
    return "Hi {$user->firstname}, welcome back to Kiwilearner! "
        . "Ask me about your assignments, deadlines, or course materials.";
}

/**
 * Course page greeting (AC3 stub).
 */
function kiwilearner_chatbot_course_greeting(stdClass $user, int $courseid): string {
    global $DB;

    if (!$courseid) {
        return 'Open a course to see course-specific reminders.';
    }

    if (!$course = $DB->get_record('course', ['id' => $courseid], 'id, fullname')) {
        return 'Sorry, I cannot find this course.';
    }

    return "You are in {$course->fullname}. I can remind you about assignments "
         . "and later give you a mini quiz on the latest topics.";
}

/**
 * Quiz stub (later you’ll fetch real questions).
 */
function kiwilearner_chatbot_quiz_message(stdClass $user, int $courseid): string {
    if (!$courseid) {
        return 'Please open a course page to get a quiz from its latest material.';
    }

    // For now, just a placeholder.
    return "Quiz feature coming soon! For now, try asking me about assignments "
         . "or deadlines in this course.";
}
/**
 * Dashboard/Home feed: show "New items added" across all enrolled courses.
 *
 * @param stdClass $user
 * @param int $days
 * @param int $limit
 * @return string HTML snippet
 */
function kiwilearner_chatbot_dashboard_feed(stdClass $user, int $days = 60, int $limit = 10): string {
    global $DB;

    $since = time() - ($days * DAYSECS);

    // Get courses user is enrolled in (includes visible courses).
    $courses = enrol_get_users_courses($user->id, true, '*', 'visible DESC, sortorder ASC');

    if (empty($courses)) {
        return "Hi {$user->firstname}! You are not enrolled in any courses yet.";
    }

    $feed = [];

    foreach ($courses as $course) {
        if (empty($course->id) || $course->id == SITEID) {
            continue;
        }

        // Use modinfo for correct visibility/access handling.
        $courseobj = get_course((int)$course->id);
        $modinfo = get_fast_modinfo($courseobj);

        foreach ($modinfo->cms as $cm) {
            // Only items CREATED recently.
            if (empty($cm->added) || $cm->added < $since) {
                continue;
            }

            // Respect access/visibility.
            if (!$cm->uservisible || !$cm->visible) {
                continue;
            }

            // Human readable module type (Assignment, File, Page, URL, etc.)
            $typename = get_string('pluginname', $cm->modname);

            $feed[] = [
                'added' => (int)$cm->added,
                'coursename' => format_string($courseobj->fullname),
                'name' => $cm->get_formatted_name(),
                'type' => $typename,
                'url' => $cm->url ? $cm->url->out(false) : null,
            ];
        }
    }

    if (empty($feed)) {
        return "Hi {$user->firstname}! No new activities/resources were added in the last {$days} day(s).";
    }

    // Sort newest first and cap.
    usort($feed, fn($a, $b) => $b['added'] <=> $a['added']);
    $feed = array_slice($feed, 0, $limit);

    // Build HTML output.
    $out = "Hi {$user->firstname}! Here are recent course updates (last {$days} day(s)):<br><br>";
    $out .= "<ul>";

    foreach ($feed as $item) {
        $when = userdate($item['added'], '%d %b %H:%M');
        $title = htmlspecialchars($item['name']);
        $course = htmlspecialchars($item['coursename']);
        $type = htmlspecialchars($item['type']);

        $line = "<strong>New {$type}</strong> added in <strong>{$course}</strong>: ";

        if (!empty($item['url'])) {
            $url = htmlspecialchars($item['url']);
            $line .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noreferrer noopener\">{$title}</a>";
        } else {
            $line .= $title;
        }

        $line .= " <span style=\"opacity:0.75;\">({$when})</span>";

        $out .= "<li>{$line}</li>";
    }

    $out .= "</ul>";
    return $out;
}
/**
 * Simple keyword-based query answering (AC4 starter).
 */
function kiwilearner_chatbot_answer_query(stdClass $user, int $courseid, string $query): string {
    $query = trim(core_text::strtolower($query));

    if ($query === '') {
        return 'Ask me something like "next assignment", "deadlines", or "latest announcement".';
    }

    // Very simple keyword demo.
    if (strpos($query, 'assignment') !== false || strpos($query, 'deadline') !== false) {
        return 'Soon I will show your upcoming assignment deadlines here.';
    }

    if (strpos($query, 'quiz') !== false) {
        return 'Use the "Quiz me" button to get a mini quiz for this course (when implemented).';
    }

    if (strpos($query, 'help') !== false) {
        return 'You can ask about assignments, deadlines, or course materials. '
             . 'More intelligent answers will be added later.';
    }

    return 'I am still learning. Try asking about "assignments", "deadlines", or "quiz".';
}
