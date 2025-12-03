<?php
require(__DIR__ . '/../../config.php');

require_login();

use local_kiwilearner\goal;
use local_kiwilearner\form\goal_form;

// Which tab is active (0 = XP, 1 = lessons).
$goaltype = optional_param('goal_type', goal::TYPE_XP, PARAM_INT);

// Page context & layout.
$context = \context_user::instance($USER->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kiwilearner/goal.php', ['goal_type' => $goaltype]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('goalsettings', 'local_kiwilearner'));
$PAGE->set_heading(get_string('goalsettings', 'local_kiwilearner'));

$record = goal::get($USER->id, $goaltype);

// Safe defaults.
$defaults = (object)[
    'goal_type'     => $goaltype,
    'xp_target'     => 20,
    'lesson_target' => 2,
];

if ($record) {
    // Use stored goal_type if present, unless URL explicitly forced one.
    if (isset($record->goal_type) &&
        !optional_param('goal_type', null, PARAM_RAW) // no explicit override
    ) {
        $defaults->goal_type = (int)$record->goal_type;
    }

    if ($defaults->goal_type === goal::TYPE_XP && isset($record->xp_target)) {
        $defaults->xp_target = (int)$record->xp_target;
    } else if ($defaults->goal_type === goal::TYPE_LESSON && isset($record->lesson_target)) {
        $defaults->lesson_target = (int)$record->lesson_target;
    }
}

$mform = new goal_form(null, ['defaults' => $defaults]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $mform->get_data()) {
    $type    = (int)$data->goal_type;
    $xp      = property_exists($data, 'xp_target') ? (int)$data->xp_target : null;
    $lessons = property_exists($data, 'lesson_target') ? (int)$data->lesson_target : null;

    goal::upsert($USER->id, $type, $xp, $lessons);

    redirect(
        new moodle_url('/local/kiwilearner/goal.php', ['goal_type' => $type]),
        get_string('goalupdated', 'local_kiwilearner')
    );
}

echo $OUTPUT->header();
$mform->set_data($defaults);
$mform->display();
echo $OUTPUT->footer();
