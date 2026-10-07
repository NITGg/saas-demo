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
 * Capabilities for local_nit_reports.
 *
 * Held in a course (or a category or the site above it), they open the reports of
 * that course; held on the whole site, every report of every course.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Every report of the courses in scope (managers; on the site: admins and site managers).
    'local/nit_reports:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    // The teaching reports of one's own courses: students, course performance, videos,
    // student results, and one's own dues.
    'local/nit_reports:viewteaching' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
