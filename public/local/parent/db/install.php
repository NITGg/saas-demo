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
 * Install: make sure the student's "parent phone" profile field exists.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create the student's "parent phone" custom profile field when missing.
 *
 * The parent dashboard matches what a parent types against it (and against the
 * father / mother phone fields of local_academy). Its shortname must match the
 * local_parent/parentphonefield setting (default 'parentphone').
 */
function xmldb_local_parent_install() {
    global $DB;

    $shortname = 'parentphone';
    if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
        return;
    }
    $catid = $DB->get_field('user_info_category', 'id', [], IGNORE_MULTIPLE);
    if (!$catid) {
        $catid = $DB->insert_record('user_info_category', (object) ['name' => 'Other', 'sortorder' => 1]);
    }
    $DB->insert_record('user_info_field', (object) [
        'shortname'    => $shortname,
        'name'         => get_string('parentphonelabel', 'local_parent'),
        'datatype'     => 'text',
        'description'  => '',
        'descriptionformat' => FORMAT_HTML,
        'categoryid'   => $catid,
        'sortorder'    => 1,
        'required'     => 0,
        'locked'       => 0,
        'visible'      => 1,   // visible to the user on their own profile
        'forceunique'  => 0,
        'signup'       => 1,   // show it on the sign-up form
        'defaultdata'  => '',
        'defaultdataformat' => FORMAT_HTML,
        'param1'       => 30,  // display size
        'param2'       => 32,  // max length
    ]);
}
