<?php
defined('MOODLE_INTERNAL') || die();

return [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_kiwilearner\local\hooks\output\before_http_headers::class . '::callback',
        'priority' => 0,
    ],
];

