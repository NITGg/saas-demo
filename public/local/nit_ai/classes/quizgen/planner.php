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

use local_nit_ai\api;
use local_nit_ai\helper;

/**
 * Turns a transcript into addressable pieces, and answers the one question the
 * whole feature is judged on: does the quiz reach every part of the video?
 *
 * "Ask about all of it" cannot be delegated to the prompt. A model handed forty
 * minutes of speech and told to write questions reliably writes them about the
 * first ten minutes and the last five. So the transcript is cut into numbered
 * blocks, questions are generated a few blocks at a time, and every question has
 * to name the block it came from. Coverage is then arithmetic rather than a
 * wish: a block with no question against it is a hole we can see and fill.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner {

    /** @var int Target length of one block, in seconds. */
    public const BLOCK_SECONDS = 120;

    /** @var int Target length of one block when the transcript has no timestamps, in characters. */
    protected const BLOCK_CHARS = 1400;

    /** @var int Blocks handed to the model in one request. */
    public const BATCH_BLOCKS = 6;

    /**
     * Cut a transcript into numbered blocks.
     *
     * A block is the unit of coverage, of citation and of blame: small enough
     * that "this block is covered" means something, large enough to hold a whole
     * idea rather than half a sentence.
     *
     * @param \stdClass $record transcript row
     * @return array list of ['id' => 'B1', 'start' => int, 'end' => int, 'text' => string]
     *               where start and end are -1 when the transcript has no timestamps
     */
    public static function blocks(\stdClass $record): array {
        $cues = api::cues($record);

        return $cues ? self::timed_blocks($cues) : self::untimed_blocks((string) $record->plaintext);
    }

    /**
     * Blocks from timestamped cues.
     *
     * @param array $cues list of {s,e,t}
     * @return array
     */
    protected static function timed_blocks(array $cues): array {
        $blocks = [];
        $start = null;
        $end = 0;
        $buffer = [];

        foreach ($cues as $cue) {
            // Close before taking a cue that falls outside the window, never
            // after: a cue belonging to the next block must not be filed under
            // this block's time, or a question cites the wrong moment.
            if ($start !== null && (int) $cue['s'] - $start >= self::BLOCK_SECONDS) {
                $blocks[] = self::block(count($blocks) + 1, $start, $end, $buffer);
                $start = null;
                $buffer = [];
            }
            if ($start === null) {
                $start = (int) $cue['s'];
            }
            $end = max($end, (int) ($cue['e'] ?? $cue['s']));
            $buffer[] = $cue['t'];
        }

        if ($buffer) {
            $blocks[] = self::block(count($blocks) + 1, (int) $start, $end, $buffer);
        }

        return $blocks;
    }

    /**
     * Blocks from flat text, split on sentence ends so a block never starts
     * mid-thought.
     *
     * @param string $plaintext
     * @return array
     */
    protected static function untimed_blocks(string $plaintext): array {
        $plaintext = trim($plaintext);
        if ($plaintext === '') {
            return [];
        }

        // The Arabic question mark and full stop are in the class too; a
        // transcript here is as likely to be Arabic as English.
        $sentences = preg_split('/(?<=[.!?\x{061F}\x{06D4}])\s+/u', $plaintext, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = $sentences ?: [$plaintext];

        $blocks = [];
        $buffer = [];
        $length = 0;

        foreach ($sentences as $sentence) {
            if ($length > 0 && $length + \core_text::strlen($sentence) > self::BLOCK_CHARS) {
                $blocks[] = self::block(count($blocks) + 1, -1, -1, $buffer);
                $buffer = [];
                $length = 0;
            }
            $buffer[] = $sentence;
            $length += \core_text::strlen($sentence);
        }

        if ($buffer) {
            $blocks[] = self::block(count($blocks) + 1, -1, -1, $buffer);
        }

        return $blocks;
    }

    /**
     * Shape one block.
     *
     * @param int $number 1-based
     * @param int $start seconds, -1 when untimed
     * @param int $end seconds, -1 when untimed
     * @param array $lines
     * @return array
     */
    protected static function block(int $number, int $start, int $end, array $lines): array {
        return [
            'id'    => 'B' . $number,
            'start' => $start,
            'end'   => $end,
            'text'  => trim(implode(' ', $lines)),
        ];
    }

    /**
     * Group blocks into the slices sent to the model, one request each.
     *
     * @param array $blocks
     * @param int $perbatch
     * @return array list of block lists
     */
    public static function batches(array $blocks, int $perbatch = self::BATCH_BLOCKS): array {
        return $blocks ? array_chunk($blocks, max(1, $perbatch)) : [];
    }

    /**
     * Render blocks for the prompt, each under its own labelled heading.
     *
     * @param array $blocks
     * @return string
     */
    public static function render(array $blocks): string {
        $out = [];
        foreach ($blocks as $block) {
            $out[] = '[' . $block['id'] . ' | ' . self::label($block) . ']' . "\n" . $block['text'];
        }
        return implode("\n\n", $out);
    }

    /**
     * A block's time range, human readable, or its ordinal when the transcript
     * carries no timestamps.
     *
     * @param array $block
     * @return string
     */
    public static function label(array $block): string {
        if ($block['start'] < 0) {
            return get_string('quizgen_part', 'local_nit_ai', ltrim($block['id'], 'B'));
        }
        return helper::timecode((int) $block['start']) . ' - ' . helper::timecode((int) $block['end']);
    }

    /**
     * Which blocks the questions reach, and which they miss.
     *
     * A block can be accounted for two ways: a question was written about it, or
     * it was examined and found to hold nothing examinable — a greeting, a pause,
     * a "we will come back to this". The two are counted apart on purpose. The
     * first is coverage; the second is a claim the teacher may want to check,
     * because "there was nothing to ask here" is exactly what a lazy pass would
     * also say.
     *
     * @param array $blocks every block in the video
     * @param array $items generated questions, each with a 'block' key
     * @param array $skipped blocks declared empty of teachable content
     * @return array ['total','covered','skipped','percent','missing' => [blockid],
     *                'gaps' => [['label','from','to']], 'skippedparts' => [['label','reason']]]
     */
    public static function coverage(array $blocks, array $items, array $skipped = []): array {
        $hits = [];
        foreach ($items as $item) {
            $id = (string) ($item['block'] ?? '');
            if ($id !== '') {
                $hits[$id] = true;
            }
        }

        $excused = [];
        foreach ($skipped as $entry) {
            $id = (string) ($entry['block'] ?? '');
            // A block with a question against it is covered, whatever else was
            // said about it: a later pass finding content beats an earlier pass
            // not finding any.
            if ($id !== '' && empty($hits[$id])) {
                $excused[$id] = (string) ($entry['reason'] ?? '');
            }
        }

        $missing = [];
        $skippedparts = [];

        foreach ($blocks as $block) {
            $id = $block['id'];
            if (!empty($hits[$id])) {
                continue;
            }
            if (isset($excused[$id])) {
                $skippedparts[] = ['label' => self::label($block), 'reason' => $excused[$id]];
                continue;
            }
            $missing[] = $id;
        }

        $total = count($blocks);
        $covered = $total - count($missing) - count($skippedparts);
        $examinable = $total - count($skippedparts);

        return [
            'total'        => $total,
            'covered'      => $covered,
            'skipped'      => count($skippedparts),
            'percent'      => $examinable ? (int) round($covered * 100 / $examinable) : 0,
            'missing'      => $missing,
            'gaps'         => self::gaps($blocks, $missing),
            'skippedparts' => $skippedparts,
        ];
    }

    /**
     * Runs of consecutive uncovered blocks, as ranges a person can act on.
     *
     * Three separate "B7 is missing" lines say less than one "nothing between
     * 14:00 and 20:00", which is what a teacher needs in order to decide whether
     * the gap matters — it may well be six minutes of worked example whose point
     * is already asked about elsewhere.
     *
     * @param array $blocks
     * @param array $missing block ids, in video order
     * @return array
     */
    protected static function gaps(array $blocks, array $missing): array {
        if (!$missing) {
            return [];
        }

        $byid = [];
        foreach ($blocks as $index => $block) {
            $byid[$block['id']] = $index;
        }

        $gaps = [];
        $run = [];

        foreach ($missing as $id) {
            if ($run && $byid[$id] !== $byid[end($run)] + 1) {
                $gaps[] = self::gap($blocks, $byid[$run[0]], $byid[end($run)]);
                $run = [];
            }
            $run[] = $id;
        }
        if ($run) {
            $gaps[] = self::gap($blocks, $byid[$run[0]], $byid[end($run)]);
        }

        return $gaps;
    }

    /**
     * One gap, from the first missing block to the last.
     *
     * @param array $blocks
     * @param int $first index into $blocks
     * @param int $last index into $blocks
     * @return array
     */
    protected static function gap(array $blocks, int $first, int $last): array {
        $from = $blocks[$first];
        $to = $blocks[$last];

        if ($from['start'] < 0) {
            return [
                'label' => $from['id'] === $to['id']
                    ? self::label($from)
                    : self::label($from) . ' - ' . self::label($to),
                'from'  => -1,
                'to'    => -1,
            ];
        }

        return [
            'label' => helper::timecode((int) $from['start']) . ' - ' . helper::timecode((int) $to['end']),
            'from'  => (int) $from['start'],
            'to'    => (int) $to['end'],
        ];
    }

    /**
     * The blocks named by a list of ids, in video order.
     *
     * @param array $blocks
     * @param array $ids
     * @return array
     */
    public static function pick(array $blocks, array $ids): array {
        $wanted = array_flip($ids);
        return array_values(array_filter($blocks, static fn($block) => isset($wanted[$block['id']])));
    }
}
