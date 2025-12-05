<?php
namespace local_kiwilearner\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_kiwilearner\goal;

class goal_form extends \moodleform {

    public function definition() {
        $mform     = $this->_form;
        $custom    = $this->_customdata ?? [];
        $defaults  = $custom['defaults'] ?? new \stdClass();

        $courseid  = $custom['courseid'] ?? ($defaults->courseid ?? 0);
        $goaltype  = $defaults->goal_type ?? goal::TYPE_XP;

        // Hidden: courseid.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Hidden: goal_type (XP or LESSON).
        $mform->addElement('hidden', 'goal_type', $goaltype);
        $mform->setType('goal_type', PARAM_INT);

        // Depending on tab, show XP or lessons field.
        if ($goaltype === goal::TYPE_XP) {
            $mform->addElement('text', 'xp_target',
                get_string('xptarget', 'local_kiwilearner'));
            $mform->setType('xp_target', PARAM_INT);
            if (isset($defaults->xp_target)) {
                $mform->setDefault('xp_target', $defaults->xp_target);
            }
        } else {
            $mform->addElement('text', 'lesson_target',
                get_string('lessontarget', 'local_kiwilearner'));
            $mform->setType('lesson_target', PARAM_INT);
            if (isset($defaults->lesson_target)) {
                $mform->setDefault('lesson_target', $defaults->lesson_target);
            }
        }

        // Standard action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

