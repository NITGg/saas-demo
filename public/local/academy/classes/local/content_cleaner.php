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
 * Cleans teacher content that Moodle shows uncleaned (noclean), as it is saved (BUG-047, part 2).
 *
 * Like questions (question_cleaner), these are shown with noclean because Moodle trusts
 * everyone who may write them — and teachers may. Our teachers are outside sellers, so a
 * <script> or onerror=… they save would run in every student's browser:
 *  - every activity's description (intro; also a Text-and-media area's content),
 *    Page content, Assignment instructions, quiz overall feedback, Book chapters,
 *    Lesson pages / answers / responses / essay feedback, Feedback items, Database
 *    templates (printed raw; their JavaScript template is dropped), section and course
 *    summaries, and the quiz grader's comment;
 *  - a File's name, which core puts unescaped into the PDF frame's title.
 * A restored or imported course is cleaned as a whole (its tables are filled directly).
 *
 * Feedback items, Lesson imports and Database presets raise no event, so a teacher's
 * POST to those activities is watched: rows it adds or changes are cleaned at shutdown.
 *
 * Site admins are trusted, as in Moodle.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_cleaner {

    /** Database templates: printed raw (jstemplate is dropped, csstemplate is only a stylesheet). */
    const DATA_TEMPLATES = ['singletemplate', 'listtemplate', 'listtemplateheader', 'listtemplatefooter', 'addtemplate',
        'rsstemplate', 'rsstitletemplate', 'asearchtemplate', 'jstemplate'];

    /**
     * Uncleaned text of an activity, besides its intro:
     * module => list of [table, column holding the instance id, [text column => format column|null], html-only columns].
     * A null format means the column has its own cleaning (see clean_value()).
     */
    const MODULE_TABLES = [
        'page' => [['page', 'id', ['content' => 'contentformat']]],
        'assign' => [['assign', 'id', ['activity' => 'activityformat']]],
        'quiz' => [['quiz_feedback', 'quizid', ['feedbacktext' => 'feedbacktextformat']]],
        'book' => [['book_chapters', 'bookid', ['content' => 'contentformat']]],
        'lesson' => [
            ['lesson_pages', 'lessonid', ['contents' => 'contentsformat']],
            // Short-answer / numerical answers are plain values that grading compares.
            ['lesson_answers', 'lessonid', ['answer' => 'answerformat', 'response' => 'responseformat'], ['answer']],
        ],
        'feedback' => [['feedback_item', 'feedback', ['name' => null, 'presentation' => null]]],
        'data' => [['data', 'id', ['singletemplate' => null, 'listtemplate' => null, 'listtemplateheader' => null,
            'listtemplatefooter' => null, 'addtemplate' => null, 'rsstemplate' => null, 'rsstitletemplate' => null,
            'asearchtemplate' => null, 'jstemplate' => null]]],
        'resource' => [['resource', 'id', ['name' => null]]],
    ];

    /** Activities whose teacher POSTs are watched (their content is saved without an event). */
    const WATCHED = ['feedback', 'lesson', 'data'];

    /** @var bool report what would change, without saving (the cleanup script) */
    public static $dryrun = false;

    /** @var array<string, int> rows changed (or that would be) per table.column */
    public static $changes = [];

    /** @var array|null the watched instance: [module, instance id, fingerprints] */
    private static $watch = null;

    // Observers.

    /**
     * \core\event\course_module_created / course_module_updated.
     *
     * @param \core\event\base $event
     */
    public static function module_saved(\core\event\base $event): void {
        $modname = (string) ($event->other['modulename'] ?? '');
        $instanceid = (int) ($event->other['instanceid'] ?? 0);
        if (self::trusted($event) || $modname === '' || !$instanceid) {
            return;
        }
        if (self::clean_module($modname, $instanceid)) {
            rebuild_course_cache((int) $event->courseid, true);
        }
    }

    /**
     * \core\event\course_section_updated.
     *
     * @param \core\event\base $event
     */
    public static function section_saved(\core\event\base $event): void {
        if (!self::trusted($event)
                && self::clean_rows('course_sections', ['id' => $event->objectid], ['summary' => 'summaryformat'])) {
            rebuild_course_cache((int) $event->courseid, true);
        }
    }

    /**
     * \core\event\course_created / course_updated.
     *
     * @param \core\event\base $event
     */
    public static function course_saved(\core\event\base $event): void {
        if (!self::trusted($event)
                && self::clean_rows('course', ['id' => $event->objectid], ['summary' => 'summaryformat'])) {
            rebuild_course_cache((int) $event->objectid, true);
        }
    }

    /**
     * \core\event\course_restored: a backup (or an import) fills the tables directly.
     *
     * @param \core\event\base $event
     */
    public static function course_restored(\core\event\base $event): void {
        if (!self::trusted($event)) {
            self::clean_course((int) $event->objectid);
        }
    }

    /**
     * \mod_book\event\chapter_created / chapter_updated.
     *
     * @param \core\event\base $event
     */
    public static function chapter_saved(\core\event\base $event): void {
        if (!self::trusted($event)) {
            self::clean_rows('book_chapters', ['id' => $event->objectid], ['content' => 'contentformat']);
        }
    }

    /**
     * \mod_lesson\event\page_created / page_updated (answers are saved with their page).
     *
     * @param \core\event\base $event
     */
    public static function lesson_page_saved(\core\event\base $event): void {
        if (self::trusted($event)) {
            return;
        }
        self::clean_rows('lesson_pages', ['id' => $event->objectid], ['contents' => 'contentsformat']);
        self::clean_rows('lesson_answers', ['pageid' => $event->objectid],
            ['answer' => 'answerformat', 'response' => 'responseformat'], ['answer']);
    }

    /**
     * \mod_lesson\event\essay_assessed: the teacher's comment on a student's essay.
     *
     * @param \core\event\base $event
     */
    public static function essay_assessed(\core\event\base $event): void {
        if (!self::trusted($event) && !empty($event->other['attemptid'])) {
            self::clean_essay_feedback(['id' => (int) $event->other['attemptid']]);
        }
    }

    /**
     * \mod_data\event\template_updated.
     *
     * @param \core\event\base $event
     */
    public static function data_templates_saved(\core\event\base $event): void {
        if (!self::trusted($event) && !empty($event->other['dataid'])) {
            self::clean_rows('data', ['id' => (int) $event->other['dataid']], array_fill_keys(self::DATA_TEMPLATES, null));
        }
    }

    /**
     * \mod_quiz\event\question_manually_graded: the grader's comment, shown to the student uncleaned.
     *
     * @param \core\event\base $event
     */
    public static function quiz_comment_saved(\core\event\base $event): void {
        global $DB;
        if (self::trusted($event) || empty($event->other['attemptid']) || empty($event->other['slot'])) {
            return;
        }
        $usageid = $DB->get_field('quiz_attempts', 'uniqueid', ['id' => (int) $event->other['attemptid']]);
        if ($usageid) {
            self::clean_grader_comments('qa.questionusageid = :usageid AND qa.slot = :slot',
                ['usageid' => $usageid, 'slot' => (int) $event->other['slot']]);
        }
    }

    // Watching requests that save without an event.

    /**
     * From require_login(): watch a teacher's POST to an activity whose content has no save event.
     *
     * @param \stdClass|\cm_info|null $cm
     */
    public static function after_require_login($cm): void {
        global $DB;
        if (self::$watch !== null || empty($cm->instance) || CLI_SCRIPT
                || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || is_siteadmin()) {
            return;
        }
        $modname = $cm->modname ?? $DB->get_field('modules', 'name', ['id' => $cm->module]);
        if (!in_array($modname, self::WATCHED, true)) {
            return;
        }
        self::watch_instance($modname, (int) $cm->instance);
        \core_shutdown_manager::register_function([self::class, 'finish_watch']);
    }

    /**
     * Remember the state of an activity's content.
     *
     * @param string $modname
     * @param int $instanceid
     */
    public static function watch_instance(string $modname, int $instanceid): void {
        self::$watch = [$modname, $instanceid, self::fingerprints($modname, $instanceid)];
    }

    /**
     * Clean the rows added or changed since watch_instance().
     */
    public static function finish_watch(): void {
        if (self::$watch === null) {
            return;
        }
        [$modname, $instanceid, $before] = self::$watch;
        self::$watch = null;
        $changed = [];
        foreach (self::fingerprints($modname, $instanceid) as $spec => $rows) {
            foreach ($rows as $id => $hash) {
                if (($before[$spec][$id] ?? null) !== $hash) {
                    $changed[$spec][] = $id;
                }
            }
        }
        if ($changed) {
            self::clean_module($modname, $instanceid, $changed);
        }
    }

    /**
     * A hash of every row of an activity's content, per table spec.
     *
     * @param string $modname
     * @param int $instanceid
     * @return array<int, array<int, string>> spec index => [row id => hash]
     */
    private static function fingerprints(string $modname, int $instanceid): array {
        global $DB;
        $result = [];
        foreach (self::module_specs($modname) as $index => [$table, $key, $columns]) {
            $fields = 'id, ' . implode(', ', array_keys($columns));
            foreach ($DB->get_records($table, [$key => $instanceid], '', $fields) as $row) {
                $result[$index][(int) $row->id] = md5(serialize((array) $row));
            }
        }
        return $result;
    }

    // Cleaning.

    /**
     * Clean an activity's content.
     *
     * @param string $modname
     * @param int $instanceid
     * @param array|null $onlyids spec index => row ids to clean (null: all rows)
     * @return bool whether anything changed
     */
    public static function clean_module(string $modname, int $instanceid, ?array $onlyids = null): bool {
        $changed = false;
        foreach (self::module_specs($modname) as $index => $spec) {
            if ($onlyids !== null && empty($onlyids[$index])) {
                continue;
            }
            [$table, $key, $columns] = $spec;
            $changed = self::clean_rows($table, [$key => $instanceid], $columns, $spec[3] ?? [], $onlyids[$index] ?? null)
                || $changed;
        }
        return $changed;
    }

    /**
     * Clean everything teachers wrote in a course (after a restore, and the cleanup script).
     *
     * @param int $courseid
     * @return bool whether anything changed
     */
    public static function clean_course(int $courseid): bool {
        global $DB;
        $changed = self::clean_rows('course', ['id' => $courseid], ['summary' => 'summaryformat']);
        $changed = self::clean_rows('course_sections', ['course' => $courseid], ['summary' => 'summaryformat']) || $changed;
        $cms = $DB->get_records_sql('SELECT cm.id, cm.instance, m.name AS modname
                                       FROM {course_modules} cm
                                       JOIN {modules} m ON m.id = cm.module
                                      WHERE cm.course = ?', [$courseid]);
        foreach ($cms as $cm) {
            $changed = self::clean_module($cm->modname, (int) $cm->instance) || $changed;
        }
        $changed = self::clean_essay_feedback(['lessonid' => array_column(
            array_filter($cms, fn($cm) => $cm->modname === 'lesson'), 'instance')]) || $changed;
        $changed = self::clean_grader_comments('qa.questionusageid IN (SELECT qza.uniqueid FROM {quiz_attempts} qza
                                                 JOIN {quiz} q ON q.id = qza.quiz WHERE q.course = :courseid)',
            ['courseid' => $courseid]) || $changed;

        // Questions in the course's question banks.
        $questionids = $DB->get_fieldset_sql('SELECT DISTINCT qv.questionid
                                                FROM {question_versions} qv
                                                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                                JOIN {context} ctx ON ctx.id = qc.contextid
                                               WHERE (ctx.contextlevel = :courselevel AND ctx.instanceid = :courseid)
                                                  OR (ctx.contextlevel = :modulelevel AND ctx.instanceid IN
                                                        (SELECT id FROM {course_modules} WHERE course = :courseid2))',
            ['courselevel' => CONTEXT_COURSE, 'courseid' => $courseid, 'modulelevel' => CONTEXT_MODULE,
                'courseid2' => $courseid]);
        foreach ($questionids as $questionid) {
            if (question_cleaner::clean_question((int) $questionid)) {
                $changed = true;
                if (!self::$dryrun) {
                    \question_bank::notify_question_edited((int) $questionid);
                }
            }
        }
        if ($changed && !self::$dryrun) {
            rebuild_course_cache($courseid, true);
        }
        return $changed;
    }

    /**
     * Clean text columns of the matching rows.
     *
     * @param string $table
     * @param array $conditions
     * @param array $columns text column => its format column (null: cleaned by clean_value() rules)
     * @param string[] $htmlonly columns cleaned only when their format is HTML
     * @param int[]|null $onlyids only these rows
     * @return bool whether a row changed (or would, in a dry run)
     */
    public static function clean_rows(string $table, array $conditions, array $columns, array $htmlonly = [],
            ?array $onlyids = null): bool {
        global $DB;
        $changed = false;
        foreach ($DB->get_records($table, $conditions) as $row) {
            if ($onlyids !== null && !in_array((int) $row->id, $onlyids, true)) {
                continue;
            }
            $update = [];
            foreach ($columns as $column => $formatcolumn) {
                if ($formatcolumn !== null && in_array($column, $htmlonly, true)
                        && (int) ($row->$formatcolumn ?? FORMAT_HTML) !== FORMAT_HTML) {
                    continue;
                }
                $clean = self::clean_value($table, $column, $row, $formatcolumn);
                if ($clean !== (string) ($row->$column ?? '')) {
                    $update[$column] = $clean;
                    self::$changes["$table.$column"] = (self::$changes["$table.$column"] ?? 0) + 1;
                }
            }
            if ($update) {
                if (!self::$dryrun) {
                    $DB->update_record($table, (object) (['id' => $row->id] + $update));
                }
                $changed = true;
            }
        }
        return $changed;
    }

    /**
     * The cleaned value of one column.
     *
     * @param string $table
     * @param string $column
     * @param \stdClass $row
     * @param string|null $formatcolumn
     * @return string
     */
    private static function clean_value(string $table, string $column, \stdClass $row, ?string $formatcolumn): string {
        $text = (string) ($row->$column ?? '');
        if ($formatcolumn !== null) {
            return question_cleaner::clean($text, (int) ($row->$formatcolumn ?? FORMAT_HTML));
        }
        switch ("$table.$column") {
            case 'data.jstemplate':
                return '';
            case 'resource.name':
                // Core prints it inside title="…" of the PDF frame without escaping.
                return str_replace('"', "'", $text);
            case 'feedback_item.presentation':
                if ($row->typ !== 'label') {
                    // Choice options: "r>>>>>one|two<<<<<1", "0####one|1####two" — clean each option.
                    $parts = preg_split('~(>>>>>|<<<<<|\||####)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    foreach ($parts as $i => $part) {
                        $parts[$i] = $i % 2 ? $part : question_cleaner::clean($part, FORMAT_HTML);
                    }
                    return implode('', $parts);
                }
                return question_cleaner::clean($text, FORMAT_HTML);
            case 'feedback_item.name':
                return question_cleaner::clean($text, FORMAT_HTML);
        }
        return $table === 'data' ? template_sanitizer::clean($text) : $text;
    }

    /**
     * Clean the teacher's comments on lesson essays.
     *
     * @param array $conditions on lesson_attempts (values may be lists)
     * @return bool whether anything changed
     */
    private static function clean_essay_feedback(array $conditions): bool {
        global $DB;
        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            [$sql, $p] = $DB->get_in_or_equal($value ?: [0], SQL_PARAMS_NAMED, $column);
            $where[] = "$column $sql";
            $params += $p;
        }
        $where[] = $DB->sql_like('useranswer', ':response');
        $params['response'] = '%"response"%';
        $changed = false;
        foreach ($DB->get_records_select('lesson_attempts', implode(' AND ', $where), $params, '', 'id, useranswer') as $row) {
            $essay = @unserialize($row->useranswer, ['allowed_classes' => ['stdClass']]);
            if (!is_object($essay) || !isset($essay->response)) {
                continue;
            }
            $clean = question_cleaner::clean((string) $essay->response, (int) ($essay->responseformat ?? FORMAT_HTML));
            if ($clean !== (string) $essay->response) {
                $essay->response = $clean;
                self::$changes['lesson_attempts.response'] = (self::$changes['lesson_attempts.response'] ?? 0) + 1;
                if (!self::$dryrun) {
                    $DB->set_field('lesson_attempts', 'useranswer', serialize($essay), ['id' => $row->id]);
                }
                $changed = true;
            }
        }
        return $changed;
    }

    /**
     * Clean graders' comments (question engine "-comment" step data).
     *
     * @param string $where on question_attempts qa
     * @param array $params
     * @return bool whether anything changed
     */
    private static function clean_grader_comments(string $where, array $params): bool {
        global $DB;
        $rows = $DB->get_records_sql("SELECT d.id, d.value, f.value AS format
                                        FROM {question_attempt_step_data} d
                                        JOIN {question_attempt_steps} s ON s.id = d.attemptstepid
                                        JOIN {question_attempts} qa ON qa.id = s.questionattemptid
                                   LEFT JOIN {question_attempt_step_data} f
                                             ON f.attemptstepid = d.attemptstepid AND f.name = '-commentformat'
                                       WHERE d.name = '-comment' AND $where", $params);
        $changed = false;
        foreach ($rows as $row) {
            $clean = question_cleaner::clean((string) $row->value, (int) ($row->format ?? FORMAT_HTML));
            if ($clean !== (string) $row->value) {
                self::$changes['question_attempt_step_data.-comment'] =
                    (self::$changes['question_attempt_step_data.-comment'] ?? 0) + 1;
                if (!self::$dryrun) {
                    $DB->set_field('question_attempt_step_data', 'value', $clean, ['id' => $row->id]);
                }
                $changed = true;
            }
        }
        return $changed;
    }

    /**
     * The tables of an activity's content: its intro first, then MODULE_TABLES.
     *
     * @param string $modname
     * @return array list of [table, key column, columns, html-only columns]
     */
    private static function module_specs(string $modname): array {
        global $DB;
        static $intro = [];
        if (!isset($intro[$modname])) {
            $columns = preg_match('~^[a-z][a-z0-9_]*$~', $modname) && $DB->get_manager()->table_exists($modname)
                ? $DB->get_columns($modname) : [];
            $intro[$modname] = isset($columns['intro'], $columns['introformat']);
        }
        $specs = $intro[$modname] ? [[$modname, 'id', ['intro' => 'introformat']]] : [];
        return array_merge($specs, self::MODULE_TABLES[$modname] ?? []);
    }

    /**
     * Whether the user who raised the event is trusted with raw HTML (site admins).
     *
     * @param \core\event\base $event
     * @return bool
     */
    private static function trusted(\core\event\base $event): bool {
        return is_siteadmin((int) $event->userid);
    }
}
