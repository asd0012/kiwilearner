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

// Kiwilearner xp field for questions
$string['kiwi_xp_participation_xp'] = 'Kiwi XP – participation xp granted';
$string['kiwi_xp_correct_xp'] = 'Kiwi XP – correct answer xp granted';
$string['kiwi_xp_enabled'] = 'Enable Kiwi XP';
