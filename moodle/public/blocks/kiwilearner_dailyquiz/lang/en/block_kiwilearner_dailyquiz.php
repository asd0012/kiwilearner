<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Daily Quiz';
$string['kiwilearner_dailyquiz:addinstance'] = 'Add a new Daily Quiz block';
$string['kiwilearner_dailyquiz:myaddinstance'] = 'Add a new Daily Quiz block to My Moodle page';
$string['numquestions'] = 'Number of questions';
$string['topics'] = 'Topics (comma separated)';
$string['generatequiz'] = 'Generate Quiz';
$string['emailsummary'] = 'Email me the summary';


$string['goalstatus_achieved'] = 'Achieved';
$string['goalstatus_missed']   = 'Missed';
$string['goalstatus_unknown']  = 'Unknown';

$string['goal_daily_hit_msg'] =
    '🎯 Goal smashed! You hit <strong>{$a->target} XP</strong> today. Goal streak: <strong>{$a->streak}</strong> days.';
$string['goal_daily_miss_msg'] =
    '😤 You’re <strong>{$a->remain} XP</strong> away from today’s goal ({$a->target} XP). One more push and you’re good.';
$string['goal_daily_unknown_msg'] =
    'Goal status is pending right now — please go to set daily goal.';

$string['streak_milestone_5']  = '🔥 5-day streak! Nice start — keep the momentum.';
$string['streak_milestone_10'] = '🎉 10-day streak! Double digits, you’re cooking.';
$string['streak_milestone_15'] = '🚀 15-day streak! That consistency is doing damage (in a good way).';
$string['streak_milestone_20'] = '🏆 20-day streak! Absolute legend. Don’t break it now.';
