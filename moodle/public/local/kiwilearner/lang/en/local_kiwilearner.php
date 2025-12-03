<?php
defined('MOODLE_INTERNAL') || die();

// Required by Moodle.
$string['pluginname']   = 'KiwiLearner';

// Form labels you use now.
$string['goalsettings'] = 'Daily goal settings';
$string['goaltype']     = 'Daily goal type';
$string['goaltype_xp']  = 'XP per day';
$string['goaltype_lesson'] = 'Lessons per day';
$string['xptarget']     = 'Daily XP target';
$string['lessontarget'] = 'Daily lessons target';

// Validation messages you throw in PHP.
$string['error_xptarget_required'] = 'Please enter an XP target.';
$string['error_xptarget_range']    = 'XP target must be between 1 and 999.';

// Only used if you call $mform->addHelpButton('goal_type','goaltype','local_kiwilearner')
// and $mform->addHelpButton('xp_target','xptarget','local_kiwilearner').
$string['goaltype_help'] = 'Choose whether your daily goal is based on XP or lessons.';
$string['xptarget_help'] = 'How many XP you aim to earn per day.';
$string['error_lessontarget_required'] = 'Please enter how many lessons per day you want to complete.';
$string['error_lessontarget_range'] = 'Daily lessons target must be between 1 and 999.';


// Only used if you pass ['placeholder' => get_string('xptarget_placeholder','local_kiwilearner')]
// in $mform->addElement('text', 'xp_target', ... , $attrs)
$string['xptarget_placeholder'] = 'e.g., 10–999';

// Only used if you call redirect(..., get_string('saved','local_kiwilearner'))
$string['goalupdated'] = 'Your daily goal has been updated.';

