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
 * Data for the public teacher page (local/academy/teacher.php) — the bassthalk
 * "صفحة المدرس": the teacher's name, title, photo and bio, the years they teach
 * and every visible course they teach, drawn with the home page's course card.
 *
 * A teacher is who home_data::teachers() lists: a user (not a site admin) with
 * a teacher or editing-teacher role in a visible course. Anyone else gets null,
 * so the page cannot be used to look up arbitrary accounts.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_page {

    /**
     * The page data for one teacher, or null when the user is not a teacher.
     *
     * Courses are newest first. `years` are the distinct years (course
     * categories) of those courses, in the order they first appear, each with
     * the course count and the divisions its courses are for.
     *
     * @param int $userid
     * @return array|null {id, name, title, photo, bio, years:[{id, name, short, label, courses}],
     *                    courses:[card + yearid, division, enrolled], counts:{courses, years, students}}
     */
    public static function get(int $userid): ?array {
        global $DB, $PAGE, $USER;

        if ($userid <= 0 || is_siteadmin($userid)) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        $roleids = home_data::teacher_roles();
        if (!$user || isguestuser($user) || !$roleids) {
            return null;
        }

        [$rsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params += ['ctxlevel' => CONTEXT_COURSE, 'siteid' => SITEID, 'userid' => $userid];
        $courses = $DB->get_records_sql(
            "SELECT DISTINCT c.*
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
               JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1 AND c.id <> :siteid
              WHERE ra.userid = :userid AND ra.roleid $rsql
           ORDER BY c.timecreated DESC, c.id DESC",
            $params
        );
        if (!$courses) {
            return null;
        }

        $years = home_data::years_by_category();
        $answers = home_data::course_answers(array_keys($courses));
        $viewer = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;

        $cards = [];
        $yearlist = [];
        foreach ($courses as $course) {
            $catid = (int) $course->category;
            $year = $years[$catid] ?? null;
            // The stored option text may carry {mlang} markup — shown in the page language.
            $division = format_string($answers[(int) $course->id]['division'], true, ['escape' => false]);
            $coursecontext = \context_course::instance($course->id);
            $card = home_data::course_card($course, $year ? $year['leaf'] : '');
            if (strpos($card['image'], '/course/generated/') !== false) {
                // Moodle's generated pattern is served behind the log-in, so a visitor gets a
                // broken picture; the template draws its own placeholder instead.
                $card['image'] = '';
            }
            $card['yearid'] = $year ? $catid : 0;
            $card['division'] = $division;
            $card['enrolled'] = $viewer && is_enrolled($coursecontext, $viewer, '', true);
            $cards[] = $card;

            if (!$year) {
                continue;
            }
            if (!isset($yearlist[$catid])) {
                $yearlist[$catid] = ['id' => $catid, 'name' => $year['leaf'], 'short' => home_data::short_year($year['leaf']),
                    'courses' => 0, 'divisions' => []];
            }
            $yearlist[$catid]['courses']++;
            if ($division !== '' && !in_array($division, $yearlist[$catid]['divisions'], true)) {
                $yearlist[$catid]['divisions'][] = $division;
            }
        }
        foreach ($yearlist as &$entry) {
            $entry['label'] = $entry['divisions'] ? $entry['name'] . ' - ' . implode(' / ', $entry['divisions']) : $entry['name'];
        }
        unset($entry);

        $picture = new \user_picture($user);
        $picture->size = 512;
        $usercontext = \context_user::instance($userid);
        $bio = trim(html_to_text(format_text((string) $user->description, (int) $user->descriptionformat,
            ['context' => $usercontext]), 0, false));
        $values = user_fields::values($userid);

        return [
            'id' => (int) $user->id,
            'name' => fullname($user),
            'title' => trim(format_string((string) ($values['teachertitle'] ?? ''), true, ['escape' => false])), // May carry {mlang}.
            'photo' => $picture->get_url($PAGE)->out(false),
            'bio' => $bio,
            'years' => array_values($yearlist),
            'courses' => $cards,
            'counts' => [
                'courses' => count($cards),
                'years' => count($yearlist),
                'students' => self::count_students(array_keys($courses)),
            ],
        ] + self::reviews((int) $user->id, $viewer);
    }

    /**
     * The teacher's learner rating (local_nit_reviews): the approved average, the
     * latest approved comments, and the "rate" link when the viewer may rate them.
     * Empty values when the reviews plugin is absent.
     *
     * @param int $teacherid
     * @param int $viewer 0 = visitor
     * @return array {rating:{has, avg, count}, canrate, rateurl, hasreviews, reviews:[{name, picture,
     *     starson, starsoff, date, text, course}]}
     */
    private static function reviews(int $teacherid, int $viewer): array {
        $out = ['rating' => ['has' => false, 'avg' => '', 'count' => 0], 'canrate' => false, 'rateurl' => '',
            'hasreviews' => false, 'reviews' => []];
        if (!class_exists('\local_nit_reviews\api')) {
            return $out;
        }
        $api = '\local_nit_reviews\api';
        $agg = $api::get_teacher_aggregate($teacherid);
        $out['rating'] = ['has' => $agg->count > 0, 'avg' => format_float($agg->avg, 1), 'count' => (int) $agg->count];
        if ($viewer && $api::rateable_courses_for_teacher($teacherid, $viewer)) {
            $out['canrate'] = true;
            $out['rateurl'] = (new \moodle_url('/local/nit_reviews/rate.php', ['teacherid' => $teacherid]))->out(false);
        }
        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        foreach ($api::get_teacher_reviews($teacherid, 0, 6)['reviews'] as $r) {
            if ($r['review'] === '') {
                continue;
            }
            $out['reviews'][] = [
                'name' => $r['fullname'],
                'picture' => $r['pictureurl'],
                'starson' => str_repeat('★', $r['rating']),
                'starsoff' => str_repeat('★', 5 - $r['rating']),
                'rating' => $r['rating'],
                'date' => userdate($r['timemodified'], $dateformat),
                'text' => $r['review'],
                'course' => $r['coursename'],
            ];
        }
        $out['hasreviews'] = !empty($out['reviews']);
        return $out;
    }

    /**
     * Distinct users holding a student role in any of these courses.
     *
     * @param int[] $courseids
     * @return int
     */
    private static function count_students(array $courseids): int {
        global $DB;
        $roleids = array_keys(get_archetype_roles('student'));
        if (!$courseids || !$roleids) {
            return 0;
        }
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        [$rsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ra.userid)
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
              WHERE ctx.instanceid $csql AND ra.roleid $rsql",
            $cparams + $rparams + ['ctxlevel' => CONTEXT_COURSE]
        );
    }
}
