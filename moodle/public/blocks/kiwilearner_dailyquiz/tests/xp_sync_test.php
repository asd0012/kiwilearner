<?php
// File: blocks/kiwilearner_dailyquiz/tests/xp_sync_test.php
defined('MOODLE_INTERNAL') || die();

global $CFG;

// Make sure the sync function is loaded.
$lib = $CFG->dirroot . '/blocks/kiwilearner_dailyquiz/lib.php';
if (file_exists($lib)) {
    require_once($lib);
} else {
    $alt = $CFG->dirroot . '/blocks/kiwilearner_dailyquiz/locallib.php';
    if (file_exists($alt)) {
        require_once($alt);
    }
}

final class block_kiwilearner_dailyquiz_xp_sync_test extends advanced_testcase {

    /**
     * Insert a record while satisfying NOT NULL columns using DB metadata.
     * Avoids guessing required fields from install.xml.
     */
    private function insert_with_defaults(string $table, array $overrides): int {
        global $DB;

        $cols = $DB->get_columns($table);
        $rec = [];

        foreach ($cols as $name => $col) {
            if ($name === 'id') {
                continue;
            }
            if (array_key_exists($name, $overrides)) {
                $rec[$name] = $overrides[$name];
                continue;
            }

            if (!empty($col->has_default)) {
                $rec[$name] = $col->default_value;
                continue;
            }

            if (empty($col->not_null)) {
                $rec[$name] = null;
                continue;
            }

            $type = strtolower((string)($col->type ?? ''));
            if (str_contains($type, 'int')) {
                $rec[$name] = 0;
            } else if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'number')) {
                $rec[$name] = 0;
            } else {
                $rec[$name] = '';
            }
        }

        return (int)$DB->insert_record($table, (object)$rec);
    }

    public function test_sync_awards_once_per_question_and_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);

        $userid = (int)$user->id;
        $courseid = (int)$course->id;

        // Fixed time => deterministic daystart/daykey.
        $now = 1700000000;
        $daystart = usergetmidnight($now);
        $daykey = date('Ymd', $daystart);
        $attemptid = (int)$daystart;

        // Seed temp table:
        // Q1: wrong (0) then correct (1) => bestscore=1 => should award ONCE.
        // Q2: only wrong (0) => should NOT award.
        $temp = 'block_kiwilearner_dailyquiz_temp';

        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => 1001,
            'score' => 0,
            'timecreated' => $now,
        ]);
        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => 1001,
            'score' => 1,
            'timecreated' => $now + 1,
        ]);

        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => 2002,
            'score' => 0,
            'timecreated' => $now + 2,
        ]);

        // Act: run sync.
        // If your function name differs, adjust it here.
        block_kiwilearner_dailyquiz_sync_xp_to_local($userid, $courseid, $daykey, $now);

        // Assert: one XP event for Q1, none for Q2 (based on common "score==1 => correct").
        $eventsQ1 = $DB->count_records('local_kiwilearner_xp_event', [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => 1001,
            'attemptid' => $attemptid,
            'reason' => 'kiwilearner:dailyquiz_correct',
        ]);
        $this->assertSame(1, (int)$eventsQ1);

        $eventsQ2 = $DB->count_records('local_kiwilearner_xp_event', [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => 2002,
            'attemptid' => $attemptid,
            'reason' => 'kiwilearner:dailyquiz_correct',
        ]);
        $this->assertSame(0, (int)$eventsQ2);

        // Summary row should exist and have xptotal > 0 (since we awarded something).
        $summary = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ], '*', IGNORE_MISSING);

        $this->assertNotEmpty($summary, 'Expected xp_summary_day row to be created/updated by sync');
        $this->assertGreaterThan(0, (int)($summary->xptotal ?? 0));

        $xptotal1 = (int)$summary->xptotal;

        // Act again (idempotency): running sync twice should not create duplicates.
        block_kiwilearner_dailyquiz_sync_xp_to_local($userid, $courseid, $daykey, $now + 10);

        $eventsQ1_after = $DB->count_records('local_kiwilearner_xp_event', [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => 1001,
            'attemptid' => $attemptid,
            'reason' => 'kiwilearner:dailyquiz_correct',
        ]);
        $this->assertSame(1, (int)$eventsQ1_after);

        $summary2 = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ], '*', MUST_EXIST);
        $this->assertSame($xptotal1, (int)$summary2->xptotal, 'xptotal should not change when sync reruns');
    }
    public function test_sync_multi_question_total_and_no_double_rows(): void
    {
        global $DB;

        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);

        $userid = (int)$user->id;
        $courseid = (int)$course->id;

        $now = 1700000000;
        $daystart = usergetmidnight($now);
        $daykey = date('Ymd', $daystart);
        $attemptid = (int)$daystart;

        $temp = 'block_kiwilearner_dailyquiz_temp';

        // 3 questions, all correct (score=1). Add a duplicate attempt for one question to ensure bestscore logic.
        $qids = [3001, 3002, 3003];

        // Q3001: wrong then correct => still should award once.
        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => $qids[0],
            'score' => 0,
            'timecreated' => $now,
        ]);
        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => $qids[0],
            'score' => 1,
            'timecreated' => $now + 1,
        ]);

        // Q3002: correct.
        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => $qids[1],
            'score' => 1,
            'timecreated' => $now + 2,
        ]);

        // Q3003: correct.
        $this->insert_with_defaults($temp, [
            'userid' => $userid,
            'courseid' => $courseid,
            'daykey' => $daykey,
            'questionid' => $qids[2],
            'score' => 1,
            'timecreated' => $now + 3,
        ]);

        // Run sync once.
        block_kiwilearner_dailyquiz_sync_xp_to_local($userid, $courseid, $daykey, $now);

        // Count XP events for these 3 questions.
        $reason = 'kiwilearner:dailyquiz_correct';

        $eventcount = 0;
        foreach ($qids as $qid) {
            $eventcount += (int)$DB->count_records('local_kiwilearner_xp_event', [
                'userid' => $userid,
                'courseid' => $courseid,
                'questionid' => $qid,
                'attemptid' => $attemptid,
                'reason' => $reason,
            ]);
        }
        $this->assertSame(3, (int)$eventcount, 'Expected exactly 3 XP events (one per correct question)');

        // Fetch one event to learn the awarded xpdelta (avoid hardcoding your XP value).
        $oneevent = $DB->get_record('local_kiwilearner_xp_event', [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => $qids[0],
            'attemptid' => $attemptid,
            'reason' => $reason,
        ], '*', MUST_EXIST);

        $xpdelta = (int)($oneevent->xpdelta ?? 0);
        // If your implementation uses negative/positive or 0, this assertion will tell you immediately.
        $this->assertGreaterThan(0, $xpdelta, 'xpdelta should be > 0 for a correct award');

        // Summary should match 3 * xpdelta.
        $summary = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ], '*', MUST_EXIST);

        $this->assertSame(3 * $xpdelta, (int)$summary->xptotal, 'xptotal should equal 3 awards');

        $xptotal1 = (int)$summary->xptotal;

        // Run sync again: should not create more events or change xptotal.
        block_kiwilearner_dailyquiz_sync_xp_to_local($userid, $courseid, $daykey, $now + 10);

        $eventcount_after = 0;
        foreach ($qids as $qid) {
            $eventcount_after += (int)$DB->count_records('local_kiwilearner_xp_event', [
                'userid' => $userid,
                'courseid' => $courseid,
                'questionid' => $qid,
                'attemptid' => $attemptid,
                'reason' => $reason,
            ]);
        }
        $this->assertSame(3, (int)$eventcount_after, 'Sync rerun should not create duplicate XP events');

        $summary2 = $DB->get_record('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
        ], '*', MUST_EXIST);

        $this->assertSame($xptotal1, (int)$summary2->xptotal, 'xptotal should not change when sync reruns');
    }
}
