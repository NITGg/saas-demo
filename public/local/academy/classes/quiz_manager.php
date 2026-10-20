<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

use local_academy\local\quiz_native;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use mod_quiz\question\bank\qbank_helper;

/**
 * Quiz API for the Academy platform.
 *
 * Covers: list quizzes, get quiz questions (structured JSON),
 * start attempt, submit answers, review attempt.
 *
 * Every core question type is answerable natively (see local\quiz_native::TYPES).
 * Answers are stored in the attempt's question usage and graded by Moodle's question
 * engine, so scores, the gradebook and the web review all match the website.
 * Unsupported types appear with supported=false: open the quiz in a webview.
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
     * The questions of a started attempt, ready for the app to draw natively.
     *
     * Unlike get_quiz(), this is the attempt's own copy: calculated questions carry the
     * numbers this student got, choices are in this attempt's shuffled order and random
     * slots hold the question that was picked. Each question also carries `response`,
     * the answer saved so far (same format save_quiz_answer takes), so the app can resume.
     */
    public static function get_attempt_questions(int $attemptid, int $userid, bool $is_admin = false): array {
        $attemptobj = self::load_attempt($attemptid, $userid, $is_admin);
        $native = self::native();

        $questions = [];
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $data = self::attempt_question($native, $qa, (int) $slot, (int) $attemptobj->get_question_page($slot));
            if ($data['supported']) {
                $data['response'] = $native->from_response($qa, $qa->get_last_qt_data());
            }
            $questions[] = $data;
        }

        $attempt = $attemptobj->get_attempt();
        $quiz = $attemptobj->get_quiz();
        return [
            'attemptid'      => (int) $attempt->id,
            'quizid'         => (int) $quiz->id,
            'quiz_name'      => $quiz->name,
            'attempt_number' => (int) $attempt->attempt,
            'state'          => $attempt->state,
            'timestart'      => (int) $attempt->timestart,
            'timelimit'      => (int) $quiz->timelimit,
            'questions'      => $questions,
        ];
    }

    /**
     * Submit answers and finish a quiz attempt.
     *
     * $answers: [{"questionid": 101, "answer": <answer>}, ...] — the answer format of each
     * question type is the one save_answer() takes. Answers already saved stay unless
     * this call sends a new one for the same question.
     */
    public static function submit_attempt(int $attemptid, int $userid, array $answers): array {
        self::require_open_attempt($attemptid, $userid);
        foreach ($answers as $a) {
            if (!is_array($a) || !isset($a['questionid'])) {
                throw new \moodle_exception('invalidquizanswer', 'local_academy', '', 'answers: each item needs questionid');
            }
            self::save_answer($attemptid, $userid, (int) $a['questionid'], $a['answer'] ?? null, (int) ($a['slot'] ?? 0));
        }
        return self::finish_attempt($attemptid, $userid);
    }

    /**
     * Save (or update) the answer to a single question WITHOUT finishing the attempt.
     *
     * @param mixed $answer the answer in the question type's app format (docs/MOBILE_API.md §3.6);
     *                      null clears it
     * @param int $slot optional slot, for a quiz that uses the same question twice
     */
    public static function save_answer(int $attemptid, int $userid, int $questionid, $answer, int $slot = 0): array {
        global $DB;
        self::require_quiz_lib();

        $attemptrow = self::require_open_attempt($attemptid, $userid);
        $quba = \question_engine::load_questions_usage_by_activity($attemptrow->uniqueid);
        $slot = self::slot_for_question($quba, $questionid, $slot);
        $qa = $quba->get_question_attempt($slot);

        if (!quiz_native::is_supported($qa->get_question())) {
            throw new \moodle_exception('invalidquizanswer', 'local_academy', '',
                $qa->get_question()->qtype->name() . ': answer this question in the webview');
        }
        $response = self::native()->to_response($qa, $answer);

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $quba->process_action($slot, $response, $now);
        \question_engine::save_questions_usage_by_activity($quba);
        $DB->set_field('quiz_attempts', 'timemodified', $now, ['id' => $attemptid]);
        $transaction->allow_commit();

        return [
            'attemptid'  => $attemptid,
            'questionid' => $questionid,
            'slot'       => $slot,
            'saved'      => true,
            'state'      => $attemptrow->state,
        ];
    }

    /**
     * Submit all questions: grade every saved answer and finish the attempt.
     */
    public static function finish_attempt(int $attemptid, int $userid): array {
        self::require_quiz_lib();

        $attemptrow = self::require_open_attempt($attemptid, $userid);
        self::apply_legacy_answers($attemptrow);

        $now = time();
        $attemptobj = quiz_attempt::create($attemptid);
        $attemptobj->process_submit($now, false);
        $attemptobj->process_grade_submission($now);

        $attemptobj = quiz_attempt::create($attemptid);
        [$score, $maxscore, $results] = self::attempt_marks($attemptobj);
        return [
            'attemptid'     => (int) $attemptid,
            'state'         => $attemptobj->get_attempt()->state,
            'score'         => $score,
            'max_score'     => $maxscore,
            'percent'       => $score !== null && $maxscore > 0 ? round(($score / $maxscore) * 100, 1) : null,
            'needs_grading' => $score === null,
            'results'       => $results,
        ];
    }

    /**
     * Review an attempt.
     *
     * Marks, correctness and right answers follow the quiz's own review options for the
     * student (Settings → Review options); a platform admin always sees everything.
     */
    public static function get_attempt(int $attemptid, int $userid, bool $is_admin = false): array {
        $attemptobj = self::load_attempt($attemptid, $userid, $is_admin);
        $attempt = $attemptobj->get_attempt();
        $quiz = $attemptobj->get_quiz();
        $native = self::native();

        $options = $attemptobj->get_display_options(true);
        $showmarks = $is_admin || $options->marks >= \question_display_options::MARK_AND_MAX;
        $showcorrect = $is_admin || $options->correctness;
        $showright = $is_admin || $options->rightanswer;

        $maxscore = 0.0;
        $questions = [];
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();
            $maxmark = (float) $qa->get_max_mark();
            $maxscore += $maxmark;

            $data = self::attempt_question($native, $qa, (int) $slot, (int) $attemptobj->get_question_page($slot));
            $data['max_mark'] = round($maxmark, 2);
            $data['response'] = $data['supported'] ? $native->from_response($qa, $qa->get_last_qt_data()) : null;
            $data['response_summary'] = $qa->get_response_summary();
            $data['state'] = (string) $qa->get_state();
            if ($showmarks) {
                $data['mark'] = $qa->get_mark() === null ? null : round((float) $qa->get_mark(), 2);
            }
            if ($showcorrect) {
                $data['correct'] = $qa->get_state() === \question_state::$gradedright;
            }
            if ($showright) {
                $data['right_answer'] = $qa->get_right_answer_summary();
                $data['correct_response'] = $data['supported']
                    ? $native->from_response($qa, $question->get_correct_response() ?? []) : null;
                if (in_array($question->qtype->name(), ['multichoice', 'truefalse'], true)) {
                    // Kept for apps written against the first version of this endpoint.
                    $data['correct_answers'] = self::legacy_correct_answers($question);
                    self::dedupe_question_images($data, 'correct_answers');
                }
            }
            $questions[] = $data;
        }

        $score = self::attempt_score($attempt);
        $showscore = $showmarks && $score !== null;
        return [
            'attemptid'      => (int) $attempt->id,
            'quizid'         => (int) $quiz->id,
            'quiz_name'      => $quiz->name,
            'attempt_number' => (int) $attempt->attempt,
            'state'          => $attempt->state,
            'timestart'      => (int) $attempt->timestart,
            'timefinish'     => (int) $attempt->timefinish,
            'score'          => $showscore ? $score : null,
            'max_score'      => round($maxscore, 2),
            'percent'        => $showscore && $maxscore > 0 ? round(($score / $maxscore) * 100, 1) : null,
            'needs_grading'  => $attempt->state === quiz_attempt::FINISHED && $score === null,
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
            'supported'   => self::type_supported($q),
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
     * The quiz library and the question engine (api.php loads neither).
     */
    private static function require_quiz_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    }

    /**
     * Is this bank question answerable natively? (get_quiz() runs before any attempt.)
     */
    private static function type_supported(\stdClass $q): bool {
        self::require_quiz_lib();
        if (!in_array($q->qtype, quiz_native::TYPES, true)) {
            return false;
        }
        if ($q->qtype !== 'essay') {
            return true;
        }
        try {
            return quiz_native::is_supported(\question_bank::load_question((int) $q->id));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The converter, wired to this API's text + token-authorised file URLs.
     */
    private static function native(): quiz_native {
        return new quiz_native(
            function (string $html, int $contextid, string $component, string $filearea, int $itemid, int $questionid) {
                return self::content_fields($html, $contextid, $filearea, $itemid, $questionid, $component ?: 'question');
            },
            function (int $contextid, string $component, string $filearea, int $itemid) {
                return self::area_image($contextid, $component, $filearea, $itemid);
            }
        );
    }

    /**
     * Shared fields of one question inside an attempt (get_attempt_questions / get_attempt).
     */
    private static function attempt_question(quiz_native $native, \question_attempt $qa, int $slot, int $page): array {
        $question = $qa->get_question();
        $content = self::content_fields($question->questiontext, (int) $question->contextid, 'questiontext',
            (int) $question->id, (int) $question->id);
        $supported = quiz_native::is_supported($question);
        $data = [
            'slot'        => $slot,
            // 0-based quiz page, as the website lays the attempt out: a reading passage
            // (description) and the questions about it share one page.
            'page'        => $page,
            'questionid'  => (int) $question->id,
            'type'        => $question->qtype->name(),
            'text'        => $content['text'],
            'images'      => $content['images'],
            'defaultmark' => (float) $question->defaultmark,
            'max_mark'    => round((float) $qa->get_max_mark(), 2),
            'supported'   => $supported,
        ];
        if ($supported) {
            $data += $native->render($qa);
            self::dedupe_question_images($data);
        }
        return $data;
    }

    /**
     * Load an attempt the user may look at (their own, or any for an admin).
     */
    private static function load_attempt(int $attemptid, int $userid, bool $is_admin): quiz_attempt {
        self::require_quiz_lib();
        $attemptobj = quiz_attempt::create($attemptid);
        if (!$is_admin && (int) $attemptobj->get_userid() !== $userid) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        return $attemptobj;
    }

    /**
     * The attempt slot holding a question. $slot picks one when the question is used twice.
     */
    private static function slot_for_question(\question_usage_by_activity $quba, int $questionid, int $slot): int {
        foreach ($quba->get_slots() as $candidate) {
            if ($slot && (int) $candidate !== $slot) {
                continue;
            }
            if ((int) $quba->get_question($candidate, false)->id === $questionid) {
                return (int) $candidate;
            }
        }
        throw new \moodle_exception('invalidquestionid', 'question');
    }

    /**
     * Per-question marks of a graded attempt: [score, max score, results[]].
     */
    private static function attempt_marks(quiz_attempt $attemptobj): array {
        $maxscore = 0.0;
        $results = [];
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $maxmark = (float) $qa->get_max_mark();
            $maxscore += $maxmark;
            $state = $qa->get_state();
            $results[] = [
                'slot'           => (int) $slot,
                'questionid'     => (int) $qa->get_question()->id,
                'type'           => $qa->get_question()->qtype->name(),
                'mark'           => $qa->get_mark() === null ? null : round((float) $qa->get_mark(), 2),
                'max_mark'       => round($maxmark, 2),
                'correct'        => $state === \question_state::$gradedright,
                'state'          => (string) $state,
                // An essay waits for the teacher: its mark is null until graded by hand.
                'needs_grading'  => $state === \question_state::$needsgrading,
            ];
        }
        return [self::attempt_score($attemptobj->get_attempt()), round($maxscore, 2), $results];
    }

    /**
     * The attempt's total, or null while a question (an essay) waits for the teacher:
     * Moodle leaves sumgrades empty until then and shows "Not yet graded".
     */
    private static function attempt_score(\stdClass $attempt): ?float {
        return $attempt->sumgrades === null ? null : round((float) $attempt->sumgrades, 2);
    }

    /**
     * Answers saved by the first version of save_quiz_answer lived in academy_quiz_answers,
     * outside the question engine. Carry them into the attempt before it is graded, unless
     * the question has been answered through the engine since.
     */
    private static function apply_legacy_answers(\stdClass $attemptrow): void {
        global $DB;
        $legacy = self::saved_answers_map((int) $attemptrow->id);
        if (!$legacy) {
            return;
        }
        $quba = \question_engine::load_questions_usage_by_activity($attemptrow->uniqueid);
        $native = self::native();
        $changed = false;
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            $questionid = (int) $qa->get_question()->id;
            // Fields starting with "_" are the engine's own state (e.g. the choice order), not an answer.
            $answered = array_filter(array_keys($qa->get_last_qt_data()), fn($name) => $name[0] !== '_');
            if (!array_key_exists($questionid, $legacy) || $answered
                    || !quiz_native::is_supported($qa->get_question())) {
                continue;
            }
            try {
                $quba->process_action($slot, $native->to_response($qa, $legacy[$questionid]));
                $changed = true;
            } catch (\moodle_exception $e) {
                // An old answer that no longer fits the question is dropped, not fatal.
                debugging('academy quiz: legacy answer skipped for question ' . $questionid, DEBUG_DEVELOPER);
            }
        }
        if ($changed) {
            \question_engine::save_questions_usage_by_activity($quba);
        }
        $DB->delete_records('academy_quiz_answers', ['attemptid' => $attemptrow->id]);
    }

    /**
     * The first review format: every option of a multichoice/truefalse with its correct flag.
     */
    private static function legacy_correct_answers(\question_definition $question): array {
        global $DB;
        $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id ASC');
        $out = [];
        foreach ($answers as $a) {
            $c = self::content_fields($a->answer, (int) $question->contextid, 'answer', (int) $a->id, (int) $question->id);
            $out[] = ['id' => (int) $a->id, 'text' => $c['text'], 'images' => $c['images'], 'correct' => (float) $a->fraction > 0];
        }
        return $out;
    }

    /**
     * The first image in a question-type file area (drag-and-drop backgrounds and drag
     * images) as a token-authorised URL plus its pixel size, or null when the area is empty.
     * Drop-zone and marker coordinates are in these pixels.
     */
    private static function area_image(int $contextid, string $component, string $filearea, int $itemid): ?array {
        $fs = get_file_storage();
        foreach ($fs->get_area_files($contextid, $component, $filearea, $itemid, 'id', false) as $file) {
            $info = $file->get_imageinfo() ?: [];
            $pluginfile = \moodle_url::make_pluginfile_url($contextid, $component, $filearea, $itemid,
                $file->get_filepath(), $file->get_filename());
            return [
                'url'    => self::local_file_url($pluginfile->out(false), self::area_questionid($component, $filearea, $itemid)),
                'width'  => (int) ($info['width'] ?? 0),
                'height' => (int) ($info['height'] ?? 0),
            ];
        }
        return null;
    }

    /**
     * The question a qtype file area belongs to (qfile.php authorises by question).
     */
    private static function area_questionid(string $component, string $filearea, int $itemid): int {
        global $DB;
        if ($filearea === 'dragimage') {
            return (int) $DB->get_field('qtype_ddimageortext_drags', 'questionid', ['id' => $itemid]);
        }
        return $itemid;
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
    private static function content_fields($html, int $contextid, string $filearea, int $itemid, int $questionid,
            string $component = 'question'): array {
        global $CFG;
        require_once($CFG->dirroot . '/lib/filelib.php');

        $html = (string)$html;
        if ($contextid) {
            $html = \file_rewrite_pluginfile_urls(
                $html, 'pluginfile.php', $contextid, $component, $filearea, $itemid);
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
        $component = clean_param($segs[1], PARAM_COMPONENT);
        $filearea = clean_param($segs[2], PARAM_ALPHANUMEXT);
        $fileitem = (int)$segs[3];
        $filename = rawurldecode(implode('/', array_slice($segs, 4)));

        $params = [
            'questionid' => $questionid,
            'area'       => $filearea,
            'itemid'     => $fileitem,
            'file'       => $filename,
            'token'      => self::$token,
        ];
        if ($component !== 'question') {
            // Drag-and-drop images and matching stems live in their question type's own areas.
            $params['component'] = $component;
        }
        return (new \moodle_url('/local/academy/qfile.php', $params))->out(false);
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
        // Moodle 5 adds `submitted` (handed in, waiting to be graded) before `finished`.
        if (in_array($attemptrow->state, [quiz_attempt::FINISHED, quiz_attempt::SUBMITTED, quiz_attempt::ABANDONED], true)) {
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
}
