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

namespace local_nit_ai;

/**
 * The only place that knows anything about video providers.
 *
 * The assistant needs four things from a player: which video this is, how long
 * it runs, where the viewer is, and the ability to jump. The last two are the
 * JS adapter's job (amd/src/player_*.js); the first two are answered here.
 *
 * Adding Vimeo or a plain uploaded file means one case here and one adapter
 * there — nothing else in the plugin refers to a provider by name.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source {

    /**
     * Identify the video behind a course module.
     *
     * @param object $cm cm_info or a course_modules record
     * @return array ['ref' => string, 'length' => int] — ref is '' when there is
     *               no recognised video, length is 0 when the provider has not
     *               reported a duration yet
     */
    public static function describe(object $cm): array {
        global $DB;

        $modname = $cm->modname ?? '';

        if ($modname === 'vdocipher') {
            $row = $DB->get_record('local_vdocipher_videos', ['cmid' => (int) $cm->id], 'videoid, length');
            if ($row) {
                return ['ref' => (string) $row->videoid, 'length' => (int) $row->length];
            }
            // The mapping row is written on save; fall back to the instance.
            $instance = $DB->get_record('vdocipher', ['id' => (int) $cm->instance], 'videoid');
            return ['ref' => $instance ? (string) $instance->videoid : '', 'length' => 0];
        }

        return ['ref' => '', 'length' => 0];
    }

    /**
     * Which JS adapter drives this module's player.
     *
     * The name is both the registry key in window.NITAI.players and the second
     * half of the file name, js/player_<name>.js.
     *
     * @param object $cm
     * @return string adapter name, or '' when we cannot talk to the player
     */
    public static function adapter(object $cm): string {
        return ($cm->modname ?? '') === 'vdocipher' ? 'vdocipher' : '';
    }
}
