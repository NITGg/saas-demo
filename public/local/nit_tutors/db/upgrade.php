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
 * Upgrade steps for local_nit_tutors.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_tutors_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101900) {
        // The one fixed "Super Tutor" badge becomes the first of the badges the admin manages.
        $table = new xmldb_table('nit_tutor_badge');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('color', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'warning');
        $table->add_field('method', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'auto');
        $table->add_field('minrating', XMLDB_TYPE_NUMBER, '3, 1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('minreviews', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('minlessons', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxabsence', XMLDB_TYPE_NUMBER, '5, 1', null, XMLDB_NOTNULL, null, '100');
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('nit_tutor_badge_user');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mode', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'auto');
        $table->add_field('awarded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeawarded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('badgeid_fk', XMLDB_KEY_FOREIGN, ['badgeid'], 'nit_tutor_badge', ['id']);
        $table->add_index('badge_user_uk', XMLDB_INDEX_UNIQUE, ['badgeid', 'userid']);
        $table->add_index('user_awarded_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'awarded']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The old badge rules (admin settings) become the rules of the first badge.
        $cfg = fn(string $name, $default) => ($v = get_config('local_nit_tutors', $name)) === false || $v === '' ? $default : $v;
        $now = time();
        $badgeid = $DB->insert_record('nit_tutor_badge', (object) [
            'name' => '{mlang ar}مدرس متميز{mlang}{mlang en}Super Tutor{mlang}',
            'description' => '{mlang ar}تقييم عالٍ من الطلاب، وحصص كثيرة، والتزام بالمواعيد.{mlang}'
                . '{mlang en}Highly rated by students, many lessons, always on time.{mlang}',
            'color' => 'warning', 'method' => 'auto',
            'minrating' => (float) $cfg('badge_minrating', 4.8), 'minreviews' => (int) $cfg('badge_minreviews', 10),
            'minlessons' => (int) $cfg('badge_minlessons', 30), 'maxabsence' => (float) $cfg('badge_maxabsence', 2),
            'active' => 1, 'sortorder' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        // Who wore it, and the reviewers' on/off pins, carry over.
        $tutor = new xmldb_table('nit_tutor');
        if ($dbman->field_exists($tutor, new xmldb_field('badgemode'))) {
            $rows = $DB->get_records_select('nit_tutor', "badge = 1 OR badgemode <> 'auto'", null, '',
                'id, userid, badge, badgemode, badgesince');
            foreach ($rows as $r) {
                $DB->insert_record('nit_tutor_badge_user', (object) ['badgeid' => $badgeid, 'userid' => $r->userid,
                    'mode' => in_array($r->badgemode, ['on', 'off'], true) ? $r->badgemode : 'auto',
                    'awarded' => (int) $r->badge, 'timeawarded' => (int) $r->badgesince]);
            }
            foreach (['badgemode', 'badge', 'badgesince'] as $name) {
                $field = new xmldb_field($name);
                if ($dbman->field_exists($tutor, $field)) {
                    $dbman->drop_field($tutor, $field);
                }
            }
        }
        foreach (['badge_minrating', 'badge_minreviews', 'badge_minlessons', 'badge_maxabsence'] as $name) {
            unset_config($name, 'local_nit_tutors');
        }
        upgrade_plugin_savepoint(true, 2026101900, 'local', 'nit_tutors');
    }

    return true;
}
