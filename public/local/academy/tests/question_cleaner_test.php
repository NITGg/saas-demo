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

/**
 * Tests that a teacher's question is saved without script (BUG-047).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\question_cleaner
 */
final class question_cleaner_test extends \advanced_testcase {

    /** The payload from the QA report, plus content that must survive. */
    const DIRTY = '<p><b>Bold</b> <img src="@@PLUGINFILE@@/graph.png" alt="graph"></p>'
        . '<script>alert(1)</script><img src="x" onerror="alert(2)">';

    /**
     * Create a multiple-choice question as $user, with the payload everywhere.
     *
     * @param \stdClass $user
     * @return \stdClass the question data
     */
    private function create_question_as(\stdClass $user): \stdClass {
        $this->setUser($user);
        /** @var \core_question_generator $qgen */
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category();
        return $qgen->create_question('multichoice', 'one_of_four', [
            'category' => $category->id,
            'questiontext' => ['text' => self::DIRTY, 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => self::DIRTY, 'format' => FORMAT_HTML],
            'correctfeedback' => ['text' => self::DIRTY, 'format' => FORMAT_HTML],
        ]);
    }

    public function test_teacher_question_is_saved_without_script(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $question = $this->create_question_as($teacher);

        $row = $DB->get_record('question', ['id' => $question->id]);
        $feedback = $DB->get_field('qtype_multichoice_options', 'correctfeedback', ['questionid' => $question->id]);
        foreach ([$row->questiontext, $row->generalfeedback, $feedback] as $html) {
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('onerror', $html);
            $this->assertStringContainsString('<b>Bold</b>', $html);
            $this->assertStringContainsString('@@PLUGINFILE@@/graph.png', $html);
        }

        // What a student's browser gets: the question as Moodle renders it.
        $this->assertStringNotContainsString('<script', \question_bank::load_question($question->id)->questiontext);
    }

    public function test_site_admin_question_is_left_as_written(): void {
        global $DB;
        $this->resetAfterTest();
        $question = $this->create_question_as(get_admin());
        $this->assertStringContainsString('<script>alert(1)</script>',
            $DB->get_field('question', 'questiontext', ['id' => $question->id]));
    }

    public function test_plain_answers_are_not_changed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $qgen->create_question_category();
        $question = $qgen->create_question('shortanswer', null, [
            'category' => $category->id,
            'answer' => ['x<5 & y>2', 'toad', '*'],
        ]);
        $answers = $DB->get_fieldset_select('question_answers', 'answer', 'question = ?', [$question->id]);
        $this->assertContains('x<5 & y>2', $answers);
    }
}
