<?php
namespace local_kiwilearner\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;
use local_kiwilearner\events\question_sync;
use local_kiwilearner\utils\xp_sync_helper;

class sync_question_xp_task extends adhoc_task {

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $questionid = isset($data->questionid) ? (int)$data->questionid : 0;

        if ($questionid <= 0 || !$DB->record_exists('question', ['id' => $questionid])) {
            error_log('[KiwiLearner] sync_question_xp_task: invalid questionid=' . $questionid);
            return;
        }

        $courseid = question_sync::resolve_course_from_question($questionid);
        if (!$courseid) {
            error_log('[KiwiLearner] sync_question_xp_task: cannot resolve course for questionid=' . $questionid);
            return;
        }

        $xp = xp_sync_helper::get_xp_from_customfields($questionid);
        if ($xp === null) {
            error_log('[KiwiLearner] sync_question_xp_task: xp is null (nothing set) questionid=' . $questionid);
            return;
        }

        xp_sync_helper::upsert_question_xp($questionid, (int)$courseid, $xp);

        error_log('[KiwiLearner] sync_question_xp_task: synced questionid=' . $questionid . ' courseid=' . $courseid
            . ' xp=' . json_encode($xp));
    }
}
