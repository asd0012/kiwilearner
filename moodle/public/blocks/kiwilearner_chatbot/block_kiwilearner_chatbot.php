<?php
defined('MOODLE_INTERNAL') || die();

class block_kiwilearner_chatbot extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_kiwilearner_chatbot');
    }

    public function applicable_formats() {
        return [
            'site-index' => true,
            'my' => true,
            'course-view' => true,
            'mod' => true,
            'all' => true,
        ];
    }

    public function get_content() {
        global $PAGE,$OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template('block_kiwilearner_chatbot/chatbot', [
            'courseid' => $PAGE->course ? (int)$PAGE->course->id : 0,
            'cmid' => !empty($PAGE->cm) ? (int)$PAGE->cm->id : 0,
        ]);


        // Load AMD (build file included so no grunt needed).
        $PAGE->requires->js_call_amd('block_kiwilearner_chatbot/chatbot', 'init', [
            'courseid' => $PAGE->course ? (int)$PAGE->course->id : 0,
            'cmid' => !empty($PAGE->cm) ? (int)$PAGE->cm->id : 0,
        ]);

        $this->content->footer = '';
        return $this->content;
    }
}
