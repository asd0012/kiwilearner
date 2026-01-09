<?php
// blocks/kiwilearner_dailyquiz/email_summary.php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
require_sesskey();

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

// ✅ avoid $PAGE warnings / redirect被擋
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/kiwilearner_dailyquiz/email_summary.php', ['id' => $courseid]));
$PAGE->set_pagelayout('standard');

global $USER, $SESSION;

$returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);

// --------------------
// 1) Prefer summary.php data (full day) from user preference
// --------------------
$daykey  = block_kiwilearner_dailyquiz_daykey();
$prefkey = 'block_kiwilearner_dailyquiz_summary_' . (int)$courseid;

$savedsummary = json_decode(get_user_preferences($prefkey, ''), true);
if (!is_array($savedsummary) || (($savedsummary['daykey'] ?? '') !== $daykey)) {
    $savedsummary = null;
}

$summary = $savedsummary;
$items = [];
if (is_array($summary) && !empty($summary['items']) && is_array($summary['items'])) {
    $items = $summary['items'];
}

// --------------------
// 2) Fallback to session payload (attempt items) if needed
// --------------------
if (empty($items)) {
    $emailkey = 'block_kiwilearner_dailyquiz_email_' . (int)$courseid;
    $payload = $SESSION->$emailkey ?? null;

    if (is_array($payload)) {
        if (empty($summary) && !empty($payload['summary']) && is_array($payload['summary'])) {
            $summary = $payload['summary'];
        }
        if (!empty($payload['items']) && is_array($payload['items'])) {
            $items = $payload['items'];
        }
    }
}

if (empty($summary) && empty($items)) {
    redirect(
        $returnurl,
        'No summary available to email. Please submit a quiz first.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// --------------------
// 3) Build email content
// --------------------
$course = get_course($courseid);

$quizname   = $summary['quizname'] ?? get_string('pluginname', 'block_kiwilearner_dailyquiz');
$xpEarned   = (int)($summary['xp_earned'] ?? 0);
$xpTarget   = (int)($summary['xp_target'] ?? 0);
$dateStr    = userdate(time(), '%Y-%m-%d');
$courseName = format_string($course->fullname);
$courseUrl  = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
$summaryUrl = (new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]))->out(false);

// score (compute from items)
$total = count($items);
$correct = 0;
foreach ($items as $it) {
    if (!empty($it['iscorrect'])) {
        $correct++;
    }
}

$subject = "KiwiLearner Daily Quiz Summary ({$course->shortname}) - {$dateStr}";
$from = \core_user::get_support_user();

// ---- HTML body
$html = '';
$html .= '<p>Hi ' . s(fullname($USER)) . ',</p>';
$html .= '<p><strong>Course:</strong> ' . s($courseName) . '<br>';
$html .= '<strong>Quiz:</strong> ' . s($quizname) . '<br>';
$html .= '<strong>Date:</strong> ' . s($dateStr) . '<br>';
$html .= '<strong>XP earned today:</strong> ' . s($xpEarned) . ' / ' . s($xpTarget) . '<br>';
if ($total > 0) {
    $html .= '<strong>Your score:</strong> ' . s($correct) . ' / ' . s($total) . '<br>';
}
$html .= '</p>';

if (!empty($items)) {
    $html .= '<hr>';
    $html .= '<p><strong>Submitted questions</strong></p>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
    $html .= '<thead><tr>';
    $html .= '<th>#</th><th>Question</th><th>Status</th><th>Your answer</th><th>Correct answer</th>';
    $html .= '</tr></thead><tbody>';

    $i = 1;
    foreach ($items as $it) {
        $label   = s(strip_tags((string)($it['label'] ?? '')));
        $your    = s((string)($it['youranswer'] ?? ''));
        $correctA= s((string)($it['correctanswer'] ?? ''));
        $status  = !empty($it['iscorrect']) ? 'Correct' : 'Incorrect';

        $html .= '<tr>';
        $html .= '<td>' . $i . '</td>';
        $html .= '<td>' . $label . '</td>';
        $html .= '<td>' . s($status) . '</td>';
        $html .= '<td>' . $your . '</td>';
        $html .= '<td>' . $correctA . '</td>';
        $html .= '</tr>';
        $i++;
    }

    $html .= '</tbody></table>';
}

$html .= '<p style="margin-top:16px;">';
$html .= 'View course: ' . s($courseUrl) . '<br>';
$html .= 'View full summary: ' . s($summaryUrl) . '</p>';
$html .= '<p>— KiwiLearner</p>';

// ---- Plain text body
$text = "Hi " . fullname($USER) . "\n\n";
$text .= "Course: {$course->fullname}\n";
$text .= "Quiz: {$quizname}\n";
$text .= "Date: {$dateStr}\n";
$text .= "XP earned today: {$xpEarned} / {$xpTarget}\n";
if ($total > 0) {
    $text .= "Your score: {$correct} / {$total}\n";
}
$text .= "\n";

if (!empty($items)) {
    $text .= "Submitted questions:\n";
    $i = 1;
    foreach ($items as $it) {
        $label = strip_tags((string)($it['label'] ?? ''));
        $your  = (string)($it['youranswer'] ?? '');
        $corr  = (string)($it['correctanswer'] ?? '');
        $status= !empty($it['iscorrect']) ? 'Correct' : 'Incorrect';

        $text .= "{$i}. {$label}\n";
        $text .= "   Status: {$status}\n";
        $text .= "   Your: {$your}\n";
        $text .= "   Correct: {$corr}\n\n";
        $i++;
    }
}

$text .= "View course: {$courseUrl}\n";
$text .= "View full summary: {$summaryUrl}\n";
$text .= "\n— KiwiLearner\n";

// --------------------
// 4) Send + redirect
// --------------------
$emailok = email_to_user($USER, $from, $subject, $text, $html);

if ($emailok) {
    redirect($returnurl, 'Summary email sent!', null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($returnurl, 'Failed to send email (email_to_user returned false).', null, \core\output\notification::NOTIFY_ERROR);
}
