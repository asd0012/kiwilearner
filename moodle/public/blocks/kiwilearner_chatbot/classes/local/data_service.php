<?php
namespace block_kiwilearner_chatbot\local;

defined('MOODLE_INTERNAL') || die();

use core_calendar\local\api as calendar_api;

class data_service {

    public static function get_deadlines_next_7_days(int $courseid): array {
        global $DB;

        $now = time();
        $end = $now + (7 * DAYSECS);

        $out = [];

        /**
         * Helper to add an item with a CM view link.
         *
         * @param string $title
         * @param int $duedate
         * @param string $modname
         * @param int $cmid
         * @return void
         */
        $add = function(string $title, int $duedate, string $modname, int $cmid) use (&$out) {
            $item = [
                'name' => s($title),
                'time' => userdate($duedate),
            ];
            if ($cmid > 0) {
                $item['url'] = (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cmid]))->out(false);
            }
            $out[] = $item;
        };

        // ---------- QUIZ: timeclose ----------
        $params = ['now' => $now, 'end' => $end];
        $coursefilter = '';
        if ($courseid > 1) {
            $coursefilter = ' AND cm.course = :courseid ';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT q.name, q.timeclose, cm.id AS cmid
                FROM {quiz} q
                JOIN {course_modules} cm ON cm.instance = q.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                WHERE q.timeclose > 0
                AND q.timeclose BETWEEN :now AND :end
                    $coursefilter
            ORDER BY q.timeclose ASC";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $add($r->name . ' (Quiz closes)', (int)$r->timeclose, 'quiz', (int)$r->cmid);
        }

        // ---------- ASSIGN: duedate ----------
        $params = ['now' => $now, 'end' => $end];
        $coursefilter = '';
        if ($courseid > 1) {
            $coursefilter = ' AND cm.course = :courseid ';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT a.name, a.duedate, cm.id AS cmid
                FROM {assign} a
                JOIN {course_modules} cm ON cm.instance = a.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                WHERE a.duedate > 0
                AND a.duedate BETWEEN :now AND :end
                    $coursefilter
            ORDER BY a.duedate ASC";
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $add($r->name . ' (Assignment due)', (int)$r->duedate, 'assign', (int)$r->cmid);
        }

        // ---------- WORKSHOP: submissionend ----------
        if ($DB->get_manager()->table_exists('workshop') &&
            $DB->get_manager()->field_exists('workshop', 'submissionend')) {

            $params = ['now' => $now, 'end' => $end];
            $coursefilter = '';
            if ($courseid > 1) {
                $coursefilter = ' AND cm.course = :courseid ';
                $params['courseid'] = $courseid;
            }

            $sql = "SELECT w.name, w.submissionend, cm.id AS cmid
                    FROM {workshop} w
                    JOIN {course_modules} cm ON cm.instance = w.id
                    JOIN {modules} m ON m.id = cm.module AND m.name = 'workshop'
                    WHERE w.submissionend > 0
                    AND w.submissionend BETWEEN :now AND :end
                        $coursefilter
                ORDER BY w.submissionend ASC";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $add($r->name . ' (Workshop closes)', (int)$r->submissionend, 'workshop', (int)$r->cmid);
            }
        }

        // ---------- LESSON: deadline (if exists) ----------
        if ($DB->get_manager()->table_exists('lesson') &&
            $DB->get_manager()->field_exists('lesson', 'deadline')) {

            $params = ['now' => $now, 'end' => $end];
            $coursefilter = '';
            if ($courseid > 1) {
                $coursefilter = ' AND cm.course = :courseid ';
                $params['courseid'] = $courseid;
            }

            $sql = "SELECT l.name, l.deadline, cm.id AS cmid
                    FROM {lesson} l
                    JOIN {course_modules} cm ON cm.instance = l.id
                    JOIN {modules} m ON m.id = cm.module AND m.name = 'lesson'
                    WHERE l.deadline > 0
                    AND l.deadline BETWEEN :now AND :end
                        $coursefilter
                ORDER BY l.deadline ASC";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $add($r->name . ' (Lesson deadline)', (int)$r->deadline, 'lesson', (int)$r->cmid);
            }
        }

        // ---------- CHOICE: timeclose (if exists) ----------
        if ($DB->get_manager()->table_exists('choice') &&
            $DB->get_manager()->field_exists('choice', 'timeclose')) {

            $params = ['now' => $now, 'end' => $end];
            $coursefilter = '';
            if ($courseid > 1) {
                $coursefilter = ' AND cm.course = :courseid ';
                $params['courseid'] = $courseid;
            }

            $sql = "SELECT c.name, c.timeclose, cm.id AS cmid
                    FROM {choice} c
                    JOIN {course_modules} cm ON cm.instance = c.id
                    JOIN {modules} m ON m.id = cm.module AND m.name = 'choice'
                    WHERE c.timeclose > 0
                    AND c.timeclose BETWEEN :now AND :end
                        $coursefilter
                ORDER BY c.timeclose ASC";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $add($r->name . ' (Choice closes)', (int)$r->timeclose, 'choice', (int)$r->cmid);
            }
        }

        // Sort all deadlines by date/time (some queries already sorted, but we merge).
        usort($out, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        // Limit output to 30 items.
        return array_slice($out, 0, 30);
    }

    public static function get_course_updates_last_7_days(int $courseid): array {
        global $DB;

        if (empty($courseid) || $courseid < 2) {
            return [];
        }

        $since = time() - (7 * DAYSECS);

        // Use cm.added (available) instead of cm.timemodified (not in your schema).
        $sql = "SELECT cm.id AS cmid, cm.added, m.name AS modname, cm.instance
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                WHERE cm.course = :courseid
                AND cm.added >= :since
            ORDER BY cm.added DESC";

        $recs = $DB->get_records_sql($sql, ['courseid' => $courseid, 'since' => $since], 0, 20);

        $out = [];
        foreach ($recs as $r) {
            $title = '';

            // Try to fetch a human-readable name from the module instance table (if it has a 'name' field).
            if ($DB->get_manager()->table_exists($r->modname) &&
                $DB->get_manager()->field_exists($r->modname, 'name')) {

                $obj = $DB->get_record($r->modname, ['id' => $r->instance], 'id,name', IGNORE_MISSING);
                if ($obj && isset($obj->name)) {
                    $title = $obj->name;
                }
            }

            $out[] = [
                'title' => $title ? s($title) : s($r->modname . ' #' . $r->cmid),
                'time'  => userdate((int)$r->added),
                'url'   => (new \moodle_url('/mod/' . $r->modname . '/view.php', ['id' => $r->cmid]))->out(false),
            ];
        }

        return $out;
    }

}
