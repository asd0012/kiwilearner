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

$daykey  = userdate(time(), '%Y%m%d');
$prefkey = 'block_kiwilearner_dailyquiz_summary_' . $courseid;

$savedsummary = json_decode(get_user_preferences($prefkey, ''), true);
if (!is_array($savedsummary) || (($savedsummary['daykey'] ?? '') !== $daykey)) {
    $savedsummary = [
        'quizname'      => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
        'questioncount' => 0,
        'xp_earned'     => 0,
        'daykey'        => $daykey,
        'items'         => [],
        'hasitems'      => false,
    ];
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
