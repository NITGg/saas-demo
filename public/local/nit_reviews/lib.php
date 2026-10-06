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
 * Navigation callbacks for local_nit_reviews.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course "More" menu: "Review moderation" (this course's reviews) for whoever
 * moderates the course — the way in for a manager scoped to a category or course.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_nit_reviews_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ((int) $course->id <= 1 || !has_capability('local/nit_reviews:moderate', $context)) {
        return;
    }
    $navigation->add(
        get_string('moderatereviews', 'local_nit_reviews'),
        new moodle_url('/local/nit_reviews/moderate.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_nit_reviews_moderate',
        new pix_icon('i/star', '')
    );
}
