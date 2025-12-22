<?php

namespace local_kiwilearner\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class xpsettings_form extends \moodleform {

    public function definition() {
        global $DB;

        $mform    = $this->_form;
        $questionid = $this->_customdata['questionid'];
        $courseid   = $this->_customdata['courseid'];

        // Load existing record if any.
        $record = $DB->get_record('local_kiwilearner_question_xp', [
            'questionid' => $questionid,
            'courseid'   => $courseid,
        ]);

        $mform->addElement('hidden', 'questionid', $questionid);
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'xp_participation',
            get_string('xp_participation', 'local_kiwilearner'));
        $mform->setType('xp_participation', PARAM_INT);
        $mform->setDefault('xp_participation', $record->xp_participation ?? 0);

        $mform->addElement('text', 'xp_correct',
            get_string('xp_correct', 'local_kiwilearner'));
        $mform->setType('xp_correct', PARAM_INT);
        $mform->setDefault('xp_correct', $record->xp_correct ?? 1);

        $mform->addElement('advcheckbox', 'enabled',
            get_string('enabled', 'local_kiwilearner'));
        $mform->setDefault('enabled', $record->enabled ?? 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
