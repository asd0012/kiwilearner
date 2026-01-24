<?php
// File: blocks/kiwilearner_chatbot/classes/local/chatbot_service.php

namespace block_kiwilearner_chatbot\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Chatbot service helpers:
 * - greetings (existing)
 * - announcements (course/site)
 * - deadlines (events)
 * - recent course content (new modules/resources)
 *
 * Notes:
 * - Announcement fetching uses forum type 'news' via forum_get_course_forum().
 * - Deadlines uses {event} table for compatibility (course-specific or all enrolled courses).
 * - Recent content uses course_modules.added + get_fast_modinfo to get names/urls.
 */
class chatbot_service {

    /**
     * Build initial greeting for homepage.
     */
    public static function get_homepage_greeting(\stdClass $user): string {
        $firstname = fullname($user);
        return get_string('homepagegreeting', 'block_kiwilearner_chatbot', $firstname);
    }

    /**
     * Build initial greeting for course page.
     */
    public static function get_course_greeting(\stdClass $user, \stdClass $course): string {
        $firstname  = fullname($user);
        $coursename = format_string($course->fullname);

        return get_string('coursegreeting', 'block_kiwilearner_chatbot', [
            'user' => $firstname,
            'course' => $coursename
        ]);
    }

    // -------------------------------------------------------------------------
    // NEW: PAGE SUMMARY HELPERS (return plain strings you can push into initialmessages[])
    // -------------------------------------------------------------------------

    /**
     * Homepage summary (site announcements + upcoming deadlines across courses).
     *
     * @param int $userid
     * @param int $annlimit
     * @param int $daysahead
     * @return string[] messages
     */
    public static function get_home_summary_messages(int $userid, int $annlimit = 3, int $daysahead = 7): array {
        $messages = [];

        $siteann = self::get_site_announcements_text($annlimit);
        if (!empty($siteann)) {
            //$messages[] = "site announcements verify";//$siteann;
            $messages[] = $siteann;
        }

        $deadlines = self::get_upcoming_deadlines_text($userid, 0, $daysahead, 8);
        if (!empty($deadlines)) {
            //$messages[] = "course deadlines" ;//$deadlines;
            $messages[] = $deadlines;
        }

        return $messages;
    }

    /**
     * Dashboard summary (upcoming deadlines across all enrolled courses).
     *
     * @param int $userid
     * @param int $daysahead
     * @return string[] messages
     */
    public static function get_dashboard_summary_messages(int $userid, int $daysahead = 7): array {
        $messages = [];

        $deadlines = self::get_upcoming_deadlines_text($userid, 0, $daysahead, 10);
        if (!empty($deadlines)) {
            //$messages[] = "deadline dashboard_summary_messages"; //$deadlines;
            $messages[] = $deadlines;
        }

        return $messages;
    }

    /**
     * Course page summary (course announcements + deadlines + recent content).
     *
     * @param int $userid
     * @param int $courseid
     * @param int $annlimit
     * @param int $daysahead
     * @param int $daysback
     * @return string[] messages
     */
    public static function get_course_summary_messages(
        int $userid,
        int $courseid,
        int $annlimit = 3,
        int $daysahead = 7,
        int $daysback = 60
    ): array {
        $messages = [];

        $ann = self::get_course_announcements_text($courseid, $annlimit);
        if (!empty($ann)) {
            //$messages[] = "course_announcements_text";//$ann;
            $messages[] = $ann;
        }

        $deadlines = self::get_upcoming_deadlines_text($userid, $courseid, $daysahead, 8);
        if (!empty($deadlines)) {
            //$messages[] = "upcoming_deadlines_text";//$deadlines;
            $messages[] = $deadlines;
        }

        $updates = self::get_recent_course_updates_text($courseid, $daysback, 6);
        if (!empty($updates)) {
            //$messages[] = "recent_course_updates_text";//$updates;
            $messages[] = $updates;
        }

        return $messages;
    }

    // -------------------------------------------------------------------------
    // NEW: ANNOUNCEMENTS
    // -------------------------------------------------------------------------

    /**
     * Site announcements (SITEID course Announcements forum), formatted as a message.
     *
     * @param int $limit
     * @return string|null
     */
    public static function get_site_announcements_text(int $limit = 3): ?string {
        return self::get_course_announcements_text(SITEID, $limit, true);
    }

    /**
     * Course announcements from the "Announcements" forum (forum type 'news').
     *
     * @param int $courseid
     * @param int $limit
     * @param bool $issite True -> message header says "Site announcements"
     * @return string|null
     */
    public static function get_course_announcements_text(int $courseid, int $limit = 3, bool $issite = false): ?string {
        global $CFG;

        if ($courseid <= 0) {
            return null;
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $course = self::get_course_record($courseid);
        if (!$course) {
            return null;
        }

        // Get the "news" forum. If missing, return null gracefully.
        try {
            $forum = forum_get_course_forum($course, 'news');
        } catch (\Throwable $e) {
            return null;
        }

        if (!$forum) {
            return null;
        }

        // Get latest discussions.
        try {
            $discussions = forum_get_discussions($forum, 'p.modified DESC', false, -1, $limit);
        } catch (\Throwable $e) {
            $discussions = [];
        }

        if (empty($discussions)) {
            return null;
        }

        $lines = [];
        foreach ($discussions as $d) {
            $title = format_string($d->name ?? '');
            $when = !empty($d->timemodified) ? userdate((int)$d->timemodified) : '';
            $lines[] = $when ? "• {$title} ({$when})" : "• {$title}";
        }

        $header = $issite ? "📢 Site announcements:" : "📢 Latest announcements:";
        return $header . "\n" . implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // NEW: DEADLINES (events)
    // -------------------------------------------------------------------------

    /**
     * Upcoming deadlines from events table, formatted as a message.
     *
     * @param int $userid
     * @param int $courseid 0 => all enrolled courses
     * @param int $daysahead
     * @param int $limit
     * @return string|null
     */
    public static function get_upcoming_deadlines_text(
        int $userid,
        int $courseid = 0,
        int $daysahead = 7,
        int $limit = 8
    ): ?string {
        global $DB;

        $timestart = time();
        $timeend = $timestart + ($daysahead * DAYSECS);

        // Determine course set.
        if ($courseid > 0) {
            $courseids = [$courseid];
        } else {
            $courseids = self::get_user_enrolled_course_ids($userid);
        }

        if (empty($courseids)) {
            return null;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        $sql = "
            SELECT e.id, e.name, e.timestart, e.courseid, e.eventtype, e.modulename
              FROM {event} e
             WHERE e.timestart >= :timestart
               AND e.timestart <= :timeend
               AND e.courseid $insql
               AND (e.userid = 0 OR e.userid = :userid)
               AND e.visible = 1
             ORDER BY
               CASE WHEN e.eventtype = 'due' THEN 0 ELSE 1 END,
               e.timestart ASC
        ";

        $params = array_merge([
            'timestart' => $timestart,
            'timeend' => $timeend,
            'userid' => $userid,
        ], $inparams);

        $events = $DB->get_records_sql($sql, $params, 0, $limit);
        if (empty($events)) {
            return null;
        }

        $lines = [];
        foreach ($events as $e) {
            $name = format_string($e->name ?? '');
            $when = userdate((int)$e->timestart);

            $courselabel = '';
            if ($courseid === 0 && !empty($e->courseid)) {
                $c = self::get_course_record((int)$e->courseid);
                if ($c) {
                    $courselabel = ' — ' . format_string($c->shortname ?? $c->fullname ?? '');
                }
            }

            $lines[] = "• {$name}{$courselabel} — {$when}";
        }

        return "⏰ Upcoming deadlines (next {$daysahead} days):\n" . implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // NEW: RECENT COURSE CONTENT POSTED
    // -------------------------------------------------------------------------

    /**
     * Recently added activities/resources in course, formatted as a message.
     *
     * @param int $courseid
     * @param int $daysback
     * @param int $limit
     * @return string|null
     */
    public static function get_recent_course_updates_text(int $courseid, int $daysback = 3, int $limit = 6): ?string {
        global $DB, $CFG;

        if ($courseid <= 0) {
            return null;
        }

        $since = time() - ($daysback * DAYSECS);

        $sql = "
            SELECT cm.id, cm.added
              FROM {course_modules} cm
             WHERE cm.course = :courseid
               AND cm.added >= :since
               AND cm.deletioninprogress = 0
             ORDER BY cm.added DESC
        ";
        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'since' => $since], 0, $limit * 3);
        if (empty($rows)) {
            return null;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        $modinfo = get_fast_modinfo($courseid);

        $lines = [];
        foreach ($rows as $r) {
            $cm = $modinfo->get_cm((int)$r->id);
            if (!$cm || !$cm->uservisible) {
                continue;
            }

            $name = format_string($cm->name ?? '');
            $when = userdate((int)$r->added);

            // Optional: include URL on next line.
            if ($cm->url) {
                $url = $cm->url->out(false);
                $lines[] = "• {$name} — {$when}\n  {$url}";
            } else {
                $lines[] = "• {$name} — {$when}";
            }

            if (count($lines) >= $limit) {
                break;
            }
        }

        if (empty($lines)) {
            return null;
        }

        return "🆕 Recent course content posted (last {$daysback} days):\n" . implode("\n", $lines);
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    /**
     * Lightweight course record.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    private static function get_course_record(int $courseid): ?\stdClass {
        global $DB;

        if ($courseid <= 0) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname', IGNORE_MISSING);
        return $course ?: null;
    }

    /**
     * Enrolled course ids for a user (excluding SITEID).
     *
     * @param int $userid
     * @return int[]
     */
    private static function get_user_enrolled_course_ids(int $userid): array {
        global $DB;

        // If enrol_get_users_courses exists, use it (preferred).
        if (function_exists('enrol_get_users_courses')) {
            $courses = enrol_get_users_courses($userid, true, 'id, shortname, fullname');
            if (empty($courses)) {
                return [];
            }

            $ids = [];
            foreach ($courses as $c) {
                $cid = (int)$c->id;
                if ($cid > 0 && $cid !== SITEID) {
                    $ids[] = $cid;
                }
            }
            return array_values(array_unique($ids));
        }

        // Fallback: use user enrolments directly (works even if enrol lib isn't loaded).
        // This gets courses where user has an active enrolment.
        $sql = "
            SELECT DISTINCT c.id
            FROM {course} c
            JOIN {enrol} e ON e.courseid = c.id
            JOIN {user_enrolments} ue ON ue.enrolid = e.id
            WHERE ue.userid = :userid
            AND c.id <> :siteid
            AND ue.status = 0
            AND e.status = 0
        ";
        $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'siteid' => SITEID]);

        return array_map(static fn($r) => (int)$r->id, $rows ?: []);
    }
}
