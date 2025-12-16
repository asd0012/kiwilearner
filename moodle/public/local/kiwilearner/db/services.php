<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_kiwilearner_get_daily_goal' => [
        'classname'     => 'local_kiwilearner\external\get_daily_goal',
        'methodname'    => 'execute',
        'description'   => 'Get current user daily goal (XP type)',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_kiwilearner_update_daily_goal' => [
        'classname'     => 'local_kiwilearner\external\update_daily_goal',
        'methodname'    => 'execute',
        'description'   => 'Update current user daily goal',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];

// Optional; not needed for AJAX-only.
$services = [];

