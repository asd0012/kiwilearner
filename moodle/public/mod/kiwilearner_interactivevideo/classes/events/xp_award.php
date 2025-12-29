<?php

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');

require_login();

$cmid = required_param('cmid', PARAM_INT);
$interactionid = required_param('interactionid', PARAM_RAW_TRIMMED);

list($course, $cm) = get_course_and_cm_from_cmid($cmid);
$context = context_module::instance($cm->id);

// Capability: adjust if you use a different one.
require_capability('mod/kiwilearner_interactivevideo:view', $context);

// Award XP (once per day per interaction).
$awarded = \local_kiwilearner\events\xp_award::award_h5p_correct_once_per_day(
    $USER->id,
    (int)$course->id,
    $interactionid,
    1 // XP value (can be config later)
);

echo json_encode([
    'ok' => true,
    'awarded' => $awarded
]);
