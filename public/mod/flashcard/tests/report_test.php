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

use mod_flashcard\local\report;

/**
 * The teacher's follow-up data, and when a deck is due.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashcard\local\report
 * @covers     ::flashcard_deck_wait
 */
final class report_test extends \advanced_testcase {

    /** @var \stdClass */
    private $flashcard;

    /** @var \stdClass */
    private $cm;

    /** @var \stdClass */
    private $course;

    /** @var int[] deckdata ids by question */
    private $words = [];

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/flashcard/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $info = create_module((object) [
            'modulename' => 'flashcard', 'course' => $this->course->id, 'section' => 0, 'visible' => 1,
            'name' => 'Words', 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'models' => 3, 'questionsmediatype' => 0, 'answersmediatype' => 0, 'audiostart' => 0,
            'decks' => 3, 'deck1_delay' => 24, 'deck2_delay' => 72, 'deck3_delay' => 168, 'deck4_delay' => 336,
            'flipdeck' => 0, 'autodowngrade' => 0, 'remindusers' => 0,
        ]);
        $this->flashcard = $DB->get_record('flashcard', ['id' => $info->instance], '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_id('flashcard', $info->coursemodule, 0, false, MUST_EXIST);
        foreach (['apple' => 'pomme', 'book' => 'livre', 'water' => 'eau'] as $q => $a) {
            $this->words[$q] = $DB->insert_record('flashcard_deckdata',
                (object) ['flashcardid' => $this->flashcard->id, 'questiontext' => $q, 'answertext' => $a]);
        }
    }

    /**
     * A student's card at a given stage.
     */
    private function card(int $userid, string $word, int $deck, int $tries, int $lastaccessed): void {
        global $DB;
        $DB->insert_record('flashcard_card', (object) [
            'flashcardid' => $this->flashcard->id, 'userid' => $userid, 'entryid' => $this->words[$word],
            'deck' => $deck, 'accesscount' => $tries, 'lastaccessed' => $lastaccessed,
        ]);
    }

    public function test_students_names_the_words_each_student_keeps_missing(): void {
        $sara = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Sara']);
        $omar = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Omar']);
        $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $now = time();
        // Sara missed "book" three times; "water" is in deck 1 but never answered; "apple" mastered.
        $this->card($sara->id, 'book', 1, 3, $now);
        $this->card($sara->id, 'water', 1, 0, $now - DAYSECS);
        $this->card($sara->id, 'apple', 3, 2, $now);

        $rows = (new report($this->flashcard, $this->cm))->students();

        $this->assertCount(2, $rows, 'Teachers are not followed.');
        $first = $rows[0];
        $this->assertSame((int) $sara->id, (int) $first->user->id, 'Started students come first.');
        $this->assertSame(['book'], array_column($first->hard, 'question'), 'An unanswered card is not hard.');
        $this->assertSame(3, $first->hard[0]->tries);
        $this->assertSame(1, $first->unseen);
        $this->assertSame(1, $first->mastered);
        $this->assertSame(33, $first->percent);
        $this->assertFalse($rows[1]->started, 'Omar never opened it.');
        $this->assertSame((int) $omar->id, (int) $rows[1]->user->id);
    }

    public function test_words_puts_the_class_hardest_word_first(): void {
        $a = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $b = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $now = time();
        $this->card($a->id, 'water', 1, 2, $now);
        $this->card($b->id, 'water', 1, 4, $now);
        $this->card($a->id, 'apple', 2, 1, $now);
        $this->card($b->id, 'apple', 3, 2, $now);

        $rows = (new report($this->flashcard, $this->cm))->words();

        $this->assertSame('water', $rows[0]->question);
        $this->assertSame(2, $rows[0]->hard);
        $this->assertSame(6, $rows[0]->tries);
        $apple = array_values(array_filter($rows, fn($r) => $r->question === 'apple'))[0];
        $this->assertSame([0, 1, 1, 0], [$apple->hard, $apple->learning, $apple->mastered, $apple->unseen]);
        $book = array_values(array_filter($rows, fn($r) => $r->question === 'book'))[0];
        $this->assertSame(2, $book->unseen, 'Nobody has seen "book" yet.');
    }

    public function test_a_deck_is_due_only_once_its_review_delay_has_passed(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertNull(flashcard_deck_wait($this->flashcard, 1, $student->id), 'An empty deck has no review.');

        // Deck 1 waits 24 hours after the last answer.
        $this->card($student->id, 'book', 1, 1, time() - 2 * HOURSECS);
        $wait = flashcard_deck_wait($this->flashcard, 1, $student->id);
        $this->assertGreaterThan(21 * HOURSECS, $wait);
        $this->assertLessThanOrEqual(22 * HOURSECS, $wait);

        // A card answered long ago makes the deck due.
        $this->card($student->id, 'water', 1, 1, time() - 2 * DAYSECS);
        $this->assertSame(0, flashcard_deck_wait($this->flashcard, 1, $student->id));
    }

    public function test_a_student_gets_no_button_on_a_deck_that_is_not_due(): void {
        global $PAGE;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->card($student->id, 'book', 1, 1, time());
        $this->setUser($student);
        $PAGE->set_url(new \moodle_url('/mod/flashcard/view.php', ['id' => $this->cm->id]));
        $PAGE->set_context(\context_module::instance($this->cm->id));
        $renderer = $PAGE->get_renderer('mod_flashcard');

        $decks = flashcard_get_deck_status($this->flashcard);
        $html = $renderer->check_decks($this->flashcard, $this->cm, $decks);

        $this->assertStringContainsString('nit-fc-deck is-reviewed', $html);
        $this->assertStringContainsString('nit-fc-deck__wait', $html);
        $this->assertStringNotContainsString('nit-fc-deck__go', $html);
    }
}
