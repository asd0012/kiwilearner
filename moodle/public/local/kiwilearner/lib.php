<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add "Daily goal settings" item to the course "More" menu.
 *
 * Moodle calls this automatically when building the course navigation.
 *
 * @param navigation_node $navigation Course navigation node ("More" menu).
 * @param stdClass        $course     Course object.
 * @param context_course  $context    Course context.
 */


function local_kiwilearner_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {

    // Only for logged-in, non-guest users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Build URL to the goal page for this course.
    $url = new moodle_url('/local/kiwilearner/goal.php', [
        'courseid'  => $course->id
    ]);

    // Create a node that will appear under "More".
    $node = navigation_node::create(
        get_string('goalsettings', 'local_kiwilearner'), // e.g. "Daily goal settings"
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kiwilearner_goal'
    );

    // Attach to the course navigation ("More" menu).
    $navigation->add_node($node);
}

function local_kiwilearner_update_goal_streak(int $userid, int $courseid, int $daystart, int $now = 0): void {
    global $DB;

    $now = $now ?: time();

    $goal = $DB->get_record('local_kiwilearner_goal', [
        'userid' => $userid,
        'courseid' => $courseid,
    ], '*', IGNORE_MISSING);

    if (!$goal) {
        return;
    }

    $target = (int)($goal->xp_target ?? 0);
    if ($target <= 0) {
        return;
    }

    $summary = $DB->get_record('local_kiwilearner_xp_summary_day', [
        'userid' => $userid,
        'courseid' => $courseid,
        'daystart' => $daystart,
    ], '*', IGNORE_MISSING);

    $xptotal = (int)($summary->xptotal ?? 0);
    $met = ($xptotal >= $target);

    // Already processed today => idempotent.
    if ((int)($goal->laststreakdaystart ?? 0) === (int)$daystart) {
        return;
    }

    if (!$met) {
        $goal->currentstreak = 0;
        $goal->timemodified = $now;
        $DB->update_record('local_kiwilearner_goal', $goal);
        return;
    }

    // DST-safe previous daystart.
    $prevdaystart = usergetmidnight($daystart - 1);

    if ((int)($goal->laststreakdaystart ?? 0) === (int)$prevdaystart) {
        $goal->currentstreak = (int)($goal->currentstreak ?? 0) + 1;
    } else {
        $goal->currentstreak = 1;
    }

    $goal->beststreak = max((int)($goal->beststreak ?? 0), (int)$goal->currentstreak);
    $goal->laststreakdaystart = $daystart;
    $goal->timemodified = $now;

    $DB->update_record('local_kiwilearner_goal', $goal);
}
