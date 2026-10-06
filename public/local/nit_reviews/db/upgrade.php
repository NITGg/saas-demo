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
 * Upgrade steps for local_nit_reviews.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_nit_reviews.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_reviews_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100700) {
        // Teacher reviews + moderation: a review now targets a course (teacherid 0)
        // or a teacher in a course, and a comment waits for a moderator.
        $table = new xmldb_table('local_nit_reviews');

        $oldkey = new xmldb_key('courseuser', XMLDB_KEY_UNIQUE, ['courseid', 'userid']);
        $dbman->drop_key($table, $oldkey);
        $oldindex = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }

        $fields = [
            new xmldb_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid'),
            new xmldb_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'review'),
            new xmldb_field('rejectreason', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status'),
            new xmldb_field('reviewedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'rejectreason'),
            new xmldb_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reviewedby'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Reviews written before moderation existed were already public: keep them so.
        $DB->set_field('local_nit_reviews', 'status', 1);

        $dbman->add_key($table, new xmldb_key('courseteacheruser', XMLDB_KEY_UNIQUE, ['courseid', 'teacherid', 'userid']));
        foreach ([new xmldb_index('teacherstatus', XMLDB_INDEX_NOTUNIQUE, ['teacherid', 'status']),
                  new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status'])] as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026100700, 'local', 'nit_reviews');
    }

    if ($oldversion < 2026100701) {
        // Rating is for learners now: teachers (who had it by default before) must
        // not rate their own course or themselves.
        $syscontext = context_system::instance();
        foreach (get_archetype_roles('editingteacher') + get_archetype_roles('teacher') as $role) {
            unassign_capability('local/nit_reviews:rate', $role->id, $syscontext->id);
        }
        upgrade_plugin_savepoint(true, 2026100701, 'local', 'nit_reviews');
    }

    return true;
}
