<?php
namespace local_kiwilearner;

defined('MOODLE_INTERNAL') || die();

use local_kiwilearner\goal_status; 

/**
 * Service class responsible for deciding who gets goal reminders
 * and sending the actual Moodle messages.
 */
class reminder_service {

    /**
     * Send reminders for a given time slot: 'morning', 'afternoon', 'evening'.
     *
     * @param string $slot
     */
    public function send_for_slot(string $slot): void {
        global $DB;

        mtrace("KiwiLearner: reminder_service::send_for_slot({$slot})");

        // 1) Figure out "today" boundary (server time for now).
        $now = time();
        $daystart = strtotime('today midnight', $now);

        // 2) Pull all users who have a daily goal AND notifications enabled,
        //    and join today's XP summary if it exists.
        $sql = "
            SELECT
                g.id            AS goalid,
                g.userid        AS userid,
                g.courseid      AS courseid,
                g.xp_target     AS xp_target,
                COALESCE(s.xptotal, 0) AS xptotal,
                u.firstname     AS firstname,
                u.lastname      AS lastname,
                c.fullname      AS coursename
            FROM {local_kiwilearner_goal} g
            JOIN {user} u
                ON u.id = g.userid
            JOIN {course} c
                ON c.id = g.courseid
            JOIN {local_kiwilearner_notify_pref} p
                ON p.userid = g.userid
               AND p.notifyenabled = 1
            LEFT JOIN {local_kiwilearner_xp_summary_day} s
                ON s.userid = g.userid
               AND s.courseid = g.courseid
               AND s.daystart = :daystart
        ";

        $params = ['daystart' => $daystart];

        $records = $DB->get_records_sql($sql, $params);

        if (!$records) {
            mtrace('KiwiLearner: no goals / notify-enabled users found for reminders.');
            return;
        }

	foreach ($records as $r) {
		$xptarget = (int)$r->xp_target;
		$xptoday  = (int)$r->xptotal;

		// Build a minimal goal object for goal_status::compute_status().
		$goal = (object)[
			'xp_target' => $xptarget,
		];

		$status = goal_status::compute_status($goal, $xptoday);

		mtrace("KiwiLearner: user {$r->userid} course {$r->courseid} ".
			"status={$status} today={$xptoday} target={$xptarget}");

		// Never send when status is unknown or already achieved.
		if ($status === goal_status::STATUS_UNKNOWN) {
			mtrace("KiwiLearner: skip user {$r->userid} – status=Unknown");
			continue;
		}

		if ($status === goal_status::STATUS_ACHIEVED) {
			mtrace("KiwiLearner: skip user {$r->userid} – status=Achieved");
			continue;
		}

		// If we get here, status=MISSED ⇒ goal set but not reached.
		// Decide reminder type based on slot.
		$type = ($slot === 'evening') ? 'missed' : 'nudge';

		mtrace("KiwiLearner: send {$type} reminder to user {$r->userid} ".
			"course {$r->courseid} ({$xptoday}/{$xptarget}, slot={$slot})");

		$this->send_reminder_message($r, $slot, $type);
	}
    }
    /**
     * Actually send the Moodle message.
     *
     * @param \stdClass $data  row from the SQL above
     * @param string    $slot  'morning' | 'afternoon' | 'evening'
     * @param string    $type  'nudge' | 'missed'
     */
    protected function send_reminder_message(\stdClass $data, string $slot, string $type): void {
        global $DB;

        $userid    = (int)$data->userid;
        $courseid  = (int)$data->courseid;
        $xptarget  = (int)$data->xp_target;
        $xptoday   = (int)$data->xptotal;

        // Get full user + course objects (for messaging + URL).
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $remaining = max($xptarget - $xptoday, 0);

        if ($type === 'nudge') {
            $subject = get_string('reminder_subject_nudge', 'local_kiwilearner', $course->fullname);
            $text = get_string('reminder_body_nudge', 'local_kiwilearner', [
                'course'    => $course->fullname,
                'xptoday'   => $xptoday,
                'xptarget'  => $xptarget,
                'remaining' => $remaining,
                'slot'      => $slot,
            ]);
            $small = get_string('reminder_small_nudge', 'local_kiwilearner', $course->fullname);
        } else { // missed
            $subject = get_string('reminder_subject_missed', 'local_kiwilearner', $course->fullname);
            $text = get_string('reminder_body_missed', 'local_kiwilearner', [
                'course'    => $course->fullname,
                'xptoday'   => $xptoday,
                'xptarget'  => $xptarget,
            ]);
            $small = get_string('reminder_small_missed', 'local_kiwilearner', $course->fullname);
        }

        // Build course URL so they can click straight to the course.
        $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

        $message = new \core\message\message();
        $message->component         = 'local_kiwilearner';
        $message->name              = 'goalreminder'; // must exist in db/messages.php.
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = ''; // plain text is fine.
        $message->smallmessage      = $small;
        $message->notification      = 1;
        $message->contexturl        = $courseurl->out(false);
        $message->contexturlname    = $course->fullname;

        $msgid = message_send($message);

        mtrace("KiwiLearner: sent {$type} reminder to user {$userid} for course {$courseid} (msgid={$msgid})");
    }
}

