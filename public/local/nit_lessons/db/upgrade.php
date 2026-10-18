<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_lessons_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100500) {
        // Teacher profiles for booking: available flag, subjects and weekly hours.
        foreach (['nit_teacher_profile', 'nit_teacher_subject', 'nit_teacher_hour'] as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $table);
            }
        }
        upgrade_plugin_savepoint(true, 2026100500, 'local', 'nit_lessons');
    }

    if ($oldversion < 2026101000) {
        // The teacher sets how many Flex one lesson costs; each lesson keeps the price it was booked at.
        $field = new xmldb_field('lesson_flex', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'headline');
        $table = new xmldb_table('nit_teacher_profile');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('flex_count', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'flex_state');
        $table = new xmldb_table('nit_lesson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101000, 'local', 'nit_lessons');
    }

    if ($oldversion < 2026101100) {
        // No maximum on the Flex price of a lesson: drop the max_lesson_flex setting and widen both
        // columns (they were 4 digits).
        unset_config('max_lesson_flex', 'local_nit_lessons');
        $field = new xmldb_field('lesson_flex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'headline');
        $dbman->change_field_precision(new xmldb_table('nit_teacher_profile'), $field);
        $field = new xmldb_field('flex_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'flex_state');
        $dbman->change_field_precision(new xmldb_table('nit_lesson'), $field);
        upgrade_plugin_savepoint(true, 2026101100, 'local', 'nit_lessons');
    }

    if ($oldversion < 2026101800) {
        // Free cancel / reschedule window is 12 hours (academy spec 2.2). Sites still on the old
        // 120-minute default move to 720; a value an admin chose on purpose is kept.
        foreach (['cancel_deadline_minutes', 'update_deadline_minutes'] as $key) {
            $current = get_config('local_nit_lessons', $key);
            if ($current === false || (int) $current === 120) {
                set_config($key, 720, 'local_nit_lessons');
            }
        }
        upgrade_plugin_savepoint(true, 2026101800, 'local', 'nit_lessons');
    }

    return true;
}
