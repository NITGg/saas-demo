<?php
namespace local_academy;

use quizaccess_seb\seb_quiz_settings;
use quizaccess_seb\settings_provider;

/**
 * Quiz access rules in the mobile quiz API: get_quiz_access and the Safe Exam Browser
 * block on start_quiz_attempt and the other attempt calls.
 *
 * Core mod_quiz_get_quiz_access_information fails for an SEB quiz because quizaccess_seb
 * puts HTML buttons into PARAM_TEXT fields; get_quiz_access must return plain text and the
 * SEB requirement as data.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_manager::class)]
final class quiz_access_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        set_config('showseblinks', 'seb,http', 'quizaccess_seb');
        set_config('downloadlink', 'https://safeexambrowser.org/download_en.html', 'quizaccess_seb');
    }

    public function test_client_config_seb_quiz_is_reported_as_plain_text_with_seb_data(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);

        $access = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id);

        $this->assert_plain_text($access);
        $this->assertContains('quizaccess_seb', $access['activerulenames']);
        $this->assertSame([get_string('clientrequiresseb', 'quizaccess_seb')], $access['preventaccessreasons']);
        $this->assertContains(get_string('sebrequired', 'quizaccess_seb'), $access['accessrules']);
        $this->assertTrue($access['canattempt']);
        $this->assertFalse($access['canstart']);

        $seb = $access['seb'];
        $this->assertTrue($seb['required']);
        $this->assertFalse($seb['canbypass']);
        $this->assertSame('client', $seb['mode']);
        $this->assertNull($seb['launchurl'], 'client mode has no quiz configuration to launch');
        $this->assertNull($seb['configurl']);
        $this->assertSame('https://safeexambrowser.org/download_en.html', $seb['downloadurl']);
        $this->assertStringEndsWith('/mod/quiz/view.php?id=' . $quiz->cmid, $seb['quizurl']);
    }

    public function test_config_key_seb_quiz_gives_launch_links_and_a_readable_reason(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);
        $this->setUser($student);

        $access = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id);

        $this->assert_plain_text($access);
        // The SEB rule's own reason is buttons only here; the app still gets a sentence.
        $this->assertSame([get_string('err_sebrequired', 'local_academy')], $access['preventaccessreasons']);
        $this->assertFalse($access['canstart']);
        $this->assertSame('manual', $access['seb']['mode']);
        $this->assertSame('sebs://www.example.com/moodle/mod/quiz/accessrule/seb/config.php?cmid=' . $quiz->cmid,
            $access['seb']['launchurl']);
        $this->assertSame('https://www.example.com/moodle/mod/quiz/accessrule/seb/config.php?cmid=' . $quiz->cmid,
            $access['seb']['configurl']);
    }

    public function test_seb_links_follow_the_site_show_links_setting(): void {
        set_config('showseblinks', '', 'quizaccess_seb');
        set_config('downloadlink', '', 'quizaccess_seb');
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CONFIG_MANUALLY);
        $this->setUser($student);

        $seb = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id)['seb'];

        $this->assertTrue($seb['required']);
        $this->assertNull($seb['launchurl']);
        $this->assertNull($seb['configurl']);
        $this->assertNull($seb['downloadurl']);
    }

    public function test_student_cannot_start_a_seb_quiz_through_the_api(): void {
        global $DB;
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);

        try {
            quiz_manager::start_attempt((int) $quiz->id, (int) $student->id);
            $this->fail('An SEB quiz must not start in the app');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_sebrequired', $e->errorcode);
            $this->assertSame(['sebrequired', get_string('err_sebrequired', 'local_academy')],
                api\endpoint::describe($e));
        }
        $this->assertSame(0, $DB->count_records('quiz_attempts', ['quiz' => $quiz->id]));
    }

    public function test_teacher_who_can_bypass_seb_sees_it_and_can_start(): void {
        [$quiz, , $course] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $access = quiz_manager::get_quiz_access((int) $quiz->id, (int) $teacher->id);
        $this->assertFalse($access['seb']['required']);
        $this->assertTrue($access['seb']['canbypass']);
        $this->assertSame('client', $access['seb']['mode']);
        $this->assertSame([], $access['preventaccessreasons']);

        $attempt = quiz_manager::start_attempt((int) $quiz->id, (int) $teacher->id);
        $this->assertSame('inprogress', $attempt['state']);
    }

    public function test_normal_quiz_has_no_seb_and_starts(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_NO);
        $this->setUser($student);

        $access = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id);
        $this->assertFalse($access['seb']['required']);
        $this->assertSame('none', $access['seb']['mode']);
        $this->assertNotContains('quizaccess_seb', $access['activerulenames']);
        $this->assertTrue($access['canstart']);

        $this->assertSame('inprogress', quiz_manager::start_attempt((int) $quiz->id, (int) $student->id)['state']);
    }

    public function test_closed_quiz_is_refused_with_quizaccessprevented(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_NO, ['timeclose' => time() - HOURSECS]);
        $this->setUser($student);

        $access = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id);
        $this->assertFalse($access['canstart']);
        $this->assertNotEmpty($access['preventaccessreasons']);

        try {
            quiz_manager::start_attempt((int) $quiz->id, (int) $student->id);
            $this->fail('A closed quiz must not start');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_quizaccessprevented', $e->errorcode);
            $this->assertSame('quizaccessprevented', api\endpoint::describe($e)[0]);
        }
    }

    public function test_attempt_calls_stop_once_the_quiz_requires_seb(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_NO);
        $this->setUser($student);
        $attemptid = quiz_manager::start_attempt((int) $quiz->id, (int) $student->id)['attemptid'];
        $questionid = (int) quiz_manager::get_attempt_questions($attemptid, (int) $student->id)['questions'][0]['questionid'];

        $settings = new seb_quiz_settings(0, (object) [
            'quizid' => $quiz->id,
            'cmid' => $quiz->cmid,
            'requiresafeexambrowser' => settings_provider::USE_SEB_CLIENT_CONFIG,
        ]);
        $settings->create();

        $calls = [
            'get_attempt_questions' => fn() => quiz_manager::get_attempt_questions($attemptid, (int) $student->id),
            'save_answer' => fn() => quiz_manager::save_answer($attemptid, (int) $student->id, $questionid, true),
            'finish_attempt' => fn() => quiz_manager::finish_attempt($attemptid, (int) $student->id),
        ];
        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail($name . ' must refuse an SEB quiz');
            } catch (\moodle_exception $e) {
                $this->assertSame('err_sebrequired', $e->errorcode, $name);
            }
        }
    }

    public function test_android_exam_mode_takes_a_seb_quiz_end_to_end(): void {
        global $DB;
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);
        $uid = (int) $student->id;

        $seb = quiz_manager::get_quiz_access((int) $quiz->id, $uid)['seb'];
        $this->assertTrue($seb['appexam'], 'exam mode is on by default');
        $this->assertTrue($seb['appexamcanstart']);

        $started = quiz_manager::start_attempt((int) $quiz->id, $uid, true);
        $this->assertTrue($started['appexam']);
        $attemptid = $started['attemptid'];
        $this->assertTrue($DB->record_exists('academy_quiz_appexam', ['attemptid' => $attemptid]));

        $questions = quiz_manager::get_attempt_questions($attemptid, $uid)['questions'];
        quiz_manager::save_answer($attemptid, $uid, (int) $questions[0]['questionid'], true);
        $this->assertSame('finished', quiz_manager::finish_attempt($attemptid, $uid)['state']);

        $mine = quiz_manager::get_my_attempts((int) $quiz->id, $uid);
        $this->assertTrue($mine[0]['appexam']);
    }

    public function test_without_app_exam_a_seb_quiz_is_still_refused(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);
        $this->expect_seb_refusal(fn() => quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, false));
    }

    public function test_site_switch_off_refuses_app_exam(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);
        set_config('seb_android_app_exam', 0, 'local_multitopics');

        $seb = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id)['seb'];
        $this->assertFalse($seb['appexam']);
        $this->assertFalse($seb['appexamcanstart']);
        $this->expect_seb_refusal(fn() => quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, true));
    }

    public function test_exam_mode_attempt_stops_when_the_switch_is_turned_off(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);
        $attemptid = quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, true)['attemptid'];

        set_config('seb_android_app_exam', 0, 'local_multitopics');
        $this->expect_seb_refusal(fn() => quiz_manager::finish_attempt($attemptid, (int) $student->id));
    }

    public function test_app_exam_still_applies_the_other_rules(): void {
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG,
            ['timeclose' => time() - HOURSECS]);
        $this->setUser($student);

        $seb = quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id)['seb'];
        $this->assertTrue($seb['appexam']);
        $this->assertFalse($seb['appexamcanstart'], 'the quiz is closed');

        try {
            quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, true);
            $this->fail('A closed quiz must not start in exam mode');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_quizaccessprevented', $e->errorcode);
        }
    }

    public function test_app_exam_on_a_normal_quiz_is_a_normal_attempt(): void {
        global $DB;
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_NO);
        $this->setUser($student);

        $this->assertFalse(quiz_manager::get_quiz_access((int) $quiz->id, (int) $student->id)['seb']['appexam']);
        $started = quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, true);
        $this->assertFalse($started['appexam']);
        $this->assertSame(0, $DB->count_records('academy_quiz_appexam'));
    }

    public function test_teacher_attempt_is_not_marked_and_stays_the_teachers(): void {
        [$quiz, $student, $course] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        // A teacher (bypasses SEB) starts a normal attempt; it is not an exam-mode one.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $attemptid = quiz_manager::start_attempt((int) $quiz->id, (int) $teacher->id, true)['attemptid'];
        $this->assertFalse(quiz_manager::is_app_exam_attempt($attemptid));

        // The same attempt id given by the student is not theirs either way.
        $this->setUser($student);
        $this->expectException(\moodle_exception::class);
        quiz_manager::finish_attempt($attemptid, (int) $student->id);
    }

    public function test_review_page_marks_an_exam_mode_attempt(): void {
        global $PAGE;
        [$quiz, $student] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $this->setUser($student);
        $examattempt = quiz_manager::start_attempt((int) $quiz->id, (int) $student->id, true)['attemptid'];

        $PAGE->set_pagetype('mod-quiz-review');
        $_GET['attempt'] = $examattempt;
        local\app_exam_review::before_http_headers(new \core\hook\output\before_http_headers($PAGE->get_renderer('core')));
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertContains(get_string('appexamattempt', 'local_academy'), $messages);

        $_GET['attempt'] = $examattempt + 1000;
        local\app_exam_review::before_http_headers(new \core\hook\output\before_http_headers($PAGE->get_renderer('core')));
        $this->assertSame([], \core\notification::fetch());
        unset($_GET['attempt']);
    }

    public function test_user_not_enrolled_is_refused(): void {
        [$quiz] = $this->make_quiz(settings_provider::USE_SEB_CLIENT_CONFIG);
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notenrolled', 'local_academy'));
        quiz_manager::get_quiz_access((int) $quiz->id, (int) $stranger->id);
    }

    /**
     * A quiz with one true/false question and an enrolled student.
     *
     * @return array [quiz, student, course]
     */
    private function make_quiz(int $sebmode, array $fields = []): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance(array_merge([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100,
            'sumgrades' => 1,
            'seb_requiresafeexambrowser' => $sebmode,
            'seb_showsebdownloadlink' => 1,
        ], $fields));
        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $question = $qgen->create_question('truefalse', 'true', ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);
        return [$quiz, $student, $course];
    }

    private function expect_seb_refusal(callable $call): void {
        try {
            $call();
            $this->fail('Expected sebrequired');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_sebrequired', $e->errorcode);
        }
    }

    /** Every message the app shows must be text, never HTML. */
    private function assert_plain_text(array $access): void {
        foreach (['accessrules', 'preventaccessreasons', 'preventnewattemptreasons'] as $key) {
            foreach ($access[$key] as $message) {
                $this->assertSame(clean_param($message, PARAM_TEXT), $message, "$key holds markup: $message");
                $this->assertStringNotContainsString('<', $message, $key);
                $this->assertStringNotContainsStringIgnoringCase('Download Safe Exam Browser', $message, $key);
            }
        }
    }
}
