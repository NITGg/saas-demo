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

namespace local_academy;

use local_academy\local\content_cleaner;

/**
 * Tests that teacher content Moodle shows uncleaned (noclean) is saved without script (BUG-047, part 2).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\content_cleaner
 */
final class content_cleaner_test extends \advanced_testcase {

    /** The payload from the QA report, plus content that must survive. */
    const DIRTY = '<p><b>Bold</b> <img src="@@PLUGINFILE@@/graph.png" alt="graph"></p>'
        . '<script>alert(1)</script><img src="x" onerror="alert(2)">';

    /** @var \stdClass */
    private $course;
    /** @var \stdClass a teacher (not a site admin) */
    private $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * The payload is gone and the formatting is kept.
     *
     * @param string|null $html
     * @param string $where
     */
    private function assert_cleaned(?string $html, string $where): void {
        $this->assertStringNotContainsString('<script', (string) $html, $where);
        $this->assertStringNotContainsString('onerror', (string) $html, $where);
        $this->assertStringContainsString('<b>Bold</b>', (string) $html, $where);
        $this->assertStringContainsString('@@PLUGINFILE@@/graph.png', (string) $html, $where);
    }

    public function test_page_content_and_intro_are_cleaned(): void {
        global $DB;
        $this->setUser($this->teacher);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id,
            'intro' => self::DIRTY, 'introformat' => FORMAT_HTML, 'content' => self::DIRTY, 'contentformat' => FORMAT_HTML]);
        $row = $DB->get_record('page', ['id' => $page->id]);
        $this->assert_cleaned($row->content, 'page content');
        $this->assert_cleaned($row->intro, 'page intro');

        // Edited later (course_module_updated).
        $DB->set_field('page', 'content', self::DIRTY, ['id' => $page->id]);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('page', $page->cmid))->trigger();
        $this->assert_cleaned($DB->get_field('page', 'content', ['id' => $page->id]), 'edited page content');

        // What a student's browser gets on the course page (label-like description).
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $cm = get_fast_modinfo($this->course->id)->get_cm($page->cmid);
        $this->assertStringNotContainsString('<script', format_module_intro('page', $DB->get_record('page', ['id' => $page->id]), $cm->id));
    }

    public function test_site_admin_content_is_left_as_written(): void {
        global $DB;
        $this->setAdminUser();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id,
            'content' => self::DIRTY, 'contentformat' => FORMAT_HTML]);
        $this->assertStringContainsString('<script>alert(1)</script>', $DB->get_field('page', 'content', ['id' => $page->id]));
    }

    public function test_label_and_assignment_instructions_are_cleaned(): void {
        global $DB;
        $this->setUser($this->teacher);
        $label = $this->getDataGenerator()->create_module('label', ['course' => $this->course->id,
            'intro' => self::DIRTY, 'introformat' => FORMAT_HTML]);
        $this->assert_cleaned($DB->get_field('label', 'intro', ['id' => $label->id]), 'label');

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id,
            'activity' => self::DIRTY, 'activityformat' => FORMAT_HTML]);
        $this->assert_cleaned($DB->get_field('assign', 'activity', ['id' => $assign->id]), 'assign activity');
    }

    public function test_section_and_course_summary_are_cleaned(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->setUser($this->teacher);
        $section = $DB->get_record('course_sections', ['course' => $this->course->id, 'section' => 0]);
        course_update_section($this->course, $section, ['summary' => self::DIRTY, 'summaryformat' => FORMAT_HTML]);
        $this->assert_cleaned($DB->get_field('course_sections', 'summary', ['id' => $section->id]), 'section summary');

        update_course((object) ['id' => $this->course->id, 'summary' => self::DIRTY, 'summaryformat' => FORMAT_HTML]);
        $this->assert_cleaned($DB->get_field('course', 'summary', ['id' => $this->course->id]), 'course summary');
    }

    public function test_book_chapter_is_cleaned(): void {
        global $DB;
        $this->setUser($this->teacher);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $this->course->id]);
        /** @var \mod_book_generator $bgen */
        $bgen = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $chapter = $bgen->create_chapter(['bookid' => $book->id, 'content' => self::DIRTY, 'contentformat' => FORMAT_HTML]);
        // What mod/book/edit.php raises after saving the chapter.
        $context = \context_module::instance($book->cmid);
        \mod_book\event\chapter_created::create_from_chapter($book, $context, $chapter)->trigger();
        $this->assert_cleaned($DB->get_field('book_chapters', 'content', ['id' => $chapter->id]), 'chapter');
    }

    public function test_lesson_page_answers_and_responses_are_cleaned(): void {
        global $DB;
        $this->setUser($this->teacher);
        $lesson = $this->getDataGenerator()->create_module('lesson', ['course' => $this->course->id]);
        /** @var \mod_lesson_generator $lgen */
        $lgen = $this->getDataGenerator()->get_plugin_generator('mod_lesson');
        $page = $lgen->create_question_multichoice($lesson, [
            'contents_editor' => ['text' => self::DIRTY, 'format' => FORMAT_HTML, 'itemid' => 0],
            'answer_editor' => [['text' => self::DIRTY, 'format' => FORMAT_HTML], ['text' => 'B', 'format' => FORMAT_HTML]],
            'response_editor' => [['text' => self::DIRTY, 'format' => FORMAT_HTML], ['text' => 'No', 'format' => FORMAT_HTML]],
        ]);
        $this->assert_cleaned($DB->get_field('lesson_pages', 'contents', ['id' => $page->id]), 'lesson page');
        $answer = $DB->get_records('lesson_answers', ['pageid' => $page->id], 'id');
        $first = reset($answer);
        $this->assert_cleaned($first->answer, 'lesson answer');
        $this->assert_cleaned($first->response, 'lesson response');

        // A short-answer answer is a plain value that grading compares: kept exactly.
        $short = $lgen->create_question_shortanswer($lesson, ['answer_editor' => [['text' => 'x<5 & y>2', 'format' => FORMAT_MOODLE]]]);
        $this->assertContains('x<5 & y>2', $DB->get_fieldset_select('lesson_answers', 'answer', 'pageid = ?', [$short->id]));
    }

    public function test_lesson_essay_feedback_is_cleaned(): void {
        global $DB;
        $lesson = $this->getDataGenerator()->create_module('lesson', ['course' => $this->course->id]);
        $lgen = $this->getDataGenerator()->get_plugin_generator('mod_lesson');
        $page = $lgen->create_question_essay($lesson);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $essay = (object) ['sent' => 0, 'graded' => 1, 'score' => 1, 'answer' => 'My essay', 'answerformat' => FORMAT_HTML,
            'response' => self::DIRTY, 'responseformat' => FORMAT_HTML];
        $attemptid = $DB->insert_record('lesson_attempts', (object) ['lessonid' => $lesson->id, 'pageid' => $page->id,
            'userid' => $student->id, 'answerid' => 0, 'retry' => 0, 'correct' => 0, 'useranswer' => serialize($essay),
            'timeseen' => time()]);
        $gradeid = $DB->insert_record('lesson_grades', (object) ['lessonid' => $lesson->id, 'userid' => $student->id,
            'grade' => 100, 'late' => 0, 'completed' => time()]);

        // What mod/lesson/essay.php raises after the teacher grades.
        $this->setUser($this->teacher);
        \mod_lesson\event\essay_assessed::create([
            'relateduserid' => $student->id,
            'objectid' => $gradeid,
            'courseid' => $this->course->id,
            'context' => \context_module::instance($lesson->cmid),
            'other' => ['lessonid' => $lesson->id, 'attemptid' => $attemptid],
        ])->trigger();

        $saved = unserialize($DB->get_field('lesson_attempts', 'useranswer', ['id' => $attemptid]));
        $this->assert_cleaned($saved->response, 'essay feedback');
        $this->assertSame('My essay', $saved->answer);
    }

    public function test_database_templates_are_cleaned_and_js_dropped(): void {
        global $DB;
        $this->setUser($this->teacher);
        $data = $this->getDataGenerator()->create_module('data', ['course' => $this->course->id]);
        $manager = \mod_data\manager::create_from_instance($data);
        $manager->update_templates((object) [
            'singletemplate' => '<div class="entry">[[title]] <a href="##moreurl##">more</a>' . self::DIRTY . '</div>',
            'listtemplateheader' => '<table class="list"><tbody>',
            'listtemplate' => '<tr><td>[[title]]</td><td><a href="javascript:alert(3)">x</a></td></tr>',
            'listtemplatefooter' => '</tbody></table>',
            'jstemplate' => 'alert(4);',
        ]);
        $row = $DB->get_record('data', ['id' => $data->id]);
        $this->assert_cleaned($row->singletemplate, 'single template');
        $this->assertStringContainsString('[[title]]', $row->singletemplate);
        $this->assertStringContainsString('href="##moreurl##"', $row->singletemplate);
        // The list is split over three templates: each part keeps its open / close tags.
        $this->assertSame('<table class="list"><tbody>', $row->listtemplateheader);
        $this->assertSame('</tbody></table>', $row->listtemplatefooter);
        $this->assertStringContainsString('<tr><td>[[title]]</td>', $row->listtemplate);
        $this->assertStringNotContainsString('javascript:', $row->listtemplate);
        $this->assertSame('', $row->jstemplate);
    }

    public function test_core_database_presets_survive_cleaning(): void {
        global $CFG, $DB;
        $this->setUser($this->teacher);
        $data = $this->getDataGenerator()->create_module('data', ['course' => $this->course->id]);
        $manager = \mod_data\manager::create_from_instance($data);
        $dir = $CFG->dirroot . '/mod/data/preset/proposals/';
        $templates = [];
        foreach (['listtemplateheader', 'listtemplate', 'listtemplatefooter', 'singletemplate'] as $name) {
            $templates[$name] = file_get_contents($dir . $name . '.html');
        }
        $manager->update_templates((object) $templates);
        $row = $DB->get_record('data', ['id' => $data->id]);
        $this->assertSame(trim($templates['listtemplateheader']), trim($row->listtemplateheader));
        $this->assertSame(trim($templates['listtemplatefooter']), trim($row->listtemplatefooter));
        foreach (['data-bs-toggle="collapse"', 'href="#collapseentry-##id##"', 'id="collapseentry-##id##"',
                'aria-labelledby="entry-##id##"', 'href="##moreurl##"', '[[Summary]]', '##actionsmenu##'] as $kept) {
            $this->assertStringContainsString($kept, $row->listtemplate);
        }
    }

    public function test_feedback_items_saved_during_a_teacher_request_are_cleaned(): void {
        global $DB;
        $this->setAdminUser();
        $feedback = $this->getDataGenerator()->create_module('feedback', ['course' => $this->course->id]);
        /** @var \mod_feedback_generator $fgen */
        $fgen = $this->getDataGenerator()->get_plugin_generator('mod_feedback');
        $adminitem = $fgen->create_item_label($feedback, ['presentation_editor' => ['text' => self::DIRTY, 'format' => FORMAT_HTML]]);

        // A teacher's request to mod/feedback/edit_item.php (feedback items raise no event).
        $this->setUser($this->teacher);
        content_cleaner::watch_instance('feedback', (int) $feedback->id);
        $label = $fgen->create_item_label($feedback, ['presentation_editor' => ['text' => self::DIRTY, 'format' => FORMAT_HTML]]);
        $choice = $fgen->create_item_multichoice($feedback, ['name' => 'Pick' . self::DIRTY,
            'values' => "one <b>1</b><script>alert(5)</script>\ntwo"]);
        content_cleaner::finish_watch();

        $this->assert_cleaned($DB->get_field('feedback_item', 'presentation', ['id' => $label->id]), 'label item');
        $this->assert_cleaned($DB->get_field('feedback_item', 'name', ['id' => $choice->id]), 'choice name');
        $presentation = $DB->get_field('feedback_item', 'presentation', ['id' => $choice->id]);
        $this->assertStringNotContainsString('<script', $presentation);
        $this->assertStringStartsWith('r>>>>>one <b>1</b>', $presentation);
        $this->assertStringEndsWith('|two', $presentation);
        // Rows the request did not touch are left alone (the admin's item).
        $this->assertStringContainsString('<script>', $DB->get_field('feedback_item', 'presentation', ['id' => $adminitem->id]));
    }

    public function test_quiz_overall_feedback_and_manual_comment_are_cleaned(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->setAdminUser();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'sumgrades' => 1]);
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category();
        $essay = $qgen->create_question('essay', null, ['category' => $category->id]);
        quiz_add_quiz_question($essay->id, $quiz);

        // Overall feedback, saved with the quiz settings (course_module_updated).
        $DB->insert_record('quiz_feedback', (object) ['quizid' => $quiz->id, 'feedbacktext' => self::DIRTY,
            'feedbacktextformat' => FORMAT_HTML, 'mingrade' => 0, 'maxgrade' => 101]);
        $this->setUser($this->teacher);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('quiz', $quiz->cmid))->trigger();
        $this->assert_cleaned($DB->get_field('quiz_feedback', 'feedbacktext', ['quizid' => $quiz->id]), 'overall feedback');

        // A student answers; the teacher comments while grading.
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, time());
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions(time(), false, [1 => ['answer' => 'My answer', 'answerformat' => FORMAT_HTML]]);
        $attemptobj->process_submit(time(), false);
        $attemptobj->process_grade_submission(time());

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $quba->manual_grade(1, self::DIRTY, 1, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
        // What mod/quiz/comment.php raises after saving the grade.
        \mod_quiz\event\question_manually_graded::create([
            'objectid' => $essay->id,
            'courseid' => $this->course->id,
            'context' => $quizobj->get_context(),
            'other' => ['quizid' => $quiz->id, 'attemptid' => $attempt->id, 'slot' => 1],
        ])->trigger();

        [$comment] = \question_engine::load_questions_usage_by_activity($attempt->uniqueid)
            ->get_question_attempt(1)->get_manual_comment();
        $this->assert_cleaned($comment, 'manual comment');
    }

    public function test_restored_course_is_cleaned(): void {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $this->course->id]);
        $chapter = $this->getDataGenerator()->get_plugin_generator('mod_book')->create_chapter(['bookid' => $book->id]);
        // What a teacher's crafted backup puts straight into the tables.
        $DB->set_field('page', 'content', self::DIRTY, ['id' => $page->id]);
        $DB->set_field('book_chapters', 'content', self::DIRTY, ['id' => $chapter->id]);
        $DB->set_field('course_sections', 'summary', self::DIRTY, ['course' => $this->course->id, 'section' => 0]);

        $this->setUser($this->teacher);
        \core\event\course_restored::create([
            'objectid' => $this->course->id,
            'context' => \context_course::instance($this->course->id),
            'other' => ['type' => 'course', 'target' => 2, 'mode' => 10, 'operation' => 'restore', 'samesite' => true],
        ])->trigger();

        $this->assert_cleaned($DB->get_field('page', 'content', ['id' => $page->id]), 'restored page');
        $this->assert_cleaned($DB->get_field('book_chapters', 'content', ['id' => $chapter->id]), 'restored chapter');
        $this->assert_cleaned($DB->get_field('course_sections', 'summary',
            ['course' => $this->course->id, 'section' => 0]), 'restored section');
    }

    public function test_resource_name_cannot_break_out_of_the_pdf_frame_title(): void {
        global $DB;
        $this->setUser($this->teacher);
        $resource = $this->getDataGenerator()->create_module('resource', ['course' => $this->course->id,
            'name' => 'Notes" onload="alert(1)']);
        $this->assertStringNotContainsString('"', $DB->get_field('resource', 'name', ['id' => $resource->id]));
    }
}
