<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_kiwilearner\task\send_reminders',
        'blocking'  => 0,
        // Run at hh:05 so it doesn't clash with other heavy tasks.
        'minute'    => '5',
        'hour'      => '9,14,19',   // morning / afternoon / evening (server time)
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];

