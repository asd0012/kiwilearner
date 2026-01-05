<?php
// mod/kiwivideo/view.php

require(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'kiwivideo');
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/kiwivideo:view', $context);

$PAGE->set_url('/mod/kiwivideo/view.php', ['id' => $cmid]);
$PAGE->set_title(format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// === IMPORTANT ===
// For MVP, we assume the module instance stores an H5P content id.
// e.g. $instance->h5pcontentid
$instance = $DB->get_record(
    'kiwivideo',
    ['id' => $cm->instance],
    '*',
    MUST_EXIST
);

echo $OUTPUT->header();



// ----------------------------------------------------
// KiwiVideo: H5P debug + render (robust for your build)
// ----------------------------------------------------
global $CFG, $DB, $OUTPUT;

$debug = optional_param('debug', 0, PARAM_BOOL);
$contentid = (int)($instance->h5pcontentid ?? 0);
$sysctx = \context_system::instance();
$sysctxid = $sysctx->id;

if ($debug && is_siteadmin()) {
    echo $OUTPUT->heading('KiwiVideo H5P Debug', 3);
    echo html_writer::div('instance->h5pcontentid = ' . $contentid);
    echo html_writer::div('System context id = ' . $sysctxid);

    // --- DB record check: mdl_h5p ---
    if ($DB->get_manager()->table_exists('h5p')) {
        $h5precord = $DB->get_record('h5p', ['id' => $contentid]);
        echo html_writer::div('Table mdl_h5p exists: YES');
        echo html_writer::div('Record in mdl_h5p: ' . ($h5precord ? 'YES' : 'NO'));
        if ($h5precord) {
            echo html_writer::div('mdl_h5p fields: ' . s(implode(', ', array_keys((array)$h5precord))));
            echo html_writer::div('mainlibraryid: ' . s($h5precord->mainlibraryid ?? ''));
            echo html_writer::div('displayoptions: ' . s($h5precord->displayoptions ?? ''));
        }
    } else {
        echo html_writer::div('Table mdl_h5p exists: NO');
    }

    echo html_writer::empty_tag('hr');

    // --- Helper to list files ---
    $print_files = function(string $title, array $where) use ($DB, $OUTPUT) {
        echo $OUTPUT->heading($title, 4);
        echo html_writer::div('Lookup: ' . s(json_encode($where)));

        $files = $DB->get_records('files', $where, 'filepath ASC, filename ASC',
            'id, filepath, filename, filesize, mimetype, timecreated');

        echo html_writer::div('Found files: ' . count($files));

        if (!$files) {
            echo html_writer::div('(none)');
            return [];
        }

        $rows = [];
        foreach ($files as $f) {
            $rows[] = s($f->filepath . $f->filename)
                . ' | size=' . (int)$f->filesize
                . (isset($f->mimetype) ? ' | mime=' . s($f->mimetype) : '')
                . ' | timecreated=' . (int)($f->timecreated ?? 0)
                . ' | files.id=' . (int)$f->id;
        }
        echo html_writer::alist($rows);
        return $files;
    };

    // --- content filearea (runtime extracted) uses itemid = contentid ---
    $print_files(
        'Filearea: core_h5p/content (runtime extracted) [itemid = contentid]',
        [
            'contextid' => $sysctxid,
            'component' => 'core_h5p',
            'filearea'  => 'content',
            'itemid'    => $contentid,
        ]
    );

    echo html_writer::empty_tag('hr');

    // --- export filearea uses itemid = 0 (THIS IS THE KEY FIX) ---
    $exportfiles = $print_files(
        'Filearea: core_h5p/export (zip packages) [itemid = 0]',
        [
            'contextid' => $sysctxid,
            'component' => 'core_h5p',
            'filearea'  => 'export',
            'itemid'    => 0,
        ]
    );

    echo $OUTPUT->notification(
        'Key fact from core_h5p_pluginfile(): EXPORT filearea uses itemid=0, not the content id.',
        'info'
    );
}




// ---------------------------
// Render strategy
// ---------------------------
if (empty($contentid)) {
    echo $OUTPUT->notification('No H5P content configured for this activity.', 'error');
} else {

    // Get all export files (itemid=0 per core_h5p_pluginfile()) and pick a .h5p.
    $exportfiles = $DB->get_records('files', [
        'contextid' => $sysctxid,
        'component' => 'core_h5p',
        'filearea'  => 'export',
        'itemid'    => 0,
    ], 'filepath ASC, filename ASC', 'id, filepath, filename, filesize, timecreated');

    $candidates = [];
    foreach ($exportfiles as $f) {
        if ((int)$f->filesize <= 0) {
            continue;
        }
        if (strtolower(substr($f->filename, -4)) !== '.h5p') {
            continue;
        }
        $candidates[] = $f;
    }

    // Heuristic: prefer ones whose filepath contains "/<contentid>/".
    $chosen = null;
    foreach ($candidates as $f) {
        if (strpos($f->filepath, '/' . $contentid . '/') !== false) {
            $chosen = $f;
            break;
        }
    }

    // Fallback: newest .h5p by timecreated (or id).
    if (!$chosen && $candidates) {
        usort($candidates, function($a, $b) {
            $ta = (int)($a->timecreated ?? 0);
            $tb = (int)($b->timecreated ?? 0);
            if ($ta === $tb) return (int)$b->id <=> (int)$a->id;
            return $tb <=> $ta;
        });
        $chosen = $candidates[0];
    }

    if (!$chosen) {
        echo $OUTPUT->notification(
            'Cannot find any exported .h5p package in core_h5p/export (itemid=0). ' .
            'This usually means exporting/downloading is disabled, or no package has been generated yet.',
            'error'
        );
    } else {
        // Build pluginfile URL to the exported .h5p package.
        // NOTE: export filearea itemid MUST be 0 here.
        $zipurl = \moodle_url::make_pluginfile_url(
            $sysctxid,
            'core_h5p',
            'export',
            0,
            $chosen->filepath,
            $chosen->filename
        );

        // Your embed.php expects ?url=... and internally uses core_h5p\player.
        $embedurl = new \moodle_url('/h5p/embed.php', [
            'url' => $zipurl->out(false),
            'component' => 'mod_kiwivideo', // optional, helps with context in some cases
        ]);

        if ($debug && is_siteadmin()) {
            echo $OUTPUT->heading('Render (debug)', 4);
            echo html_writer::div('Chosen export file: ' . s($chosen->filepath . $chosen->filename) . ' size=' . (int)$chosen->filesize);
            echo html_writer::div('ZIP URL: ' . s($zipurl->out(false)));
            echo html_writer::div(html_writer::link($embedurl, 'Open embed URL (debug)'));
        }

        // A clean responsive video wrapper (16:9).
        echo html_writer::start_tag('div', [
            'style' => 'max-width:1200px; margin:0 auto;'
        ]);

        echo html_writer::start_tag('div', [
            'style' => 'position:relative; width:100%; aspect-ratio:4/3; background:#000; border-radius:12px; overflow:hidden;'
        ]);

        echo html_writer::tag('iframe', '', [
            'class' => 'kiwivideo-h5p-iframe',
            'src' => $embedurl->out(false),
            'style' => 'position:absolute; inset:0; width:100%; height:100%; border:0; display:block; overflow:hidden;',
            'scrolling' => 'no',
            'allowfullscreen' => 'allowfullscreen',
            'allow' => 'autoplay; fullscreen; encrypted-media',
            'title' => format_string($instance->name),
        ]);

        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');



    }
}





// ----------------------------------------------------
// Inject JS context for XP awarding
// ----------------------------------------------------
$PAGE->requires->js_init_code(
    'window.KL_IV_CMID = ' . (int)$cm->id . ';'
);
$PAGE->requires->js_init_code(
    'window.KL_IV_AWARD_URL = "' . $CFG->wwwroot . '/mod/kiwivideo/ajax_award.php";'
);

// ----------------------------------------------------
// MVP inline JS: listen for H5P answered(success) events
// ----------------------------------------------------
$PAGE->requires->js_init_code(<<<'JS'
(function () {
  const hook = () => {
    if (!window.H5P || !H5P.externalDispatcher) {
      setTimeout(hook, 500);
      return;
    }

    console.log('[KiwiLearner] H5P XP listener active');

    const sent = new Set();

    H5P.externalDispatcher.on('xAPI', function (evt) {
      const st = evt?.data?.statement;
      if (!st) return;

      const verb = st?.verb?.id || '';
      if (!verb.includes('/answered')) return;

      const result = st?.result || {};
      if (result.success !== true) return;

      const def = st?.object?.definition || {};
      const ex = def.extensions || {};
      const subcontentid = ex['http://h5p.org/x-api/h5p-subContentId'];

      if (!subcontentid) return;

      // Ignore container-level answered events
      const isRealQuestion =
        !!def.interactionType ||
        Array.isArray(def.choices) ||
        Array.isArray(def.correctResponsesPattern) ||
        (def.type && String(def.type).includes('cmi.interaction'));

      if (!isRealQuestion) return;

      // Prevent repeat posts in same session
      if (sent.has(subcontentid)) return;
      sent.add(subcontentid);

      const params = new URLSearchParams();
      params.set('cmid', window.KL_IV_CMID);
      params.set('subcontentid', subcontentid);
      params.set('success', '1');
      params.set('sesskey', M.cfg.sesskey);

      fetch(window.KL_IV_AWARD_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
      });
    });
  };

  hook();
})();
JS
);

echo $OUTPUT->footer();
