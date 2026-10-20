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
 * a controller for the play view
 *
 * Runs before playview.php prints anything. $round (mod_flashcard\local\round) is the
 * round of $deck for $USER; see that class for how a round works.
 *
 * @package mod_flashcard
 * @category mod
 * @author Valery Fremaux
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @version Moodle 2.0
 *
 * @usecase initialize
 * @usecase reset
 * @usecase igotit
 * @usecase ifailed
 */
defined('MOODLE_INTERNAL') || die();

/* ********************************** initialize a deck *************************************** */

if ($action == 'initialize') {
    $select = "flashcardid = ? AND userid = ? AND deck = ? ";
    if ($initials = $DB->get_records_select('flashcard_card', $select, array($flashcard->id, $USER->id, $deck))) {
        $_SESSION['flashcard_initials'] = implode("','", array_keys($initials));
    }
    // A new round starts in playview.php, once it has checked the deck is due.
    $round->clear();
}

/* ********************************** reset a deck *************************************** */

if ($action == 'reset') {
    $initials = explode("','", $_SESSION['flashcard_initials']);
    list($usql, $params) = $DB->get_in_or_equal(array_keys($initials));
    $DB->set_field_select('flashcard_card', 'deck', $deck, "id $usql ", $params);
    $round->clear();
}

/*
 * An answer counts only for the card the round is showing, and only for the student's own
 * card of this flashcard: a reloaded or re-posted form, or a forged cardid, changes nothing.
 */
$answeredcard = null;
if ($action == 'igotit' || $action == 'ifailed') {
    $cardid = required_param('cardid', PARAM_INT);
    if (confirm_sesskey() && $round->current() === $cardid) {
        $answeredcard = $DB->get_record('flashcard_card',
            ['id' => $cardid, 'flashcardid' => $flashcard->id, 'userid' => $USER->id]);
    }
}

/* ********************************* a card was declared right ************************ */

if ($action == 'igotit' && $answeredcard) {
    $card = $answeredcard;
    $olddeck = $card->deck;
    if ($card->deck < $flashcard->decks) {
        $card->deck = $deck + 1;
    }
    $card->lastaccessed = time();
    $card->accesscount++;
    $DB->update_record('flashcard_card', $card);
    $round->known((int) $card->id);

    /*
     * If pre-last deck, we need check the completion condition for "all good",
     * that is this is the last card that is passing to the last deck
     * note we will also confirm in checkview.php
     */

    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {

        if ($flashcard->completionallgood) {

            if ($olddeck == $flashcard->decks - 1) {

                $conditions = array('userid' => $USER->id, 'flashcardid' => $flashcard->id, 'deck' => $flashcard->decks);
                $lastdeckcards = $DB->count_records('flashcard_card', $conditions);
                $allcards = count($subquestions); // See playview.php.

                if ($lastdeckcards == $allcards) {
                    // Whatever the status of the last deck, we have brought all the cards there.
                    // Update completion state.
                    $completion->update_state($cm, COMPLETION_COMPLETE);
                }
            }
        } else if ($flashcard->completionallviewed) {
            // Allgood superseedes allviewed.
            // Deck does not matter here, all viewed cards in all decks... usually the first one.
            $allseencards = $DB->count_records('flashcard_card', array('userid' => $USER->id, 'flashcardid' => $flashcard->id));
            $allcards = count($subquestions); // See playview.php.
            if ($allseencards >= min($allcards, $flashcard->completionallviewed)) {
                // Update completion state.
                $completion = new completion_info($course);
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    }
}

/* **************************** a card was declared wrong **************************** */

if ($action == 'ifailed' && $answeredcard) {
    $card = $answeredcard;
    $card->lastaccessed = time();
    $card->accesscount++;
    $DB->update_record('flashcard_card', $card);
    $round->missed((int) $card->id);

    // Note allgood cannot win with a failed card as card do not move.
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        if ($flashcard->completionallviewed) {
            // Deck does not matter here, all viewed cards in all decks... usually the first one.
            $params = array('userid' => $USER->id, 'flashcardid' => $flashcard->id);
            $allseencards = $DB->count_records('flashcard_card', $params);
            $allcards = count($subquestions); // See playview.php.
            if ($allseencards >= min($allcards, $flashcard->completionallviewed)) {
                // Update completion state.
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }
    }
}
