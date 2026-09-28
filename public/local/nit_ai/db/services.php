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
 * External functions for the NIT AI video assistant.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_nit_ai_ask' => [
        'classname'   => 'local_nit_ai\external\ask',
        'description' => 'Ask the video assistant a question about the video in a course module.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/nit_ai:use',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_nit_ai_quizgen_generate' => [
        'classname'    => 'local_nit_ai\external\quizgen_generate',
        'description'  => 'Generate one batch of quiz questions from a slice of a video transcript.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/nit_ai:generatequiz',
    ],
];
