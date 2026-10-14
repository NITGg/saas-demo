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

namespace local_academy\local;

/**
 * Cleans the HTML of a question when a teacher saves it (BUG-047).
 *
 * Moodle shows question text, feedback and hints without cleaning (noclean),
 * trusting everyone who may add questions — and teachers may. Here a teacher can
 * put <script> or onerror=… in a question and it runs in the browser of every
 * student who takes the quiz. So the question is cleaned (Moodle's HTMLPurifier,
 * the same cleaning other content gets) as it is saved. Every save of a question
 * — new, edited (a new version) or imported — raises question_created.
 *
 * Site admins are trusted, as in Moodle. Answers are cleaned only when they are
 * HTML (short-answer / numerical answers are plain values that grading compares).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_cleaner {

    /**
     * Observer: \core\event\question_created.
     *
     * @param \core\event\question_created $event
     */
    public static function question_created(\core\event\question_created $event): void {
        if (empty($event->objectid) || is_siteadmin((int) $event->userid)) {
            return;
        }
        if (self::clean_question((int) $event->objectid)) {
            \question_bank::notify_question_edited((int) $event->objectid);
        }
    }

    /**
     * Clean every rich-text part of one question.
     *
     * @param int $questionid
     * @return bool whether anything changed
     */
    public static function clean_question(int $questionid): bool {
        $changed = self::clean_rows('question', ['id' => $questionid],
            ['questiontext' => 'questiontextformat', 'generalfeedback' => 'generalfeedbackformat']);
        $changed = self::clean_rows('question_answers', ['question' => $questionid],
            ['answer' => 'answerformat', 'feedback' => 'feedbackformat'], ['answer']) || $changed;
        $changed = self::clean_rows('question_hints', ['questionid' => $questionid], ['hint' => 'hintformat']) || $changed;
        // Question-type tables: combined feedback, matching sub-questions … (any text column with a …format column).
        foreach (self::qtype_text_columns() as $table => $columns) {
            $changed = self::clean_rows($table, ['questionid' => $questionid], $columns) || $changed;
        }
        return $changed;
    }

    /**
     * Clean text columns of the matching rows.
     *
     * @param string $table
     * @param array $conditions
     * @param array $columns text column => its format column
     * @param string[] $htmlonly columns cleaned only when their format is HTML
     * @return bool whether a row changed
     */
    private static function clean_rows(string $table, array $conditions, array $columns, array $htmlonly = []): bool {
        return content_cleaner::clean_rows($table, $conditions, $columns, $htmlonly);
    }

    /**
     * Cleaned text; text without any tag is left exactly as it is.
     *
     * @param string $text
     * @param int $format
     * @return string
     */
    public static function clean(string $text, int $format): string {
        if ($format === FORMAT_PLAIN || !preg_match('~<[a-z!/?]~i', $text)) {
            return $text;
        }
        return clean_text($text, FORMAT_HTML);
    }

    /**
     * Question-type tables (qtype_*) keyed by questionid, with their text columns
     * that have a matching …format column.
     *
     * @return array<string, array<string, string>> table => [text column => format column]
     */
    private static function qtype_text_columns(): array {
        global $DB;
        static $tables = null;
        if ($tables === null) {
            $tables = [];
            foreach ($DB->get_tables() as $table) {
                if (strpos($table, 'qtype_') !== 0) {
                    continue;
                }
                $columns = $DB->get_columns($table);
                if (!isset($columns['questionid'])) {
                    continue;
                }
                foreach ($columns as $name => $info) {
                    if (isset($columns[$name . 'format'])) {
                        $tables[$table][$name] = $name . 'format';
                    }
                }
            }
        }
        return $tables;
    }
}
