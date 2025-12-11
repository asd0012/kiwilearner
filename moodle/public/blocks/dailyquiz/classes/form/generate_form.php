<?php
namespace block_dailyquiz\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

class generate_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'numquestions', get_string('numquestions', 'block_dailyquiz'));
        $mform->setType('numquestions', PARAM_INT);

        $mform->addElement('text', 'topics', get_string('topics', 'block_dailyquiz'));
        $mform->setType('topics', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('generatequiz', 'block_dailyquiz'));
    }
}

?>
