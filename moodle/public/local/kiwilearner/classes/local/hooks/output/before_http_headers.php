<?php
namespace local_kiwilearner\local\hooks\output;

defined('MOODLE_INTERNAL') || die();

class before_http_headers {
    public static function callback(\core\hook\output\before_http_headers $hook): void {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
    }
}

