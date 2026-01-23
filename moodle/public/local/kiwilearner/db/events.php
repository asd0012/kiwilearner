<?php
defined('MOODLE_INTERNAL') || die();

$observers = [

    // Existing observers for syncing XP config.
    [
        'eventname'   => '\core\event\question_created',
        'callback'    => '\local_kiwilearner\events\question_sync::handle_question_saved',
        'includefile' => '/local/kiwilearner/classes/events/question_sync.php',
        'priority'    => 100,
    ],
    [
        'eventname'   => '\core\event\question_updated',
        'callback'    => '\local_kiwilearner\events\question_sync::handle_question_saved',
        'includefile' => '/local/kiwilearner/classes/events/question_sync.php',
        'priority'    => 100,
    ],

    // NEW — Award participation XP when quiz attempt is submitted.
    [
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => '\local_kiwilearner\events\xp_award::attempt_submitted',
        'includefile' => '/local/kiwilearner/classes/events/xp_award.php',
        'priority'    => 200,
    ],

    // NEW — Award XP for correctness.
    [
        'eventname'   => '\mod_quiz\event\attempt_graded',
        'callback'    => '\local_kiwilearner\events\xp_award::attempt_graded',
        'includefile' => '/local/kiwilearner/classes/events/xp_award.php',
        'priority'    => 200,
    ],
];
