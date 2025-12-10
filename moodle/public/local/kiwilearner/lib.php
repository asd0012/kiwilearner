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

