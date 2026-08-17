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
 * Job Form external function and service definitions.
 *
 * @package    mod_jobform
 * @category   external
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_jobform_get_jobforms_by_courses' => [
        'classname'    => 'mod_jobform_external',
        'methodname'   => 'get_jobforms_by_courses',
        'description'  => 'Returns the Job Form activities the student can see in the given courses.',
        'type'         => 'read',
        'capabilities' => 'mod/jobform:view',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_jobform_view_jobform' => [
        'classname'    => 'mod_jobform_external',
        'methodname'   => 'view_jobform',
        'description'  => 'Logs that the Job Form was viewed and updates activity completion.',
        'type'         => 'write',
        'capabilities' => 'mod/jobform:view',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_jobform_get_form' => [
        'classname'    => 'mod_jobform_external',
        'methodname'   => 'get_form',
        'description'  => 'Returns the fields, groups, certificate-gate status and any saved answers '
            . 'the student needs to render and fill in a Job Form.',
        'type'         => 'read',
        'capabilities' => 'mod/jobform:view',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_jobform_submit_form' => [
        'classname'    => 'mod_jobform_external',
        'methodname'   => 'submit_form',
        'description'  => 'Validates and stores the student\'s answers (as a draft or a final submission).',
        'type'         => 'write',
        'capabilities' => 'mod/jobform:submit',
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
