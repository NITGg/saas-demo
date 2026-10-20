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

/**
 * This view allows playing with a deck
 *
 * One visit plays one round of the deck (mod_flashcard\local\round): every card of the
 * deck once, a missed card once more at the end, then a summary screen. A student can
 * only start a round on a deck that is due; the deck table shows when the others are.
 *
 * @package mod_flashcard
 * @category mod
 * @author Gustav Delius
 * @author Valery Fremaux
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die();

use mod_flashcard\local\round;


$deck = required_param('deck', PARAM_INT);
$deckstable = new moodle_url('/mod/flashcard/view.php', ['id' => $cm->id, 'view' => 'checkdecks']);

$subquestions = $DB->get_records('flashcard_deckdata', array('flashcardid' => $flashcard->id));
$round = new round((int) $flashcard->id, $deck);

// Invoke controller.
if ($action != '') {
    include($CFG->dirroot.'/mod/flashcard/playview.controller.php');
}

if (empty($subquestions)) {
    echo $out;
    echo $OUTPUT->box_start();
    echo print_string('undefinedquestionset', 'flashcard');
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    return;
}

if (!$round->exists()) {
    // A new round: only on a due deck, unless a teacher is previewing.
    $wait = flashcard_deck_wait($flashcard, $deck, $USER->id);
    if ($wait === null) {
        redirect($deckstable);
    }
    if ($wait > 0 && !has_capability('mod/flashcard:manage', $context)) {
        redirect($deckstable, get_string('decklocked', 'flashcard', format_time($wait)), null,
            \core\output\notification::NOTIFY_INFO);
    }
    $cardids = $DB->get_fieldset_select('flashcard_card', 'id', 'flashcardid = ? AND userid = ? AND deck = ?',
        [$flashcard->id, $USER->id, $deck]);
    $round->start($cardids);

    // Unmark state for this deck.
    $params = array('userid' => $USER->id, 'flashcardid' => $flashcard->id, 'deck' => $deck);
    $DB->delete_records('flashcard_userdeck_state', $params);
}

$cardid = $round->current();
$card = $cardid === null ? null : $DB->get_record('flashcard_card', ['id' => $cardid]);
if ($cardid !== null && !$card) {
    // The teacher deleted the card mid-round.
    $round->clear();
    redirect($deckstable);
}

// Print deferred header.
echo $out;

if ($card === null) {
    echo $renderer->round_done($flashcard, $cm, $deck, $round->results());
    return;
}

$subquestion = (object) ['entryid' => $card->entryid, 'cardid' => $card->id];
$cards = [$card->id => $card];
$current = [$subquestion];

echo $renderer->playview($flashcard, $cm, $cards, $current, $round->remaining());
