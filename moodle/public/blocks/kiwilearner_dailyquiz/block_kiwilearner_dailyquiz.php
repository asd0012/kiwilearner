<?php
defined('MOODLE_INTERNAL') || die();

class block_kiwilearner_dailyquiz extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_kiwilearner_dailyquiz');
    }

    public function get_content() {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        require_once($this->dir . '/classes/form/generate_form.php');
        require_once($this->dir . '/lib.php');

        $mform = new \block_kiwilearner_dailyquiz\form\generate_form();

        if ($mform->is_cancelled()) {
            $this->content->text .= 'Quiz cancelled.';
        } else if ($data = $mform->get_data()) {
            // Fetch questions
            $topics = array_map('trim', explode(',', $data->topics));
            $questions = block_kiwilearner_dailyquiz_get_mcq_questions($this->page->course->id, $topics, $data->numquestions);

            // Display quiz form or submission results
            if (isset($_POST['submitquiz'])) {
                $answers = [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'q') === 0) {
                        $qid = str_replace('q', '', $key);
                        $answers[$qid] = $value;
                    }
                }
                block_kiwilearner_dailyquiz_submit_attempt($USER->id, $answers);

                // Fetch results
                $results = block_kiwilearner_dailyquiz_get_results($USER->id);
                $dataresult = new stdClass();
                $dataresult->quizname = get_string('pluginname', 'block_kiwilearner_dailyquiz');
                $dataresult->results = $results;
                $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $dataresult);

            } else {
                // Show quiz
                $quizdata = new stdClass();
                $quizdata->quizname = get_string('pluginname', 'block_kiwilearner_dailyquiz');
                $quizdata->questions = $questions;
                $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $quizdata);
            }

        } else {
            $this->content->text .= $mform->render();
        }

        return $this->content;
    }
}

?>