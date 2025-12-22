<?php
require_once(__DIR__ . '/../../config.php');

$questionid = required_param('questionid', PARAM_INT);

require_login();
$question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);

// Optional: resolve course from question category/context.
$courseid = your_helper_get_courseid_from_question($question);

// Capability check: only users who can edit questions here.
$context = context_course::instance($courseid);
require_capability('moodle/question:edit', $context);

$PAGE->set_url('/local/kiwilearner/editxp.php', ['questionid' => $questionid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('editxp', 'local_kiwilearner'));
$PAGE->set_heading(format_string($question->name));

require_once($CFG->dirroot . '/local/kiwilearner/classes/form/xpsettings_form.php');

$mform = new \local_kiwilearner\form\xpsettings_form(null, [
    'questionid' => $questionid,
    'courseid'   => $courseid,
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/question/edit.php', ['courseid' => $courseid]));
} else if ($data = $mform->get_data()) {
    // Prepare record for upsert.
    $record = (object)[
        'questionid'        => $questionid,
        'courseid'          => $courseid,
        'xp_participation'  => $data->xp_participation,
        'xp_correct'        => $data->xp_correct,
        'enabled'           => empty($data->enabled) ? 0 : 1,
        'timemodified'      => time(),
    ];

    // If a row exists, keep its id; else create.
    if ($existing = $DB->get_record('local_kiwilearner_question_xp',
        ['questionid' => $questionid, 'courseid' => $courseid])) {

        $record->id = $existing->id;
        $DB->update_record('local_kiwilearner_question_xp', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_kiwilearner_question_xp', $record);
    }

    redirect(
        new moodle_url('/question/edit.php', ['courseid' => $courseid]),
        get_string('xpsaved', 'local_kiwilearner')
    );
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
