<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version metadata for the plugintype_pluginname plugin.
 *
 * @package   mod_kiwilearner_segvideo
 * @copyright 2025, COSC680 Team University of Canterbury, New Zealand <ypa31@uclive.ac.nz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025122900;
$plugin->requires = TODO;
$plugin->supported = TODO;   // Available as of Moodle 3.9.0 or later.
$plugin->incompatible = TODO;   // Available as of Moodle 3.9.0 or later.
$plugin->component = 'mod_kiwilearner_interactivevideo'; // Full name of the plugin (used for diagnostics)
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'TODO';

$plugin->dependencies = [
    'mod_forum' => 2022042100,
    'mod_data' => 2022042100
];