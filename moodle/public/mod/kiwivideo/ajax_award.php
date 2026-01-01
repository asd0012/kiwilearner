<?php

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_login();

$cmid = required_param('cmid', PARAM_INT);
$subcontentid = required_param('subcontentid', PARAM_RAW_TRIMMED);
$success = required_param('success', PARAM_BOOL);

list($course, $cm) = get_course_and_cm_from_cmid($cmid);
$context = context_module::instance($cm->id);

// Adjust capability to whatever your module defines; view is typical.
require_capability('mod/kiwivideo:view', $context);

if (!$success) {
    echo json_encode(['ok' => true, 'awarded' => false, 'reason' => 'not_success']);
    die();
}

$subcontentid = clean_param($subcontentid, PARAM_RAW_TRIMMED);
$xpdelta = 1; // MVP fixed XP

$awarded = \local_kiwilearner\events\xp_award::award_h5p_correct_once_per_day(
    $USER->id,
    (int)$course->id,
    $subcontentid,
    $xpdelta,
    $USER->id
);

echo json_encode(['ok' => true, 'awarded' => $awarded]);
die();
