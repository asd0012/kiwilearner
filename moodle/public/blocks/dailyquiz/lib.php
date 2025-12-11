<?php
defined('MOODLE_INTERNAL') || die();

function shuffle_assoc($list) {
    if (!is_array($list)) return $list;
    $keys = array_keys($list);
    shuffle($keys);
    $random = [];
    foreach ($keys as $key) {
        $random[$key] = $list[$key];
    }
    return $random;
}

function block_dailyquiz_get_mcq_questions($courseid, $topics = [], $numquestions = 5) {
    global $DB;

    $sql = "SELECT q.id, q.questiontext, q.qtype
            FROM {question} q
            JOIN {question_categories} qc ON q.category = qc.id
            LEFT JOIN {question_tags} qt ON qt.questionid = q.id
            WHERE qc.course = :courseid";

    $params = ['courseid' => $courseid];

    if (!empty($topics)) {
        $placeholders = implode(',', array_fill(0, count($topics), '?'));
        $sql .= " AND qt.tag IN ($placeholders)";
        $params = array_merge($params, $topics);
    }

    $questions = $DB->get_records_sql($sql, $params);

    $questions = array_slice(shuffle_assoc($questions), 0, $numquestions);

    $quizquestions = [];
    foreach ($questions as $q) {
        if ($q->qtype === 'multichoice') {
            $answers = $DB->get_records('question_answers', ['question' => $q->id]);
            $opts = [];
            foreach ($answers as $a) {
                $opts[] = ['id' => $a->id, 'text' => $a->answer, 'fraction' => $a->fraction];
            }
            shuffle($opts);
            $quizquestions[] = [
                'id' => $q->id,
                'text' => $q->questiontext,
                'options' => $opts
            ];
        }
    }
    return $quizquestions;
}

function block_dailyquiz_submit_attempt($userid, $answers) {
    global $DB;
    foreach ($answers as $questionid => $answer) {
        $question = $DB->get_record('question', ['id' => $questionid]);
        $score = 0;
        $ansrecord = $DB->get_record('question_answers', ['id' => $answer]);
        if ($ansrecord && $ansrecord->fraction > 0) {
            $score = 1;
        }
        $record = new stdClass();
        $record->userid = $userid;
        $record->questionid = $questionid;
        $record->answer = $answer;
        $record->score = $score;
        $DB->insert_record('block_dailyquiz_temp', $record);
    }
}

function block_dailyquiz_get_results($userid) {
    global $DB;
    $attempts = $DB->get_records('block_dailyquiz_temp', ['userid' => $userid]);
    $results = ['total' => 0, 'questions' => []];
    foreach ($attempts as $a) {
        $question = $DB->get_record('question', ['id' => $a->questionid]);
        $answer = $DB->get_record('question_answers', ['id' => $a->answer]);
        $results['total'] += $a->score;
        $results['questions'][] = [
            'text' => $question->questiontext,
            'answer' => $answer ? $answer->answer : '',
            'correct' => ($a->score > 0) ? 'Yes' : 'No'
        ];
    }
    return $results;
}

?>