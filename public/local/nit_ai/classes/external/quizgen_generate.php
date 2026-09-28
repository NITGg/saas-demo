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
use local_nit_ai\quizgen\generator;
use local_nit_ai\quizgen\planner;
use local_nit_ai\quizgen\run;

/**
 * Generate one slice of a quiz.
 *
 * One call, one request to the provider, one visible step on the progress bar.
 * A forty minute video is seven or eight of these, and doing them one at a time
 * is not an optimisation — it is the only shape that survives a PHP time limit,
 * shows the teacher that something is happening, and lets a single failed slice
 * be reported as a gap instead of losing the whole run.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizgen_generate extends external_api {

    /** @var int Batch number meaning "go back for whatever the first pass missed". */
    public const BATCH_GAPS = -1;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the video'),
            'runid' => new external_value(PARAM_INT, 'The generation run to add to'),
            'batch' => new external_value(PARAM_INT, 'Which slice to generate, or -1 for the gap-filling pass'),
        ]);
    }

    /**
     * Generate one slice.
     *
     * @param int $cmid
     * @param int $runid
     * @param int $batch
     * @return array
     */
    public static function execute(int $cmid, int $runid, int $batch): array {
        ['cmid' => $cmid, 'runid' => $runid, 'batch' => $batch] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'runid' => $runid, 'batch' => $batch]
        );

        global $USER;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/nit_ai:generatequiz', $context);

        $record = run::get($runid);
        if (!$record || (int) $record->cmid !== $cmid || (int) $record->userid !== (int) $USER->id) {
            return self::failure(get_string('quizgen_err_norun', 'local_nit_ai'));
        }
        if ($record->status !== run::STATUS_DRAFT) {
            return self::failure(get_string('quizgen_err_alreadybuilt', 'local_nit_ai'));
        }

        // Rechecked on every slice, not just at the start: a run can outlive the
        // switch being turned off, or the video being replaced mid-generation.
        $blockers = generator::blockers($cm, $context);
        if ($blockers) {
            return self::failure(reset($blockers));
        }

        $transcript = api::get($cmid);
        $blocks = planner::blocks($transcript);
        if (!$blocks) {
            return self::failure(get_string('check_empty', 'local_nit_ai'));
        }

        $options = run::options($record);
        $held = run::held($record);

        // A reloaded progress page would otherwise buy every slice a second time.
        if ($batch >= 0 && run::is_done($record, $batch)) {
            return self::progress($record, $blocks);
        }

        if (count($held['questions']) >= self::max_questions()) {
            // Already at the site's ceiling. Stopping quietly beats spending
            // another request on questions that would be thrown away.
            return self::progress($record, $blocks, get_string('quizgen_atlimit', 'local_nit_ai'));
        }

        $slice = $batch === self::BATCH_GAPS
            ? self::gap_slice($blocks, $held)
            : self::batch_slice($blocks, $batch);

        if (!$slice) {
            return self::progress($record, $blocks);
        }

        $result = generator::generate($transcript, $context, $slice, $options, $batch === self::BATCH_GAPS);

        if (!$result['success']) {
            // One bad slice is a gap, not a dead run: the coverage report will
            // show exactly which minutes went unasked, and the teacher can decide.
            $error = $result['error'];
            if (!empty($result['detail']) && has_capability('local/nit_ai:manage', $context)) {
                $error .= ' — ' . $result['detail'];
            }
            return self::progress($record, $blocks, $error);
        }

        $items = self::within_limit($result['items'], count($held['questions']));
        $record = run::add($record, $items, $result['skipped'], $batch >= 0 ? $batch : null);

        return self::progress($record, $blocks);
    }

    /**
     * The blocks belonging to one numbered slice.
     *
     * @param array $blocks
     * @param int $batch
     * @return array
     */
    protected static function batch_slice(array $blocks, int $batch): array {
        $batches = planner::batches($blocks);

        return $batches[$batch] ?? [];
    }

    /**
     * The next handful of blocks nothing has been asked about yet.
     *
     * Capped at one batch per call so the gap pass costs what a normal slice
     * costs, and so a transcript the model keeps refusing cannot spin.
     *
     * @param array $blocks
     * @param array $held
     * @return array
     */
    protected static function gap_slice(array $blocks, array $held): array {
        $coverage = planner::coverage($blocks, $held['questions'], $held['skipped']);

        return array_slice(planner::pick($blocks, $coverage['missing']), 0, planner::BATCH_BLOCKS);
    }

    /**
     * Trim a slice's questions to whatever is left under the site's ceiling.
     *
     * @param array $items
     * @param int $alreadyheld
     * @return array
     */
    protected static function within_limit(array $items, int $alreadyheld): array {
        $room = self::max_questions() - $alreadyheld;

        return $room > 0 ? array_slice($items, 0, $room) : [];
    }

    /**
     * The most questions one run may hold.
     *
     * A guard on spending rather than on pedagogy: the number of questions is
     * meant to follow the content, but a two hour transcript should not be able
     * to bill for three hundred of them without anyone deciding to.
     *
     * @return int
     */
    protected static function max_questions(): int {
        return (int) (get_config('local_nit_ai', 'quizgen_maxquestions') ?: 80);
    }

    /**
     * Where the run stands, recomputed from what it now holds.
     *
     * @param \stdClass $record
     * @param array $blocks
     * @param string $warning something worth showing, but not worth stopping for
     * @return array
     */
    protected static function progress(\stdClass $record, array $blocks, string $warning = ''): array {
        $held = run::held($record);
        $coverage = planner::coverage($blocks, $held['questions'], $held['skipped']);

        run::set_coverage($record, $coverage);

        return [
            'success'   => true,
            'runid'     => (int) $record->id,
            'batches'   => count(planner::batches($blocks)),
            'questions' => count($held['questions']),
            'percent'   => (int) $coverage['percent'],
            'gaps'      => count($coverage['missing']),
            'warning'   => $warning,
            'error'     => '',
        ];
    }

    /**
     * Shape a refusal the same way as progress, so the UI has one path.
     *
     * @param string $message
     * @return array
     */
    protected static function failure(string $message): array {
        return [
            'success'   => false,
            'runid'     => 0,
            'batches'   => 0,
            'questions' => 0,
            'percent'   => 0,
            'gaps'      => 0,
            'warning'   => '',
            'error'     => $message,
        ];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the run can continue'),
            'runid' => new external_value(PARAM_INT, 'The run this progress belongs to'),
            'batches' => new external_value(PARAM_INT, 'How many slices the video was cut into'),
            'questions' => new external_value(PARAM_INT, 'Questions held so far'),
            'percent' => new external_value(PARAM_INT, 'Share of the examinable video covered'),
            'gaps' => new external_value(PARAM_INT, 'Blocks still with no question against them'),
            'warning' => new external_value(PARAM_TEXT, 'A slice that failed, when one did'),
            'error' => new external_value(PARAM_TEXT, 'Why the run cannot continue, when it cannot'),
        ]);
    }
}
