<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use core_contentbank\contentbank;
use context_course;

class mod_kiwivideo_mod_form extends moodleform_mod {

    public function definition() {
        global $COURSE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // --- Content bank H5P selector ---
        $context = context_course::instance($COURSE->id);
        $cb = new contentbank();

        $options = [0 => get_string('none')];

        // Filter to H5P content type if possible.
        $contents = $cb->search_contents(null, $context->id, ['h5p']);
        foreach ($contents as $content) {
            $options[$content->get_id()] = format_string($content->get_name());
        }

        $mform->addElement('select', 'h5pcontentid',
            get_string('h5pcontent_select', 'mod_kiwivideo'), $options);
        $mform->setDefault('h5pcontentid', 0);
        $mform->addRule('h5pcontentid', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
