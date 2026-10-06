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
 * Admin tree: the review moderation page, under Site administration → Courses.
 * A manager scoped to a category or course opens it from the course's menu instead
 * (local_nit_reviews_extend_navigation_course).
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/nit_reviews:moderate', context_system::instance())) {
    $ADMIN->add('courses', new admin_externalpage(
        'local_nit_reviews_moderate',
        get_string('moderatereviews', 'local_nit_reviews'),
        new moodle_url('/local/nit_reviews/moderate.php'),
        'local/nit_reviews:moderate'
    ));
}
