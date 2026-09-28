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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_nit_ai\api;
use local_nit_ai\assistant;
use local_nit_ai\source;

/**
 * Ask the video assistant a question.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ask extends external_api {

    /** @var int Longest question we will accept, in characters. */
    protected const MAX_QUESTION = 1000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the video'),
            'question' => new external_value(PARAM_TEXT, 'The student question'),
            'currenttime' => new external_value(
                PARAM_INT,
                'Playback position in seconds, -1 when the player did not report one',
                VALUE_DEFAULT,
                -1
            ),
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'text' => new external_value(PARAM_TEXT, 'What was said'),
                ]),
                'Earlier turns, held by the browser only',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Answer a question about the video attached to a course module.
     *
     * @param int $cmid
     * @param string $question
     * @param int $currenttime
     * @param array $history
     * @return array
     */
    public static function execute(int $cmid, string $question, int $currenttime = -1, array $history = []): array {
        [
            'cmid' => $cmid,
            'question' => $question,
            'currenttime' => $currenttime,
            'history' => $history,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'question' => $question,
            'currenttime' => $currenttime,
            'history' => $history,
        ]);

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/nit_ai:use', $context);

        $describe = source::describe($cm);

        if (!api::is_available($cm, $context, $describe['ref'])) {
            return self::failure(get_string('err_unavailable', 'local_nit_ai'));
        }

        $question = \core_text::substr(trim($question), 0, self::MAX_QUESTION);
        if ($question === '') {
            return self::failure(get_string('err_emptyquestion', 'local_nit_ai'));
        }

        $record = api::get($cmid);
        $result = assistant::ask(
            $record,
            $context,
            $question,
            max(-1, $currenttime),
            $history
        );

        // Someone who can manage the activity can act on the provider's own
        // message; a student can only be confused by it.
        $error = $result['error'];
        if (!empty($result['detail']) && has_capability('local/nit_ai:manage', $context)) {
            $error .= ' — ' . $result['detail'];
        }

        return [
            'success' => $result['success'],
            'answer'  => $result['answer'],
            'error'   => $error,
        ];
    }

    /**
     * Shape a refusal the same way as an answer, so the UI has one path.
     *
     * @param string $message
     * @return array
     */
    protected static function failure(string $message): array {
        return ['success' => false, 'answer' => '', 'error' => $message];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether an answer was produced'),
            'answer' => new external_value(PARAM_RAW, 'The answer text, with [MM:SS] citations'),
            'error' => new external_value(PARAM_TEXT, 'Why it failed, when it did'),
        ]);
    }
}
