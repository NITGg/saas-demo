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

/**
 * The student screens our fork of mod_flashcard renders (NIT changes).
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashcard_renderer
 */
final class renderer_test extends \advanced_testcase {

    /** @var \stdClass the flashcard instance row */
    private $flashcard;

    /** @var \stdClass its course module */
    private $cm;

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/flashcard/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $info = create_module((object) [
            'modulename' => 'flashcard',
            'course' => $course->id,
            'section' => 0,
            'visible' => 1,
            'name' => 'Words',
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'models' => 3,
            'questionsmediatype' => FLASHCARD_MEDIA_TEXT,
            'answersmediatype' => FLASHCARD_MEDIA_TEXT,
            'audiostart' => 0,
            'decks' => 2,
            'deck1_delay' => 48,
            'deck2_delay' => 96,
            'deck3_delay' => 168,
            'deck4_delay' => 336,
            'flipdeck' => 0,
            'autodowngrade' => 0,
            'remindusers' => 0,
        ]);
        $this->flashcard = $DB->get_record('flashcard', ['id' => $info->instance], '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_id('flashcard', $info->coursemodule, 0, false, MUST_EXIST);
    }

    /**
     * The renderer for the page of this activity.
     *
     * @return \mod_flashcard_renderer
     */
    private function renderer(): \mod_flashcard_renderer {
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/mod/flashcard/view.php', ['id' => $this->cm->id]));
        $PAGE->set_context(\context_module::instance($this->cm->id));
        return $PAGE->get_renderer('mod_flashcard');
    }

    /**
     * One card in deck 1 for the current user.
     *
     * @return array [$cards, $subquestions] as playview.php hands them to the renderer
     */
    private function one_card(): array {
        global $DB, $USER;
        $entryid = $DB->insert_record('flashcard_deckdata', (object) [
            'flashcardid' => $this->flashcard->id, 'questiontext' => 'Apple', 'answertext' => 'Pomme',
        ]);
        $cardid = $DB->insert_record('flashcard_card', (object) [
            'flashcardid' => $this->flashcard->id, 'userid' => $USER->id, 'entryid' => $entryid,
            'deck' => 1, 'lastaccessed' => 0, 'accesscount' => 0,
        ]);
        $cards = [$cardid => $DB->get_record('flashcard_card', ['id' => $cardid])];
        return [$cards, [(object) ['entryid' => $entryid, 'cardid' => $cardid]]];
    }

    /**
     * Both answers post to view.php by its absolute URL. A relative "view.php" broke on a
     * site under a sub-path once the activity ran inside local_academy's player iframe.
     */
    public function test_play_answers_post_to_absolute_view_url(): void {
        global $CFG;
        [$cards, $subquestions] = $this->one_card();
        $_GET['deck'] = 1;

        $html = $this->renderer()->playview($this->flashcard, $this->cm, $cards, $subquestions);

        preg_match_all('~<form method="post" action="([^"]*)"~', $html, $m);
        $this->assertCount(2, $m[1], 'One form for "I got it", one for "I missed".');
        foreach ($m[1] as $action) {
            $this->assertSame($CFG->wwwroot . '/mod/flashcard/view.php', html_entity_decode($action));
        }
        $this->assertStringContainsString('name="what" value="igotit"', $html);
        $this->assertStringContainsString('name="what" value="ifailed"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        // The card itself, both faces.
        $this->assertStringContainsString('Apple', $html);
        $this->assertStringContainsString('Pomme', $html);
    }

    /**
     * Each deck tile says whether it is due, reviewed or empty, and only a non-empty deck
     * links to its play screen.
     */
    public function test_deck_tiles_carry_their_state(): void {
        $decks = (object) ['decks' => [
            (object) ['count' => 3, 'reactivate' => true],
            (object) ['count' => 0, 'reactivate' => false],
        ]];

        $html = $this->renderer()->check_decks($this->flashcard, $this->cm, $decks);

        $this->assertSame(2, substr_count($html, '<section class="nit-fc-deck'));
        $this->assertMatchesRegularExpression('~nit-fc-deck is-due">.*?<span class="nit-fc-deck__count">3</span>~s', $html);
        $this->assertMatchesRegularExpression('~nit-fc-deck is-empty">.*?<span class="nit-fc-deck__count">0</span>~s', $html);
        $this->assertSame(1, substr_count($html, 'nit-fc-deck__go'), 'Only the deck with cards gets a button.');
        $this->assertStringContainsString('deck=1', $html);

        // A reviewed (not yet due) deck gets the outline button instead.
        $decks->decks[0]->reactivate = false;
        $html = $this->renderer()->check_decks($this->flashcard, $this->cm, $decks);
        $this->assertStringContainsString('nit-fc-deck is-reviewed', $html);
        $this->assertStringContainsString('btn btn-outline-primary nit-fc-deck__go', $html);
    }

    /**
     * Free play opens with the counter at the size of the set; it used to print nothing
     * because the template read a misspelt variable.
     */
    public function test_free_play_counter_starts_at_set_size(): void {
        $subquestions = [new \stdClass(), new \stdClass(), new \stdClass()];

        $html = $this->renderer()->freebuttons($subquestions);

        $this->assertStringContainsString('<b id="remain">3</b>', $html);
    }
}
