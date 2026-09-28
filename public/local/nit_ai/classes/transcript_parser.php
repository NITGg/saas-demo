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
 * Turns whatever transcript file the teacher uploaded into one normalised shape.
 *
 * We detect rather than ask: the teacher should be confirming what we read, not
 * answering questions about subtitle formats. Everything here is best-effort —
 * a file we cannot make sense of comes back with an empty cue list and the
 * caller reports that, it never throws at the teacher.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transcript_parser {

    /** @var int Longest transcript we will keep, in characters. */
    public const MAX_CHARS = 400000;

    /**
     * Parse a transcript file.
     *
     * @param string $content raw file content
     * @param string $filename used only as a hint when the content is ambiguous
     * @return array {format, cues: [{s,e,t}], plaintext, lang, hastimestamps, lasttimestamp}
     */
    public static function parse(string $content, string $filename = ''): array {
        $content = self::normalise_input($content);

        $format = self::detect_format($content, $filename);
        switch ($format) {
            case 'json':
                $cues = self::parse_json($content);
                break;
            case 'vtt':
            case 'srt':
                $cues = self::parse_cues($content);
                break;
            default:
                $cues = [];
        }

        $cues = self::tidy($cues);
        $plaintext = $cues
            ? implode("\n", array_column($cues, 't'))
            : trim(preg_replace('/\n{3,}/u', "\n\n", $content));

        return [
            'format'        => $format,
            'cues'          => $cues,
            'plaintext'     => \core_text::substr($plaintext, 0, self::MAX_CHARS),
            'lang'          => self::detect_language($plaintext),
            'hastimestamps' => !empty($cues),
            'lasttimestamp' => $cues ? (int) end($cues)['s'] : 0,
        ];
    }

    /**
     * Strip BOM, normalise line endings and cap the size.
     *
     * @param string $content
     * @return string
     */
    protected static function normalise_input(string $content): string {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        return \core_text::substr($content, 0, self::MAX_CHARS);
    }

    /**
     * Work out which of the four shapes we are looking at.
     *
     * @param string $content
     * @param string $filename
     * @return string vtt|srt|json|txt
     */
    protected static function detect_format(string $content, string $filename): string {
        $head = ltrim(\core_text::substr($content, 0, 400));

        if (str_starts_with($head, 'WEBVTT')) {
            return 'vtt';
        }
        if ($head !== '' && ($head[0] === '[' || $head[0] === '{')) {
            return 'json';
        }
        // SRT and headerless VTT both look like "timestamp --> timestamp".
        if (preg_match('/\d{1,2}:\d{2}(:\d{2})?[.,]\d{1,3}\s*-->/', $content)) {
            return str_contains($content, ',') && !str_contains($content, '.') ? 'srt' : 'vtt';
        }
        if (preg_match('/\.(vtt|srt|json)$/i', $filename, $m)) {
            return \core_text::strtolower($m[1]);
        }
        return 'txt';
    }

    /**
     * Parse SRT / WebVTT cue blocks. Both use the same "start --> end" line, so
     * one reader covers them; SRT's comma decimal separator is handled in
     * {@see self::seconds()}.
     *
     * @param string $content
     * @return array list of {s,e,t}
     */
    protected static function parse_cues(string $content): array {
        $cues = [];
        $lines = explode("\n", $content);
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^(\S+)\s*-->\s*(\S+)/', $line, $m)) {
                if ($current !== null) {
                    $cues[] = $current;
                }
                $current = ['s' => self::seconds($m[1]), 'e' => self::seconds($m[2]), 'lines' => []];
                continue;
            }

            if ($current === null) {
                continue; // Header, cue number or stray text before the first cue.
            }
            if ($line === '') {
                $cues[] = $current;
                $current = null;
                continue;
            }
            // Drop the cue-number line that SRT puts before each timestamp.
            if (!$current['lines'] && preg_match('/^\d+$/', $line)) {
                continue;
            }
            $text = self::strip_markup($line);
            if ($text !== '') {
                $current['lines'][] = $text;
            }
        }
        if ($current !== null) {
            $cues[] = $current;
        }
        return self::flatten_rolling($cues);
    }

    /**
     * Collapse rolling captions into each cue's own words.
     *
     * YouTube's auto-captions scroll: every cue repeats the previous cue's line
     * above its new one, so a naive read returns each sentence two or three
     * times. That doubles the tokens we send and reads like a stutter. Keeping
     * only the lines a cue did not inherit leaves one clean pass of the speech.
     *
     * @param array $cues cues carrying a 'lines' list
     * @return array cues carrying a 't' string
     */
    protected static function flatten_rolling(array $cues): array {
        $out = [];
        $previous = [];

        foreach ($cues as $cue) {
            $lines = $cue['lines'] ?? [];
            $fresh = array_filter($lines, static fn($line) => !in_array($line, $previous, true));

            $previous = $lines;
            unset($cue['lines']);
            $cue['t'] = trim(implode(' ', $fresh));

            // A cue that said nothing new is the tail of the one before it, so
            // it lends its end time rather than disappearing.
            if ($cue['t'] === '' && $out) {
                $last = count($out) - 1;
                $out[$last]['e'] = max($out[$last]['e'], $cue['e']);
                continue;
            }
            $out[] = $cue;
        }

        return $out;
    }

    /**
     * Parse a JSON transcript. We accept the field names the common tools emit
     * rather than insisting on one shape.
     *
     * @param string $content
     * @return array list of {s,e,t}
     */
    protected static function parse_json(string $content): array {
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }
        // Whisper-style wrappers put the list under "segments".
        foreach (['segments', 'cues', 'results', 'transcript'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data = $data[$key];
                break;
            }
        }
        if (!is_array($data) || !array_is_list($data)) {
            return [];
        }

        $cues = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = $row['text'] ?? $row['t'] ?? $row['content'] ?? '';
            if (!is_string($text) || trim($text) === '') {
                continue;
            }
            $start = $row['start'] ?? $row['s'] ?? $row['startTime'] ?? $row['offset'] ?? 0;
            $end   = $row['end'] ?? $row['e'] ?? $row['endTime'] ?? $start;
            $cues[] = [
                's' => self::seconds((string) $start),
                'e' => self::seconds((string) $end),
                't' => self::strip_markup($text),
            ];
        }
        return $cues;
    }

    /**
     * Convert a timestamp to seconds. Accepts "00:04:12.500", "04:12,500",
     * "252" and "252.5"; milliseconds are dropped because nothing downstream
     * needs sub-second precision.
     *
     * @param string $value
     * @return int
     */
    protected static function seconds(string $value): int {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            // Some tools emit milliseconds; anything past ~28h is not a video.
            $number = (float) $value;
            return (int) ($number > 100000 ? $number / 1000 : $number);
        }
        $parts = array_reverse(explode(':', $value));
        $seconds = 0;
        foreach ($parts as $i => $part) {
            $seconds += ((float) $part) * (60 ** $i);
        }
        return (int) $seconds;
    }

    /**
     * Remove subtitle markup and speaker tags that would only be noise in a prompt.
     *
     * @param string $text
     * @return string
     */
    protected static function strip_markup(string $text): string {
        $text = preg_replace('/<[^>]*>/u', '', $text);          // VTT <v Speaker> / <i> tags.
        $text = preg_replace('/\{[^}]*\}/u', '', $text);         // SSA-style overrides.
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Drop empty cues, sort by start time and merge cues that repeat the same
     * line (rolling captions do this constantly).
     *
     * @param array $cues
     * @return array
     */
    protected static function tidy(array $cues): array {
        $cues = array_values(array_filter($cues, static fn($c) => trim($c['t']) !== ''));
        usort($cues, static fn($a, $b) => $a['s'] <=> $b['s']);

        $out = [];
        foreach ($cues as $cue) {
            $last = $out ? count($out) - 1 : null;
            if ($last !== null && $out[$last]['t'] === $cue['t']) {
                $out[$last]['e'] = max($out[$last]['e'], $cue['e']);
                continue;
            }
            $out[] = $cue;
        }
        return $out;
    }

    /**
     * Rough language read, used only to show the teacher what we saw.
     *
     * @param string $text
     * @return string ar|en|mixed|unknown
     */
    protected static function detect_language(string $text): string {
        $sample = \core_text::substr($text, 0, 20000);
        $arabic = preg_match_all('/\p{Arabic}/u', $sample);
        $latin  = preg_match_all('/[A-Za-z]/u', $sample);
        $total  = $arabic + $latin;

        if ($total < 20) {
            return 'unknown';
        }
        if ($arabic / $total > 0.85) {
            return 'ar';
        }
        if ($latin / $total > 0.85) {
            return 'en';
        }
        return 'mixed';
    }
}
