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

use local_nit_assistant\knowledge\help_index;
use local_nit_assistant\tools\context;
use local_nit_assistant\tools\sales\helper;
use local_nit_assistant\tools\tool;

/**
 * Questions about a lesson's content belong to that video's own assistant
 * (local_nit_ai), which answers from the transcript. This finds the user's video
 * lessons that have it, so the reply can link straight to them.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lesson_video_help extends tool {
    /** Lessons returned. */
    private const MAX = 6;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'lesson_video_help';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'For questions about the CONTENT of a lesson (explain this rule, solve this exercise from the video): '
            . 'finds the user\'s video lessons that have a video assistant, which answers from that lesson\'s '
            . 'transcript and jumps to the right minute. Pass words from the lesson or course name. Do not teach the '
            . 'lesson yourself; point them to the lesson link.';
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
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Lesson or course words']],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_nit_ai_transcripts')) {
            return self::ok(['lessons' => [], 'note' => 'This academy has no video assistant.']);
        }
        $courses = enrol_get_all_users_courses($ctx->userid, true, 'id, fullname');
        if (!$courses) {
            return self::ok(['lessons' => []]);
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'local_nit_ai_transcripts',
            "courseid {$in} AND enabled = 1 AND approved = 1",
            $params,
            'courseid, cmid',
            'id, cmid, courseid'
        );
        $words = help_index::words($input['query']);
        $found = [];
        foreach ($rows as $r) {
            $modinfo = get_fast_modinfo($courses[$r->courseid], $ctx->userid);
            $cm = $modinfo->cms[$r->cmid] ?? null;
            if (!$cm || !$cm->uservisible || !$cm->url) {
                continue;
            }
            $name = $cm->get_formatted_name();
            $course = format_string($courses[$r->courseid]->fullname);
            $hay = help_index::normalise($name . ' ' . $course);
            $score = 0;
            foreach ($words as $w) {
                $score += str_contains($hay, $w) ? 1 : 0;
            }
            if ($words && !$score) {
                continue;
            }
            $found[] = ['score' => $score, 'lesson' => $name, 'course' => $course, 'url' => $cm->url->out(false)];
        }
        usort($found, static fn($a, $b) => $b['score'] <=> $a['score']);
        $found = array_map(static fn($f) => array_diff_key($f, ['score' => 1]), array_slice($found, 0, self::MAX));
        if (count($found) === 1) {
            helper::button($ctx, 'action_openlesson', $found[0]['url'], $found[0]['lesson']);
        }
        return self::ok(['lessons' => $found]);
    }
}
