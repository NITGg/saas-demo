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
 * The teacher's follow-up of a Flash Card Set (view=summary).
 *
 * The upstream summary drew each student's decks as bars and clocks and never named a
 * word. This answers the two questions a teacher actually has:
 *  - students(): where is each student — how many words mastered, which words they keep
 *    missing, whether a review is due or overdue;
 *  - words(): which words the class finds hardest.
 *
 * A card in deck 1 is only "hard" once it has been answered (accesscount > 0): every card
 * starts in deck 1, so an unanswered one is "not seen yet", not a difficulty.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {

    /** @var \stdClass flashcard instance */
    protected $flashcard;

    /** @var \context_module */
    protected $context;

    /**
     * @param \stdClass $flashcard
     * @param \stdClass|\cm_info $cm
     */
    public function __construct(\stdClass $flashcard, $cm) {
        $this->flashcard = $flashcard;
        $this->context = \context_module::instance($cm->id);
    }

    /**
     * The learners followed by this report: enrolled users of the group, minus those who
     * manage the activity (teachers).
     *
     * @param int $groupid 0 = everybody
     * @return \stdClass[] user records keyed by id
     */
    public function learners(int $groupid = 0): array {
        $users = get_enrolled_users($this->context, '', $groupid, 'u.*', 'u.lastname, u.firstname');
        $managers = get_users_by_capability($this->context, 'mod/flashcard:manage', 'u.id');
        return array_diff_key($users, $managers);
    }

    /**
     * One row per learner.
     *
     * @param int $groupid
     * @return array [{user, started, total, decks[], mastered, percent, hard[], unseen, lastseen, isdue, isoverdue}]
     */
    public function students(int $groupid = 0): array {
        global $DB;

        $learners = $this->learners($groupid);
        $total = $DB->count_records('flashcard_deckdata', ['flashcardid' => $this->flashcard->id]);
        if (!$learners) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
        $params['fid'] = $this->flashcard->id;
        $cards = $DB->get_records_sql("SELECT c.id, c.userid, c.deck, c.accesscount, c.lastaccessed,
                                              d.questiontext, d.answertext
                                         FROM {flashcard_card} c
                                         JOIN {flashcard_deckdata} d ON d.id = c.entryid
                                        WHERE c.flashcardid = :fid AND c.userid $insql
                                     ORDER BY c.accesscount DESC, d.id", $params);
        $byuser = [];
        foreach ($cards as $card) {
            $byuser[$card->userid][] = $card;
        }

        $decks = (int) $this->flashcard->decks;
        $rows = [];
        foreach ($learners as $user) {
            $mine = $byuser[$user->id] ?? [];
            $row = (object) [
                'user' => $user,
                'started' => false,
                'total' => $total,
                'decks' => array_fill(1, $decks, 0),
                'mastered' => 0,
                'percent' => 0,
                'hard' => [],
                'unseen' => 0,
                'lastseen' => 0,
                'isdue' => false,
                'isoverdue' => false,
            ];
            foreach ($mine as $card) {
                if ($card->accesscount > 0) {
                    $row->started = true;
                    $row->lastseen = max($row->lastseen, (int) $card->lastaccessed);
                }
                $row->decks[(int) $card->deck] = ($row->decks[(int) $card->deck] ?? 0) + 1;
                if ($card->deck == 1) {
                    if ($card->accesscount > 0) {
                        $row->hard[] = (object) [
                            'question' => $card->questiontext,
                            'answer' => $card->answertext,
                            'tries' => (int) $card->accesscount,
                        ];
                    } else {
                        $row->unseen++;
                    }
                }
            }
            if ($row->started) {
                $row->mastered = $row->decks[$decks] ?? 0;
                $row->percent = $total ? (int) round(100 * $row->mastered / $total) : 0;
                for ($deck = 1; $deck <= $decks; $deck++) {
                    if (empty($row->decks[$deck])) {
                        continue;
                    }
                    $wait = flashcard_deck_wait($this->flashcard, $deck, (int) $user->id);
                    if ($wait === 0) {
                        $row->isdue = true;
                        // Overdue: the deck has been waiting for the student longer than a day.
                        $delaykey = "deck{$deck}_delay";
                        $since = time() - (int) flashcard_get_lastaccessed($this->flashcard, $deck, (int) $user->id)
                            - (int) $this->flashcard->$delaykey * HOURSECS;
                        if ($since > DAYSECS) {
                            $row->isoverdue = true;
                        }
                    }
                }
            }
            $rows[] = $row;
        }

        // Who needs help first: started students with the most hard words, then by progress;
        // students who never opened the activity last.
        usort($rows, function($a, $b) {
            if ($a->started !== $b->started) {
                return $a->started ? -1 : 1;
            }
            return [count($b->hard), $a->percent] <=> [count($a->hard), $b->percent];
        });
        return $rows;
    }

    /**
     * One row per word, hardest first.
     *
     * @param int $groupid
     * @return array [{question, answer, hard, learning, mastered, unseen, tries}]
     */
    public function words(int $groupid = 0): array {
        global $DB;

        $learners = $this->learners($groupid);
        $words = $DB->get_records('flashcard_deckdata', ['flashcardid' => $this->flashcard->id], 'id');
        $decks = (int) $this->flashcard->decks;

        $rows = [];
        foreach ($words as $word) {
            $rows[$word->id] = (object) [
                'question' => $word->questiontext,
                'answer' => $word->answertext,
                'hard' => 0,
                'learning' => 0,
                'mastered' => 0,
                'unseen' => count($learners),
                'tries' => 0,
            ];
        }
        if ($learners && $rows) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
            $params['fid'] = $this->flashcard->id;
            $cards = $DB->get_recordset_sql("SELECT id, entryid, deck, accesscount
                                               FROM {flashcard_card}
                                              WHERE flashcardid = :fid AND userid $insql", $params);
            foreach ($cards as $card) {
                if (!isset($rows[$card->entryid]) || $card->accesscount == 0) {
                    continue;
                }
                $row = $rows[$card->entryid];
                $row->unseen--;
                $row->tries += (int) $card->accesscount;
                if ($card->deck == 1) {
                    $row->hard++;
                } else if ($card->deck == $decks) {
                    $row->mastered++;
                } else {
                    $row->learning++;
                }
            }
            $cards->close();
        }

        $rows = array_values($rows);
        usort($rows, function($a, $b) {
            return [$b->hard, $b->tries] <=> [$a->hard, $a->tries];
        });
        return $rows;
    }
}
