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
 * Web-service functions for local_parent (parent frontend + mobile app).
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_parent_list_children' => [
        'classname'   => 'local_parent\external\list_children',
        'description' => 'The children linked to the calling parent.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_parent_child_grades' => [
        'classname'   => 'local_parent\external\child_grades',
        'description' => 'One linked child\'s course marks.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/parent:view',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_parent_child_quizzes' => [
        'classname'   => 'local_parent\external\child_quizzes',
        'description' => 'One linked child\'s quiz attempts (score, time, duration).',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/parent:view',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
