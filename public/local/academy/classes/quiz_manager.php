<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\quiz_settings;
use mod_quiz\question\bank\qbank_helper;

/**
 * Quiz API for the Academy platform.
 *
 * Covers: list quizzes, get quiz questions (structured JSON),
 * start attempt, submit answers, review attempt.
 *
 * Supported question types: multichoice, truefalse.
 * Unsupported types appear in the response with supported=false and no options.
 *
 * Ported from the old academy (Moodle 3.11) and adapted to the Moodle 4.x/5.x
 * question bank: slots no longer carry questionid, so slot->question is resolved
 * via mod_quiz\question\bank\qbank_helper::get_question_structure().
 */
class quiz_manager {

    /** @var string Web-service token of the current request, appended to image URLs. */
    private static $token = '';

    /**
     * Set the web-service token used to authenticate returned image URLs.
     * Call this before get_quiz()/get_attempt() so <img> URLs are ready to load.
     */
    public static function set_token(string $token): void {
        self::$token = $token;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Check if a user is enrolled in a course. Throws if not.
     */
    private static function require_enrolled(int $userid, int $courseid): void {
        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $userid, '', true)) {
            throw new \moodle_exception('notenrolled', 'local_academy', '', null, 'You are not enrolled in this course');
        }
    }

    /**
     * Resolve the question content of a quiz's slots, Moodle 4.x/5.x safe.
     *
     * @param int $quizid
     * @return array [\cm_info|\stdClass $cm, \context_module $context, array $structure]
     *               $structure is indexed by slot number; each row exposes ->slot, ->slotid,
     *               ->maxmark, ->questionid and the full {question} fields (+ ->contextid).
     */
    private static function quiz_structure(int $quizid): array {
        $cm      = get_coursemodule_from_instance('quiz', $quizid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $structure = qbank_helper::get_question_structure($quizid, $context);
        return [$cm, $context, $structure];
    }

    /**
     * Turn a get_question_structure() row into the question object the format_ and grade_
     * helpers expect ($q->id is the question id). Random/empty slots return null.
     */
    private static function struct_to_q(\stdClass $row): ?\stdClass {
        if (empty($row->questionid)) {
            return null;
        }
        $q = clone $row;
        $q->id = (int) $row->questionid;
        return $q;
    }

    /**
     * List quizzes. Admins see all; students see only their enrolled courses.
     */
    public static function get_quizzes(int $userid, bool $is_admin = false, int $courseid = 0): array {
        global $DB;

        if ($is_admin) {
            $quizzes = $courseid
                ? $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, course, name, intro, timelimit, attempts')
                : $DB->get_records('quiz', [],                       'name ASC', 'id, course, name, intro, timelimit, attempts');
        } else {
            // Only return quizzes from courses the student is enrolled in.
            $sql = "SELECT q.id, q.course, q.name, q.intro, q.timelimit, q.attempts
                      FROM {quiz} q
                      JOIN {enrol} e       ON e.courseid = q.course AND e.status = 0
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid AND ue.status = 0
                     WHERE 1=1" . ($courseid ? " AND q.course = :courseid" : "") . "
                  ORDER BY q.name ASC";
            $params = ['userid' => $userid];
            if ($courseid) { $params['courseid'] = $courseid; }
            $quizzes = $DB->get_records_sql($sql, $params);
        }

        $out = [];
        foreach ($quizzes as $q) {
            $cm = get_coursemodule_from_instance('quiz', $q->id, $q->course);
            $out[] = [
                'quizid'           => (int)$q->id,
                'cmid'             => $cm ? (int)$cm->id : null,
                'courseid'         => (int)$q->course,
                'name'             => $q->name,
                'intro'            => strip_tags($q->intro ?? ''),
                'timelimit'        => (int)$q->timelimit,
                'attempts_allowed' => (int)$q->attempts,
            ];
        }
        return $out;
    }

    /**
     * Get a single quiz with all questions structured as JSON.
     *
     * @param int  $cmid         Course module id
     * @param bool $show_correct Include correct flag on each answer option (admin only)
     */
    public static function get_quiz(int $cmid, int $userid, bool $show_correct = false, bool $is_admin = false): array {
        global $DB;

        $cm   = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        if (!$is_admin) {
            self::require_enrolled($userid, $cm->course);
        }

        $context   = \context_module::instance($cm->id);
        $structure = qbank_helper::get_question_structure($quiz->id, $context);

        $questions = [];
        foreach ($structure as $row) {
            $q = self::struct_to_q($row);
            if (!$q) { continue; }
            $questions[] = self::format_question($q, (int)$row->slot, $show_correct);
        }

        return [
            'quizid'           => (int)$quiz->id,
            'cmid'             => (int)$cmid,
            'courseid'         => (int)$cm->course,
            'name'             => $quiz->name,
            'intro'            => strip_tags($quiz->intro ?? ''),
            'timelimit'        => (int)$quiz->timelimit,
            'attempts_allowed' => (int)$quiz->attempts,
            'questions'        => $questions,
        ];
    }

    /**
     * Start a new quiz attempt for a user.
     */
    public static function start_attempt(int $quizid, int $userid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

        // Ensure the student is enrolled in the course that contains this quiz.
        self::require_enrolled($userid, $quiz->course);

        // Check attempt limit (0 = unlimited).
        if ($quiz->attempts > 0) {
            $done = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid, 'state' => 'finished']);
            if ($done >= $quiz->attempts) {
                throw new \moodle_exception('attemptsexhausted', 'quiz');
            }
        }

        // Moodle 4.x/5.x: the \quiz class became \mod_quiz\quiz_settings.
        $quizobj       = quiz_settings::create($quizid, $userid);
        $attempts      = quiz_get_user_attempts($quizid, $userid, 'all', true);
        $lastattempt   = end($attempts) ?: null;
        $attemptnumber = count($attempts) + 1;
        $attempt       = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);

        return [
            'attemptid'        => (int)$attempt->id,
            'quizid'           => (int)$quizid,
            'attempt_number'   => (int)$attempt->attempt,
            'timestart'        => (int)$attempt->timestart,
            'timelimit'        => (int)$quiz->timelimit,
            'state'            => $attempt->state,
        ];
    }

    /**
     * Submit answers and finish a quiz attempt.
     *
     * $answers format:
     *   For single-answer MCQ or true/false: { "questionid": 101, "answer": 3 }  (answer = answer row id)
     *   For multi-answer MCQ:                { "questionid": 102, "answer": [3, 5] }
     */
    public static function submit_attempt(int $attemptid, int $userid, array $answers): array {
        $attemptrow = self::require_open_attempt($attemptid, $userid);

        // Start from any answers previously saved with save_answer, then let the
        // answers passed in this call override them.
        $submitted = self::saved_answers_map($attemptid);
        foreach ($answers as $a) {
            $submitted[(int)$a['questionid']] = $a['answer'];
        }

        return self::grade_and_finish($attemptrow, $userid, $submitted);
    }

    /**
     * Save (or update) the answer to a single question WITHOUT finishing the attempt.
     *
     * @param mixed $answer answer row id (int) for single MCQ / true-false,
     *                      or an array of answer row ids for multi-answer MCQ
     */
    public static function save_answer(int $attemptid, int $userid, int $questionid, $answer): array {
        global $DB;

        self::require_answers_table();
        $attemptrow = self::require_open_attempt($attemptid, $userid);

        // The question must belong to this attempt's quiz. In Moodle 4.x/5.x this is
        // resolved through the question bank, not a quiz_slots.questionid column.
        [$cm, $context, $structure] = self::quiz_structure((int)$attemptrow->quiz);
        $belongs = false;
        foreach ($structure as $row) {
            if ((int)($row->questionid ?? 0) === $questionid) {
                $belongs = true;
                break;
            }
        }
        if (!$belongs) {
            throw new \moodle_exception('invalidquestionid', 'question');
        }

        $now      = time();
        $encoded  = json_encode($answer);
        $existing = $DB->get_record('academy_quiz_answers',
            ['attemptid' => $attemptid, 'questionid' => $questionid]);

        if ($existing) {
            $DB->update_record('academy_quiz_answers', (object)[
                'id'           => $existing->id,
                'answer'       => $encoded,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('academy_quiz_answers', (object)[
                'attemptid'    => $attemptid,
                'questionid'   => $questionid,
                'userid'       => $userid,
                'answer'       => $encoded,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        return [
            'attemptid'  => $attemptid,
            'questionid' => $questionid,
            'saved'      => true,
            'state'      => $attemptrow->state,
        ];
    }

    /**
     * Submit all questions: grade every saved answer and finish the attempt.
     */
    public static function finish_attempt(int $attemptid, int $userid): array {
        self::require_answers_table();
        $attemptrow = self::require_open_attempt($attemptid, $userid);
        $submitted  = self::saved_answers_map($attemptid);
        return self::grade_and_finish($attemptrow, $userid, $submitted);
    }

    /**
     * Review a finished attempt.
     *
     * @param bool $show_correct Include correct answers (admin only)
     */
    public static function get_attempt(int $attemptid, int $userid, bool $is_admin = false): array {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if (!$is_admin && (int)$attempt->userid !== $userid) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }

        $quiz  = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        [$cm, $context, $structure] = self::quiz_structure((int)$quiz->id);

        $maxscore = 0.0;
        $questions = [];

        foreach ($structure as $row) {
            $q = self::struct_to_q($row);
            if (!$q) { continue; }
            $maxmark = (float)$row->maxmark;
            $maxscore += $maxmark;

            $ctx     = self::question_contextid($q);
            $content = self::content_fields($q->questiontext, $ctx, 'questiontext', (int)$q->id, (int)$q->id);
            $qdata = [
                'slot'       => (int)$row->slot,
                'questionid' => (int)$q->id,
                'type'       => $q->qtype,
                'text'       => $content['text'],
                'images'     => $content['images'],
                'max_mark'   => round($maxmark, 2),
            ];

            if ($is_admin && in_array($q->qtype, ['multichoice', 'truefalse'])) {
                $allAnswers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');
                $qdata['correct_answers'] = array_values(array_map(function($a) use ($ctx, $q) {
                    $c = self::content_fields($a->answer, $ctx, 'answer', (int)$a->id, (int)$q->id);
                    return ['id' => (int)$a->id, 'text' => $c['text'], 'images' => $c['images'], 'correct' => (float)$a->fraction > 0];
                }, $allAnswers));
                self::dedupe_question_images($qdata, 'correct_answers');
            }

            $questions[] = $qdata;
        }

        return [
            'attemptid'      => (int)$attempt->id,
            'quizid'         => (int)$quiz->id,
            'quiz_name'      => $quiz->name,
            'attempt_number' => (int)$attempt->attempt,
            'state'          => $attempt->state,
            'timestart'      => (int)$attempt->timestart,
            'timefinish'     => (int)$attempt->timefinish,
            'score'          => round((float)($attempt->sumgrades ?? 0), 2),
            'max_score'      => round($maxscore, 2),
            'percent'        => $maxscore > 0 ? round(((float)($attempt->sumgrades ?? 0) / $maxscore) * 100, 1) : 0,
            'questions'      => $questions,
        ];
    }

    /**
     * List all attempts by the current user on a quiz.
     */
    public static function get_my_attempts(int $quizid, int $userid): array {
        global $DB;

        $attempts = $DB->get_records('quiz_attempts',
            ['quiz' => $quizid, 'userid' => $userid], 'attempt ASC');

        $quiz     = $DB->get_record('quiz', ['id' => $quizid], 'id, name, attempts');
        // quiz_slots still stores maxmark in Moodle 4.x/5.x, so the max-score sum is unchanged.
        $slots    = $DB->get_records('quiz_slots', ['quizid' => $quizid], '', 'id, maxmark');
        $maxscore = array_sum(array_column(array_map('get_object_vars', $slots), 'maxmark'));

        $out = [];
        foreach ($attempts as $a) {
            $out[] = [
                'attemptid'      => (int)$a->id,
                'attempt_number' => (int)$a->attempt,
                'state'          => $a->state,
                'score'          => isset($a->sumgrades) ? round((float)$a->sumgrades, 2) : null,
                'max_score'      => round((float)$maxscore, 2),
                'percent'        => ($maxscore > 0 && isset($a->sumgrades))
                    ? round(((float)$a->sumgrades / $maxscore) * 100, 1) : null,
                'timestart'      => (int)$a->timestart,
                'timefinish'     => (int)$a->timefinish,
            ];
        }
        return $out;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function format_question(\stdClass $q, int $slot, bool $show_correct): array {
        $content = self::content_fields($q->questiontext, self::question_contextid($q), 'questiontext', (int)$q->id, (int)$q->id);
        $base = [
            'slot'        => $slot,
            'questionid'  => (int)$q->id,
            'type'        => $q->qtype,
            'text'        => $content['text'],
            'images'      => $content['images'],
            'defaultmark' => (float)$q->defaultmark,
            'supported'   => in_array($q->qtype, ['multichoice', 'truefalse']),
        ];

        switch ($q->qtype) {
            case 'multichoice': $result = array_merge($base, self::format_multichoice($q, $show_correct)); break;
            case 'truefalse':   $result = array_merge($base, self::format_truefalse($q, $show_correct)); break;
            default:            return $base;
        }

        self::dedupe_question_images($result);
        return $result;
    }

    /**
     * An image that was (accidentally) inserted into both the question stem and one of
     * its answer options is only useful attached to the option, so drop it from the
     * question's own images[] when the same URL also appears under one of its options.
     */
    private static function dedupe_question_images(array &$question, string $optionskey = 'options'): void {
        if (empty($question[$optionskey])) {
            return;
        }
        $optionfilenames = [];
        foreach ($question[$optionskey] as $opt) {
            foreach ($opt['images'] ?? [] as $url) {
                $optionfilenames[self::image_filename($url)] = true;
            }
        }
        if ($optionfilenames) {
            $question['images'] = array_values(array_filter($question['images'], function($url) use ($optionfilenames) {
                return !isset($optionfilenames[self::image_filename($url)]);
            }));
        }
    }

    /**
     * Best-effort filename for an image URL, used to detect the same file reused across
     * a question and its options.
     */
    private static function image_filename(string $url): string {
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            if (!empty($params['file'])) {
                return basename($params['file']);
            }
        }
        return basename((string)parse_url($url, PHP_URL_PATH));
    }

    private static function format_multichoice(\stdClass $q, bool $show_correct): array {
        global $DB;
        $opts    = $DB->get_record('qtype_multichoice_options', ['questionid' => $q->id]);
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');
        $ctx     = self::question_contextid($q);

        $options = [];
        foreach ($answers as $a) {
            $content = self::content_fields($a->answer, $ctx, 'answer', (int)$a->id, (int)$q->id);
            $opt = ['id' => (int)$a->id, 'text' => $content['text'], 'images' => $content['images']];
            if ($show_correct) { $opt['correct'] = (float)$a->fraction > 0; }
            $options[] = $opt;
        }

        return [
            'single'  => $opts ? (bool)$opts->single : true,
            'options' => $options,
        ];
    }

    private static function format_truefalse(\stdClass $q, bool $show_correct): array {
        global $DB;
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');
        $ctx     = self::question_contextid($q);

        $options = [];
        foreach ($answers as $a) {
            $content = self::content_fields($a->answer, $ctx, 'answer', (int)$a->id, (int)$q->id);
            $opt = ['id' => (int)$a->id, 'text' => $content['text'], 'images' => $content['images']];
            if ($show_correct) { $opt['correct'] = (float)$a->fraction > 0; }
            $options[] = $opt;
        }

        return ['options' => $options];
    }

    /**
     * Context id used for a question's file areas (the question category context).
     * The question-bank structure already provides ->contextid; fall back to a lookup.
     */
    private static function question_contextid(\stdClass $q): int {
        if (!empty($q->contextid)) {
            return (int)$q->contextid;
        }
        global $DB;
        $cat = $DB->get_record('question_categories', ['id' => $q->category], 'contextid');
        return $cat ? (int)$cat->contextid : 0;
    }

    /**
     * Rewrite @@PLUGINFILE@@ placeholders to real URLs and split the content into
     * plain text + a list of image URLs (served by our token-authorised qfile.php).
     *
     * @return array{text: string, images: string[]}
     */
    private static function content_fields($html, int $contextid, string $filearea, int $itemid, int $questionid): array {
        global $CFG;
        require_once($CFG->dirroot . '/lib/filelib.php');

        $html = (string)$html;
        if ($contextid) {
            $html = \file_rewrite_pluginfile_urls(
                $html, 'pluginfile.php', $contextid, 'question', $filearea, $itemid);
        }

        $images = [];
        if (preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*("|\')(.*?)\1/i', $html, $m)) {
            foreach ($m[2] as $src) {
                $src = trim(html_entity_decode($src, ENT_QUOTES));
                if ($src === '') {
                    continue;
                }
                $url = self::local_file_url($src, $questionid);
                if ($url !== null) {
                    $images[] = $url;
                }
            }
        }

        $text = trim(\html_to_text($html, 0, false));

        return ['text' => $text, 'images' => $images];
    }

    /**
     * Turn a Moodle pluginfile URL for a question image into a URL served by our own
     * token-authorised endpoint (local/academy/qfile.php).
     */
    private static function local_file_url(string $src, int $questionid): ?string {
        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path)) {
            return $src;
        }
        $marker = '/pluginfile.php/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return $src; // Not a Moodle file URL — leave it untouched.
        }
        $rest = substr($path, $pos + strlen($marker));
        $segs = array_values(array_filter(explode('/', $rest), function($s) {
            return $s !== '';
        }));
        if (count($segs) < 5) {
            return $src;
        }
        $filearea = clean_param($segs[2], PARAM_ALPHANUMEXT);
        $fileitem = (int)$segs[3];
        $filename = rawurldecode(implode('/', array_slice($segs, 4)));

        $url = new \moodle_url('/local/academy/qfile.php', [
            'questionid' => $questionid,
            'area'       => $filearea,
            'itemid'     => $fileitem,
            'file'       => $filename,
            'token'      => self::$token,
        ]);
        return $url->out(false);
    }

    /**
     * Load an in-progress attempt, verifying ownership and that it is not finished.
     */
    private static function require_open_attempt(int $attemptid, int $userid): \stdClass {
        global $DB;
        $attemptrow = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if ((int)$attemptrow->userid !== $userid) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if ($attemptrow->state === 'finished') {
            throw new \moodle_exception('attemptalreadyclosed', 'quiz');
        }
        return $attemptrow;
    }

    /**
     * Return the per-question answers saved with save_answer(), keyed by questionid.
     */
    private static function saved_answers_map(int $attemptid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('academy_quiz_answers')) {
            return [];
        }
        $rows = $DB->get_records('academy_quiz_answers', ['attemptid' => $attemptid]);
        $map  = [];
        foreach ($rows as $r) {
            $map[(int)$r->questionid] = json_decode($r->answer, true);
        }
        return $map;
    }

    /**
     * The per-question save/finish flow needs the academy_quiz_answers table.
     */
    private static function require_answers_table(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('academy_quiz_answers')) {
            throw new \Exception('Per-question answer storage is not installed yet — run the '
                . 'Academy plugin upgrade (Site administration → Notifications, or '
                . 'php admin/cli/upgrade.php).');
        }
    }

    /**
     * Grade the given answers against every question in the attempt's quiz, mark the
     * attempt finished, update the gradebook and return the score payload.
     *
     * @param array $submitted questionid => answer (int or array of ints)
     */
    private static function grade_and_finish(\stdClass $attemptrow, int $userid, array $submitted): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $attemptrow->quiz], '*', MUST_EXIST);
        [$cm, $context, $structure] = self::quiz_structure((int)$quiz->id);

        $results   = [];
        $sumgrades = 0.0;
        $maxscore  = 0.0;

        foreach ($structure as $row) {
            $q = self::struct_to_q($row);
            if (!$q) { continue; }
            $maxmark = (float)$row->maxmark;
            $maxscore += $maxmark;
            $answer  = $submitted[(int)$q->id] ?? null;

            list($mark, $correct) = self::grade_one($q, $answer, $maxmark);

            $sumgrades += $mark;
            $results[] = [
                'questionid' => (int)$q->id,
                'type'       => $q->qtype,
                'mark'       => round($mark, 2),
                'max_mark'   => round($maxmark, 2),
                'correct'    => $correct,
            ];
        }

        // Mark attempt as finished.
        $now = time();
        $DB->update_record('quiz_attempts', (object)[
            'id'           => $attemptrow->id,
            'state'        => 'finished',
            'timefinish'   => $now,
            'timemodified' => $now,
            'sumgrades'    => $sumgrades,
        ]);

        // Update gradebook. quiz_save_best_grade() was removed in Moodle 4.x/5.x;
        // the grade calculator now owns this.
        $quizobj = quiz_settings::create($quiz->id, $userid);
        $quizobj->get_grade_calculator()->recompute_final_grade($userid);

        return [
            'attemptid' => (int)$attemptrow->id,
            'state'     => 'finished',
            'score'     => round($sumgrades, 2),
            'max_score' => round((float)$maxscore, 2),
            'percent'   => $maxscore > 0 ? round(($sumgrades / $maxscore) * 100, 1) : 0,
            'results'   => $results,
        ];
    }

    /**
     * Grade a single question. Returns [mark, correct].
     *
     * @param mixed $answer answer row id (int) or array of ids (multi-answer MCQ)
     */
    private static function grade_one(\stdClass $q, $answer, float $maxmark): array {
        global $DB;

        $mark    = 0.0;
        $correct = false;

        if ($answer !== null && in_array($q->qtype, ['multichoice', 'truefalse'])) {
            $allAnswers = array_values($DB->get_records('question_answers', ['question' => $q->id], 'id ASC'));

            if ($q->qtype === 'truefalse' || !is_array($answer)) {
                // Single answer: find the fraction for the chosen answer id.
                foreach ($allAnswers as $a) {
                    if ((int)$a->id === (int)$answer) {
                        $frac    = (float)$a->fraction;
                        $mark    = max(0, $frac * $maxmark);
                        $correct = $frac >= 1.0;
                        break;
                    }
                }
            } else {
                // Multi-answer MCQ: sum fractions of chosen answers.
                $frac = 0.0;
                foreach ($allAnswers as $a) {
                    if (in_array((int)$a->id, array_map('intval', $answer))) {
                        $frac += (float)$a->fraction;
                    }
                }
                $mark    = max(0, round($frac * $maxmark, 5));
                $correct = $frac >= 1.0;
            }
        }

        return [$mark, $correct];
    }
}
