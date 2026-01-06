<?php

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

$cmid = required_param('cmid', PARAM_INT);
$subcontentid = required_param('subcontentid', PARAM_RAW_TRIMMED);
$success = required_param('success', PARAM_BOOL);

// Pin to this module explicitly.
list($course, $cm) = get_course_and_cm_from_cmid($cmid);
$context = context_module::instance($cm->id);

// Adjust capability to view
require_capability('mod/kiwivideo:view', $context);

// Set header
header('Content-Type: application/json; charset=utf-8');

if (!$success) {
    echo json_encode(['ok' => true, 'awarded' => false, 'reason' => 'not_success']);
    die();
}

// subContentId is often UUID-like; keep it trimmed and bounded.
$subcontentid = trim(clean_param($subcontentid, PARAM_RAW_TRIMMED));
if ($subcontentid === '' || strlen($subcontentid) > 255) {
    echo json_encode(['ok' => false, 'error' => 'invalid_subcontentid']);
    exit;
}

$xpdelta = 1; // MVP fixed XP

$awarded = \local_kiwilearner\utils\xp_engine::award_h5p_correct_once_per_day(
    (int)$USER->id,
    (int)$course->id,
    $subcontentid,
    (int)$xpdelta,
);

echo json_encode(['ok' => true, 'awarded' => $awarded]);
die();
