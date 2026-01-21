<?php

use aiprovider_openai\aimodel\o1;

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]));
$PAGE->set_pagelayout('report');
$PAGE->set_title('Daily Quiz Summary');
$PAGE->set_heading('Daily Quiz Summary');

require_once(__DIR__ . '/lib.php');

// --- Streak update (only meaningful for "today") ---
require_once($CFG->dirroot . '/local/kiwilearner/lib.php'); // <- make sure function exists here
global $DB, $USER;

$dayparam = optional_param('day', '', PARAM_ALPHANUM);

if ($dayparam !== '' && preg_match('/^\d{8}$/', $dayparam)) {
    $daykey = $dayparam; // only accept correct 8-digit format
} else {
    $daykey = block_kiwilearner_dailyquiz_daykey();
}


$todaykey = block_kiwilearner_dailyquiz_daykey();


// //************ */ DEV ONLY: time-travel testing via ?dayoffset=1 (tomorrow), -1 (yesterday) **********

// $dayoffset = optional_param('dayoffset', 0, PARAM_INT);
// if (!debugging('', DEBUG_DEVELOPER)) {
//     $dayoffset = 0;
// }
// $dayoffset = max(-7, min(7, $dayoffset));

// $now = time() + ($dayoffset * DAYSECS);
// $daystart = usergetmidnight($now);

// // Recompute daykey based on the shifted day (match your storage format)
// $daykey = date('Ymd', $daystart);
// $todaykey = date('Ymd', usergetmidnight(time()));


$now = time();
$daystart = usergetmidnight($now); // user-TZ midnight epoch

// Only update streak when viewing "today", otherwise you risk backfilling + messing current streak.
if ($daykey === $todaykey) {
    // Keep local xp tables synced before streak calc (so xptotal is fresh).
    block_kiwilearner_dailyquiz_sync_xp_to_local($USER->id, $courseid, $daykey, $now);

    // Update goal streak fields in local_kiwilearner_goal.
    local_kiwilearner_update_goal_streak($USER->id, $courseid, $daystart, $now);
}


// $xptarget = block_kiwilearner_dailyquiz_get_xp_target($USER->id, $courseid, 0);

// --- Handle "Email me this summary" POST (must run before any output) ---
$doemail = optional_param('emailsummary', 0, PARAM_BOOL);

if ($doemail) {
    require_sesskey();

    // Use the SAME daykey the page is showing (supports ?day=YYYYMMDD)
    [$todayxp, $todaytotal] = block_kiwilearner_dailyquiz_get_today_totals_from_temp($USER->id, $courseid, $daykey);

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $coursename = format_string($course->fullname, true, ['context' => $context]);

    // Pretty date from daykey
    $datestr = $daykey;
    $dt = \DateTime::createFromFormat('Ymd', $daykey);
    if ($dt) {
        $datestr = $dt->format('Y-m-d');
    }

    $summaryurl = new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', [
        'id' => $courseid,
        'day' => $daykey,
    ]);

    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

    $subject = "KiwiLearner Daily Quiz Summary (" . $coursename . ") - " . $datestr;

    $text = "Hi {$USER->firstname},\n\n"
          . "Course: {$coursename}\n"
          . "Quiz: Daily Quiz\n"
          . "Date: {$datestr}\n"
          . "XP earned today: {$todayxp} / {$xptarget}\n\n"
          . "View course: " . $courseurl->out(false) . "\n"
          . "View full summary: " . $summaryurl->out(false) . "\n\n"
          . "— KiwiLearner\n";

    $html = "<p>Hi " . s($USER->firstname) . ",</p>"
          . "<p><strong>Course:</strong> " . s($coursename) . "<br>"
          . "<strong>Quiz:</strong> Daily Quiz<br>"
          . "<strong>Date:</strong> " . s($datestr) . "<br>"
          . "<strong>XP earned today:</strong> " . s("{$todayxp} / {$xptarget}") . "</p>"
          . "<p>View course: <a href=\"" . s($courseurl->out(false)) . "\">" . s($courseurl->out(false)) . "</a><br>"
          . "View full summary: <a href=\"" . s($summaryurl->out(false)) . "\">" . s($summaryurl->out(false)) . "</a></p>"
          . "<p>— KiwiLearner</p>";

    $from = \core_user::get_noreply_user();
    $ok = email_to_user($USER, $from, $subject, $text, $html);

    $returnurl = new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', [
        'id' => $courseid,
        'day' => $daykey,
    ]);

    if ($ok) {
        redirect($returnurl, 'Summary email sent!', 2, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($returnurl, 'Failed to send summary email.', 3, \core\output\notification::NOTIFY_ERROR);
    }
}

$rows = block_kiwilearner_dailyquiz_get_results($USER->id, $courseid, $daykey);

$items = [];

foreach ($rows as $qid => $r) {
    $qid = (int)$qid;

    $q = $DB->get_record('question', ['id' => $qid], 'id,name', IGNORE_MISSING);
    $label = $q ? $q->name : "Q{$qid}";

    $your = (string)$r->answer;
    if (!empty($r->answer)) {
        $ans = $DB->get_record('question_answers', ['id' => (int)$r->answer], 'id,answer', IGNORE_MISSING);
        if ($ans) {
            $your = trim(strip_tags($ans->answer));
        }
    }

    $correctrec = $DB->get_record_sql(
        'SELECT id, answer
           FROM {question_answers}
          WHERE question = :qid AND fraction > 0
       ORDER BY fraction DESC, id ASC',
        ['qid' => $qid],
        IGNORE_MISSING
    );

    $correct = $correctrec ? trim(strip_tags($correctrec->answer)) : '';
    $correctid = $correctrec ? (int)$correctrec->id : 0;

    $iscorrect = ((int)$r->answer === $correctid) || ((float)$r->score > 0);

    $items[] = [
        'label'         => s($label),
        'iscorrect'     => $iscorrect,
        'isincorrect'   => !$iscorrect,
        'youranswer'    => $your,
        'correctanswer' => $correct,
    ];
}

// ✅ source of truth for totals (same as course homepage)
[$todayxp, $todaytotal] = block_kiwilearner_dailyquiz_get_today_totals_from_temp($USER->id, $courseid, $daykey);

$daystart = usergetmidnight(time());

$todaykey = block_kiwilearner_dailyquiz_daykey();
if ($daykey === $todaykey) {
    local_kiwilearner_update_goal_streak($USER->id, $courseid, $daystart);
}



$goal = $DB->get_record('local_kiwilearner_goal', [
    'userid' => $USER->id,
    'courseid' => $courseid,
], 'xp_target,currentstreak,beststreak,laststreakdaystart', IGNORE_MISSING);

$xptarget = $goal ? (int)$goal->xp_target : 0;

$currentstreak = $goal ? (int)$goal->currentstreak : 0;
$beststreak    = $goal ? (int)$goal->beststreak : 0;

// Goal status + daily message (always show something to reduce emptiness).
$savedsummary['goalstatus_label'] = get_string('goalstatus_unknown', 'block_kiwilearner_dailyquiz');
$savedsummary['is_goal_achieved'] = false;
$savedsummary['is_goal_missed']   = false;
$savedsummary['is_goal_unknown']  = false;
$savedsummary['goal_daily_msg']   = '';


$savedsummary = [
    'daykey'       => $daykey,
    'xp_earned'    => $todayxp,       // <= use totals
    'xp_target'    => $xptarget,
    'questioncount' => $todaytotal,    // <= use totals
    'hasitems'     => !empty($items),
    'items'        => $items,
    'isunknown'    => false,
    'currentstreak' => $currentstreak,
    'beststreak'    => $beststreak,

    // goal UI fields (default)
    'goalstatus_label' => '',
    'is_goal_achieved' => false,
    'is_goal_missed'   => false,
    'is_goal_unknown'  => false,
    'goal_daily_msg'   => '',
];


if (empty($xptarget) || (int)$xptarget <= 0) {
    $savedsummary['goalstatus_label'] = get_string('goalstatus_unknown', 'block_kiwilearner_dailyquiz');
    $savedsummary['is_goal_unknown']  = true;
    $savedsummary['goal_daily_msg']   = get_string('goal_daily_unknown_msg', 'block_kiwilearner_dailyquiz');
} else if ((int)$todayxp >= (int)$xptarget) {
    $savedsummary['goalstatus_label'] = get_string('goalstatus_achieved', 'block_kiwilearner_dailyquiz');
    $savedsummary['is_goal_achieved'] = true;

    $a = (object)[
        'target' => (int)$xptarget,
        'streak' => (int)$currentstreak,
    ];
    $savedsummary['goal_daily_msg'] = get_string('goal_daily_hit_msg', 'block_kiwilearner_dailyquiz', $a);
} else {
    $savedsummary['goalstatus_label'] = get_string('goalstatus_missed', 'block_kiwilearner_dailyquiz');
    $savedsummary['is_goal_missed']   = true;

    $a = (object)[
        'remain' => max(0, (int)$xptarget - (int)$todayxp),
        'target' => (int)$xptarget,
    ];
    $savedsummary['goal_daily_msg'] = get_string('goal_daily_miss_msg', 'block_kiwilearner_dailyquiz', $a);
}

$streak = (int)($goal->currentstreak ?? 0);

$milestones = [
    5  => 'streak_milestone_5',
    10 => 'streak_milestone_10',
    15 => 'streak_milestone_15',
    20 => 'streak_milestone_20',
];

$savedsummary['streak_milestone_msg'] = '';
if (isset($milestones[$streak])) {
    $savedsummary['streak_milestone_msg'] = get_string($milestones[$streak], 'block_kiwilearner_dailyquiz');
}

$data = (object)[
    'quizname'    => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
    'posturl'     => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'courseid'    => $courseid,
    'sesskey'     => sesskey(),
    'questions'   => [],
    'summary'     => $savedsummary,
    'continueurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $data);
echo $OUTPUT->footer();
