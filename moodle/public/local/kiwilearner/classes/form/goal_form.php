<?php
namespace local_kiwilearner\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_kiwilearner\goal;

class goal_form extends \moodleform {

    public function definition() {
        $mform     = $this->_form;
        $custom    = $this->_customdata ?? [];
        $defaults  = $custom['defaults'] ?? new \stdClass();

        $courseid  = $custom['courseid'] ?? ($defaults->courseid ?? 0);

        // Hidden: courseid.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Show xp field
        $mform->addElement(
            'text',
            'xp_target',
            get_string('xptarget', 'local_kiwilearner')
        );
        $mform->setType('xp_target', PARAM_RAW_TRIMMED);

        // Default XP target if provided.
        if (isset($defaults->xp_target)) {
            $mform->setDefault('xp_target', $defaults->xp_target);
        }

        // Standard action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    // Moodle will automatically call this function for validation
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Required check.
        if (!isset($data['xp_target']) || $data['xp_target'] === '') {
            $errors['xp_target'] = get_string('error_xptarget_required');
            return $errors; // No need to continue.
        }

        $raw = trim($data['xp_target']);

        // STRICT integer check: only digits allowed.
        if (!preg_match('/^\d+$/', $raw)) {
            $errors['xp_target'] = get_string('error_xptarget_notint', 'local_kiwilearner');
            return $errors;
        }

        // Now safe to cast to int.
        $xp = (int)$raw;

        if ($xp < 1 || $xp > 999) {
            $errors['xp_target'] = get_string('error_xptarget_range', 'local_kiwilearner');
        }

        return $errors;
    }


}

