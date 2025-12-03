<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add "Daily goal settings" to the navigation drawer for logged-in users.
 *
 * This is called automatically by Moodle if the file exists.
 *
 * @param global_navigation $nav
 */
function local_kiwilearner_extend_navigation(global_navigation $nav): void {
    global $USER;

    // No link for guests / not logged in.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $url = new moodle_url('/local/kiwilearner/goal.php');

    $node = navigation_node::create(
        get_string('goalsettings', 'local_kiwilearner'), // "Daily goal settings"
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kiwilearner_goal'
    );

    // For Boost/4.x themes: force it to show in the side drawer.
    $node->showinflatnavigation = true;

    $nav->add_node($node);
}

