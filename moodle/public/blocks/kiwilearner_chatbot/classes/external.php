<?php
namespace block_kiwilearner_chatbot;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use context_module;
use core_ai\aiactions\generate_text;

class external extends external_api {

    /* =========================
     * Context cards (greeting + deadlines + updates)
     * ========================= */

    public static function get_context_cards_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0),
            'cmid' => new external_value(PARAM_INT, 'Course module ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_context_cards($courseid = 0, $cmid = 0) {
        global $USER;

        $params = self::validate_parameters(self::get_context_cards_parameters(), [
            'courseid' => $courseid,
            'cmid' => $cmid,
        ]);

        // Must work on dashboard/home too.
        if (!empty($params['courseid']) && (int)$params['courseid'] > 1) {
            require_login((int)$params['courseid']);
        } else {
            require_login();
        }

        $greeting = "Kia Ora <b>" . fullname($USER) . "</b>! "
            . \block_kiwilearner_chatbot\local\context_helper::greeting((int)$params['courseid'], (int)$params['cmid']);

        $deadlines = \block_kiwilearner_chatbot\local\data_service::get_deadlines_next_7_days((int)$params['courseid']);
        $updates = \block_kiwilearner_chatbot\local\data_service::get_course_updates_last_7_days((int)$params['courseid']);

        return [
            'greeting' => $greeting,
            'deadlines' => $deadlines,
            'updates' => $updates,
        ];
    }

    public static function get_context_cards_returns() {
        return new external_single_structure([
            'greeting' => new external_value(PARAM_RAW, 'Greeting HTML'),
            'deadlines' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Deadline name'),
                    'time' => new external_value(PARAM_TEXT, 'Formatted time'),
                    'url' => new external_value(PARAM_URL, 'Link', VALUE_OPTIONAL),
                ])
            ),
            'updates' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Activity/resource title'),
                    'time' => new external_value(PARAM_TEXT, 'Formatted time'),
                    'url' => new external_value(PARAM_URL, 'Link'),
                ])
            ),
        ]);
    }

    /* =========================
     * Detect resource context (PDF?)
     * ========================= */

    public static function detect_resource_context_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function detect_resource_context($cmid) {
        global $DB;

        $params = self::validate_parameters(self::detect_resource_context_parameters(), [
            'cmid' => $cmid,
        ]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        $result = [
            'cmid' => (int)$cm->id,
            'courseid' => (int)$cm->course,
            'is_resource' => false,
            'is_pdf' => false,
            'resource_name' => '',
        ];

        if ($cm->modname !== 'resource') {
            return $result;
        }
        $result['is_resource'] = true;

        $resource = $DB->get_record('resource', ['id' => $cm->instance], '*', MUST_EXIST);
        $result['resource_name'] = format_string($resource->name);

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder, id',
            false
        );

        foreach ($files as $f) {
            if ($f->is_directory()) {
                continue;
            }

            error_log('KIWI DETECT: found file name=' . $f->get_filename() .
                ' type=' . $f->get_mimetype());

            $filename = $f->get_filename();
            $mimetype = strtolower((string)$f->get_mimetype());

            if (preg_match('/\.pdf$/i', $filename) || $mimetype === 'application/pdf') {
                $result['is_pdf'] = true;
                error_log('KIWI DETECT: matched PDF=' . $filename);
                break;
            }
        }

        return $result;
    }

    public static function detect_resource_context_returns() {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'is_resource' => new external_value(PARAM_BOOL, 'Whether the module is a resource'),
            'is_pdf' => new external_value(PARAM_BOOL, 'Whether the resource is a PDF'),
            'resource_name' => new external_value(PARAM_TEXT, 'Resource name'),
        ]);
    }

    /* =========================
     * PDF Survey: load/save
     * ========================= */

    public static function get_pdf_survey_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function get_pdf_survey($cmid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_pdf_survey_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        $rec = $DB->get_record('block_kiwi_pdfsurvey', ['userid' => $USER->id, 'cmid' => $cm->id]);

        if (!$rec) {
            return [
                'found' => false,
                'state' => '',
                'read_status' => '',
                'completion' => '',
                'about_text' => '',
                'takeaways_text' => '',
            ];
        }

        return [
            'found' => true,
            'state' => (string)$rec->state,
            'read_status' => (string)($rec->read_status ?? ''),
            'completion' => (string)($rec->completion ?? ''),
            'about_text' => (string)($rec->about_text ?? ''),
            'takeaways_text' => (string)($rec->takeaways_text ?? ''),
        ];
    }

    public static function get_pdf_survey_returns() {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether a record exists'),
            'state' => new external_value(PARAM_TEXT, 'Current state'),
            'read_status' => new external_value(PARAM_TEXT, 'YES/NO/LATER', VALUE_OPTIONAL),
            'completion' => new external_value(PARAM_TEXT, 'FULL/PARTIAL', VALUE_OPTIONAL),
            'about_text' => new external_value(PARAM_RAW, 'About text', VALUE_OPTIONAL),
            'takeaways_text' => new external_value(PARAM_RAW, 'Takeaways text', VALUE_OPTIONAL),
        ]);
    }

    public static function save_pdf_survey_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'state' => new external_value(PARAM_TEXT, 'New state'),
            'read_status' => new external_value(PARAM_TEXT, 'YES/NO/LATER', VALUE_DEFAULT, ''),
            'completion' => new external_value(PARAM_TEXT, 'FULL/PARTIAL', VALUE_DEFAULT, ''),
            'about_text' => new external_value(PARAM_RAW, 'About text', VALUE_DEFAULT, ''),
            'takeaways_text' => new external_value(PARAM_RAW, 'Takeaways text', VALUE_DEFAULT, ''),
        ]);
    }

    public static function save_pdf_survey($cmid, $state, $read_status = '', $completion = '', $about_text = '', $takeaways_text = '') {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_pdf_survey_parameters(), [
            'cmid' => $cmid,
            'state' => $state,
            'read_status' => $read_status,
            'completion' => $completion,
            'about_text' => $about_text,
            'takeaways_text' => $takeaways_text,
        ]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        $now = time();

        $existing = $DB->get_record('block_kiwi_pdfsurvey', ['userid' => $USER->id, 'cmid' => $cm->id]);

        if ($existing) {
            $existing->state = $params['state'];
            $existing->read_status = $params['read_status'];
            $existing->completion = $params['completion'];
            $existing->about_text = $params['about_text'];
            $existing->takeaways_text = $params['takeaways_text'];
            $existing->timemodified = $now;

            $DB->update_record('block_kiwi_pdfsurvey', $existing);
            $id = $existing->id;
        } else {
            $rec = (object)[
                'userid' => $USER->id,
                'courseid' => (int)$cm->course,
                'cmid' => (int)$cm->id,
                'state' => $params['state'],
                'read_status' => $params['read_status'],
                'completion' => $params['completion'],
                'about_text' => $params['about_text'],
                'takeaways_text' => $params['takeaways_text'],
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            $id = $DB->insert_record('block_kiwi_pdfsurvey', $rec);
        }

        return ['ok' => true, 'id' => (int)$id];
    }

    public static function save_pdf_survey_returns() {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Saved OK'),
            'id' => new external_value(PARAM_INT, 'Record id'),
        ]);
    }

    /* =========================
     * Cache PDF text
     * ========================= */

    public static function cache_pdf_text_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function cache_pdf_text($cmid) {
        global $DB;

        $params = self::validate_parameters(self::cache_pdf_text_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        error_log('KIWI PDFCACHE: ENTER cache_pdf_text cmid=' . $cm->id);

        if ($cm->modname !== 'resource') {
            return ['ok' => false, 'chars' => 0, 'message' => 'Not a resource module'];
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_resource',
            'content',
            0,
            'sortorder, id',
            false
        );

        if (empty($files)) {
            return ['ok' => false, 'chars' => 0, 'message' => 'No files found in resource area'];
        }

        // Log found files.
        foreach ($files as $f) {
            if ($f->is_directory()) {
                continue;
            }
            error_log('KIWI PDFCACHE: found file name=' . $f->get_filename() . ' type=' . $f->get_mimetype());
        }

        // Pick PDF explicitly.
        $pdffile = null;
        foreach ($files as $f) {
            if ($f->is_directory()) {
                continue;
            }
            $filename = (string)$f->get_filename();
            $mimetype = strtolower((string)$f->get_mimetype());

            if (preg_match('/\.pdf$/i', $filename) || $mimetype === 'application/pdf') {
                $pdffile = $f;
                break;
            }
        }

        if (!$pdffile) {
            return ['ok' => false, 'chars' => 0, 'message' => 'No PDF file found in this resource'];
        }

        error_log('KIWI PDFCACHE: using file=' . $pdffile->get_filename() . ' mimetype=' . $pdffile->get_mimetype());
        $contenthash = $pdffile->get_contenthash();
        error_log('KIWI PDFCACHE: contenthash=' . $contenthash);

        // If cache already ok, return.
        $cache = $DB->get_record('block_kiwi_pdfcache', ['cmid' => $cm->id], '*', IGNORE_MISSING);
        if ($cache && $cache->contenthash === $contenthash && !empty($cache->extractedtext)) {
            return ['ok' => true, 'chars' => strlen($cache->extractedtext), 'message' => 'Cache already up to date'];
        }

        // Create ONE temp file and use it.
        $temp = tempnam(sys_get_temp_dir(), 'kiwi_pdf_');
        error_log('KIWI PDFCACHE: tempnam returned=' . var_export($temp, true));
        if ($temp === false) {
            return ['ok' => false, 'chars' => 0, 'message' => 'tempnam failed'];
        }

        try {
            $pdffile->copy_content_to($temp);
            error_log('KIWI PDFCACHE: copy_content_to OK size=' . (file_exists($temp) ? filesize($temp) : -1));
        } catch (\Throwable $e) {
            @unlink($temp);
            error_log('KIWI PDFCACHE: copy_content_to FAILED: ' . $e->getMessage());
            return ['ok' => false, 'chars' => 0, 'message' => 'copy_content_to failed: ' . $e->getMessage()];
        }

        // Confirm PDF header.
        $head = file_get_contents($temp, false, null, 0, 8);
        $hex = bin2hex((string)$head);
        error_log('KIWI PDFCACHE: temp header=' . $hex);

        $text = \block_kiwilearner_chatbot\local\pdf_extractor::extract_text($temp);
        @unlink($temp);

        $text = trim((string)$text);
        error_log('KIWI PDFCACHE: extracted chars=' . strlen($text));
        error_log('KIWI PDFCACHE: first200=' . substr($text, 0, 200));

        if ($text === '') {
            return ['ok' => false, 'chars' => 0, 'message' => 'No extractable text found (scanned PDF or extraction failed)'];
        }

        $now = time();
        if (!$cache) {
            $rec = (object)[
                'cmid' => $cm->id,
                'contenthash' => $contenthash,
                'extractedtext' => $text,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('block_kiwi_pdfcache', $rec);
        } else {
            $cache->contenthash = $contenthash;
            $cache->extractedtext = $text;
            $cache->timemodified = $now;
            $DB->update_record('block_kiwi_pdfcache', $cache);
        }

        return ['ok' => true, 'chars' => strlen($text), 'message' => 'PDF text cached'];
    }



    public static function cache_pdf_text_returns() {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'OK'),
            'chars' => new external_value(PARAM_INT, 'Number of extracted characters'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }

    /* =========================
     * Evaluate takeaways using Moodle AI subsystem
     * ========================= */

    public static function evaluate_pdf_takeaways_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function evaluate_pdf_takeaways($cmid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::evaluate_pdf_takeaways_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        // 1) Student answers.
        $survey = $DB->get_record('block_kiwi_pdfsurvey', [
            'userid' => $USER->id,
            'cmid'   => $cm->id,
        ], '*', IGNORE_MISSING);

        if (!$survey) {
            return ['ok' => false, 'feedback' => 'No survey data found yet. Please complete the survey first.'];
        }

        $about = trim((string)($survey->about_text ?? ''));
        $takeaways = trim((string)($survey->takeaways_text ?? ''));

        if ($takeaways === '') {
            return ['ok' => false, 'feedback' => 'No takeaways found yet. Please submit your takeaways first.'];
        }

        // 2) Cached PDF text.
        $pdfcache = $DB->get_record('block_kiwi_pdfcache', ['cmid' => $cm->id], '*', IGNORE_MISSING);
        $pdftext = $pdfcache ? (string)$pdfcache->extractedtext : '';

        if (trim($pdftext) === '') {
            return ['ok' => false, 'feedback' => 'PDF text is not available yet. Please run the PDF cache step first.'];
        }

        // 3) Trim prompt.
        $maxchars = 12000;
        if (\core_text::strlen($pdftext) > $maxchars) {
            $pdftext = \core_text::substr($pdftext, 0, $maxchars) . "\n\n[TRUNCATED]";
        }

        // 4) Prompt.
        $prompt = "You are a helpful learning tutor.\n\n"
            . "TASK: Evaluate the student's takeaways against the PDF content.\n"
            . "Return:\n"
            . "1) Accuracy (0-5)\n"
            . "2) Coverage (0-5)\n"
            . "3) Missing key points (bullets)\n"
            . "4) Corrections (bullets)\n"
            . "5) Suggestions to improve the takeaways (bullets)\n"
            . "Keep it friendly and concise.\n\n"
            . "PDF CONTENT:\n" . $pdftext . "\n\n"
            . "STUDENT SUMMARY:\n" . ($about !== '' ? $about : "[none]") . "\n\n"
            . "STUDENT TAKEAWAYS:\n" . $takeaways . "\n";

        // 5) Call AI.
        try {
            $action = new \core_ai\aiactions\generate_text($context->id, $USER->id, $prompt);
            $manager = \core\di::get(\core_ai\manager::class);
            $response = $manager->process_action($action);
        } catch (\Throwable $e) {
            error_log('KIWI AI: process_action exception: ' . $e->getMessage());
            return ['ok' => false, 'feedback' => 'AI request failed: ' . $e->getMessage()];
        }

        // 6) Handle error/success.
        if (is_object($response) && method_exists($response, 'get_success') && !$response->get_success()) {
            $msg = method_exists($response, 'get_errormessage') ? (string)$response->get_errormessage() : 'Unknown AI error';
            $code = method_exists($response, 'get_errorcode') ? (string)$response->get_errorcode() : '';
            return ['ok' => false, 'feedback' => trim($code . ' ' . $msg)];
        }

        $feedback = '';
        $data = $response->get_response_data();

        /* SAFE DEBUG LOGGING — DO NOT CRASH */
        if (is_array($data)) {
            error_log('KIWI AI: response_data keys=' . implode(',', array_keys($data)));
            error_log('KIWI AI: response_data json=' . substr(json_encode($data), 0, 1200));
        } else if (is_object($data)) {
            error_log('KIWI AI: response_data is object class=' . get_class($data));
            error_log('KIWI AI: response_data json=' . substr(json_encode($data), 0, 1200));
        } else {
            error_log('KIWI AI: response_data type=' . gettype($data));
            error_log('KIWI AI: response_data=' . substr((string)$data, 0, 1200));
        }    

        // Common key locations (provider-dependent).
            if (is_array($data)) {
                // Try likely keys in order.
                $candidates = [
                    'generatedcontent',      // <-- ADD THIS (your provider returns this)
                    'generated_content',
                    'generatedtext',
                    'generated_text',
                    'content',
                    'text',
                    'output',
                    'result',
                    'response',
                    'message',
                ];

                foreach ($candidates as $k) {
                    if (isset($data[$k]) && is_string($data[$k]) && trim($data[$k]) !== '') {
                        $feedback = $data[$k];
                        break;
                    }
                }

                // Sometimes nested:
                if ($feedback === '' && isset($data['data']) && is_array($data['data'])) {
                    foreach ($candidates as $k) {
                        if (isset($data['data'][$k]) && is_string($data['data'][$k]) && trim($data['data'][$k]) !== '') {
                            $feedback = $data['data'][$k];
                            break;
                        }
                    }
                }

                // Sometimes nested like OpenAI chat format:
                if ($feedback === '' && isset($data['choices'][0]['message']['content'])) {
                    $maybe = $data['choices'][0]['message']['content'];
                    if (is_string($maybe) && trim($maybe) !== '') {
                        $feedback = $maybe;
                    }
                }
            } else if (is_string($data)) {
                $feedback = $data;
            }
        

        $feedback = trim((string)$feedback);
        if ($feedback === '') {
            // As a last resort, return response_data JSON (readable debugging).
            $fallback = (is_object($response) && method_exists($response, 'get_response_data'))
                ? json_encode($response->get_response_data())
                : json_encode($response);
            return ['ok' => true, 'feedback' => 'AI returned no readable text. response_data=' . $fallback];
        }
        
        // format the "AI returned feedback to look as pointers and list in chatbot
        // Convert AI text into HTML formatted tutor report.
        // --- Format AI feedback nicely as HTML (no <br> inside <ul>) ---
        $raw = trim((string)$feedback);

        // Normalize line endings
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        // If the model returned literal "\n" (escaped), unescape them:
        $raw = str_replace(["\\n", "\\t"], ["\n", "\t"], $raw);

        $lines = array_map('trim', explode("\n", $raw));

        $html = '';
        $section = 0;

        $openList = function() use (&$html) {
            $html .= "<ul>";
        };
        $closeList = function() use (&$html) {
            $html .= "</ul>";
        };

        $listOpen = false;

        foreach ($lines as $line) {
            if ($line === '') { continue; }

            // Detect section headers like "1) Accuracy: 3"
            if (preg_match('/^1\)\s*Accuracy\s*:?/i', $line)) {
                if ($listOpen) { $closeList(); $listOpen = false; }
                $html .= "<h4>" . htmlspecialchars($line, ENT_QUOTES) . "</h4>";
                continue;
            }
            if (preg_match('/^2\)\s*Coverage\s*:?/i', $line)) {
                if ($listOpen) { $closeList(); $listOpen = false; }
                $html .= "<h4>" . htmlspecialchars($line, ENT_QUOTES) . "</h4>";
                continue;
            }
            if (preg_match('/^3\)\s*Missing key points\s*:?/i', $line)) {
                if ($listOpen) { $closeList(); }
                $html .= "<h4>3) Missing key points</h4>";
                $openList(); $listOpen = true;
                continue;
            }
            if (preg_match('/^4\)\s*Corrections\s*:?/i', $line)) {
                if ($listOpen) { $closeList(); }
                $html .= "<h4>4) Corrections</h4>";
                $openList(); $listOpen = true;
                continue;
            }
            if (preg_match('/^5\)\s*Suggestions/i', $line)) {
                if ($listOpen) { $closeList(); }
                $html .= "<h4>5) Suggestions to improve the takeaways</h4>";
                $openList(); $listOpen = true;
                continue;
            }

            // Bullet lines like "- something"
            if (preg_match('/^-\s*(.+)$/', $line, $m)) {
                if (!$listOpen) { $openList(); $listOpen = true; }
                $html .= "<li>" . htmlspecialchars($m[1], ENT_QUOTES) . "</li>";
                continue;
            }

            // Any other line: show as paragraph (outside list)
            if ($listOpen) { $closeList(); $listOpen = false; }
            $html .= "<p>" . htmlspecialchars($line, ENT_QUOTES) . "</p>";
        }

        if ($listOpen) { $closeList(); }

        $feedback = $html;



        return ['ok' => true, 'feedback' => $feedback];
    }    



    public static function evaluate_pdf_takeaways_returns() {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'OK'),
            'feedback' => new external_value(PARAM_RAW, 'AI feedback'),
        ]);
    }

    //functions to call moodle mail API
    //parameters
    public static function send_takeaways_to_tutor_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'feedback' => new \external_value(PARAM_RAW, 'AI feedback (html or text)'),
        ]);
    }

    public static function send_takeaways_to_tutor($cmid, $feedback) {
        global $DB, $USER;

        $params = self::validate_parameters(self::send_takeaways_to_tutor_parameters(), [
            'cmid' => $cmid,
            'feedback' => $feedback,
        ]);

        $cm = get_coursemodule_from_id(null, (int)$params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_login($cm->course, false, $cm);

        // Pull student survey data (same table you already use)
        $survey = $DB->get_record('block_kiwi_pdfsurvey', [
            'userid' => $USER->id,
            'cmid'   => $cm->id,
        ], '*', MUST_EXIST);

        $about = trim((string)($survey->about_text ?? ''));
        $takeaways = trim((string)($survey->takeaways_text ?? ''));

        // Get course + module name for email context
        $course = get_course($cm->course);
        $modname = $cm->name ?? 'Resource';

        // Find tutors/teachers for this course (editingteachers + teachers)
        $recipients = self::get_course_tutors($course->id);
        if (empty($recipients)) {
            return ['ok' => false, 'message' => 'No tutor/teacher found for this course.'];
        }

        // Build email content
        $studentname = fullname($USER);
        $subject = "[Kiwilearner] Takeaways for {$modname} - {$studentname}";

        // Convert feedback to plain text for email if needed
        $feedbackhtml = (string)$params['feedback'];
        $feedbacktext = html_to_text($feedbackhtml); // Moodle helper

        $bodytext =
            "Kia ora,\n\n" .
            "Student: {$studentname}\n" .
            "Course: {$course->fullname}\n" .
            "Activity: {$modname}\n\n" .
            "STUDENT SUMMARY:\n" . ($about !== '' ? $about : "[none]") . "\n\n" .
            "STUDENT TAKEAWAYS:\n" . $takeaways . "\n\n" .
            "AI FEEDBACK:\n" . $feedbacktext . "\n\n" .
            "Sent via Kiwilearner.\n";

        $bodyhtml =
            "<p>Kia ora,</p>" .
            "<p><b>Student:</b> " . s($studentname) . "<br>" .
            "<b>Course:</b> " . s($course->fullname) . "<br>" .
            "<b>Activity:</b> " . s($modname) . "</p>" .
            "<h4>Student Summary</h4><p>" . format_text($about, FORMAT_PLAIN) . "</p>" .
            "<h4>Student Takeaways</h4><p>" . format_text($takeaways, FORMAT_PLAIN) . "</p>" .
            "<h4>AI Feedback</h4>" . $feedbackhtml .
            "<p>Sent via Kiwilearner.</p>";

        // Send email to each tutor
        $support = \core_user::get_support_user();
        $sentcount = 0;

        foreach ($recipients as $tutor) {
            if (email_to_user($tutor, $support, $subject, $bodytext, $bodyhtml)) {
                $sentcount++;
            }
        }

        if ($sentcount === 0) {
            return ['ok' => false, 'message' => 'Email could not be sent (check SMTP/outgoing mail config).'];
        }

        return ['ok' => true, 'message' => "Sent to {$sentcount} tutor(s)."];
    }

    public static function send_takeaways_to_tutor_returns() {
        return new \external_single_structure([
            'ok' => new \external_value(PARAM_BOOL, 'OK'),
            'message' => new \external_value(PARAM_TEXT, 'Result message'),
        ]);
    }

    private static function get_course_tutors(int $courseid): array {
        $context = \context_course::instance($courseid);

        // Try both roles (site may use one or the other)
        $users = [];

        $editing = get_role_users(3, $context, false, 'u.*'); // common: editingteacher roleid=3
        $teacher = get_role_users(4, $context, false, 'u.*'); // common: teacher roleid=4

        foreach ([$editing, $teacher] as $set) {
            if (is_array($set)) {
                foreach ($set as $u) {
                    $users[$u->id] = $u;
                }
            }
        }
        return array_values($users);
    }




}
