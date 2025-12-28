<?php
namespace local_kiwilearner\local;

defined('MOODLE_INTERNAL') || die();

final class hook_callbacks {
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        // Avoid issues during install / before plugin config exists.
        if (during_initial_install() || !get_config('local_kiwilearner', 'version')) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        $existing = array_map('strtolower', headers_list());
        $has = static function(string $name) use ($existing): bool {
            $prefix = strtolower($name) . ':';
            foreach ($existing as $h) {
                if (str_starts_with($h, $prefix)) {
                    return true;
                }
            }
            return false;
        };

        if (!$has('X-Content-Type-Options')) {
            header('X-Content-Type-Options: nosniff', false);
        }
        if (!$has('Cache-Control')) {
            header('Cache-Control: no-store, no-cache, must-revalidate', false);
        }
        if (!$has('Pragma')) {
            header('Pragma: no-cache', false);
        }
        if (!$has('Expires')) {
            header('Expires: 0', false);
        }
    }
}

