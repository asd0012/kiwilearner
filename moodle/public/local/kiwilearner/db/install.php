<?php
// local/kiwilearner/db/install.php

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook for local_kiwilearner.
 *
 * Called once, immediately after DB tables from install.xml are created. :contentReference[oaicite:10]{index=10}
 */
function xmldb_local_kiwilearner_install(): bool {
    // Autoloads classes/local_kiwilearner/customfields/question_fields_manager.php.
    \local_kiwilearner\customfields\question_fields_manager::ensure_fields_exist();
    return true;
}
