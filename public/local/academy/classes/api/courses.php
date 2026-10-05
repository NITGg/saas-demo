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

namespace local_academy\api;

defined('MOODLE_INTERNAL') || die();

use local_academy\player;

/**
 * Course / lesson data for the mobile app — the same rules the web player applies
 * (lesson order lock, lessons sold one by one, watched %, completion).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses {

    /**
     * Load a course the current user may open (enrolled, or staff that can view it).
     *
     * @param int $courseid
     * @return \stdClass
     * @throws \moodle_exception
     */
    public static function require_course(int $courseid): \stdClass {
        global $DB, $USER;
        if ($courseid <= SITEID || !($course = $DB->get_record('course', ['id' => $courseid]))) {
            throw new \moodle_exception('err_coursenotfound', 'local_academy');
        }
        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $USER, '', true) && !has_capability('moodle/course:view', $context)) {
            throw new \moodle_exception('err_notenrolled', 'local_academy');
        }
        return $course;
    }

    /**
     * The course's lessons in order, grouped by section, with the learner's state.
     *
     * @param int $courseid
     * @return array
     */
    public static function lessons(int $courseid): array {
        $course = self::require_course($courseid);
        $walk = player::walk($course);
        $resume = player::resume_cm($course);

        $sections = [];
        $total = 0;
        $done = 0;
        foreach ($walk['sections'] as $section) {
            $items = [];
            foreach ($section['items'] as $l) {
                $items[] = self::lesson_entry($l);
                if ($l['tracked']) {
                    $total++;
                    $done += $l['done'] ? 1 : 0;
                }
            }
            $sections[] = ['name' => $section['name'], 'lessons' => $items];
        }
        return [
            'courseid'     => (int) $course->id,
            'fullname'     => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'lockorder'    => player::lock_order(),
            'markcomplete' => player::mark_complete_enabled(),
            'resume_cmid'  => $resume ? (int) $resume->id : 0,
            'progress'     => [
                'tracked'   => $total,
                'completed' => $done,
                'percent'   => $total ? (int) round($done * 100 / $total) : 0,
            ],
            'sections'     => $sections,
        ];
    }

    /**
     * One lesson as the app sees it.
     *
     * @param array $l an entry of player::walk()
     * @return array
     */
    protected static function lesson_entry(array $l): array {
        /** @var \cm_info $cm */
        $cm = $l['cm'];
        return [
            'cmid'      => $l['cmid'],
            'instance'  => (int) $cm->instance,
            'name'      => $l['name'],
            'modname'   => $l['type'],
            'typename'  => $l['typename'],
            'video'     => $l['video'],
            'tracked'   => $l['tracked'],
            'manual'    => $l['manual'],
            'completed' => $l['done'],
            'locked'    => $l['locked'],
            'forsale'   => (bool) $l['forsale'],
            'watched_percent' => $l['watched'] === null ? null : (int) $l['watched'],
            'weburl'    => $cm->url ? $cm->url->out(false) : '',
        ];
    }

    /**
     * Whether the current user can open a lesson now; fails with a clear code when not.
     *
     * @param int $cmid
     * @return array{0:\stdClass,1:\cm_info} [course, cm]
     * @throws \moodle_exception
     */
    public static function require_lesson_open(int $cmid): array {
        global $DB;
        $cmrec = $DB->get_record('course_modules', ['id' => $cmid], 'id, course');
        if (!$cmrec) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }
        $course = self::require_course((int) $cmrec->course);
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        if (!$cm->uservisible) {
            throw new \moodle_exception('err_nopermission', 'local_academy');
        }
        foreach (player::walk($course)['lessons'] as $l) {
            if ($l['cmid'] !== $cmid) {
                continue;
            }
            if ($l['locked']) {
                throw new \moodle_exception('err_lessonlocked', 'local_academy');
            }
            if ($l['forsale']) {
                throw new \moodle_exception('err_lessonforsale', 'local_academy');
            }
        }
        return [$course, $cm];
    }

    /**
     * Record that the learner opened a lesson: the module's "viewed" event (for our
     * video modules) and view-based completion — what the web view.php does.
     * Other module types also have Moodle's own mod_<x>_view_<x> web services.
     *
     * @param int $cmid
     * @return array
     */
    public static function log_view(int $cmid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        [$course, $cm] = self::require_lesson_open($cmid);
        $context = \context_module::instance($cm->id);

        $eventclass = '\\mod_' . $cm->modname . '\\event\\course_module_viewed';
        if (in_array($cm->modname, player::VIDEO_MODS, true) && class_exists($eventclass)) {
            require_capability('mod/' . $cm->modname . ':view', $context);
            $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
            $event = $eventclass::create(['objectid' => $instance->id, 'context' => $context]);
            $event->add_record_snapshot('course_modules', $cm->get_course_module_record());
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot($cm->modname, $instance);
            $event->trigger();
        }
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);
        $state = $completion->is_enabled($cm) ? (int) $completion->get_data($cm)->completionstate : null;
        return [
            'cmid'      => (int) $cm->id,
            'viewed'    => true,
            'completed' => in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
        ];
    }

    /**
     * The certificates (mod_customcert) in the user's courses, with whether each
     * has been issued and a token download URL for the PDF.
     *
     * @param int $userid
     * @param string $token
     * @return array
     */
    public static function my_certificates(int $userid, string $token): array {
        global $DB, $CFG;
        if (!$DB->get_manager()->table_exists('customcert')) {
            return [];
        }
        $courses = enrol_get_users_courses($userid, true, 'id, fullname');
        $out = [];
        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course, $userid);
            foreach ($modinfo->get_instances_of('customcert') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $issue = $DB->get_record_sql(
                    "SELECT i.id, i.code, i.timecreated
                       FROM {customcert_issues} i
                      WHERE i.customcertid = :cid AND i.userid = :uid",
                    ['cid' => $cm->instance, 'uid' => $userid], IGNORE_MULTIPLE);
                $out[] = [
                    'cmid'        => (int) $cm->id,
                    'name'        => format_string($cm->name),
                    'courseid'    => (int) $course->id,
                    'coursename'  => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                    'issued'      => (bool) $issue,
                    'code'        => $issue ? (string) $issue->code : '',
                    'timeissued'  => $issue ? (int) $issue->timecreated : 0,
                    'downloadurl' => (new \moodle_url('/local/academy/certificate.php',
                        ['cmid' => $cm->id, 'token' => $token]))->out(false),
                ];
            }
        }
        return $out;
    }
}
