<?php
// This file lists all instances of KiwiLearner Interactive Video in a course.

require(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('mod/kiwivideo:view', $context);

$PAGE->set_url('/mod/kiwivideo/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'kiwivideo'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

// Fetch all instances of this module in the course.
$instances = get_all_instances_in_course(
    'kiwivideo',
    $course
);

if (!$instances) {
    notice(
        get_string('noinstances', 'kiwivideo'),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
    exit;
}

// Build a simple table.
$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('h5pcontentid', 'kiwivideo')
];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url(
            '/mod/kiwivideo/view.php',
            ['id' => $instance->coursemodule]
        ),
        format_string($instance->name)
    );

    $table->data[] = [
        $link,
        s($instance->h5pcontentid ?? '-')
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
