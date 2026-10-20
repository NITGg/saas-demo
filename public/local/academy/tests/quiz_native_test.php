<?php
namespace local_academy;

use core_question\local\bank\question_version_status;
use local_academy\local\quiz_native;
use mod_quiz\quiz_attempt;

/**
 * The mobile quiz API answering every core question type natively.
 *
 * Each test builds a real quiz, starts an attempt as an enrolled student, answers
 * through quiz_manager (what api.php calls) and checks what Moodle's question engine
 * graded — never our own idea of the right answer.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_native::class)]
final class quiz_native_test extends \advanced_testcase {

    /** Every core type, as [qtype, test fixture]. */
    private const ALL_TYPES = [
        'tf'      => ['truefalse', 'true'],
        'mc'      => ['multichoice', 'one_of_four'],
        'mcmulti' => ['multichoice', 'two_of_four'],
        'sa'      => ['shortanswer', 'frogtoad'],
        'num'     => ['numerical', 'pi'],
        'calc'    => ['calculated', 'sum'],
        'calcs'   => ['calculatedsimple', 'sumwithvariants'],
        'calcm'   => ['calculatedmulti', 'singleresponse'],
        'essay'   => ['essay', 'plain'],
        'match'   => ['match', 'foursubq'],
        'gaps'    => ['gapselect', 'missingchoiceno'],
        'ddwtos'  => ['ddwtos', 'fox'],
        'ddimage' => ['ddimageortext', 'xsection'],
        'marker'  => ['ddmarker', 'mkmap'],
        'order'   => ['ordering', 'moodle'],
        'cloze'   => ['multianswer', 'twosubq'],
        'info'    => ['description', 'info'],
    ];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    }

    public function test_every_core_type_is_native_and_full_marks_from_its_correct_answer(): void {
        [$quiz, $student] = $this->make_quiz(self::ALL_TYPES, true);
        $attempt = $this->start($quiz, $student);

        $payload = quiz_manager::get_attempt_questions($attempt, (int) $student->id);
        $types = array_column($payload['questions'], 'type');
        sort($types);
        $expected = array_merge(array_column(self::ALL_TYPES, 0), ['randomsamatch']);
        sort($expected);
        $this->assertSame($expected, $types);
        foreach ($payload['questions'] as $q) {
            $this->assertTrue($q['supported'], $q['type'] . ' should be native');
        }

        foreach ($this->correct_answers($attempt) as $questionid => [$type, $answer]) {
            if ($type === 'description') {
                continue;
            }
            quiz_manager::save_answer($attempt, (int) $student->id, $questionid,
                $type === 'essay' ? 'Once upon a time there was a frog.' : $answer);
        }
        $result = quiz_manager::finish_attempt($attempt, (int) $student->id);

        $this->assertSame('finished', $result['state']);
        foreach ($result['results'] as $r) {
            if ($r['type'] === 'essay') {
                $this->assertTrue($r['needs_grading']);
                $this->assertNull($r['mark']);
            } else if ($r['type'] !== 'description') {
                $this->assertTrue($r['correct'], $r['type'] . ' was not graded right: ' . $r['state']);
                $this->assertEquals($r['max_mark'], $r['mark'], $r['type']);
            }
        }
        // The essay waits for the teacher, so like Moodle there is no total yet.
        $this->assertTrue($result['needs_grading']);
        $this->assertNull($result['score']);
        $this->assertNull($result['percent']);
    }

    public function test_answers_written_from_the_payload_text_are_graded_right(): void {
        [$quiz, $student] = $this->make_quiz(array_intersect_key(self::ALL_TYPES,
            array_flip(['tf', 'mc', 'sa', 'num', 'match', 'gaps', 'ddwtos', 'order', 'cloze'])));
        $attempt = $this->start($quiz, $student);
        $q = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));

        $answers = [
            'truefalse'   => true,
            'multichoice' => $this->option_id($q['multichoice']['options'], 'One'),
            'shortanswer' => 'frog',
            'numerical'   => '3.14',
            'match'       => [
                $this->find($q['match']['stems'], 'frog')['id'] => $this->find($q['match']['choices'], 'amphibian')['id'],
                $this->find($q['match']['stems'], 'cat')['id']  => $this->find($q['match']['choices'], 'mammal')['id'],
                $this->find($q['match']['stems'], 'newt')['id'] => $this->find($q['match']['choices'], 'amphibian')['id'],
            ],
            'gapselect'   => $this->gap_answer($q['gapselect'], ['cat', 'mat']),
            'ddwtos'      => $this->gap_answer($q['ddwtos'], ['quick', 'fox', 'lazy']),
            'ordering'    => array_map(fn($w) => $this->find($q['ordering']['items'], $w)['key'],
                ['Modular', 'Object', 'Oriented', 'Dynamic', 'Learning', 'Environment']),
            'multianswer' => [
                '1' => 'Owl',
                '2' => $this->option_id($q['multianswer']['subquestions'][1]['options'], 'Pussy-cat'),
            ],
        ];
        foreach ($answers as $type => $answer) {
            quiz_manager::save_answer($attempt, (int) $student->id, $q[$type]['questionid'], $answer);
        }
        $result = quiz_manager::finish_attempt($attempt, (int) $student->id);

        foreach ($result['results'] as $r) {
            $this->assertTrue($r['correct'], $r['type'] . ': ' . $r['state']);
        }
        $this->assertFalse($result['needs_grading']);
        $this->assertEquals($result['max_score'], $result['score']);
        $this->assertEquals(100, $result['percent']);
    }

    public function test_wrong_and_partial_answers_lose_marks(): void {
        [$quiz, $student] = $this->make_quiz(array_intersect_key(self::ALL_TYPES, array_flip(['mc', 'sa', 'order'])));
        $attempt = $this->start($quiz, $student);
        $q = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));

        quiz_manager::save_answer($attempt, (int) $student->id, $q['multichoice']['questionid'],
            $this->option_id($q['multichoice']['options'], 'Two'));
        quiz_manager::save_answer($attempt, (int) $student->id, $q['shortanswer']['questionid'], 'toad');
        quiz_manager::save_answer($attempt, (int) $student->id, $q['ordering']['questionid'],
            array_reverse(array_column($this->find_all_in_order($q['ordering']['items'],
                ['Modular', 'Object', 'Oriented', 'Dynamic', 'Learning', 'Environment']), 'key')));
        $marks = array_column(quiz_manager::finish_attempt($attempt, (int) $student->id)['results'], null, 'type');

        $this->assertEquals(0, $marks['multichoice']['mark']);
        $this->assertSame('gradedpartial', $marks['shortanswer']['state']);   // Toad is worth 80%.
        $this->assertEquals(0.8, $marks['shortanswer']['mark']);
        $this->assertFalse($marks['ordering']['correct']);
    }

    /**
     * @param string $key which question
     * @param mixed $answer an answer that does not fit it
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_answers')]
    public function test_answers_that_do_not_fit_are_refused(string $key, $answer): void {
        [$quiz, $student] = $this->make_quiz([$key => self::ALL_TYPES[$key]]);
        $attempt = $this->start($quiz, $student);
        $question = quiz_manager::get_attempt_questions($attempt, (int) $student->id)['questions'][0];

        try {
            quiz_manager::save_answer($attempt, (int) $student->id, $question['questionid'], $answer);
            $this->fail('accepted an answer that does not fit');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidquizanswer', $e->errorcode);
        }
    }

    /**
     * @return array
     */
    public static function invalid_answers(): array {
        return [
            'option from another question' => ['mc', 999999],
            'true/false in words'          => ['tf', 'maybe'],
            'number sent as an object'     => ['num', ['x' => 1]],
            'gap that does not exist'      => ['ddwtos', ['9' => 1]],
            'choice from another group'    => ['ddwtos', ['1' => 99]],
            'ordering with an item missing' => ['order', ['nope']],
            'marker placed too often'      => ['marker', ['1' => [[1, 1], [2, 2]]]],
            'marker point not x,y'         => ['marker', ['1' => [[1]]]],
            'cloze part that does not exist' => ['cloze', ['7' => 'Owl']],
        ];
    }

    public function test_saved_answers_come_back_for_resuming(): void {
        [$quiz, $student] = $this->make_quiz(array_intersect_key(self::ALL_TYPES,
            array_flip(['mcmulti', 'num', 'ddwtos', 'order', 'marker', 'cloze'])));
        $attempt = $this->start($quiz, $student);
        $q = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));

        $sent = [
            'multichoice'   => array_slice(array_column($q['multichoice']['options'], 'id'), 0, 2),
            'numerical'     => '3.1',
            'ddwtos'        => ['1' => $q['ddwtos']['groups'][1][0]['value']],
            'ordering'      => array_reverse(array_column($q['ordering']['items'], 'key')),
            'ddmarker'      => ['2' => [[10, 20], [30, 40]]],
            'multianswer'   => ['1' => 'Dog'],
        ];
        foreach ($sent as $type => $answer) {
            quiz_manager::save_answer($attempt, (int) $student->id, $q[$type]['questionid'], $answer);
        }
        $back = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));
        foreach ($sent as $type => $answer) {
            $this->assertEquals($answer, $back[$type]['response'], $type);
        }

        // null clears an answer.
        quiz_manager::save_answer($attempt, (int) $student->id, $q['numerical']['questionid'], null);
        $back = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));
        $this->assertNull($back['numerical']['response']);
    }

    public function test_payload_shows_the_attempt_and_hides_the_answers(): void {
        [$quiz, $student] = $this->make_quiz(array_intersect_key(self::ALL_TYPES,
            array_flip(['calcm', 'calc', 'marker', 'ddimage', 'gaps'])));
        $attempt = $this->start($quiz, $student);
        quiz_manager::set_token('tok');
        $q = $this->by_type(quiz_manager::get_attempt_questions($attempt, (int) $student->id));

        // Calculated questions carry this attempt's numbers, not the {a} placeholders.
        // ("What is {a} + {b}?" — the core calculated/sum fixture saves an empty text, so check calculatedmulti.)
        $this->assertStringNotContainsString('{a}', $q['calculated']['text']);
        $this->assertMatchesRegularExpression('/^What is \d+ \+ \d+\?$/', $q['calculatedmulti']['text']);
        foreach ($q['calculatedmulti']['options'] as $option) {
            $this->assertStringNotContainsString('{', $option['text']);
        }

        // The marker drop zones are the answer: only the markers go out.
        $this->assertArrayNotHasKey('drops', $q['ddmarker']);
        $this->assertSame(['OU', 'Railway station'], array_column($q['ddmarker']['markers'], 'text'));
        $this->assertSame([1, 3], array_column($q['ddmarker']['markers'], 'limit'));
        $this->assertGreaterThan(0, $q['ddmarker']['background']['width']);
        $this->assertStringContainsString('component=qtype_ddmarker', $q['ddmarker']['background']['url']);
        $this->assertStringContainsString('token=tok', $q['ddmarker']['background']['url']);

        // Drop zones of drag-onto-image are visible boxes, so they do go out, with coordinates.
        $this->assertNotEmpty($q['ddimageortext']['drops']);
        $this->assertArrayHasKey('left', $q['ddimageortext']['drops'][0]);

        // Gap text keeps its spaces around the gaps.
        $text = array_values(array_filter($q['gapselect']['segments'], fn($s) => $s['type'] === 'text'));
        $this->assertSame('The ', $text[0]['text']);
        $this->assertSame(' sat on the ', $text[1]['text']);

        // Nothing in the payload marks a choice as correct.
        $this->assertStringNotContainsString('"correct"', json_encode($q));
    }

    public function test_another_student_cannot_touch_the_attempt_and_a_finished_one_is_closed(): void {
        [$quiz, $student, $course] = $this->make_quiz(['sa' => self::ALL_TYPES['sa']]);
        $attempt = $this->start($quiz, $student);
        $question = quiz_manager::get_attempt_questions($attempt, (int) $student->id)['questions'][0];
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');

        foreach ([
            fn() => quiz_manager::get_attempt_questions($attempt, (int) $other->id),
            fn() => quiz_manager::save_answer($attempt, (int) $other->id, $question['questionid'], 'frog'),
            fn() => quiz_manager::finish_attempt($attempt, (int) $other->id),
        ] as $call) {
            $this->assert_error('notyourattempt', $call);
        }

        quiz_manager::finish_attempt($attempt, (int) $student->id);
        $this->assert_error('attemptalreadyclosed',
            fn() => quiz_manager::save_answer($attempt, (int) $student->id, $question['questionid'], 'frog'));
    }

    public function test_answers_saved_by_the_old_endpoint_are_graded_on_finish(): void {
        global $DB;
        [$quiz, $student] = $this->make_quiz(['mc' => self::ALL_TYPES['mc']]);
        $attempt = $this->start($quiz, $student);
        $question = quiz_manager::get_attempt_questions($attempt, (int) $student->id)['questions'][0];
        $DB->insert_record('academy_quiz_answers', (object) [
            'attemptid' => $attempt, 'questionid' => $question['questionid'], 'userid' => $student->id,
            'answer' => json_encode($this->option_id($question['options'], 'One')),
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $result = quiz_manager::finish_attempt($attempt, (int) $student->id);

        $this->assertTrue($result['results'][0]['correct']);
        $this->assertFalse($DB->record_exists('academy_quiz_answers', ['attemptid' => $attempt]));
    }

    public function test_review_follows_the_quiz_review_options_for_students(): void {
        [$quiz, $student] = $this->make_quiz(['sa' => self::ALL_TYPES['sa']], false, ['reviewrightanswer' => 0]);
        $attempt = $this->start($quiz, $student);
        $question = quiz_manager::get_attempt_questions($attempt, (int) $student->id)['questions'][0];
        quiz_manager::save_answer($attempt, (int) $student->id, $question['questionid'], 'toad');
        quiz_manager::finish_attempt($attempt, (int) $student->id);

        $mine = quiz_manager::get_attempt($attempt, (int) $student->id)['questions'][0];
        $this->assertSame('toad', $mine['response']);
        $this->assertEquals(0.8, $mine['mark']);
        $this->assertArrayNotHasKey('right_answer', $mine);
        $this->assertArrayNotHasKey('correct_response', $mine);

        $admin = quiz_manager::get_attempt($attempt, 0, true)['questions'][0];
        $this->assertSame('frog', $admin['correct_response']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * A quiz in a course with an enrolled student, one slot per spec.
     *
     * @return array [quiz, student, course]
     */
    private function make_quiz(array $specs, bool $randommatch = false, array $quizfields = []): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(
            ['course' => $course->id, 'questionsperpage' => 0, 'grade' => 100, 'sumgrades' => 0,
             'preferredbehaviour' => 'deferredfeedback']);
        // The generator replaces review settings with its own defaults, so set them afterwards.
        foreach ($quizfields as $field => $value) {
            $DB->set_field('quiz', $field, $value, ['id' => $quiz->id]);
        }
        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        // The drag-and-drop fixtures upload their images through a user's draft area.
        $this->setAdminUser();

        foreach ($specs as [$qtype, $which]) {
            $question = $qgen->create_question($qtype, $which, ['category' => $cat->id]);
            if (in_array($qtype, ['calculated', 'calculatedmulti'], true)) {
                $this->seed_datasets((int) $question->id);
            }
            quiz_add_quiz_question($question->id, $quiz);
        }
        if ($randommatch) {
            // Random matching draws two short-answer questions from its category.
            $qgen->create_question('shortanswer', 'frogtoad', ['category' => $cat->id]);
            $qgen->create_question('shortanswer', 'frogtoad', ['category' => $cat->id,
                'name' => 'Pet', 'questiontext' => ['text' => 'Name a pet.', 'format' => FORMAT_HTML],
                'answer' => ['dog', 'cat', '*']]);
            quiz_add_quiz_question($this->make_random_match($cat)->id, $quiz);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return [$quiz, $student, $course];
    }

    /**
     * The calculated fixtures are saved with no sets of numbers, and Moodle cannot start an
     * attempt at a calculated question without one (min() on an empty list in
     * least_used_strategy). Give every wildcard two values, as core's own test does.
     */
    private function seed_datasets(int $questionid): void {
        global $DB;
        $definitions = $DB->get_fieldset_sql("
            SELECT qdd.id
              FROM {question_dataset_definitions} qdd
              JOIN {question_datasets} qd ON qd.datasetdefinition = qdd.id
             WHERE qd.question = ?", [$questionid]);
        foreach ($definitions as $i => $definitionid) {
            $DB->set_field('question_dataset_definitions', 'itemcount', 2, ['id' => $definitionid]);
            foreach ([1, 2] as $itemnumber) {
                $DB->insert_record('question_dataset_items',
                    ['definition' => $definitionid, 'itemnumber' => $itemnumber, 'value' => 3 + $i + $itemnumber]);
            }
        }
        \question_bank::notify_question_edited($questionid);
    }

    /**
     * Random short-answer matching has no database fixture in core, so save one here.
     */
    private function make_random_match(\stdClass $cat): \stdClass {
        $feedback = ['text' => '', 'format' => FORMAT_HTML];
        $question = (object) ['qtype' => 'randomsamatch', 'category' => $cat->id, 'createdby' => 0,
            'idnumber' => null, 'status' => question_version_status::QUESTION_STATUS_READY];
        $form = (object) [
            'category' => $cat->id . ',' . $cat->contextid, 'name' => 'Random matching',
            'questiontext' => ['text' => 'Match each question to its answer.', 'format' => FORMAT_HTML],
            'generalfeedback' => $feedback, 'defaultmark' => 1, 'penalty' => 0.3333333,
            'choose' => 2, 'subcats' => 0, 'shownumcorrect' => 0,
            'correctfeedback' => $feedback, 'partiallycorrectfeedback' => $feedback, 'incorrectfeedback' => $feedback,
            'status' => question_version_status::QUESTION_STATUS_READY,
        ];
        return \question_bank::get_qtype('randomsamatch')->save_question($question, $form);
    }

    /** Start an attempt as the student; returns the attempt id. */
    private function start(\stdClass $quiz, \stdClass $student): int {
        $this->setUser($student);
        return quiz_manager::start_attempt((int) $quiz->id, (int) $student->id)['attemptid'];
    }

    /**
     * Each question's correct answer in app format, from the question engine itself.
     *
     * @return array questionid => [qtype, answer]
     */
    private function correct_answers(int $attemptid): array {
        $attemptobj = quiz_attempt::create($attemptid);
        $native = new quiz_native(fn() => ['text' => '', 'images' => []], fn() => null);
        $out = [];
        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();
            $out[(int) $question->id] = [$question->qtype->name(),
                $native->from_response($qa, $question->get_correct_response() ?? [])];
        }
        return $out;
    }

    private function by_type(array $payload): array {
        return array_column($payload['questions'], null, 'type');
    }

    private function find(array $rows, string $text): array {
        foreach ($rows as $row) {
            if (trim($row['text']) === $text) {
                return $row;
            }
        }
        $this->fail("No row with text '$text' in " . json_encode($rows));
    }

    private function find_all_in_order(array $rows, array $texts): array {
        return array_map(fn($t) => $this->find($rows, $t), $texts);
    }

    private function option_id(array $options, string $text): int {
        return $this->find($options, $text)['id'];
    }

    /** One choice text per gap, in gap order → {place: value}. */
    private function gap_answer(array $question, array $texts): array {
        $answer = [];
        $gaps = array_values(array_filter($question['segments'], fn($s) => $s['type'] === 'gap'));
        foreach ($gaps as $i => $gap) {
            $answer[(string) $gap['place']] = $this->find($question['groups'][$gap['group']], $texts[$i])['value'];
        }
        return $answer;
    }

    private function assert_error(string $code, callable $call): void {
        try {
            $call();
            $this->fail("expected $code");
        } catch (\moodle_exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }
}
