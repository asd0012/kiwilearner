<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_kiwivideo_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;
        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Intro.
        $this->standard_intro_elements();

        // H5P content id.
        $mform->addElement('text', 'h5pcontentid', get_string('h5pcontentid', 'kiwivideo'));
        $mform->setType('h5pcontentid', PARAM_INT);
        $mform->addHelpButton('h5pcontentid', 'h5pcontentid', 'kiwivideo');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
