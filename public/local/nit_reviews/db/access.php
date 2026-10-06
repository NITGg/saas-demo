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
 * Capabilities for local_nit_reviews.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Post / edit your own rating of a course you're enrolled in, and of its teachers.
    'local/nit_reviews:rate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'student' => CAP_ALLOW,
        ],
    ],
    // Moderate: approve, reject and delete reviews. Managers only — a teacher must
    // not approve the reviews written about them. A manager assigned on a category
    // (or a course) moderates the courses below it; a site manager moderates all.
    'local/nit_reviews:moderate' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'riskbitmask'  => RISK_SPAM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
