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
 * Admin tree: the one page "Teacher applications" with its tabs (applications, application fields,
 * badges, settings; local_nit_tutors\local\admin_tabs).
 *
 * It lives in Local plugins → Lesson packages (local_nit_flex's category), and local_nit_flex adds it
 * there itself (the load order of local plugins' settings follows their names in the current
 * language, so this file cannot rely on that category existing yet). This file adds it to Local
 * plugins only when there is no such category for this user: no local_nit_flex, or a reviewer who
 * cannot manage packages.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$systemcontext = context_system::instance();
$flexcategory = core_component::get_component_directory('local_nit_flex') !== null
    && ($hassiteconfig || has_capability('local/nit_flex:managepackages', $systemcontext));
if (!$flexcategory && ($hassiteconfig || has_capability('local/nit_tutors:review', $systemcontext))) {
    $ADMIN->add('localplugins', \local_nit_tutors\local\admin_tabs::admin_page());
    if (class_exists('\local_academy\local\admin_order')) {
        \local_academy\local\admin_order::apply($ADMIN);
    }
}
