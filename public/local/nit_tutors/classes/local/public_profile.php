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

namespace local_nit_tutors\local;

use local_nit_lessons\service\teacher_service;

/**
 * What the public teacher page (/local/academy/teacher.php) adds for a tutor: the approved intro
 * video, qualifications, the Super Tutor badge, the price of a lesson, subjects, weekly hours,
 * the public application answers and the "Book a lesson" link.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class public_profile {

    /**
     * Can the teacher page be shown for this user even without a course (an approved tutor)?
     *
     * @param int $userid
     * @return bool
     */
    public static function has_page(int $userid): bool {
        return application::is_approved($userid);
    }

    /**
     * The extra template context of the teacher page.
     *
     * @param int $userid
     * @param int $viewerid 0 = visitor
     * @return array
     */
    public static function extras(int $userid, int $viewerid = 0): array {
        $service = new teacher_service();
        $profile = $service->profile($userid);
        $approved = application::is_approved($userid) || !application::approval_required();
        $listed = $approved && $service->bookable($userid);
        $video = video::player($userid, true);
        $quals = qualifications::public_list($userid);
        $answers = fields::public_answers($userid);
        $hours = $listed ? self::hours($service, $userid, $viewerid) : [];

        return [
            'tutor' => [
                'listed' => $listed,
                'badge' => $approved && badge::has($userid),
                'headline' => format_string($profile['headline']),
                'price' => $profile['lesson_flex'],
                'subjects' => $listed ? array_map(fn($s) => ['name' => format_string($s)], $profile['subjects']) : [],
                'hasvideo' => $video !== null,
                'video' => $video,
                'hasquals' => !empty($quals),
                'quals' => $quals,
                'hasanswers' => !empty($answers),
                'answers' => $answers,
                'hashours' => !empty($hours),
                'hours' => $hours,
                'timezone' => \core_date::get_localised_timezone(
                    teacher_service::timezone($viewerid ?: $userid)->getName()),
                'lessons' => search::lesson_counts([$userid])[$userid] ?? 0,
                'bookurl' => $listed && $viewerid !== $userid ? search::book_url($userid)->out(false) : '',
            ],
        ];
    }

    /**
     * Weekly hours in the viewer's timezone, one row per weekday that has hours.
     *
     * @param teacher_service $service
     * @param int $userid
     * @param int $viewerid
     * @return array [{day, ranges}]
     */
    private static function hours(teacher_service $service, int $userid, int $viewerid): array {
        $tz = $viewerid ? teacher_service::timezone($viewerid) : \core_date::get_server_timezone_object();
        $byday = [];
        foreach (search::weekly_intervals($service, $userid, $tz) as [$day, $from, $to]) {
            $byday[$day][] = sprintf('%02d:%02d – %02d:%02d', intdiv($from, 60), $from % 60, intdiv($to, 60) % 24, $to % 60);
        }
        ksort($byday);
        $out = [];
        // Saturday first, as an Arabic week reads.
        foreach ([6, 0, 1, 2, 3, 4, 5] as $day) {
            if (!empty($byday[$day])) {
                $out[] = ['day' => get_string('day' . $day, 'local_nit_lessons'), 'ranges' => implode('، ', $byday[$day])];
            }
        }
        return $out;
    }
}
