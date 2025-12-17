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

function block_kiwilearner_dailyquiz_get_mcq_questions($courseid, $topics = [], $numquestions = 5) {
    global $DB;

    error_log('DailyQuiz RUNNING FILE='.__FILE__);
    error_log('DailyQuiz MARKER=2025-12-16-08:05');


    $courseid = (int)$courseid;
    $numquestions = (int)$numquestions;

    if ($courseid <= 0 || $numquestions <= 0) {
        return [];
    }

    // Normalise topics (comma-separated user input often becomes ['']).
    $topics = array_values(array_unique(array_filter(array_map(function($t) {
        return trim(core_text::strtolower($t));
    }, (array)$topics), function($t) {
        return $t !== '';
    })));

    $course = get_course($courseid);

    $context = context_course::instance($courseid, MUST_EXIST);
    $contextid = $context->id;

    error_log('DailyQuiz courseid='.$courseid.' course->category='.$course->category.' searching contexts: '.json_encode($contextids));

    $readystatus = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY; // Moodle 5.x. :contentReference[oaicite:5]{index=5}

    // Base query:
    // - Restrict to this course's question bank context (like old qc.course = :courseid idea)
    // - Only multichoice
    // - Only latest READY version per question_bank_entry (Moodle 4+ versioning)
    $sql = "
        SELECT DISTINCT q.id
          FROM {question} q
          JOIN {question_versions} qv ON qv.questionid = q.id
          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
          JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
         WHERE qc.contextid = :contextid
           AND q.parent = 0

           AND qv.status = :readystatus
           AND qv.version = (
                SELECT MAX(v.version)
                  FROM {question_versions} v
                 WHERE v.questionbankentryid = qbe.id
                   AND v.status = qv.status
           )
    ";

    $params = [
        'contextid' => $contextid,
        // 'qtype' => 'multichoice',
        'readystatus' => $readystatus,
    ];

    // Optional tag filter: question tags live in {tag_instance}/{tag}
    // Questions are tagged with component='core_question', itemtype='question'. :contentReference[oaicite:6]{index=6}
    if (!empty($topics)) {
        list($tagsql, $tagparams) = $DB->get_in_or_equal($topics, SQL_PARAMS_NAMED, 'tag');

        $sql .= "
        AND EXISTS (
                SELECT 1
                FROM {tag_instance} ti
                JOIN {tag} t ON t.id = ti.tagid
                WHERE ti.itemid = q.id
                AND ti.component = :tagcomponent
                AND ti.itemtype = :tagitemtype
                AND LOWER(t.name) $tagsql
        )
        ";

        $params = array_merge($params, [
            'tagcomponent' => 'core_question',
            'tagitemtype'  => 'question',
            // 'tagcontextid' => $contextid,
        ], $tagparams);
    }

    error_log('DailyQuiz courseid='.$courseid.' course->category='.$course->category.' using contextid='.$contextid);
    error_log("DailyQuiz SQL:\n".$sql);
    error_log("DailyQuiz params:\n".json_encode($params));

    // Memory-safe random selection (reservoir sampling) from the matching IDs.
    $selectedids = [];
    $seen = 0;

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $row) {
        $seen++;
        $qid = (int)$row->id;

        if ($seen <= $numquestions) {
            $selectedids[] = $qid;
        } else {
            $j = random_int(1, $seen);
            if ($j <= $numquestions) {
                $selectedids[$j - 1] = $qid;
            }
        }
    }
    $rs->close();

    if (empty($selectedids)) {
        return [];
    }

    // Fetch question text + answers in bulk.
    list($insql, $inparams) = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED, 'qid');

    $qrecs = $DB->get_records_sql("
        SELECT q.id, q.questiontext, q.questiontextformat
          FROM {question} q
         WHERE q.id $insql
    ", $inparams);

    $arecs = $DB->get_records_sql("
        SELECT qa.id, qa.question, qa.answer, qa.answerformat, qa.fraction
          FROM {question_answers} qa
         WHERE qa.question $insql
         ORDER BY qa.question, qa.id
    ", $inparams);

    $answersbyq = [];
    foreach ($arecs as $a) {
        $qid = (int)$a->question;
        if (!isset($answersbyq[$qid])) {
            $answersbyq[$qid] = [];
        }
        $answersbyq[$qid][] = $a;
    }

    // Build return structure expected by your mustache template.
    // IMPORTANT: Do NOT expose 'fraction' to the browser (it leaks correct answers).
    $quizquestions = [];
    shuffle($selectedids);

    foreach ($selectedids as $qid) {
        if (empty($qrecs[$qid])) {
            continue;
        }
        $q = $qrecs[$qid];

        $opts = [];
        foreach ($answersbyq[$qid] ?? [] as $a) {
            $opts[] = [
                'id' => (int)$a->id,
                'text' => format_text($a->answer, $a->answerformat, ['context' => $context]),
            ];
        }
        shuffle($opts);

        $quizquestions[] = [
            'id' => (int)$q->id,
            'text' => format_text($q->questiontext, $q->questiontextformat, ['context' => $context]),
            'options' => $opts,
        ];
    }

    debugging('DailyQuiz: generated '.count($quizquestions).' questions', DEBUG_DEVELOPER);
    error_log('DailyQuiz course '.$courseid.' generated '.count($quizquestions).' questions: ' .
        json_encode(array_column($quizquestions, 'id')));


    return $quizquestions;
}

function block_kiwilearner_dailyquiz_submit_attempt($userid, $answers) {
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
        $DB->insert_record('block_kiwilearner_dailyquiz_temp', $record);
    }
}

function block_kiwilearner_dailyquiz_get_results($userid) {
    global $DB;
    $attempts = $DB->get_records('block_kiwilearner_dailyquiz_temp', ['userid' => $userid]);
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