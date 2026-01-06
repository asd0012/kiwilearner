<?php
require(__DIR__ . '/../../config.php');

use core_contentbank\contentbank;

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

$cb = new \core_contentbank\contentbank();
$contentid = (int)($instance->h5pcontentid ?? 0);

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
// Render strategy (NO EXPORT FILES)
// ---------------------------
if (empty($contentid)) {
    echo $OUTPUT->notification('No H5P content configured for this activity.', 'error');
} else {
    try {
        $content = $cb->get_content_from_id($contentid);

        // Render content-bank item directly (no /h5p/embed.php and no export zips).
        $contenttype = $content->get_content_type_instance();
        echo html_writer::start_div('kiwivideo-contentbank-wrap', [
            'id' => 'kiwivideo-wrap',
            'style' => 'max-width:1200px; margin:0 auto;'
        ]);
        echo $contenttype->get_view_content($content);
        echo html_writer::end_div();

    } catch (\Throwable $e) {
        if (is_siteadmin()) {
            echo $OUTPUT->notification('Failed to render content bank H5P: ' . s($e->getMessage()), 'error');
        } else {
            echo $OUTPUT->notification('Failed to render H5P content.', 'error');
        }
    }
}


// ----------------------------------------------------
// Ajax event debug panel
// ----------------------------------------------------
$xpdebug = optional_param('xpdebug', 0, PARAM_BOOL);

if ($xpdebug && is_siteadmin()) {
    echo html_writer::tag('div', '', [
        'id' => 'kl-xp-debug',
        'style' => 'margin:12px auto; max-width:1200px; padding:10px; border:1px solid #ddd; border-radius:10px; background:#fff; font-family:monospace; font-size:12px; line-height:1.4;'
    ]);

    echo html_writer::tag('div',
        'KiwiLearner XP Debug enabled. This panel logs xAPI postMessage events + AJAX responses.',
        ['style' => 'margin-bottom:6px; font-weight:600;']
    );

    echo html_writer::tag('div', 'Tip: interact with a question and watch logs below.', ['style' => 'color:#666;']);

    echo html_writer::tag('div', '', ['id' => 'kl-xp-debug-log', 'style' => 'margin-top:8px; white-space:pre-wrap;']);
}

// ----------------------------------------------------
// Inject JS context for XP awarding
// ----------------------------------------------------
$PAGE->requires->js_init_code(
    'window.KL_IV = ' . json_encode([
        'cmid' => (int)$cm->id,
        'awardUrl' => $CFG->wwwroot . '/mod/kiwivideo/ajax_award.php',
        'sesskey' => sesskey(),
    ]) . ';'
);

// ----------------------------------------------------
// MVP inline JS: listen for H5P answered(success) events
// ----------------------------------------------------

$xpdebug = optional_param('xpdebug', 0, PARAM_BOOL);

$PAGE->requires->js_init_code(<<<JS
(function () {
  if (!window.KL_IV) return;

  const XPDEBUG = Boolean({$xpdebug}) && typeof console !== 'undefined';

  function log(...args) {
    if (!XPDEBUG) return;
    console.log('[KL-XP]', ...args);
    const box = document.getElementById('kl-xp-debug-log');
    if (box) box.textContent += args.map(a => {
      try { return (typeof a === 'string') ? a : JSON.stringify(a, null, 2); }
      catch (e) { return String(a); }
    }).join(' ') + "\\n\\n";
  }

  const sent = new Set();

  function getSubContentId(statement) {
    return statement?.object?.definition?.extensions?.['http://h5p.org/x-api/h5p-subContentId'];
  }

  function isAnswered(statement) {
    const verb = statement?.verb?.id || '';
    return verb.includes('/answered');
  }

  async function awardXP(subcontentid, success) {
    // Dedup in-session
    const key = subcontentid + ':' + (success ? '1' : '0');
    if (sent.has(key)) {
      log('SKIP duplicate', key);
      return;
    }
    sent.add(key);

    const params = new URLSearchParams();
    params.set('cmid', KL_IV.cmid);
    params.set('subcontentid', subcontentid);
    params.set('success', success ? '1' : '0');
    params.set('sesskey', KL_IV.sesskey);

    log('POST award', KL_IV.awardUrl, Object.fromEntries(params.entries()));

    try {
      const resp = await fetch(KL_IV.awardUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
        credentials: 'same-origin'
      });
      const text = await resp.text();
      log('AJAX status', resp.status, 'body', text);
    } catch (e) {
      log('AJAX error', String(e));
    }
  }

  // ---- hook into the iframe's H5P externalDispatcher ----
  function findH5PDispatcher() {
    // Case A: inline render on the same page
    if (window.H5P && window.H5P.externalDispatcher) {
        return { dispatcher: window.H5P.externalDispatcher, where: 'parent' };
    }

    // Case B: rendered in an iframe somewhere inside our wrapper
    const wrap = document.getElementById('kiwivideo-wrap');
    const iframe = wrap ? wrap.querySelector('iframe') : null;
    if (!iframe) return null;

    try {
        const w = iframe.contentWindow;
        if (w && w.H5P && w.H5P.externalDispatcher) {
        return { dispatcher: w.H5P.externalDispatcher, where: 'iframe' };
        }
    } catch (e) {
        // Cross-origin iframe (unlikely in Moodle), can't access.
        return null;
    }

    return null;
    }


  // Poll until iframe+H5P exist (simple + robust)
    let tries = 0;
    const timer = setInterval(() => {
    tries++;

    const found = findH5PDispatcher();
    if (found) {
        clearInterval(timer);
        log('Attached to H5P externalDispatcher in', found.where);

        found.dispatcher.on('xAPI', (event) => {
        const statement = event?.data?.statement;
        if (!statement) return;

        log('xAPI', statement);

        const verb = statement?.verb?.id || '';
        if (!verb.includes('/answered')) return;

        const subcontentid = statement?.object?.definition?.extensions?.['http://h5p.org/x-api/h5p-subContentId'];
        const success = Boolean(statement?.result?.success);

        if (success && subcontentid) {
            
            const def = statement?.object?.definition || {};
            const isQuestion =
            def.interactionType === 'choice' ||
            Array.isArray(def.choices) ||
            Array.isArray(def.correctResponsesPattern) ||
            (def.type && String(def.type).includes('cmi.interaction'));

            if (!isQuestion) {
            log('Skip non-question answered statement');
            return;
            }

            awardXP(subcontentid, true);
        }
        });

        return;
    }

    if (tries > 100) { // ~20s
        clearInterval(timer);
        log('Gave up: could not find H5P dispatcher (no iframe and no inline H5P)');
    }
    }, 200);


  log('KiwiLearner XP listener active. cmid=', KL_IV.cmid);
})();
JS
);


echo $OUTPUT->footer();
