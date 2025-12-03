<?php
defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->version   = 2025120300;  // <— bump this
$plugin->requires  = 2024042200;  // Moodle 4.3 baseline
$plugin->component = 'local_kiwilearner';
$plugin->maturity  = MATURITY_ALPHA;

