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
 * Upgrade steps for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_nit_finance_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100400) {
        // Wallets, per-activity prices, purchases and access codes.
        foreach (['nit_wallet', 'nit_wallet_txn', 'nit_item_price', 'nit_purchase', 'nit_access_code'] as $table) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $table);
            }
        }
        // Per-teacher percent on the teacher's profile.
        \local_nit_finance\local\teacher_share::ensure_profile_field();
        upgrade_plugin_savepoint(true, 2026100400, 'local', 'nit_finance');
    }

    if ($oldversion < 2026100500) {
        // Earnings come from two places now: activity sales (lessonid = cmid) and live
        // lessons paid with a Flex (lessonid = nit_lesson.id). Tell them apart.
        $table = new xmldb_table('nit_earning');
        $field = new xmldb_field('source', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'cm', 'lessonid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('source_lesson_idx', XMLDB_INDEX_NOTUNIQUE, ['source', 'lessonid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Earnings written by the live-lesson engine before this field existed.
        if ($dbman->table_exists('nit_lesson')) {
            $DB->execute("UPDATE {nit_earning}
                             SET source = 'lesson'
                           WHERE EXISTS (SELECT 1 FROM {nit_lesson} l
                                          WHERE l.id = {nit_earning}.lessonid
                                            AND l.purchaseid = {nit_earning}.purchaseid
                                            AND l.teacherid = {nit_earning}.teacherid)
                             AND NOT EXISTS (SELECT 1 FROM {nit_purchase} p
                                              WHERE p.id = {nit_earning}.purchaseid
                                                AND p.itemtype = 'cm' AND p.itemid = {nit_earning}.lessonid)");
        }
        upgrade_plugin_savepoint(true, 2026100500, 'local', 'nit_finance');
    }

    return true;
}
