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
 * Install: create the parent role and store its id.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create the 'parent' role (assignable in a user context) with the capabilities
 * a parent needs to see their linked child, and remember its id.
 */
function xmldb_local_parent_install() {
    global $DB;

    // Reuse an existing 'parent' shortname if one is already there, else create it.
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'parent']);
    if (!$roleid) {
        $roleid = create_role(
            get_string('parentrole', 'local_parent'),
            'parent',
            get_string('parentroledesc', 'local_parent')
        );
    }
    set_config('roleid', $roleid, 'local_parent');

    // The role is assigned in each child's USER context.
    set_role_contextlevels($roleid, [CONTEXT_USER]);

    // Grant the capabilities: our own view flag plus the core rights that let the
    // parent see the child's profile and grades. Assigned against the system
    // context (the role's permission), checked later in the child's user context.
    $syscontext = context_system::instance();
    $caps = [
        'local/parent:view',
        'moodle/user:viewdetails',
        'moodle/user:viewalldetails',
        'moodle/grade:viewall',
        'gradereport/user:view',
    ];
    foreach ($caps as $cap) {
        if (get_capability_info($cap)) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
    }
    $syscontext->mark_dirty();

    // The student's "parent phone" custom profile field, shown on the sign-up form.
    // Its shortname must match the local_parent/parentphonefield setting (default
    // 'parentphone'); the user_created observer reads it to create the link.
    $shortname = 'parentphone';
    if (!$DB->record_exists('user_info_field', ['shortname' => $shortname])) {
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
}
