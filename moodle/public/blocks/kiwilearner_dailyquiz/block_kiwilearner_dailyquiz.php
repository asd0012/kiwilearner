<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class block_kiwilearner_dailyquiz extends block_base
{
	public function init()
	{
		$this->title = get_string('pluginname', 'block_kiwilearner_dailyquiz');
	}

	public function get_content()
	{
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
		$prefkey = 'block_kiwilearner_dailyquiz_summary_' . $courseid;

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
					$qid = (int)$qid;
					if ($qid <= 0) {
						continue;
					}

					// Radio group name is q{questionid}, e.g. q8509
					$picked = optional_param('q' . $qid, 0, PARAM_INT);
					if ($picked > 0) {
						$answers[$qid] = $picked;
					}
				}

				// Optional: prevent re-submitting the same quiz via refresh/back
				unset($SESSION->$storekey);

				block_kiwilearner_dailyquiz_submit_attempt($USER->id, $answers);
				$results = block_kiwilearner_dailyquiz_get_results($USER->id);

				// Build inline summary data from $results.
				// all questions for TODAY (your get_results currently returns whole day)
				$allquestions = $results->questions ?? ($results['questions'] ?? []);
				if (!is_array($allquestions)) {
					$allquestions = [];
				}

				// "this submit" question count: use the generated qids (not answers count)
				$attemptcount = is_array($qids) ? count($qids) : 0;
				$attemptcount = max(1, $attemptcount);

				// take only the latest N questions as "this submit"
				$attemptquestions = array_slice($allquestions, -$attemptcount);

				// helper to build items + correctcount (same logic you already wrote)
				$build_items = function (array $questions) use ($posturl): array {
					$items = [];
					$correctcount = 0;

					foreach ($questions as $q) {
						$qtext = is_object($q) ? ($q->text ?? '') : ($q['text'] ?? '');
						$ansraw = is_object($q) ? ($q->answer ?? '') : ($q['answer'] ?? '');
						$correctraw = is_object($q) ? ($q->correct ?? '') : ($q['correct'] ?? '');

						$correctstr = strtolower(trim((string)$correctraw));

						if (in_array($correctstr, ['yes', 'no', 'true', 'false', '1', '0'], true)) {
							$iscorrect = in_array($correctstr, ['yes', 'true', '1'], true);
							$correctdisplay = $iscorrect ? 'Yes' : 'No';
						} else {
							$iscorrect = (trim((string)$ansraw) !== '' && trim((string)$ansraw) === trim((string)$correctraw));
							$correctdisplay = (string)$correctraw;
						}

						if ($iscorrect) {
							$correctcount++;
						}

						$items[] = [
							'label'          => $qtext,
							'youranswer'     => $ansraw,
							'correctanswer'  => $correctdisplay, // 用 display，別再塞 raw
							'iscorrect'      => $iscorrect,
							'isincorrect'    => !$iscorrect,
							'remediationurl' => !$iscorrect ? $posturl : '',
						];
					}

					return [$items, $correctcount];
				};

				// build FULL-DAY summary (for saving + summary page)
				[$alldayitems, $todaycorrect] = $build_items($allquestions);
				$todaycount = count($alldayitems);

				// build INLINE summary (for course page right after submit)
				[$inlineitems, $attemptcorrect] = $build_items($attemptquestions);

				// store FULL DAY in preferences (so summary page can show everything)
				$summarydata = [
					'quizname'       => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
					'questioncount'  => $todaycount,
					'xp_earned'      => $todaycorrect,
					'daykey'         => $daykey,
					'items'          => $alldayitems,
					'hasitems'       => ($todaycount > 0),
				];

				// render only THIS SUBMIT items, but keep XP as TODAY
				$inlinesummary = $summarydata;
				$inlinesummary['items'] = $inlineitems;
				$inlinesummary['hasitems'] = (count($inlineitems) > 0);

				// urls
				$summaryurl = (new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]))->out(false);
				$continueurl = (new moodle_url('/course/view.php', ['id' => $courseid, 'newquiz' => 1]))->out(false);

				if ($todaycount > 0) {
					set_user_preference($prefkey, json_encode($summarydata));
					$savedsummary = $summarydata;
				}

				$dataresult = (object)[
					'quizname'    => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
					'results'     => $results,
					'posturl'     => $posturl,
					'courseid'    => $COURSE->id,
					'sesskey'     => sesskey(),
					'questions'   => [],
					'summary'     => $inlinesummary,  // ✅ 只顯示本次 items
					'summaryurl'  => $summaryurl,
					'continueurl' => $continueurl,
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

			foreach ($questions as &$q) {
				$qid = is_array($q) ? (int)($q['id'] ?? 0) : (int)($q->id ?? 0);
				$qname = 'q' . $qid;

				if (is_array($q)) {
					$q['qname'] = $qname;
					if (!empty($q['options']) && is_array($q['options'])) {
						foreach ($q['options'] as &$opt) {
							if (is_array($opt)) {
								$opt['qname'] = $qname;
							} else {
								$opt->qname = $qname;
							}
						}
						unset($opt);
					}
				} else {
					$q->qname = $qname;
					if (!empty($q->options) && is_array($q->options)) {
						foreach ($q->options as &$opt) {
							if (is_array($opt)) {
								$opt['qname'] = $qname;
							} else {
								$opt->qname = $qname;
							}
						}
						unset($opt);
					}
				}
			}
			unset($q);

			// Store QIDs in session so the submit handler can validate what was asked.
			$storekey = 'block_kiwilearner_dailyquiz_' . (int)$COURSE->id;

			// build qids robustly (supports array OR object)
			$qids = [];
			foreach ($questions as $q) {
				$qids[] = is_array($q) ? (int)($q['id'] ?? 0) : (int)($q->id ?? 0);
			}
			$qids = array_values(array_filter($qids, fn($id) => $id > 0));

			$SESSION->$storekey = [
				'qids' => $qids,
				'time' => time(),
			];


			if (empty($questions)) {
				$this->content->text .= $OUTPUT->notification('No matching multichoice questions found. Try different tags/topics.', 'warning');
				$this->content->text .= $mform->render();
				return $this->content;
			}

			$quizdata = (object)[
				'quizname' => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
				'questions' => $questions,
				'posturl'  => $posturl,
				'courseid' => $COURSE->id,
				'sesskey'  => sesskey(),
				'summary'  => null,
			];

			$this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $quizdata);
			return $this->content;
		}

		if (!empty($savedsummary)) {
			$summaryurl = (new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]))->out(false);

			$this->content->text .= html_writer::div(
				'XP earned today: <strong>' . (int)($savedsummary['xp_earned'] ?? 0) .
					' / ' . (int)($savedsummary['questioncount'] ?? 0) . '</strong> ' .
					html_writer::link($summaryurl, 'View today\'s summary', ['class' => 'btn btn-sm btn-outline-secondary ms-2']),
				'alert alert-info mt-2'
			);
		}
		// 然後照常顯示 generator form
		$this->content->text .= $mform->render();

		$this->content->text .= html_writer::link(
			new moodle_url('/course/view.php', ['id' => $courseid, 'newquiz' => 1]),
			'Generate quiz',
			['class' => 'btn btn-primary mt-2']
		);

		return $this->content;


		// Default: show generator form.
		$this->content->text .= $mform->render();
		return $this->content;
	}
}
