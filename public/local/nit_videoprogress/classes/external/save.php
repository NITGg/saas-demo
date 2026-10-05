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

namespace local_nit_videoprogress\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_nit_videoprogress\progress;

/**
 * The player reports where the student is and which parts they played.
 *
 * @package    local_nit_videoprogress
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Video lesson course module id'),
            'position' => new external_value(PARAM_INT, 'Current playback position, seconds'),
            'duration' => new external_value(PARAM_INT, 'Video length, seconds (0 if unknown)'),
            'slices' => new external_value(PARAM_SEQUENCE, 'Comma-separated slice numbers (0-99) played since the last report', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * @param int $cmid
     * @param int $position
     * @param int $duration
     * @param string $slices
     * @return array
     */
    public static function execute(int $cmid, int $position, int $duration, string $slices = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'position' => $position, 'duration' => $duration, 'slices' => $slices]);

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        if (!in_array($cm->modname, progress::PROVIDERS, true)) {
            throw new \invalid_parameter_exception('Not a video lesson');
        }
        $context = \context_module::instance($cm->id);
        // Checks login, enrolment and that the lesson is available to this user.
        self::validate_context($context);
        require_capability('mod/' . $cm->modname . ':view', $context);

        $list = $params['slices'] === '' ? [] : explode(',', $params['slices']);
        $row = progress::record((int) $USER->id, $cm, $params['position'], $params['duration'], $list);
        return [
            'percent' => (int) $row->percent,
            'position' => (int) $row->position,
            'duration' => (int) $row->duration,
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'percent' => new external_value(PARAM_INT, 'Share of the video watched, 0-100'),
            'position' => new external_value(PARAM_INT, 'Saved position, seconds'),
            'duration' => new external_value(PARAM_INT, 'Known video length, seconds'),
        ]);
    }
}
