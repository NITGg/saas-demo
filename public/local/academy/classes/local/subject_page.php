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
 * Data for the public subject page (local/academy/subject.php) — the bassthalk
 * "صفحة المادة" (bassthalk.com/subject/<id>): one subject of one year, its
 * teachers and every visible course of it, drawn with the home course card.
 *
 * A subject = a course category (the year) + a value of the course field
 * "Subject" (course_fields::SUBJECT); its courses are the visible courses of
 * that category with that subject. No such course → no page.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subject_page {

    /**
     * The page address of a subject.
     *
     * @param int $yearid course category id
     * @param int $subject 1-based position in the Subject list
     * @return \moodle_url
     */
    public static function url(int $yearid, int $subject): \moodle_url {
        return new \moodle_url('/local/academy/subject.php', ['year' => $yearid, 'subject' => $subject]);
    }

    /**
     * The page data, or null when the subject has no visible course in that year.
     *
     * @param int $yearid course category id
     * @param int $subject 1-based position in the Subject list
     * @return array|null {name, year, teachers:[{id, name, photo, title, url}],
     *                    courses:[card + enrolled], counts:{teachers, courses}}
     */
    public static function get(int $yearid, int $subject): ?array {
        global $DB, $PAGE;

        $options = course_fields::subject_options();
        $years = home_data::years_by_category();
        if (!isset($options[$subject - 1], $years[$yearid])) {
            return null;
        }
        $incategory = $DB->get_records_select('course', 'category = :cat AND visible = 1 AND id <> :siteid',
            ['cat' => $yearid, 'siteid' => SITEID], 'timecreated DESC, id DESC');
        $subjectof = course_fields::subjects_of(array_keys($incategory));
        $courses = array_filter($incategory, static fn($c): bool => ($subjectof[(int) $c->id] ?? 0) === $subject);
        if (!$courses) {
            return null;
        }

        $cards = [];
        foreach ($courses as $course) {
            $card = home_data::course_card($course, $years[$yearid]['leaf']);
            if (strpos($card['image'], '/course/generated/') !== false) {
                // Moodle's generated pattern is served behind the log-in, so a visitor gets a
                // broken picture; the template draws its own placeholder instead.
                $card['image'] = '';
            }
            $cards[] = $card;
        }

        $teachers = [];
        $userids = home_data::teacher_ids(array_keys($courses));
        if ($userids) {
            $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
            [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $users = $DB->get_records_sql("SELECT $fields FROM {user} u WHERE u.id $usql", $params);
            foreach ($userids as $userid) {
                $user = $users[$userid];
                $picture = new \user_picture($user);
                $picture->size = 256;
                $values = user_fields::values($userid);
                $teachers[] = [
                    'id' => $userid,
                    'name' => fullname($user),
                    'photo' => $picture->get_url($PAGE)->out(false),
                    'title' => format_string($values['teachertitle'] ?? '', true, ['escape' => false]), // May carry {mlang}.
                    'url' => (new \moodle_url('/local/academy/teacher.php', ['id' => $userid]))->out(false),
                ];
            }
        }

        return [
            'name' => format_string($options[$subject - 1], true, ['escape' => false]),
            'year' => $years[$yearid]['leaf'],
            'teachers' => $teachers,
            'courses' => $cards,
            'counts' => ['teachers' => count($teachers), 'courses' => count($cards)],
        ];
    }
}
