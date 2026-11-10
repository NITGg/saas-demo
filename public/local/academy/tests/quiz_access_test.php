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
