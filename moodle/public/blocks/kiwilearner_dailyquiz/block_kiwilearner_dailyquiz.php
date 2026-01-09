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

		$daykey = block_kiwilearner_dailyquiz_daykey();
		$prefkey = 'block_kiwilearner_dailyquiz_summary_' . $courseid;
		[$todayxp, $todaytotal] = block_kiwilearner_dailyquiz_get_today_totals_from_temp($USER->id, $courseid, $daykey);

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

				global $DB;

				// ✅ Compute incorrect qids for THIS attempt using answer IDs.
				$incorrectqids = [];

				foreach ($qids as $qid) {
					$qid = (int)$qid;
					$yourid = (int)($answers[$qid] ?? 0);

					// If they didn't pick an answer, treat as incorrect.
					if ($yourid <= 0) {
						$incorrectqids[] = $qid;
						continue;
					}

					// Support multiple correct answers (fraction=1).
					$correctids = $DB->get_fieldset_select(
						'question_answers',
						'id',
						'question = :qid AND fraction >= :f',
						['qid' => $qid, 'f' => 0.999]
					);

					$correctids = array_map('intval', $correctids ?: []);
					if (empty($correctids) || !in_array($yourid, $correctids, true)) {
						$incorrectqids[] = $qid;
					}
				}

				$incorrectqids = array_values(array_unique($incorrectqids));
				$attemptincorrectqids = $incorrectqids; 

				// ✅ Store for reattempt (store a separate key, NOT $storekey)
				$lastkey = 'block_kiwilearner_dailyquiz_last_' . (int)$courseid;
				$SESSION->$lastkey = [
					'daykey' => $daykey,                 // you already have $daykey earlier
					'incorrectqids' => $incorrectqids,
					'time' => time(),
				];

				// Optional: prevent re-submitting the same quiz via refresh/back
				// unset($SESSION->$storekey);
				$attemptqids = $qids; 

				block_kiwilearner_dailyquiz_submit_attempt($USER->id,  $courseid, $answers);
				$daykey = block_kiwilearner_dailyquiz_daykey();
				[$todayxp, $todaytotal] = block_kiwilearner_dailyquiz_get_today_totals_from_temp($USER->id, $courseid, $daykey);
				$results = block_kiwilearner_dailyquiz_get_results($USER->id, $courseid, $daykey);

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
						$qid = is_object($q)
							? (int)($q->qid ?? $q->questionid ?? $q->question ?? $q->id ?? 0)
							: (int)($q['qid'] ?? $q['questionid'] ?? $q['question'] ?? $q['id'] ?? 0);

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
							'qid' => $qid,                       // ✅ this is the one you need
							'label'          => $qtext,
							'youranswer'     => $ansraw,
							'correctanswer'  => $correctdisplay,
							'iscorrect'      => $iscorrect,
							'isincorrect'    => !$iscorrect,
							'remediationurl' => !$iscorrect ? $posturl : '',
						];
					}

					return [$items, $correctcount];
				};

				// build FULL-DAY summary (for saving + summary page)
			#	[$alldayitems, $todaycorrect] = $build_items($allquestions);
				[$alldayitems, $ignoredCorrect] = $build_items($allquestions);  // <-- don't store into $todayxp
				$todaycount = count($alldayitems);

				// build INLINE summary (for course page right after submit)
				// [$inlineitems, $attemptcorrect] = $build_items($attemptquestions);

				// store FULL DAY in preferences (so summary page can show everything)
				$summarydata = [
					'quizname'       => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
					'questioncount'  => $todaytotal,
					'xp_earned'      => $todayxp,
					'daykey'         => $daykey,
					'items'          => $alldayitems,
					'hasitems'       => ($todaytotal > 0),
				];

				// render only THIS SUBMIT items, but keep XP as TODAY
				#$inlinesummary = $summarydata;
				#$inlinesummary['items'] = $inlineitems;
				#$inlinesummary['hasitems'] = (count($inlineitems) > 0);

				[$inlineitems, $attemptcorrect] = $build_items($attemptquestions);

				// incorrect qids for THIS attempt (matches what UI says)
				$canreattempt = !empty($attemptincorrectqids);


				$canreattempt = !empty($incorrectqids);

				// urls FIRST (so they exist)
				$summaryurl  = (new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]))->out(false);
				$continueurl = (new moodle_url('/course/view.php', ['id' => $courseid, 'newquiz' => 1]))->out(false);

				$reattempturl = (new moodle_url('/course/view.php', [
					'id' => $courseid,
					'reattempt' => 1,
					'sesskey' => sesskey(),
				]))->out(false);


				// after computing $incorrectqids:
				$canreattempt = !empty($incorrectqids);   // IMPORTANT: must be !empty, not empty

				$quizname = get_string('pluginname', 'block_kiwilearner_dailyquiz');

				$inlinesummary = [
					'quizname'      => $quizname,
					'xp_earned'     => $todayxp,
					'questioncount' => $todaytotal,

					'continueurl'   => $continueurl,
					'summaryurl'    => $summaryurl,

					'items'         => $inlineitems,
					'hasitems'      => !empty($inlineitems),

					'canreattempt'  => $canreattempt,
					'reattempturl'  => $reattempturl,

					'incorrectcount' => count($attemptincorrectqids),
				];


				#if ($todaycount > 0) {
				if ($todaytotal > 0) {
					set_user_preference($prefkey, json_encode($summarydata));
					$savedsummary = $summarydata;
				}

				// BEFORE you unset the session storekey:
				$attemptqids = $SESSION->$storekey['qids'] ?? array_keys($answers);

				// If you need daykey:
				$daykey = block_kiwilearner_dailyquiz_daykey();

				// Build attempt items (however you build it)
				$attemptitems = block_kiwilearner_dailyquiz_build_attempt_items($USER->id, $courseid, $daykey, $attemptqids);
				$attemptqids = array_values(array_map('intval', (array)$attemptqids));

				if (!empty($attemptitems['items']) && is_array($attemptitems['items'])) {
					foreach ($attemptitems['items'] as $i => &$it) {
						$qid = (int)($it['qid'] ?? $it['questionid'] ?? $it['id'] ?? ($attemptqids[$i] ?? 0));
						$it['qid'] = $qid;

						$iscorrect = !empty($it['iscorrect']);
						$it['remediationurl'] = (!$iscorrect && $qid > 0)
							? (new moodle_url('/local/kiwilearner/review.php', [
								'courseid' => $courseid,
								'qid' => $qid,
							]))->out(false)
							: '';
					}
					unset($it);
				}
				$dataresult = (object)[
					'quizname'    => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
					'results'     => $results,
					'posturl'     => $posturl,
					'courseid'    => $COURSE->id,
					'sesskey'     => sesskey(),
					'questions'   => [],
					'summary'     => $inlinesummary, 
					'summaryurl'  => $summaryurl,
					'continueurl' => $continueurl,
					'attempt' => $attemptitems,

					// ✅ ADD THESE
					'canreattempt' => $canreattempt,
					'reattempturl' => $reattempturl,
				];

				$this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $dataresult);
				return $this->content;
			}
		}

		// ------------
		// (B) Generator moodleform (explicit action URL).
		// ------------
		$mform = new \block_kiwilearner_dailyquiz\form\generate_form($courseurl);


		$reattempt = optional_param('reattempt', 0, PARAM_BOOL);

		if ($reattempt) {
			// Optional: if you put sesskey in URL, you can validate it
			// require_sesskey();

			$lastkey  = 'block_kiwilearner_dailyquiz_last_' . (int)$courseid;
			$storekey = 'block_kiwilearner_dailyquiz_' . (int)$courseid;

			$last = $SESSION->$lastkey ?? null;

			if (!is_array($last) || (($last['daykey'] ?? '') !== $daykey)) {
				$this->content->text .= $OUTPUT->notification(
					'Reattempt session expired. Please generate a new quiz.',
					'warning'
				);
				// fall through to generator below
			} else {
				$incorrectqids = $last['incorrectqids'] ?? [];
				$incorrectqids = array_values(array_filter(array_map('intval', (array)$incorrectqids), fn($id) => $id > 0));

				if (empty($incorrectqids)) {
					$this->content->text .= $OUTPUT->notification(
						'No incorrect questions to reattempt.',
						'info'
					);
					// fall through to generator below
				} else {
					// ✅ Load those questions
					$questions = block_kiwilearner_dailyquiz_get_questions_by_ids($courseid, $incorrectqids);

					if (empty($questions)) {
						$this->content->text .= $OUTPUT->notification(
							'Could not load the incorrect questions. Please generate a new quiz.',
							'warning'
						);
						// fall through
					} else {
						// ✅ Add qname to match your mustache radio group names
						foreach ($questions as &$q) {
							$qid = is_array($q) ? (int)($q['id'] ?? 0) : (int)($q->id ?? 0);
							$qname = 'q' . $qid;

							if (is_array($q)) {
								$q['qname'] = $qname;
								if (!empty($q['options']) && is_array($q['options'])) {
									foreach ($q['options'] as &$opt) {
										if (is_array($opt)) $opt['qname'] = $qname;
										else $opt->qname = $qname;
									}
									unset($opt);
								}
							} else {
								$q->qname = $qname;
								if (!empty($q->options) && is_array($q->options)) {
									foreach ($q->options as &$opt) {
										if (is_array($opt)) $opt['qname'] = $qname;
										else $opt->qname = $qname;
									}
									unset($opt);
								}
							}
						}
						unset($q);

						// ✅ Store these qids so submit handler knows what was asked
						$SESSION->$storekey = [
							'qids' => $incorrectqids,
							'time' => time(),
							'mode' => 'reattempt',
						];

						$quizdata = (object)[
							'quizname'  => get_string('pluginname', 'block_kiwilearner_dailyquiz'),
							'questions' => $questions,
							'posturl'   => $posturl,
							'courseid'  => $courseid,
							'sesskey'   => sesskey(),
							'summary'   => null, // IMPORTANT: show quiz form
						];

						$this->content->text .= $OUTPUT->render_from_template('block_kiwilearner_dailyquiz/attempt_quiz', $quizdata);
						return $this->content;
					}
				}
			}
		}


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

		$summaryurl = (new moodle_url('/blocks/kiwilearner_dailyquiz/summary.php', ['id' => $courseid]))->out(false);

		if ($todaytotal > 0) {
			$this->content->text .= html_writer::div(
				'XP earned today: <strong>' . (int)$todayxp . ' / ' . (int)$todaytotal . '</strong> ' .
					html_writer::link($summaryurl, 'View today\'s summary', ['class' => 'btn btn-sm btn-outline-secondary ms-2']),
				'alert alert-info mt-2'
			);
		} else if (!empty($savedsummary)) {
			$this->content->text .= html_writer::div(
				'XP earned today: <strong>0 / 0</strong> ' .
					html_writer::link($summaryurl, 'View today\'s summary', ['class' => 'btn btn-sm btn-outline-secondary ms-2']),
				'alert alert-info mt-2'
			);
		}


		$this->content->text .= $mform->render();

		return $this->content;


		// Default: show generator form.
		$this->content->text .= $mform->render();
		return $this->content;
	}
}
