<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class block_kiwilearner_dailyquiz extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_kiwilearner_dailyquiz');
    }

    public function get_content() {
        global $USER, $OUTPUT, $COURSE, $PAGE, $SESSION;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once(__DIR__ . '/lib.php');


        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Always use an explicit course URL (prevents action="/course/view.php" without id).
        $courseurl = new moodle_url('/course/view.php', ['id' => $COURSE->id]);

        // ------------
        // (A) Handle quiz submit FIRST (this POST does NOT include generator fields).
        // ------------
        $submitquiz = optional_param('submitquiz', 0, PARAM_BOOL);
        if ($submitquiz) {
            require_sesskey();

            // Get the previously generated question ids from session.
            $storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id;
            $qids = $SESSION->$storekey['qids'] ?? [];

            if (empty($qids) || !is_array($qids)) {
                $this->content->text .= $OUTPUT->notification('Quiz session expired. Please generate a new quiz.', 'warning');
                // Fall through to show generator form below.
            } else {
                // Collect answers only for known question ids (avoid picking up unrelated POST keys).
                $answers = [];
                foreach ($qids as $qid) {
                    $paramname = 'q' . (int)$qid;
                    $ans = optional_param($paramname, null, PARAM_INT);
                    if ($ans !== null) {
                        $answers[(int)$qid] = $ans;
                    }
                }

                block_kiwilearner_dailyquiz_submit_attempt($USER->id, $answers);
                $results = block_kiwilearner_dailyquiz_get_results($USER->id);

                $dataresult = (object)[
                    'quizname'  => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
                    'results'   => $results,
                    'posturl'   => $courseurl->out(false),
                    'courseid'  => $COURSE->id,
                    'sesskey'   => sesskey(),
                    'questions' => [], // optional; results section shows score
                ];

                $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $dataresult);
                return $this->content;
            }
        }

        // ------------
        // (B) Generator moodleform (explicit action URL).
        // ------------
        $mform = new \block_kiwilearner_dailyquiz\form\generate_form($courseurl);

        if ($mform->is_cancelled()) {
            $this->content->text .= $OUTPUT->notification('Quiz cancelled.', 'info');
            return $this->content;
        }

        if ($data = $mform->get_data()) {
            $topics = array_filter(array_map('trim', explode(',', (string)$data->topics)), fn($t) => $t !== '');
            $questions = block_kiwilearner_dailyquiz_get_mcq_questions($COURSE->id, $topics, (int)$data->numquestions);

            // Store QIDs in session so the submit handler can validate what was asked.
            $storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id;
            $SESSION->$storekey = [
                'qids' => array_values(array_map(fn($q) => (int)$q['id'], $questions)),
                'time' => time(),
            ];

            if (empty($questions)) {
                $this->content->text .= $OUTPUT->notification('No matching multichoice questions found. Try different tags/topics.', 'warning');
                $this->content->text .= $mform->render();
                return $this->content;
            }

            $quizdata = (object)[
                'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
                'questions'=> $questions,
                'posturl'  => $courseurl->out(false),
                'courseid' => $COURSE->id,
                'sesskey'  => sesskey(),
            ];

            $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $quizdata);
            return $this->content;
        }

        // Default: show generator form.
        $this->content->text .= $mform->render();
        return $this->content;
    }

}

?>
