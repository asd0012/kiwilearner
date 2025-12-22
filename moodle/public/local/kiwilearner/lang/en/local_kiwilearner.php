<?php
defined('MOODLE_INTERNAL') || die();

// Required by Moodle.
$string['pluginname']   = 'KiwiLearner';

// String related to XP
$string['goalsettings'] = 'Daily goal settings';
$string['xptarget']     = 'Daily XP target (a whole number)';

// Validation messages you throw in PHP.
$string['error_xptarget_required'] = 'Please enter an XP target.';
$string['error_xptarget_notint'] = 'Please enter a whole number (no decimals).';
$string['error_xptarget_range']    = 'XP target must be between 1 and 999.';

$string['xptarget_help'] = 'How many XP you aim to earn per day.';

$string['goalupdated'] = 'Your daily XP goal has been updated.';
$string['task_send_reminders'] = 'Send daily goal reminders';

// Reminder emails.
$string['reminder_subject_nudge'] = 'Daily XP reminder for {$a}';
$string['reminder_body_nudge'] = 'Hi,

You have earned {$a->xptoday} XP today in "{$a->course}".
Your daily target is {$a->xptarget} XP, so you still have {$a->remaining} XP to go.

This is your {$a->slot} reminder from KiwiLearner.';

$string['reminder_small_nudge'] = 'Daily XP reminder for {$a}';

$string['reminder_subject_missed'] = 'You didn\'t reach your XP goal in {$a} today';
$string['reminder_body_missed'] = 'Hi,

Today you earned {$a->xptoday} XP in "{$a->course}" but your daily target was {$a->xptarget} XP.

We\'ll help you try again tomorrow!';

$string['reminder_small_missed'] = 'You missed today\'s XP goal in {$a}.';

$string['messageprovider:goalreminder'] = 'KiwiLearner daily goal reminders';

// Kiwilearner xp field for questions
$string['kiwi_xp_participation_xp'] = 'Kiwi XP – participation xp granted';
$string['kiwi_xp_correct_xp'] = 'Kiwi XP – correct answer xp granted';
$string['kiwi_xp_enabled'] = 'Enable Kiwi XP';
