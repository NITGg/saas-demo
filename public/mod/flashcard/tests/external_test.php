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

namespace mod_flashcard;

use core_external\external_api;
use mod_flashcard\external\get_cards;
use mod_flashcard\external\get_flashcard;
use mod_flashcard\external\get_report;
use mod_flashcard\external\submit_answer;

/**
 * The mobile API (docs/apis/flashcard-mobile-api.md).
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashcard\external\get_flashcard
 * @covers     \mod_flashcard\external\get_cards
 * @covers     \mod_flashcard\external\submit_answer
 * @covers     \mod_flashcard\external\get_report
 * @covers     \mod_flashcard\local\api
 */
final class external_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var int */
    private $cmid;

    /** @var \stdClass */
    private $student;

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $info = create_module((object) [
            'modulename' => 'flashcard', 'course' => $this->course->id, 'section' => 0, 'visible' => 1,
            'name' => 'Words', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'models' => 3, 'questionsmediatype' => 0, 'answersmediatype' => 0, 'audiostart' => 0,
            'decks' => 2, 'deck1_delay' => 24, 'deck2_delay' => 72, 'deck3_delay' => 168, 'deck4_delay' => 336,
            'flipdeck' => 0, 'autodowngrade' => 0, 'remindusers' => 0,
        ]);
        $this->cmid = (int) $info->coursemodule;
        foreach (['apple' => 'pomme', 'book' => 'livre'] as $q => $a) {
            $DB->insert_record('flashcard_deckdata', (object) ['flashcardid' => $info->instance, 'questiontext' => $q, 'answertext' => $a]);
        }
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Call a function the way the web-service layer does (cleaned return value).
     *
     * @param string $class
     * @param array $args
     * @return array
     */
    private function call(string $class, array $args): array {
        $result = $class::execute(...array_values($args));
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    public function test_a_first_visit_puts_every_card_in_a_due_first_deck(): void {
        $this->setUser($this->student);

        $info = $this->call(get_flashcard::class, ['cmid' => $this->cmid]);

        $this->assertFalse($info['canmanage']);
        $this->assertSame(2, $info['totalcards']);
        $this->assertSame(['due', 'empty'], array_column($info['decks'], 'state'));
        $this->assertSame([2, 0], array_column($info['decks'], 'count'));
    }

    public function test_an_answer_moves_a_card_once_and_then_locks_the_deck(): void {
        $this->setUser($this->student);
        $this->call(get_flashcard::class, ['cmid' => $this->cmid]);
        $cards = $this->call(get_cards::class, ['cmid' => $this->cmid, 'deck' => 1])['cards'];
        $this->assertCount(2, $cards);
        [$first, $second] = $cards;

        $known = $this->call(submit_answer::class, ['cmid' => $this->cmid, 'deck' => 1, 'cardid' => $first['cardid'], 'known' => true]);
        $this->assertTrue($known['accepted']);
        $this->assertSame(2, $known['deck']);

        // The same answer again (double tap, retry) changes nothing.
        $again = $this->call(submit_answer::class, ['cmid' => $this->cmid, 'deck' => 1, 'cardid' => $first['cardid'], 'known' => true]);
        $this->assertFalse($again['accepted']);
        $this->assertSame(2, $again['deck'], 'Not moved a second time.');

        $missed = $this->call(submit_answer::class, ['cmid' => $this->cmid, 'deck' => 1, 'cardid' => $second['cardid'], 'known' => false]);
        $this->assertTrue($missed['accepted']);
        $this->assertSame(1, $missed['deck']);

        // Both decks were just answered: a student must now wait for their review time.
        $this->assertSame(['waiting', 'waiting'], array_column($missed['decks'], 'state'));
        $this->expectExceptionMessage(get_string('decklocked', 'flashcard', format_time(DAYSECS)));
        $this->call(get_cards::class, ['cmid' => $this->cmid, 'deck' => 1]);
    }

    public function test_a_student_cannot_answer_another_students_card(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);
        $this->call(get_flashcard::class, ['cmid' => $this->cmid]);
        $theirs = $this->call(get_cards::class, ['cmid' => $this->cmid, 'deck' => 1])['cards'][0]['cardid'];

        $this->setUser($this->student);
        $this->expectException(\moodle_exception::class);
        $this->call(submit_answer::class, ['cmid' => $this->cmid, 'deck' => 1, 'cardid' => $theirs, 'known' => true]);
    }

    public function test_free_play_returns_every_card_without_progress(): void {
        $this->setUser($this->student);

        $free = $this->call(get_cards::class, ['cmid' => $this->cmid, 'deck' => 0]);

        $this->assertCount(2, $free['cards']);
        $this->assertSame([0, 0], array_column($free['cards'], 'cardid'));
        $this->assertEqualsCanonicalizing(['apple', 'book'], array_map('strip_tags', array_column(array_column($free['cards'], 'question'), 'text')));
    }

    public function test_only_a_teacher_reads_the_report(): void {
        $this->setUser($this->student);
        $this->call(get_flashcard::class, ['cmid' => $this->cmid]);

        $this->setAdminUser();
        $report = $this->call(get_report::class, ['cmid' => $this->cmid, 'groupid' => 0]);
        $this->assertSame(['notstarted'], array_column($report['students'], 'status'),
            'Opened but never answered: not started.');
        $this->assertCount(2, $report['words']);

        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        $this->call(get_report::class, ['cmid' => $this->cmid, 'groupid' => 0]);
    }
}
