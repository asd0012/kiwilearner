<?php

/**
 * Version metadata for the plugintype_pluginname plugin.
 *
 * @package   mod_kiwilearner_interactivevideo
 * @copyright 2025, COSC680 Team University of Canterbury, New Zealand
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025123000;
$plugin->requires  = 2024042200;  // Moodle 4.3 baseline
$plugin->supported = TODO;   // Available as of Moodle 3.9.0 or later.
$plugin->incompatible = TODO;   // Available as of Moodle 3.9.0 or later.
$plugin->component = 'mod_kiwilearner_interactivevideo'; // Full name of the plugin (used for diagnostics)
$plugin->maturity = MATURITY_ALPHA;
$plugin->release   = 'v0.1';

$plugin->dependencies = [
    'mod_forum' => 2022042100,
    'mod_data' => 2022042100
];

?>