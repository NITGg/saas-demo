<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\tools\student;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * The signed-in student's own courses: completion, how much of the videos they
 * watched, the lesson they stopped at, and their certificates.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class my_courses extends tool {
    /** Courses returned. */
    private const MAX = 12;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'my_courses';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The user\'s own courses: completion %, watched-video %, the last lesson they watched with its link, '
            . 'and their certificates (issued or not yet). Use it for progress, "where did I stop", "my courses", '
            . 'and certificate questions.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['student'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        $courses = array_slice(
            enrol_get_all_users_courses($ctx->userid, true, 'id, fullname, enablecompletion'),
            0,
            self::MAX,
            true
        );
        $videos = [];
        if ($courses && class_exists('\local_nit_videoprogress\progress')) {
            foreach (\local_nit_videoprogress\progress::overview($ctx->userid, array_keys($courses)) as $v) {
                $videos[(int) $v['courseid']] = $v;
            }
        }
        $out = [];
        foreach ($courses as $c) {
            $item = [
                'id' => (int) $c->id,
                'name' => format_string($c->fullname, true, ['context' => \context_course::instance($c->id)]),
                'completion_percent' => null,
                'url' => helper::url('/local/academy/course.php', ['id' => $c->id]),
            ];
            $pct = \core_completion\progress::get_course_progress_percentage($c, $ctx->userid);
            if ($pct !== null) {
                $item['completion_percent'] = (int) round($pct);
            }
            if (isset($videos[$c->id])) {
                $v = $videos[$c->id];
                $item['videos_watched_percent'] = (int) round((float) $v['percent']);
                $last = null;
                foreach ($v['lessons'] as $l) {
                    if (!empty($l['lastwatched']) && (!$last || $l['lastwatched'] > $last['lastwatched'])) {
                        $last = $l;
                    }
                }
                if ($last) {
                    $item['last_watched'] = ['lesson' => (string) $last['name'], 'url' => (string) $last['url']];
                }
            }
            $out[] = $item;
        }
        $certs = [];
        if (class_exists('\local_academy\api\courses')) {
            foreach (\local_academy\api\courses::my_certificates($ctx->userid, '') as $cert) {
                $certs[] = [
                    'name' => (string) $cert['name'],
                    'course' => (string) $cert['coursename'],
                    'issued' => (bool) $cert['issued'],
                    'url' => helper::url('/mod/customcert/view.php', ['id' => $cert['cmid']]),
                ];
            }
        }
        helper::button($ctx, 'action_mycourses', helper::url('/my/courses.php'));
        return self::ok(['courses' => $out, 'certificates' => $certs]);
    }
}
