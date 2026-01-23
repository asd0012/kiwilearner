<?php
defined('MOODLE_INTERNAL') || die();

use local_kiwilearner\utils\xp_engine;

class local_kiwilearner_xp_engine_testcase extends advanced_testcase {

    public function setUp(): void {
        // Reset DB after each test
        $this->resetAfterTest(true);
    }

    public function test_xp_awarded_once_only() {
        global $DB;

        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // First award
        xp_engine::award_xp(
            $user->id,
            $course->id,
            10,
            'quiz_attempt',
            'attempt-123'
        );

        // Duplicate award (should be ignored)
        xp_engine::award_xp(
            $user->id,
            $course->id,
            10,
            'quiz_attempt',
            'attempt-123'
        );

        $count = $DB->count_records('local_kiwilearner_xp_event');

        $this->assertEquals(1, $count);
    }
}
