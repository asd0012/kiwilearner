<?php
require(__DIR__ . '/../../config.php');

require_login();

use local_kiwilearner\goal;
use local_kiwilearner\form\goal_form;

// -----------------------------------------------------------------------------
// 1. Read parameters.
// -----------------------------------------------------------------------------
$courseid = required_param('courseid', PARAM_INT);

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
]));
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('goalsettings', 'local_kiwilearner'));
$PAGE->set_heading($course->fullname);

// -----------------------------------------------------------------------------
// 3. Load existing goal record (per user + course).
// -----------------------------------------------------------------------------
$record = goal::get($USER->id, $courseid);

// Reasonable defaults if nothing stored yet.
$defaults = (object) [
    'courseid'      => $courseid,
    'xp_target'     => 20,
];

if ($record) {

    // Only copy xp_target if the isset actually exists.
    if (isset($record->xp_target)) {
        $defaults->xp_target = (int)$record->xp_target;
    }

}

// -----------------------------------------------------------------------------
// 4. Build the form.
// -----------------------------------------------------------------------------
$mform = new goal_form(null, [
    'courseid'  => $courseid,
    'defaults'  => $defaults,
]);

// -----------------------------------------------------------------------------
// 5. Form handling.
// -----------------------------------------------------------------------------
if ($mform->is_cancelled()) {
    // Go back to the course page.
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

} else if ($data = $mform->get_data()) {

    $xptarget = isset($data->xp_target) ? (int)$data->xp_target : 0;

    goal::upsert(
        $USER->id,
        $courseid,
        $xptarget
    );

    redirect(
        new moodle_url('/local/kiwilearner/goal.php', [
            'courseid'  => $courseid,
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

