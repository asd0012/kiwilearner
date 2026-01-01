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

/**
 * Render the H5P content.
 *
 * This uses Moodle core H5P renderer.
 * Works for Interactive Video and other H5P types.
 */
if (!empty($instance->h5pcontentid)) {
    $factory = \core_h5p\factory::get_instance();
    $h5poutput = $factory->get_renderer('core_h5p');

    echo $h5poutput->display_h5p_content($instance->h5pcontentid);
} else {
    echo $OUTPUT->notification('No H5P content configured for this activity.', 'error');
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
