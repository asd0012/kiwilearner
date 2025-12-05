<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade for local_kiwilearner.
 */
function xmldb_local_kiwilearner_upgrade(int $oldversion): bool {
	global $DB;

	$dbman = $DB->get_manager();

	// 2025-12-03 – add goal_type & migrate from legacy "type".
	if ($oldversion < 2024120300) {
		$table = new xmldb_table('local_kiwilearner_goal');

		// 1) Add courseid column with default 0
		if (!$dbman->field_exists($table, new xmldb_field('courseid'))) {
			$field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'userid');
			$dbman->add_field($table, $field);
		}

		// 2) Drop old unique index
		$oldindex = new xmldb_index('usegoa_uix', XMLDB_INDEX_UNIQUE, ['userid', 'goal_type']);
		if ($dbman->index_exists($table, $oldindex)) {
			$dbman->drop_index($table, $oldindex);
		}

		// 3) Add new unique index
		$newindex = new xmldb_index('usercoursegoal_uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'goal_type']);
		if (!$dbman->index_exists($table, $newindex)) {
			$dbman->add_index($table, $newindex);
		}

		upgrade_plugin_savepoint(true, 2024120300, 'local', 'kiwilearner');
	}

	return true;
}

