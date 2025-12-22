<?php
namespace local_kiwilearner\bank\column;

defined('MOODLE_INTERNAL') || die();

class xp_column extends \core_question\local\bank\column_base {

    public function get_title() {
        return get_string('xp', 'local_kiwilearner'); // Column header text
    }

    public function get_required_fields() {
        // Fields from the question table you need. q is the alias Moodle uses.
        return ['q.id', 'q.category'];
    }

    public function display_content($question, $rowclasses) {
        global $DB;

        // Try to get existing XP config for this question.
        $record = $DB->get_record('local_kiwilearner_question_xp', [
            'questionid' => $question->id,
        ]);

        if ($record) {
            $label = "{$record->xp_correct}/{$record->xp_participation}";
        } else {
            $label = '-';
        }

        // Link to our XP settings page.
        $url = new \moodle_url('/local/kiwilearner/editxp.php', [
            'questionid' => $question->id,
        ]);

        return \html_writer::link($url, $label);
    }
}
