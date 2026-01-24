<?php
// File: local/kiwilearner/tests/streak_update_test.php

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Adjust this if your file is named differently.
$libpath = $CFG->dirroot . '/local/kiwilearner/lib.php';
if (file_exists($libpath)) {
    require_once($libpath);
} else {
    // Fallbacks if your project uses a different filename.
    $alt1 = $CFG->dirroot . '/local/kiwilearner/locallib.php';
    if (file_exists($alt1)) {
        require_once($alt1);
    }
}

final class local_kiwilearner_streak_update_test extends advanced_testcase {

    /**
     * Insert a record while satisfying NOT NULL columns using DB metadata.
     * This avoids guessing required fields in install.xml.
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

            // Prefer DB default when available.
            if (!empty($col->has_default)) {
                $rec[$name] = $col->default_value;
                continue;
            }

            // Nullable column => NULL is fine.
            if (empty($col->not_null)) {
                $rec[$name] = null;
                continue;
            }

            // Fill required columns with a safe type-based value.
            $type = strtolower((string)($col->type ?? ''));

            if (str_contains($type, 'int')) {
                $rec[$name] = 0;
            } else if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'number')) {
                $rec[$name] = 0;
            } else {
                // char/text/date/time fall back to empty string
                $rec[$name] = '';
            }
        }

        return (int)$DB->insert_record($table, (object)$rec);
    }

    public function test_streak_increments_when_met_and_yesterday_was_processed(): void {
        global $DB;

        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);

        $userid = (int)$user->id;
        $courseid = (int)$course->id;

        // Fixed time for deterministic daystart.
        $now = 1700000000;
        $daystart = usergetmidnight($now);
        $prevdaystart = (int)$daystart - DAYSECS;

        // Goal: target 10 XP, current streak 3, yesterday processed.
        $goalid = $this->insert_with_defaults('local_kiwilearner_goal', [
            'userid' => $userid,
            'courseid' => $courseid,
            'xp_target' => 10,
            'currentstreak' => 3,
            'beststreak' => 3,
            'laststreakdaystart' => $prevdaystart,
            'timemodified' => $now,
        ]);

        // Summary: today met target.
        $this->insert_with_defaults('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
            'xptotal' => 10,
        ]);

        // Act.
        local_kiwilearner_update_goal_streak($userid, $courseid, $daystart, $now);

        // Assert.
        $goal = $DB->get_record('local_kiwilearner_goal', ['id' => $goalid], '*', MUST_EXIST);
        $this->assertSame(4, (int)$goal->currentstreak);
        $this->assertSame(4, (int)$goal->beststreak);
        $this->assertSame((int)$daystart, (int)$goal->laststreakdaystart);

        // Idempotency: calling again same day should not change anything.
        local_kiwilearner_update_goal_streak($userid, $courseid, $daystart, $now + 5);

        $goal2 = $DB->get_record('local_kiwilearner_goal', ['id' => $goalid], '*', MUST_EXIST);
        $this->assertSame(4, (int)$goal2->currentstreak);
        $this->assertSame(4, (int)$goal2->beststreak);
        $this->assertSame((int)$daystart, (int)$goal2->laststreakdaystart);
    }

    public function test_streak_resets_to_one_when_met_but_gap_exists(): void {
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

        // Last processed was 2 days ago => gap.
        $twodaysago = (int)$daystart - 2 * DAYSECS;

        $goalid = $this->insert_with_defaults('local_kiwilearner_goal', [
            'userid' => $userid,
            'courseid' => $courseid,
            'xp_target' => 10,
            'currentstreak' => 5,
            'beststreak' => 5,
            'laststreakdaystart' => $twodaysago,
            'timemodified' => $now,
        ]);

        $this->insert_with_defaults('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
            'xptotal' => 999, // definitely met
        ]);

        local_kiwilearner_update_goal_streak($userid, $courseid, $daystart, $now);

        $goal = $DB->get_record('local_kiwilearner_goal', ['id' => $goalid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$goal->currentstreak);
        $this->assertSame(5, (int)$goal->beststreak); // best stays at least previous best
        $this->assertSame((int)$daystart, (int)$goal->laststreakdaystart);
    }

    public function test_streak_resets_to_zero_when_not_met(): void {
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
        $prevdaystart = (int)$daystart - DAYSECS;

        $goalid = $this->insert_with_defaults('local_kiwilearner_goal', [
            'userid' => $userid,
            'courseid' => $courseid,
            'xp_target' => 10,
            'currentstreak' => 3,
            'beststreak' => 3,
            'laststreakdaystart' => $prevdaystart,
            'timemodified' => $now,
        ]);

        $this->insert_with_defaults('local_kiwilearner_xp_summary_day', [
            'userid' => $userid,
            'courseid' => $courseid,
            'daystart' => $daystart,
            'xptotal' => 9, // not met
        ]);

        local_kiwilearner_update_goal_streak($userid, $courseid, $daystart, $now);

        $goal = $DB->get_record('local_kiwilearner_goal', ['id' => $goalid], '*', MUST_EXIST);
        $this->assertSame(0, (int)$goal->currentstreak);
        // Note: your current function does NOT set laststreakdaystart on a failed day.
        $this->assertSame((int)$prevdaystart, (int)$goal->laststreakdaystart);
    }
}
