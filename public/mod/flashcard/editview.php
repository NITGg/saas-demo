<?php
// This file is part of the learningtimecheck plugin for Moodle - http://moodle.org/
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
 * This view provides a way for editing questions
 *
 * @package mod_flashcard
 * @category mod
 * @author Gustav Delius
 * @contributors Valery Fremaux
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/flashcard/editview_form.php');

$usercontext = context_user::instance($USER->id);

if ($cardid = optional_param('cardid', 0, PARAM_INT)) {
    $card = $DB->get_record('flashcard_deckdata', array('id' => $cardid));
}
$formurl = new moodle_url('/mod/flashcard/view.php', array('view' => 'edit'));
$params = array('flashcard' => $flashcard, 'cmid' => $cm->id, 'cmd' => $action, 'cardid' => $cardid);
$mform = new CardEdit_Form($formurl, $params);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/flashcard/view.php', array('id' => $id, 'view' => 'manage')));
}

if ($data = $mform->get_data()) {

    /*
     * Each card of the form is numbered (0, 1, 2) and each side names its fields after its
     * own media type: q0 / a0 for one text, image, sound or video, qi0 + qs0 / ai0 + as0 for
     * "image + sound". The upstream code derived the answer field from the question field
     * (qi0 -> ai0), so a set with "image + sound" on one side only died on a missing "a0".
     */
    $fs = get_file_storage();
    $count = ($data->what == 'addthree') ? 3 : 1;

    for ($n = 0; $n < $count; $n++) {
        if ($data->what == 'update') {
            $card = $DB->get_record('flashcard_deckdata', ['id' => $data->cardid, 'flashcardid' => $flashcard->id], '*', MUST_EXIST);
        } else {
            if (!flashcard_card_side_filled($flashcard->questionsmediatype, 'q', $n, $usercontext)) {
                continue; // Do NOT add unfilled cards.
            }
            $card = (object) ['flashcardid' => $flashcard->id, 'questiontext' => '', 'answertext' => ''];
            $card->id = $DB->insert_record('flashcard_deckdata', $card);

            // Add card to all student decks and reset state of deck 1 for all.
            $params = array('flashcardid' => $flashcard->id);
            $deckusers = $DB->get_records('flashcard_card', $params, 'userid', 'DISTINCT userid, userid');
            foreach (array_keys($deckusers) as $duid) {
                $DB->insert_record('flashcard_card', (object) [
                    'flashcardid' => $flashcard->id, 'userid' => $duid, 'deck' => 1,
                    'entryid' => $card->id, 'lastaccessed' => 0, 'accesscount' => 0,
                ]);
                $DB->set_field('flashcard_userdeck_state', 'state', 0, ['flashcardid' => $flashcard->id, 'userid' => $duid]);
            }
        }

        flashcard_save_card_side($card, 'question', $flashcard->questionsmediatype, $n, $context, $usercontext);
        flashcard_save_card_side($card, 'answer', $flashcard->answersmediatype, $n, $context, $usercontext);
        $DB->update_record('flashcard_deckdata', $card);
    }

    redirect(new moodle_url('/mod/flashcard/view.php', array('id' => $id, 'view' => 'manage')));
}

// Print deferred header.
echo $out;

// If cardid, load card into form.
if ($cardid) {
    $card->cardid = $card->id;
    $card->id = $cm->id;
    $mform->set_data($card);
} else {
    $data = new StdClass;
    $data->id = $cm->id;
    $mform->set_data($data);
}
echo '<div id="flashcard-card-form">';
$mform->display();
echo '</div>';

