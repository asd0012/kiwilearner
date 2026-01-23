<?php
defined('MOODLE_INTERNAL') || die();

use local_kiwilearner\utils\xp_engine;

/**
 * PHPUnit tests for KiwiLearner XP rules.
 *
 * These tests do NOT simulate the quiz UI.
 * Instead, they seed the core tables that xp_engine reads:
 *  - quiz_attempts (uniqueid == question usage id)
 *  - question_usages
 *  - question_attempts
 *  - question_attempt_steps (fraction)
 */
final class xp_engine_test extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function set_global_defaults(int $part, int $correct, int $enabled, float $threshold): void {
        set_config('default_xp_participation', $part, 'local_kiwilearner');
        set_config('default_xp_correct', $correct, 'local_kiwilearner');
        set_config('default_xp_enabled', $enabled, 'local_kiwilearner');
        // Stored as string in config, but xp_engine casts to float.
        set_config('correct_fraction_threshold', (string)$threshold, 'local_kiwilearner');
    }

    private function create_question_in_course(int $courseid): int {
        global $DB;

        $context = context_course::instance($courseid);

        // Create a question category.
        $cat = (object)[
            'name'         => 'KiwiLearner test category',
            'contextid'    => $context->id,
            'info'         => '',
            'infoformat'   => FORMAT_HTML,
            'stamp'        => 'kiwi_' . uniqid(),
            'parent'       => 0,
            'sortorder'    => 999,
            'idnumber'     => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $catid = $DB->insert_record('question_categories', $cat);

        // Create a minimal question record.
        $q = (object)[
            'category'           => $catid,
            'parent'             => 0,
            'name'               => 'KiwiLearner test question',
            'questiontext'       => '<p>Test?</p>',
            'questiontextformat' => FORMAT_HTML,
            'generalfeedback'    => '',
            'generalfeedbackformat' => FORMAT_HTML,
            'defaultmark'        => 1.0,
            'penalty'            => 0.0,
            'qtype'              => 'shortanswer',
            'length'             => 1,
            'stamp'              => 'kiwiq_' . uniqid(),
            'version'            => '1',
            'hidden'             => 0,
            'timecreated'        => time(),
            'timemodified'       => time(),
            'createdby'          => 2,
            'modifiedby'         => 2,
        ];
        return (int)$DB->insert_record('question', $q);
    }

    /**
     * Seed a "quiz attempt" + question usage + question attempt + final step fraction.
     *
     * Returns [$attemptid, $quizcm, $quiz, $usageid, $qaid]
     */
    private function seed_attempt(int $userid, int $courseid, int $questionid, float $finalfraction): array {
        global $DB;

        // Create a quiz module instance (for realism; xp_engine doesn't join to quiz tables).
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $courseid]);
        $quizid = (int)$quiz->id;
        
        $attemptno = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid]) + 1;

        $context = context_course::instance($courseid);

        // Create question usage.
        $usageid = (int)$DB->insert_record('question_usages', (object)[
            'contextid' => $context->id,
            'component' => 'mod_quiz',
            'preferredbehaviour' => 'deferredfeedback',
        ]);

        // Create question attempt row.
        $qaid = (int)$DB->insert_record('question_attempts', (object)[
            'questionusageid' => $usageid,
            'slot'            => 1,
            'behaviour'       => 'deferredfeedback',
            'questionid'      => $questionid,
            'variant'         => 1,
            'maxmark'         => 1.0,
            'minfraction'     => 0.0,
            'maxfraction'     => 1.0,
            'flagged'         => 0,
            'questionsummary' => '',
            'rightanswer'     => '',
            'responsesummary' => '',
            'timemodified'    => time(),
        ]);

        // Final step with fraction.
        $DB->insert_record('question_attempt_steps', (object)[
            'questionattemptid' => $qaid,
            'sequencenumber'    => 1,
            'state'             => 'complete',
            'fraction'          => $finalfraction,
            'timecreated'       => time(),
            'userid'            => $userid,
        ]);

        // Create quiz attempt row.
        $attemptid = (int)$DB->insert_record('quiz_attempts', (object)[
            'quiz'           => $quizid,
            'userid'         => $userid,
            'attempt'        => $attemptno,
            'uniqueid'       => $usageid,     // IMPORTANT: xp_engine reads this into $usageid
            'layout'         => '1',
            'currentpage'    => 0,
            'preview'        => 0,
            'state'          => 'finished',
            'timestart'      => time() - 60,
            'timefinish'     => time(),
            'timemodified'   => time(),
            'timecheckstate' => 0,
            'sumgrades'      => 0.0,
        ]);

        return [$attemptid, $quizid, $usageid, $qaid];
    }

    private function insert_question_xp_record(
        int $courseid,
        int $questionid,
        int $xp_participation,
        int $xp_correct,
        int $enabled
    ): void {
        global $DB;
        $now = time();

        $DB->insert_record('local_kiwilearner_question_xp', (object)[
            'courseid'         => $courseid,
            'questionid'       => $questionid,
            'xp_participation' => $xp_participation,
            'xp_correct'       => $xp_correct,
            'enabled'          => $enabled ? 1 : 0,
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);
    }

    private function count_events(int $userid, int $courseid, string $reason): int {
        global $DB;
        return (int)$DB->count_records('local_kiwilearner_xp_event', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'reason'   => $reason,
        ]);
    }

    private function get_event_xpdelta(int $userid, int $courseid, string $reason): ?int {
        global $DB;
        $rec = $DB->get_record('local_kiwilearner_xp_event', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'reason'   => $reason,
        ], 'xpdelta', IGNORE_MISSING);
        return $rec ? (int)$rec->xpdelta : null;
    }

    // -------------------------------------------------------------------------
    // Scenario 1: No per-question record, defaults (1,1,1) threshold 0.6
    // -------------------------------------------------------------------------

    public function test_defaults_participation_and_correct_awarded_when_fraction_meets_threshold(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $qid = $this->create_question_in_course($course->id);
        [$attemptid] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);

        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attemptid);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptid);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        $this->assertEquals(1, $this->count_events($user->id, $course->id, $participationreason));
        $this->assertEquals(1, $this->count_events($user->id, $course->id, $correctreason));
        $this->assertEquals(1, $this->get_event_xpdelta($user->id, $course->id, $participationreason));
        $this->assertEquals(1, $this->get_event_xpdelta($user->id, $course->id, $correctreason));
    }

    public function test_defaults_wrong_then_correct_awards_correct_once(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);

        // First attempt: wrong (fraction 0.0) => NO correct XP.
        [$attemptwrong] = $this->seed_attempt($user->id, $course->id, $qid, 0.0);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptwrong);

        $correctreason = 'quiz_question_correct:' . $qid;
        $this->assertEquals(0, $this->count_events($user->id, $course->id, $correctreason));

        // Second attempt: correct (fraction 1.0) => correct XP should be awarded.
        [$attemptcorrect] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptcorrect);

        $this->assertEquals(1, $this->count_events($user->id, $course->id, $correctreason));
    }

    public function test_defaults_idempotency_blocks_same_question_same_day_across_attempts_and_quizzes(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);

        // First correct attempt.
        [$attempt1] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);
        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attempt1);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attempt1);

        // Second correct attempt (different quiz instance because seed_attempt creates a quiz each time).
        [$attempt2] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);
        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attempt2);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attempt2);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        // Because your xp_engine passes onceperday=true, both should be awarded only once per day.
        $this->assertEquals(1, $this->count_events($user->id, $course->id, $participationreason));
        $this->assertEquals(1, $this->count_events($user->id, $course->id, $correctreason));
    }

    public function test_defaults_threshold_blocks_fraction_0_5_then_allows_after_fraction_1_0(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);
        $correctreason = 'quiz_question_correct:' . $qid;

        // Fraction 0.5 < threshold 0.6 => no correct.
        [$attemptlow] = $this->seed_attempt($user->id, $course->id, $qid, 0.5);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptlow);
        $this->assertEquals(0, $this->count_events($user->id, $course->id, $correctreason));

        // Later correct attempt => should award.
        [$attemptok] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptok);
        $this->assertEquals(1, $this->count_events($user->id, $course->id, $correctreason));
    }

    // -------------------------------------------------------------------------
    // Scenario 2: No per-question record, defaults (0,1,1) threshold 0.6
    // -------------------------------------------------------------------------

    public function test_defaults_participation_zero_disables_participation_only(): void {
        $this->set_global_defaults(0, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);
        [$attemptid] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);

        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attemptid);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptid);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        $this->assertEquals(0, $this->count_events($user->id, $course->id, $participationreason));
        $this->assertEquals(1, $this->count_events($user->id, $course->id, $correctreason));
    }

    // -------------------------------------------------------------------------
    // Scenario 3: No per-question record, defaults (1,1,0) threshold 0.6
    // -------------------------------------------------------------------------

    public function test_defaults_disabled_blocks_all_xp(): void {
        $this->set_global_defaults(1, 1, 0, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);
        [$attemptid] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);

        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attemptid);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptid);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        $this->assertEquals(0, $this->count_events($user->id, $course->id, $participationreason));
        $this->assertEquals(0, $this->count_events($user->id, $course->id, $correctreason));
    }

    // -------------------------------------------------------------------------
    // Scenario 4: Per-question record overrides global defaults
    // -------------------------------------------------------------------------

    public function test_question_record_overrides_global_defaults(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);

        // Per-question override: (2,2,1)
        $this->insert_question_xp_record($course->id, $qid, 2, 2, 1);

        [$attemptid] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);

        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attemptid);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptid);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        $this->assertEquals(2, $this->get_event_xpdelta($user->id, $course->id, $participationreason));
        $this->assertEquals(2, $this->get_event_xpdelta($user->id, $course->id, $correctreason));
    }

    public function test_question_record_disabled_blocks_xp_even_if_globals_enabled(): void {
        $this->set_global_defaults(1, 1, 1, 0.6);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $qid = $this->create_question_in_course($course->id);

        // Per-question override disabled: (2,2,0)
        $this->insert_question_xp_record($course->id, $qid, 2, 2, 0);

        [$attemptid] = $this->seed_attempt($user->id, $course->id, $qid, 1.0);

        xp_engine::award_participation_xp_for_quiz_attempts($user->id, $course->id, $attemptid);
        xp_engine::award_correct_xp_for_quiz_attempt($user->id, $course->id, $attemptid);

        $participationreason = 'quiz_question_participate:' . $qid;
        $correctreason = 'quiz_question_correct:' . $qid;

        $this->assertEquals(0, $this->count_events($user->id, $course->id, $participationreason));
        $this->assertEquals(0, $this->count_events($user->id, $course->id, $correctreason));
    }
}

