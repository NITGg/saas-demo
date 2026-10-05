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

namespace local_academy\local;

/**
 * Sends learners and visitors who open a course (course/view.php?id=N) to the
 * course details page, local/academy/course.php?id=N — as on bassthalk.com, where
 * a course link always shows the course page (price, about, lessons) and the
 * lessons open from there.
 *
 * It runs on the after_config hook, before course/view.php's require_login() would
 * send a visitor to the log-in and a not-enrolled user to the enrolment page.
 * Staff (who may update the course or see its hidden activities — teachers) and
 * site admins keep the real Moodle course,
 * and so does a single-section view (section / sectionid).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_route {

    /**
     * Hook callback.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;

        if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || ($SCRIPT ?? '') !== '/course/view.php') {
            return;
        }
        $url = self::target(
            optional_param('id', 0, PARAM_INT),
            optional_param('section', null, PARAM_INT) !== null || optional_param('sectionid', null, PARAM_INT) !== null
        );
        if ($url !== null) {
            redirect($url);
        }
    }

    /**
     * Where course/view.php should go instead, or null to let it run.
     *
     * @param int $courseid the course asked for (0 when it came by name / idnumber)
     * @param bool $onesection a single section was asked for
     * @return \moodle_url|null
     */
    public static function target(int $courseid, bool $onesection): ?\moodle_url {
        global $DB;

        if ($courseid <= 0 || $courseid === (int) SITEID || $onesection
                || !$DB->record_exists('course', ['id' => $courseid])) {
            return null;
        }
        if (isloggedin() && !isguestuser()) {
            $context = \context_course::instance($courseid);
            if (is_siteadmin() || has_any_capability(['moodle/course:update', 'moodle/course:viewhiddenactivities'], $context)) {
                return null;
            }
        }
        return new \moodle_url('/local/academy/course.php', ['id' => $courseid]);
    }
}
