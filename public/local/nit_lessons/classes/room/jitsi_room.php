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

namespace local_nit_lessons\room;

use local_nit_lessons\entity\lesson;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\service\settings_service;

/**
 * Meeting room = a Jitsi activity in the "live lessons" course (setting lessons_courseid),
 * visible only to the lesson's teacher and student (a per-lesson group), linked to a
 * local_academysessions session so its whitelist and time window apply. Ported from the
 * old academy room_manager.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jitsi_room implements room_interface {

    /**
     * Is the Jitsi room usable on this site (module installed, course chosen)?
     *
     * @return bool
     */
    public static function configured(): bool {
        global $DB;
        $courseid = (new settings_service())->get('lessons_courseid');
        return $courseid > 0 && $courseid != SITEID
            && $DB->record_exists('course', ['id' => $courseid])
            && $DB->record_exists('modules', ['name' => 'jitsi', 'visible' => 1])
            && class_exists('\local_academysessions\session_manager');
    }

    /**
     * Create the room when the teacher starts the lesson. Re-uses the room of a lesson that
     * was already started.
     *
     * @param lesson $lesson
     * @return object {sessionid, cmid, join_url}
     */
    public function create_for_lesson(lesson $lesson): object {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/enrollib.php');

        if (!self::configured()) {
            throw new lesson_exception('err_nolessonscourse');
        }
        $courseid = (new settings_service())->get('lessons_courseid');
        $teacherid = (int) $lesson->get('teacherid');
        $studentid = (int) $lesson->get('studentid');

        if ((int) $lesson->get('cmid') > 0 && $DB->record_exists('course_modules', ['id' => $lesson->get('cmid')])) {
            return (object) ['sessionid' => (int) $lesson->get('sessionid'), 'cmid' => (int) $lesson->get('cmid'),
                'join_url' => self::join_url((int) $lesson->get('cmid'))];
        }

        self::lock_down_teacher_visibility($courseid);
        self::let_students_into_hidden_course($courseid);
        self::enrol($courseid, $teacherid, 'editingteacher');
        self::enrol($courseid, $studentid, 'student');

        $student = \core_user::get_user($studentid);
        $title = trim(format_string($lesson->get('subject'))) . ' — '
            . ($student ? fullname($student) : get_string('lessonnum', 'local_nit_lessons', $lesson->get('id')));

        $moduleinfo = (object) [
            'modulename' => 'jitsi',
            'course' => $courseid,
            'section' => 0,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber' => '',
            'name' => \core_text::substr($title, 0, 255),
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'lobby_enabled' => 1, // 1:1 lesson: the teacher admits the student.
        ];
        $created = self::create_module_elevated($moduleinfo);
        $cmid = (int) $created->coursemodule;
        $jitsiid = (int) $created->instance;
        $joinurl = self::join_url($cmid);

        $groupid = self::participant_group($courseid, (int) $lesson->get('id'), $teacherid, $studentid);
        self::restrict_module_to_group($courseid, $cmid, $groupid);

        $sessionid = \local_academysessions\session_manager::create_session($courseid, $teacherid, $title, time(),
            [$studentid], $joinurl, (int) $lesson->get('duration'), null, $jitsiid);
        \local_academysessions\session_manager::start_session($sessionid);

        return (object) ['sessionid' => (int) $sessionid, 'cmid' => $cmid, 'join_url' => $joinurl];
    }

    /**
     * Close the session when the lesson ends.
     *
     * @param lesson $lesson
     * @return void
     */
    public function end_for_lesson(lesson $lesson): void {
        if ((int) $lesson->get('sessionid') > 0 && class_exists('\local_academysessions\session_manager')) {
            \local_academysessions\session_manager::end_session((int) $lesson->get('sessionid'));
        }
    }

    /**
     * Where a participant joins: the Jitsi activity page.
     *
     * @param lesson $lesson
     * @param int $viewerid
     * @return array|null
     */
    public function session_payload(lesson $lesson, int $viewerid): ?array {
        $cmid = (int) $lesson->get('cmid');
        if ($cmid <= 0) {
            return null;
        }
        return ['join_url' => self::join_url($cmid)];
    }

    /**
     * The Jitsi activity URL.
     *
     * @param int $cmid
     * @return string
     */
    private static function join_url(int $cmid): string {
        return (new \moodle_url('/mod/jitsi/view.php', ['id' => $cmid]))->out(false);
    }

    /**
     * Enrol a participant in the lessons course (manual enrolment).
     *
     * @param int $courseid
     * @param int $userid
     * @param string $roleshortname
     * @return void
     */
    private static function enrol(int $courseid, int $userid, string $roleshortname): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
        enrol_try_internal_enrol($courseid, $userid, $roleid ?: null);
    }

    /**
     * Create the activity as the admin: teachers are denied manageactivities in this course.
     *
     * @param \stdClass $moduleinfo
     * @return \stdClass
     */
    private static function create_module_elevated(\stdClass $moduleinfo): \stdClass {
        global $USER;
        $realuser = $USER;
        \core\session\manager::set_user(get_admin());
        try {
            return create_module($moduleinfo);
        } finally {
            \core\session\manager::set_user($realuser);
        }
    }

    /**
     * In the lessons course, teachers must not see other teachers' rooms.
     *
     * @param int $courseid
     * @return void
     */
    private static function lock_down_teacher_visibility(int $courseid): void {
        global $DB;
        $context = \context_course::instance($courseid);
        $caps = [
            'moodle/site:accessallgroups',
            'moodle/course:manageactivities',
            'moodle/course:ignoreavailabilityrestrictions',
            'moodle/course:viewhiddenactivities',
        ];
        $changed = false;
        foreach (['editingteacher', 'teacher'] as $shortname) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
            if (!$roleid) {
                continue;
            }
            foreach ($caps as $cap) {
                $existing = $DB->get_field('role_capabilities', 'permission',
                    ['roleid' => $roleid, 'contextid' => $context->id, 'capability' => $cap]);
                if ((int) $existing === CAP_PREVENT) {
                    continue;
                }
                assign_capability($cap, CAP_PREVENT, $roleid, $context->id, true);
                $changed = true;
            }
        }
        if ($changed) {
            $context->mark_dirty();
        }
    }

    /**
     * The lessons course is meant to be hidden (kept out of the catalogue), but Moodle then
     * shuts its enrolled students out of the Jitsi room: let students in this course see it.
     *
     * @param int $courseid
     * @return void
     */
    private static function let_students_into_hidden_course(int $courseid): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$roleid) {
            return;
        }
        $context = \context_course::instance($courseid);
        $existing = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'contextid' => $context->id, 'capability' => 'moodle/course:viewhiddencourses']);
        if ((int) $existing === CAP_ALLOW) {
            return;
        }
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, $context->id, true);
        $context->mark_dirty();
    }

    /**
     * One group per lesson holding its teacher and student.
     *
     * @param int $courseid
     * @param int $lessonid
     * @param int $teacherid
     * @param int $studentid
     * @return int group id
     */
    private static function participant_group(int $courseid, int $lessonid, int $teacherid, int $studentid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $idnumber = 'nit_lesson_' . $lessonid;
        $groupid = (int) $DB->get_field('groups', 'id', ['courseid' => $courseid, 'idnumber' => $idnumber]);
        if (!$groupid) {
            $groupid = (int) groups_create_group((object) [
                'courseid' => $courseid,
                'name' => get_string('lessongroup', 'local_nit_lessons', $lessonid),
                'idnumber' => $idnumber,
                'description' => '',
            ]);
        }
        groups_add_member($groupid, $teacherid);
        groups_add_member($groupid, $studentid);
        return $groupid;
    }

    /**
     * Show the activity only to members of the group.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $groupid
     * @return void
     */
    private static function restrict_module_to_group(int $courseid, int $cmid, int $groupid): void {
        global $DB;
        $availability = json_encode([
            'op' => '&',
            'c' => [['type' => 'group', 'id' => $groupid]],
            'showc' => [false],
        ]);
        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cmid]);
        rebuild_course_cache($courseid, true);
    }
}
