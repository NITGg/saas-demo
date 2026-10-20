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

namespace mod_flashcard\local;

/**
 * One Leitner round: a single pass over the cards a deck holds when the student opens it.
 *
 * The upstream plugin picked a random card from the deck on every answer and kept "seen"
 * cards in $_SESSION['flashcard_consumed'] — but read them back from $SESSION, which is a
 * different array, so nothing was ever excluded: a missed card (it stays in its deck) could
 * come straight back, and a deck never ran out, so the round never ended.
 *
 * A round now has a fixed, shuffled queue kept in $SESSION:
 *  - "I got it" takes the card out of the queue (the card itself moves up a deck);
 *  - "I missed" sends the card to the END of the queue once, so it is asked again after
 *    the other cards; missed a second time, it leaves the queue and stays in its deck;
 *  - an empty queue is the end of the round: the deck screen then shows what was learnt
 *    and what comes back at the deck's next review time.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class round {

    /** @var int flashcard instance id */
    protected $flashcardid;

    /** @var int deck number (1..4) */
    protected $deck;

    /**
     * @param int $flashcardid
     * @param int $deck
     */
    public function __construct(int $flashcardid, int $deck) {
        $this->flashcardid = $flashcardid;
        $this->deck = $deck;
    }

    /**
     * Start a fresh round over every card of the deck for this user.
     *
     * @param int[] $cardids flashcard_card ids currently in the deck
     */
    public function start(array $cardids): void {
        $cardids = array_values(array_map('intval', $cardids));
        shuffle($cardids);
        $this->save([
            'queue' => $cardids,
            'retried' => [],
            'known' => [],
            'missed' => [],
        ]);
    }

    /**
     * Whether a round for this deck is in the session.
     *
     * @return bool
     */
    public function exists(): bool {
        return $this->load() !== null;
    }

    /**
     * The card to show now, or null when the round is over.
     *
     * @return int|null
     */
    public function current(): ?int {
        $state = $this->load();
        return ($state && $state['queue']) ? (int) reset($state['queue']) : null;
    }

    /**
     * How many cards are still to answer in this round.
     *
     * @return int
     */
    public function remaining(): int {
        $state = $this->load();
        return $state ? count($state['queue']) : 0;
    }

    /**
     * The student knew the card.
     *
     * @param int $cardid
     */
    public function known(int $cardid): void {
        $state = $this->load();
        if (!$state) {
            return;
        }
        $state['queue'] = $this->without($state['queue'], $cardid);
        $state['known'][] = $cardid;
        $this->save($state);
    }

    /**
     * The student did not know the card: ask it again after the others, once.
     *
     * @param int $cardid
     */
    public function missed(int $cardid): void {
        $state = $this->load();
        if (!$state) {
            return;
        }
        $state['queue'] = $this->without($state['queue'], $cardid);
        if (!in_array($cardid, $state['retried'])) {
            $state['retried'][] = $cardid;
            $state['queue'][] = $cardid;
        } else if (!in_array($cardid, $state['missed'])) {
            $state['missed'][] = $cardid;
        }
        $this->save($state);
    }

    /**
     * The outcome of a finished (or abandoned) round.
     *
     * @return array ['known' => int[], 'missed' => int[]] flashcard_card ids
     */
    public function results(): array {
        $state = $this->load() ?? ['known' => [], 'missed' => []];
        return ['known' => $state['known'], 'missed' => $state['missed']];
    }

    /**
     * Forget this round.
     */
    public function clear(): void {
        global $SESSION;
        unset($SESSION->flashcard_rounds[$this->key()]);
    }

    /**
     * @param int[] $queue
     * @param int $cardid
     * @return int[]
     */
    protected function without(array $queue, int $cardid): array {
        return array_values(array_filter($queue, function($id) use ($cardid) {
            return (int) $id !== $cardid;
        }));
    }

    /**
     * @return string session key of this round
     */
    protected function key(): string {
        return $this->flashcardid . '_' . $this->deck;
    }

    /**
     * @return array|null
     */
    protected function load(): ?array {
        global $SESSION;
        return $SESSION->flashcard_rounds[$this->key()] ?? null;
    }

    /**
     * @param array $state
     */
    protected function save(array $state): void {
        global $SESSION;
        if (!isset($SESSION->flashcard_rounds) || !is_array($SESSION->flashcard_rounds)) {
            $SESSION->flashcard_rounds = [];
        }
        $SESSION->flashcard_rounds[$this->key()] = $state;
    }
}
