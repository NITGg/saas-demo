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

namespace local_nit_ai\quizgen;

/**
 * One pass of the generator, from the first batch to the finished quiz.
 *
 * The draft questions live here rather than in the browser for a plain reason:
 * they cost money to produce. A reload, a closed laptop or a slow provider must
 * not throw away forty questions and ask the site to pay for them again.
 *
 * The row outlives the review, too. Afterwards it is the only record of which
 * video a quiz was written from, which is what lets us say "this quiz describes
 * a video that has since been replaced".
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run {

    /** @var string Table holding generation runs. */
    public const TABLE = 'local_nit_ai_quizgen';

    /** @var string Questions generated, teacher still reviewing. */
    public const STATUS_DRAFT = 'draft';

    /** @var string The quiz exists. */
    public const STATUS_CREATED = 'created';

    /**
     * Begin a run, replacing whatever draft this user had for this video.
     *
     * One draft per person per video: a second attempt means the first was not
     * wanted, and keeping both only raises the question of which one the Create
     * button would use.
     *
     * @param int $cmid video course module
     * @param int $courseid
     * @param string $sourceref provider video id at generation time
     * @param array $options the choices made in the wizard
     * @return \stdClass the new run
     */
    public static function start(int $cmid, int $courseid, string $sourceref, array $options): \stdClass {
        global $DB, $USER;

        $DB->delete_records(self::TABLE, [
            'cmid'   => $cmid,
            'userid' => (int) $USER->id,
            'status' => self::STATUS_DRAFT,
        ]);

        $now = time();
        $record = (object) [
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'userid'       => (int) $USER->id,
            'sourceref'    => $sourceref,
            'status'       => self::STATUS_DRAFT,
            'options'      => json_encode($options, JSON_UNESCAPED_UNICODE),
            'items'        => json_encode(['questions' => [], 'skipped' => [], 'done' => []], JSON_UNESCAPED_UNICODE),
            'coverage'     => null,
            'quizcmid'     => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Fetch a run.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * The draft this user is currently reviewing for a video, if any.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function draft_for(int $cmid): ?\stdClass {
        global $DB, $USER;

        $records = $DB->get_records(self::TABLE, [
            'cmid'   => $cmid,
            'userid' => (int) $USER->id,
            'status' => self::STATUS_DRAFT,
        ], 'timecreated DESC', '*', 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * The most recent quiz generated from a video, whoever generated it.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function last_created(int $cmid): ?\stdClass {
        global $DB;

        $records = $DB->get_records(self::TABLE, [
            'cmid'   => $cmid,
            'status' => self::STATUS_CREATED,
        ], 'timemodified DESC', '*', 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Add what one batch produced.
     *
     * Two things are guarded here, and both come from the same cause — a browser
     * that reloads or retries. The batch number is remembered so the same slice
     * is never paid for twice, and a question whose wording is already held is
     * dropped, which also catches the honest overlap between a slice and the gap
     * pass that revisits it.
     *
     * @param \stdClass $record
     * @param array $items questions
     * @param array $skipped blocks with nothing to examine
     * @param int|null $batch the slice these came from, when it was a numbered one
     * @return \stdClass the updated run
     */
    public static function add(\stdClass $record, array $items, array $skipped, ?int $batch = null): \stdClass {
        $held = self::held($record);

        $seen = [];
        foreach ($held['questions'] as $question) {
            $seen[self::fingerprint((string) ($question['stem'] ?? ''))] = true;
        }

        foreach ($items as $item) {
            $print = self::fingerprint((string) ($item['stem'] ?? ''));
            if (isset($seen[$print])) {
                continue;
            }
            $seen[$print] = true;
            $held['questions'][] = $item;
        }

        $held['skipped'] = array_merge($held['skipped'], $skipped);

        if ($batch !== null && !in_array($batch, $held['done'], true)) {
            $held['done'][] = $batch;
        }

        $record->items = json_encode($held, JSON_UNESCAPED_UNICODE);

        return self::save($record);
    }

    /**
     * Has this slice already been generated?
     *
     * @param \stdClass $record
     * @param int $batch
     * @return bool
     */
    public static function is_done(\stdClass $record, int $batch): bool {
        return in_array($batch, self::held($record)['done'], true);
    }

    /**
     * Two questions asking the same thing in the same words are one question.
     *
     * @param string $stem
     * @return string
     */
    protected static function fingerprint(string $stem): string {
        return \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $stem)));
    }

    /**
     * Drop the questions a reviewer did not want.
     *
     * Positions rather than ids: the draft is a list the reviewer is looking at,
     * and the list is what the browser sends back.
     *
     * @param \stdClass $record
     * @param array $keep positions to keep, 0 based
     * @return \stdClass
     */
    public static function keep_only(\stdClass $record, array $keep): \stdClass {
        $held = self::held($record);
        $wanted = array_flip(array_map('intval', $keep));

        $held['questions'] = array_values(array_filter(
            $held['questions'],
            static fn($item, $index) => isset($wanted[$index]),
            ARRAY_FILTER_USE_BOTH
        ));

        $record->items = json_encode($held, JSON_UNESCAPED_UNICODE);

        return self::save($record);
    }

    /**
     * Store a fresh coverage report.
     *
     * @param \stdClass $record
     * @param array $coverage
     * @return \stdClass
     */
    public static function set_coverage(\stdClass $record, array $coverage): \stdClass {
        $record->coverage = json_encode($coverage, JSON_UNESCAPED_UNICODE);

        return self::save($record);
    }

    /**
     * Record that this run produced a quiz.
     *
     * @param \stdClass $record
     * @param int $quizcmid
     * @return \stdClass
     */
    public static function mark_created(\stdClass $record, int $quizcmid): \stdClass {
        $record->status = self::STATUS_CREATED;
        $record->quizcmid = $quizcmid;

        return self::save($record);
    }

    /**
     * The questions and skipped blocks a run is holding, and the slices already
     * paid for.
     *
     * @param \stdClass $record
     * @return array ['questions' => array, 'skipped' => array, 'done' => int[]]
     */
    public static function held(\stdClass $record): array {
        $held = json_decode((string) $record->items, true);

        return [
            'questions' => is_array($held['questions'] ?? null) ? $held['questions'] : [],
            'skipped'   => is_array($held['skipped'] ?? null) ? $held['skipped'] : [],
            'done'      => is_array($held['done'] ?? null) ? array_map('intval', $held['done']) : [],
        ];
    }

    /**
     * The questions a run is holding.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function questions(\stdClass $record): array {
        return self::held($record)['questions'];
    }

    /**
     * The wizard choices this run was started with.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function options(\stdClass $record): array {
        $options = json_decode((string) $record->options, true);

        return is_array($options) ? $options : [];
    }

    /**
     * The stored coverage report.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function coverage(\stdClass $record): array {
        $coverage = json_decode((string) $record->coverage, true);

        return is_array($coverage) ? $coverage : [];
    }

    /**
     * Write a run back, keeping timemodified honest.
     *
     * @param \stdClass $record
     * @return \stdClass
     */
    protected static function save(\stdClass $record): \stdClass {
        global $DB;

        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Forget every run for a course module.
     *
     * @param int $cmid
     * @return void
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
