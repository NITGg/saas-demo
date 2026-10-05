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

namespace local_nit_ai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_nit_ai\api;
use local_nit_ai\source;

/**
 * Whether the video assistant is available on an activity — the app calls this
 * before showing the "Ask AI" button, then uses local_nit_ai_ask to chat.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_status extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the video'),
        ]);
    }

    /**
     * Report whether the assistant can be used on this activity, and why not.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $adapter = source::adapter($cm);
        $describe = source::describe($cm);
        $record = null;

        if ($adapter === '') {
            // Only VdoCipher video lessons are supported today.
            $state = ['available' => false, 'reason' => 'unsupported'];
        } else {
            $state = api::availability($cm, $context, $describe['ref']);
            if ($state['available']) {
                $record = api::get((int) $cm->id);
            }
        }

        return [
            'cmid'          => (int) $cm->id,
            'available'     => $state['available'],
            'reason'        => $state['reason'],
            'message'       => $state['available'] ? '' : get_string('status_' . $state['reason'], 'local_nit_ai'),
            'placement'     => api::PLACEMENT,
            'provider'      => $adapter,
            'hastimestamps' => $record ? (bool) $record->hastimestamps : false,
        ];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'          => new external_value(PARAM_INT, 'Course module id'),
            'available'     => new external_value(PARAM_BOOL, 'Show the AI chat button'),
            'reason'        => new external_value(PARAM_ALPHA, 'Why not, when unavailable: unsupported, nopermission, '
                . 'notentitled, notranscript, disabled, notapproved, stale, placementdisabled, noprovider, '
                . 'disabledincontext ("" when available)'),
            'message'       => new external_value(PARAM_TEXT, 'Readable reason ("" when available)'),
            'placement'     => new external_value(PARAM_COMPONENT, 'The AI placement that serves the assistant'),
            'provider'      => new external_value(PARAM_ALPHANUMEXT, 'Video player the assistant talks to '
                . '(vdocipher), "" when the activity is not a supported video'),
            'hastimestamps' => new external_value(PARAM_BOOL, 'Answers cite [MM:SS] times the player can seek to'),
        ]);
    }
}
