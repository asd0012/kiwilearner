<?php
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

global $DB, $USER;

$dayparam = optional_param('day', '', PARAM_ALPHANUM);

if ($dayparam !== '' && preg_match('/^\d{8}$/', $dayparam)) {
    $daykey = $dayparam; // only accept correct 8-digit format
} else {
    $daykey = block_kiwilearner_dailyquiz_daykey();
}
$rows = block_kiwilearner_dailyquiz_get_results($USER->id, $courseid, $daykey);

$items = [];
$xp = 0;
$xptarget = block_kiwilearner_dailyquiz_get_xp_target($USER->id, $courseid, 10);

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

    $xp += $iscorrect ? 1 : 0;
}

$savedsummary = [
    'daykey'        => $daykey,
    'xp_earned'     => $xp,
	'xp_target'      => $xptarget,       // ✅ add this
    'questioncount' => count($items),
    'hasitems'      => !empty($items),
    'items'         => $items,
    'isunknown'     => false,
];

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
