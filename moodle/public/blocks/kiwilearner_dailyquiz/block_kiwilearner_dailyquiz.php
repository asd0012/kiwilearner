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

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

	require_once(__DIR__ . '/lib.php');

	// -------------------------
	// (0) Common vars (do this early)
	// -------------------------
	
	$courseid  = (int)$COURSE->id;
	$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
	$posturl   = $courseurl->out(false);

	$daykey  = userdate(time(), '%Y%m%d');
	$storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id . '_' . $daykey;
	$prefkey  = 'block_kiwilearner_dailyquiz_summary_' . (int)$COURSE->id . '_' . $daykey;


	$reattempt = optional_param('reattempt', 0, PARAM_BOOL);
	if ($reattempt) {
		require_sesskey();

		// Clear the saved summary so the quiz form shows again.
		unset_user_preference($prefkey);

		redirect(new moodle_url('/course/view.php', ['id' => $COURSE->id]));
	}
	$results = [
		'total' => 0,
	];

	$newquiz = optional_param('newquiz', 0, PARAM_BOOL);

	// =========================================================
	// (1) Load saved summary at the start of the request (GET path)
	//     Put it HERE: before any submit/generate handling.
	// =========================================================
	
	$savedsummary = json_decode(get_user_preferences($prefkey, ''), true);
	if (!is_array($savedsummary) || (($savedsummary['daykey'] ?? '') !== $daykey)) {
		$savedsummary = null;
	}

	if ($newquiz) {
		$savedsummary = null; // force showing generator form
	}
	// =========================================================
	// (2) On submit, compute + store (POST path)
	//     Put it HERE: right after reading submitquiz.
	// =========================================================

	$submitquiz = optional_param('submitquiz', 0, PARAM_BOOL);
	$simulateincorrect = optional_param('simulateincorrect', 0, PARAM_BOOL); // GET/POST

	if ($submitquiz) {
		require_sesskey();
		// Get the previously generated question ids from session.
		$storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id;
		$sessionquestions = $SESSION->$storekey['questions'] ?? [];

		$idx = 0;
		$correctcount = 0;

		foreach ($sessionquestions as $q) {
			$qid = (int)($q['id'] ?? 0);
			$qtext = (string)($q['text'] ?? '');

			$chosenid = $answers[$qid] ?? null;
			$yourtext = '';

			// Find chosen option text (same as you already do)
			if ($chosenid !== null && !empty($q['options']) && is_array($q['options'])) {
				foreach ($q['options'] as $opt) {
					if ((int)$opt['id'] === (int)$chosenid) {
						$yourtext = (string)($opt['text'] ?? '');
						break;
					}
				}
			}

			// ---- MOCK correctness for UI testing ----
			$correctopt = (!empty($q['options']) && is_array($q['options'])) ? ($q['options'][0] ?? null) : null;
			$correctid = $correctopt ? (int)($correctopt['id'] ?? 0) : 0;
			$correcttext = $correctopt ? (string)($correctopt['text'] ?? '') : '';

			$isunknown = ($chosenid === null);
			$iscorrect = (!$isunknown && $correctid && ((int)$chosenid === $correctid));
			$isincorrect = (!$isunknown && !$iscorrect);

			// Force “incorrect situation” for the first question when simulateincorrect=1
			if ($simulateincorrect && $idx === 0 && !$isunknown) {
				$iscorrect = false;
				$isincorrect = true;
			}

			if ($iscorrect) {
				$correctcount++;
			}

			$items[] = [
				'label' => $qtext,
				'youranswer' => $yourtext,
				'correctanswer' => $correcttext,
				'iscorrect' => $iscorrect,
				'isincorrect' => $isincorrect,
				'isunknown' => $isunknown,
				'remediationurl' => '', // optional
			];

			$idx++;
		}
	#	$items = [];
	#	foreach ($sessionquestions as $q) {
	#		$qid = (int)($q['id'] ?? 0);
	#		$qtext = (string)($q['text'] ?? '');

	#		$chosenid = $answers[$qid] ?? null;
	#		$yourtext = '';

	#		if ($chosenid !== null && !empty($q['options']) && is_array($q['options'])) {
	#			foreach ($q['options'] as $opt) {
	#				if ((int)$opt['id'] === (int)$chosenid) {
	#					$yourtext = (string)($opt['text'] ?? '');
	#					break;
	#				}
	#			}
	#		}

	#		$items[] = [
	#			'label' => $qtext,
	#			'youranswer' => $yourtext,
	#			'correctanswer' => '',      // 先空
	#			'iscorrect' => false,
	#			'isincorrect' => false,
	#			'isunknown' => true,        // ✅ 先全部 Pending
	#			'remediationurl' => '',
	#		];
	#	}

		$questioncount = count($items);
		$xp_earned = $correctcount;   // ✅ XP = correct answers
		$scorepercent = $questioncount ? round(($correctcount / $questioncount) * 100, 1) : 0.0;

		$summarydata = [
			'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
			'questioncount' => $questioncount,
			'xp_earned' => $xp_earned,
			'correctcount' => $correctcount,       // 還沒資料就先 0
			'scorepercent' => 0,
			'daykey' => $daykey,
			'items' => $items,
			'hasitems' => ($questioncount > 0),
		];

		set_user_preference($prefkey, json_encode($summarydata));
		$savedsummary = $summarydata;

		$attemptmax = 2;
		$attemptnum = 1; // TODO: later read from DB when attempts feature is done.

		$courseurl = new moodle_url('/course/view.php', ['id' => $COURSE->id]);

		$allcorrect = ($questioncount > 0 && $correctcount === $questioncount);

		// Show reattempt ONLY when not all-correct and we're still on attempt 1.
		$showreattempt = (!$allcorrect && $attemptnum < $attemptmax);

		$feedback = [
			'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
			'attemptnum' => $attemptnum,
			'attemptmax' => $attemptmax,

			// show something meaningful for now:
			'xp' => $xp_earned,

			// ✅ STEP 2: required for forms/buttons in feedback.mustache
			'courseid' => $COURSE->id,
			'sesskey'  => sesskey(),
			'posturl'  => $courseurl->out(false),

			// optional UI controls
			'is_attempt1_all_correct' => $allcorrect,
			'is_attempt1_partial' => !$allcorrect,
			'is_attempt2_final' => false,

			'showreattempt' => $showreattempt,
			'showfinalsubmit' => true,

		];

		$completedtoday = !empty($savedsummary);  
		$showgenerator  = !$completedtoday;       

		$dataresult = (object)[
			'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
			'results'  => $results,
			'posturl'  => $posturl,
			'courseid' => $COURSE->id,
			'sesskey'  => sesskey(),
			'questions'=> [],
			'summary'  => $summarydata,   // ✅ attach to mustache
			'feedback' => $feedback,
			'completedtoday' => $completedtoday,
			'showgenerator' => $showgenerator,
		];

                $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $dataresult);
                return $this->content;
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
            $topics = [(string)$data->topics];
            $questions = block_kiwilearner_dailyquiz_get_mcq_questions($COURSE->id, $topics, (int)$data->numquestions);
	    $emailsummary = !empty($data->emailsummary);

            // Store QIDs in session so the submit handler can validate what was asked.
            $storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id;
	    $SESSION->$storekey = [
		    'questions'   => $questions,
		    'qids'        => array_values(array_map(fn($q) => (int)$q['id'], $questions)),
		    'attemptnum'  => 1,
		    'attemptmax'  => 2,
		    'time'        => time(),
	    ];


            if (empty($questions)) {
                $this->content->text .= $OUTPUT->notification('No matching multichoice questions found. Try different tags/topics.', 'warning');
                $this->content->text .= $mform->render();
                return $this->content;
            }

	    $quizdata = (object)[
		    'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
		    'questions'=> $questions,
		    'posturl'  => $posturl,
		    'courseid' => $COURSE->id,
		    'sesskey'  => sesskey(),
		    'summary'  => null,
		    'feedback' => null,
	    ];

            $this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $quizdata);
            return $this->content;
	}

	if (!empty($savedsummary) ) {
		$data = (object)[
			'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
			'posturl'  => $posturl,
			'courseid' => $courseid,
			'sesskey'  => sesskey(),
			'questions'=> [],
			'summary'  => $savedsummary,
			'feedback' => null,
		];

		$this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $data);


		$this->content->text .= html_writer::link(
			new moodle_url('/course/view.php', ['id' => $courseid, 'newquiz' => 1]),
			'Generate quiz',
			['class' => 'btn btn-primary mt-2']
		); 

		return $this->content;
	}

	// Default: show generator form.
	$this->content->text .= $mform->render();
	return $this->content;
    }

}

?>
