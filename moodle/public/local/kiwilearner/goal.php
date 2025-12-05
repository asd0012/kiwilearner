<?php
require(__DIR__ . '/../../config.php');

require_login();

use local_kiwilearner\goal;
use local_kiwilearner\form\goal_form;

// -----------------------------------------------------------------------------
// 1. Read parameters.
// -----------------------------------------------------------------------------
$courseid = required_param('courseid', PARAM_INT);
$goaltype = optional_param('goal_type', goal::TYPE_LESSON, PARAM_INT);

// Validate course and context.
$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);

// -----------------------------------------------------------------------------
// 2. Page setup.
// -----------------------------------------------------------------------------
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kiwilearner/goal.php', [
    'courseid'  => $courseid,
    'goal_type' => $goaltype,
]));
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('goalsettings', 'local_kiwilearner'));
$PAGE->set_heading($course->fullname);

// -----------------------------------------------------------------------------
// 3. Load existing goal record (per user + course).
// -----------------------------------------------------------------------------
$record = goal::get($USER->id, $goaltype, $courseid);

// Reasonable defaults if nothing stored yet.
// Reasonable defaults if nothing stored yet.
$defaults = (object) [
    'courseid'      => $courseid,
    'goal_type'     => $goaltype,
    'xp_target'     => 20,
    'lesson_target' => 2,
];

if ($record) {
    if (isset($record->goal_type)) {
        $defaults->goal_type = (int)$record->goal_type;
    }

    // Only copy xp_target if the property actually exists.
    if (property_exists($record, 'xp_target') && $record->xp_target !== null) {
        $defaults->xp_target = (int)$record->xp_target;
    }

    // Only copy lesson_target if the property actually exists.
    if (property_exists($record, 'lesson_target') && $record->lesson_target !== null) {
        $defaults->lesson_target = (int)$record->lesson_target;
    }
}

// -----------------------------------------------------------------------------
// 4. Build the form.
// -----------------------------------------------------------------------------
$mform = new goal_form(null, [
    'courseid'  => $courseid,
    'goal_type' => $goaltype,
    'defaults'  => $defaults,
]);

// -----------------------------------------------------------------------------
// 5. Form handling.
// -----------------------------------------------------------------------------
if ($mform->is_cancelled()) {
    // Go back to the course page.
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

} else if ($data = $mform->get_data()) {
    $newgoaltype = (int)$data->goal_type;

    // Only update the field that is relevant for the selected type.
    $xptarget     = null;
    $lessontarget = null;

    if ($newgoaltype === goal::TYPE_XP && isset($data->xp_target)) {
        $xptarget = (int)$data->xp_target;
    }

    if ($newgoaltype === goal::TYPE_LESSON && isset($data->lesson_target)) {
        $lessontarget = (int)$data->lesson_target;
    }

    goal::upsert(
        $USER->id,
        $newgoaltype,
        $xptarget,
        $lessontarget,
        $courseid
    );

    redirect(
        new moodle_url('/local/kiwilearner/goal.php', [
            'courseid'  => $courseid,
            'goal_type' => $newgoaltype,
        ]),
        get_string('goalupdated', 'local_kiwilearner')
    );
}

// -----------------------------------------------------------------------------
// 6. Output.
// -----------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('goalsettings', 'local_kiwilearner'));

$mform->display();

echo $OUTPUT->footer();

