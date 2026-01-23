<?php
// File: local/kiwilearner/tests/xp_event_unique_index_test.php
defined('MOODLE_INTERNAL') || die();

final class local_kiwilearner_xp_event_unique_index_test extends advanced_testcase {

    /**
     * Insert a record while satisfying NOT NULL columns using DB metadata.
     * Avoids guessing required fields.
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

    public function test_unique_index_blocks_duplicate_dailyquiz_event(): void {
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
        $attemptid = (int)$daystart;

        // Sanity: make sure the index exists in this test DB.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_kiwilearner_xp_event');
        $index = new xmldb_index('uniq_dailyquiz_event', XMLDB_INDEX_UNIQUE,
            ['userid', 'courseid', 'questionid', 'attemptid', 'reason']
        );
        $this->assertTrue($dbman->index_exists($table, $index), 'uniq_dailyquiz_event index missing in test DB');

        // Insert the first event.
        $base = [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => 8469,
            'attemptid' => $attemptid,
            'reason' => 'kiwilearner:dailyquiz_correct',
        ];
        $this->insert_with_defaults('local_kiwilearner_xp_event', $base);

        // Insert exact same key again => should fail (duplicate).
        $this->expectException(dml_exception::class);
        $this->insert_with_defaults('local_kiwilearner_xp_event', $base);
    }

    public function test_unique_index_allows_different_reason_or_attemptid(): void {
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
        $attemptid = (int)$daystart;

        $base = [
            'userid' => $userid,
            'courseid' => $courseid,
            'questionid' => 8469,
            'attemptid' => $attemptid,
            'reason' => 'kiwilearner:dailyquiz_correct',
        ];

        $this->insert_with_defaults('local_kiwilearner_xp_event', $base);

        // Same everything but different reason => should be allowed.
        $this->insert_with_defaults('local_kiwilearner_xp_event', array_merge($base, [
            'reason' => 'kiwilearner:dailyquiz_attempt',
        ]));

        // Same everything but different attemptid => should be allowed.
        $this->insert_with_defaults('local_kiwilearner_xp_event', array_merge($base, [
            'attemptid' => $attemptid + DAYSECS,
        ]));

        // Assert we have 3 rows.
        $count = $DB->count_records('local_kiwilearner_xp_event', ['userid' => $userid, 'courseid' => $courseid]);
        $this->assertSame(3, (int)$count);
    }
}
