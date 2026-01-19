<?php
// local/kiwilearner/settings.php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Create a settings page under "Local plugins".
    $settings = new admin_settingpage(
        'local_kiwilearner',
        get_string('pluginname', 'local_kiwilearner')
    );

    // Default XP: participation.
    $settings->add(new admin_setting_configtext(
        'local_kiwilearner/default_xp_participation',
        get_string('default_xp_participation', 'local_kiwilearner'),
        get_string('default_xp_participation_desc', 'local_kiwilearner'),
        0,              // default value
        PARAM_INT
    ));

    // Default XP: correct answer.
    $settings->add(new admin_setting_configtext(
        'local_kiwilearner/default_xp_correct',
        get_string('default_xp_correct', 'local_kiwilearner'),
        get_string('default_xp_correct_desc', 'local_kiwilearner'),
        1,              // default value
        PARAM_INT
    ));

    // Default XP: enabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_kiwilearner/default_xp_enabled',
        get_string('default_xp_enabled', 'local_kiwilearner'),
        get_string('default_xp_enabled_desc', 'local_kiwilearner'),
        1               // default checked
    ));

    // Register the page.
    $ADMIN->add('localplugins', $settings);
}
