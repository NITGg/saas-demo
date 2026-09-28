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

namespace local_parent;

defined('MOODLE_INTERNAL') || die();

/**
 * One child's data for a parent — shared by the web-service functions and the
 * parent dashboard page, so both authorize and read the same way.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class child_report {

    /**
     * Assert the caller is a parent linked to this child and holds the view
     * right in the child's user context. Throws otherwise.
     *
     * @param int $parentid
     * @param int $studentid
     */
    public static function guard(int $parentid, int $studentid): void {
        if (is_siteadmin($parentid)) {
            return;
        }

        if (!link_manager::is_linked($parentid, $studentid)) {
            throw new \required_capability_exception(
                \context_system::instance(), 'local/parent:view', 'nopermissions', '');
        }

        // Parent is verified linked in the database. Ensure role is assigned in child context.
        link_manager::assign_role($parentid, $studentid);

        $context = \context_user::instance($studentid);
        if (!has_capability('local/parent:view', $context, $parentid)) {
            $context->mark_dirty();
            $roleid = link_manager::get_parent_role_id();
            link_manager::ensure_role_capabilities($roleid);
        }
    }

    /**
     * Course marks — the course total for each course the child is enrolled in.
     *
     * @param int $studentid
     * @return array<int, array{courseid:int, coursename:string, grade:string, percentage:?float}>
     */
    public static function grades(int $studentid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $out = [];
        foreach (enrol_get_all_users_courses($studentid, true, 'id, fullname') as $course) {
            $cg = grade_get_course_grades($course->id, $studentid);
            $g = $cg->grades[$studentid] ?? null;
            $out[] = [
                'courseid'   => (int) $course->id,
                'coursename' => format_string($course->fullname),
                'grade'      => ($g && $g->str_grade !== null) ? $g->str_grade : '-',
                'percentage' => ($g && $g->grade !== null && $cg->grade_item->grademax > 0)
                    ? round(($g->grade / $cg->grade_item->grademax) * 100, 1) : null,
            ];
        }
        return $out;
    }

    /**
     * Quiz attempts — score, when taken and how long it took, newest first.
     *
     * @param int $studentid
     * @return array<int, array>
     */
    public static function quizzes(int $studentid): array {
        global $DB;

        $sql = "SELECT qa.id, qa.sumgrades, qa.timestart, qa.timefinish, qa.state,
                       q.name AS quizname, q.sumgrades AS maxgrade,
                       c.fullname AS coursename
                  FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {course} c ON c.id = q.course
                 WHERE qa.userid = :uid AND qa.preview = 0
              ORDER BY qa.timestart DESC";
        $rows = $DB->get_records_sql($sql, ['uid' => $studentid]);

        $out = [];
        foreach ($rows as $r) {
            $finished = ($r->state === 'finished');
            $out[] = [
                'quizname'   => format_string($r->quizname),
                'coursename' => format_string($r->coursename),
                'score'      => ($finished && $r->sumgrades !== null) ? (float) $r->sumgrades : null,
                'maxscore'   => ($r->maxgrade !== null) ? (float) $r->maxgrade : null,
                'state'      => $r->state,
                'timestart'  => (int) $r->timestart,
                'timefinish' => (int) $r->timefinish,
                'duration'   => ($finished && $r->timefinish) ? (int) ($r->timefinish - $r->timestart) : null,
            ];
        }
        return $out;
    }
}
