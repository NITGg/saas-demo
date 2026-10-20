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

use mod_flashcard\local\round;

/**
 * A Leitner round: each card once, a missed card once more at the end, then it is over.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_flashcard\local\round
 */
final class round_test extends \advanced_testcase {

    protected function setUp(): void {
        global $SESSION;
        parent::setUp();
        unset($SESSION->flashcard_rounds);
    }

    /**
     * Answer the card the round shows until it is over.
     *
     * @param round $round
     * @param callable $knows fn(int $cardid, int $times): bool — times = how often it was shown
     * @return int[] the cards in the order they were shown
     */
    private function play(round $round, callable $knows): array {
        $shown = [];
        $times = [];
        while (($card = $round->current()) !== null) {
            $this->assertLessThan(20, count($shown), 'A round must end.');
            $shown[] = $card;
            $times[$card] = ($times[$card] ?? 0) + 1;
            $knows($card, $times[$card]) ? $round->known($card) : $round->missed($card);
        }
        return $shown;
    }

    public function test_a_missed_card_is_not_shown_again_straight_away(): void {
        $round = new round(7, 1);
        $round->start([11, 12, 13]);
        $first = $round->current();

        $round->missed($first);

        $this->assertNotSame($first, $round->current(), 'The other cards come first.');
        $this->assertSame(3, $round->remaining(), 'The missed card is still to be asked.');
    }

    public function test_a_missed_card_comes_back_once_at_the_end_then_the_round_ends(): void {
        $round = new round(7, 1);
        $round->start([11, 12, 13]);
        $hard = $round->current();

        $shown = $this->play($round, function($card) use ($hard) {
            return $card !== $hard;
        });

        $this->assertCount(4, $shown, 'Three cards, plus the missed one a second time.');
        $this->assertSame($hard, end($shown), 'The second try is the last card of the round.');
        $this->assertNull($round->current());
        $this->assertSame(0, $round->remaining());
        $results = $round->results();
        $this->assertEqualsCanonicalizing(array_values(array_diff([11, 12, 13], [$hard])), $results['known']);
        $this->assertSame([$hard], $results['missed']);
    }

    public function test_a_card_known_on_its_second_try_counts_as_known(): void {
        $round = new round(7, 1);
        $round->start([11, 12]);

        $this->play($round, function($card, $times) {
            return $times > 1;
        });

        $results = $round->results();
        $this->assertEqualsCanonicalizing([11, 12], $results['known']);
        $this->assertSame([], $results['missed']);
    }

    public function test_rounds_are_kept_per_flashcard_and_deck(): void {
        $deck1 = new round(7, 1);
        $deck2 = new round(7, 2);
        $other = new round(8, 1);
        $deck1->start([11]);

        $this->assertTrue($deck1->exists());
        $this->assertFalse($deck2->exists());
        $this->assertFalse($other->exists());

        $deck1->clear();
        $this->assertFalse($deck1->exists());
    }
}
