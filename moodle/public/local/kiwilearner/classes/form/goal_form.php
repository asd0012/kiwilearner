<?php
declare(strict_types=1);

namespace local_kiwilearner\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_kiwilearner\goal;

class goal_form extends \moodleform {

    public function definition(): void {
        $mform    = $this->_form;
        $defaults = $this->_customdata['defaults'] ?? (object)[];

        // === Header ===
        $mform->addElement(
            'header',
            'hdr',
            get_string('goalsettings', 'local_kiwilearner')
        );

        // === Goal type select (XP vs Lesson) ===
        $options = [
            goal::TYPE_XP     => get_string('goaltype_xp', 'local_kiwilearner'),
            goal::TYPE_LESSON => get_string('goaltype_lesson', 'local_kiwilearner'),
        ];

        $mform->addElement(
            'select',
            'goal_type',
            get_string('goaltype', 'local_kiwilearner'),
            $options
        );
        $mform->setType('goal_type', PARAM_INT);
        $mform->setDefault('goal_type', $defaults->goal_type ?? goal::TYPE_XP);

        // === XP target textbox ===
        $mform->addElement(
            'text',
            'xp_target',
            get_string('xptarget', 'local_kiwilearner')
        );

	$mform->setType('xp_target', PARAM_INT);
        $mform->setDefault('xp_target', $defaults->xp_target ?? 20);
        $mform->addRule('xp_target', null, 'numeric', null, 'client');

        // === Lesson target textbox ===
        $mform->addElement(
            'text',
            'lesson_target',
            get_string('lessontarget', 'local_kiwilearner')
        );
        $mform->setType('lesson_target', PARAM_INT);
        $mform->setDefault('lesson_target', $defaults->lesson_target ?? 2);
        $mform->addRule('lesson_target', null, 'numeric', null, 'client');

        // Only show the one that matches the goal type.
        $mform->hideIf(
            'xp_target',
            'goal_type',
            'noteq',
            goal::TYPE_XP
        );
        $mform->hideIf(
            'lesson_target',
            'goal_type',
            'noteq',
            goal::TYPE_LESSON
        );

        // Buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
	    $errors = parent::validation($data, $files);

	    $type = (int)$data['goal_type'];

	    if ($type === goal::TYPE_XP) {
		    $xp = (int)$data['xp_target'];
		    if ($xp < 1 || $xp > 999) {
			    $errors['xp_target'] = get_string('error_xptarget_range', 'local_kiwilearner');
		    }
	    } else if ($type === goal::TYPE_LESSON) {
		    $lessons = (int)$data['lesson_target'];
		    if ($lessons < 1 || $lessons > 999) {
			    $errors['lesson_target'] = get_string('error_lessontarget_range', 'local_kiwilearner');
		    }
	    }

	    return $errors;
    }
    
}

