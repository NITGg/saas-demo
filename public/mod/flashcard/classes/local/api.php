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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/flashcard/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * What the web-service functions (classes/external) share: one activity, its cards as
 * plain data, its deck table and the answer rule.
 *
 * The web plays a round from a queue kept in $SESSION (round.php). A web-service call has
 * no session that outlives the request, so the app keeps the queue itself; the server only
 * accepts an answer for a card that is still in the deck the app says it is playing, which
 * makes a repeated or replayed answer harmless.
 *
 * @package    mod_flashcard
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** Media type names the app sees, by FLASHCARD_MEDIA_* value. */
    const MEDIA = [
        FLASHCARD_MEDIA_TEXT => 'text',
        FLASHCARD_MEDIA_IMAGE => 'image',
        FLASHCARD_MEDIA_SOUND => 'sound',
        FLASHCARD_MEDIA_IMAGE_AND_SOUND => 'imageandsound',
        FLASHCARD_MEDIA_VIDEO => 'video',
    ];

    /** @var \stdClass */
    public $flashcard;

    /** @var \cm_info */
    public $cm;

    /** @var \stdClass */
    public $course;

    /** @var \context_module */
    public $context;

    /**
     * Load an activity and check the caller may see it (the caller of validate_context()).
     *
     * @param int $cmid
     */
    public function __construct(int $cmid) {
        global $DB;
        [$this->course, $this->cm] = get_course_and_cm_from_cmid($cmid, 'flashcard');
        $this->context = \context_module::instance($this->cm->id);
        $this->flashcard = $DB->get_record('flashcard', ['id' => $this->cm->instance], '*', MUST_EXIST);
    }

    /**
     * Whether the caller manages the activity (teacher): may open any deck, sees the report.
     *
     * @return bool
     */
    public function can_manage(): bool {
        return has_capability('mod/flashcard:manage', $this->context);
    }

    /**
     * Refuse students outside the activity's open/close dates (teachers always pass).
     */
    public function require_open(): void {
        if ($this->can_manage()) {
            return;
        }
        $now = time();
        $f = $this->flashcard;
        if (($f->starttime && $now < $f->starttime) || ($f->endtime && $now > $f->endtime)) {
            throw new \moodle_exception('outoftimerange', 'flashcard');
        }
    }

    /**
     * The decks of the caller, as the deck table shows them. Adds any new card first, like
     * the web page does on every visit.
     *
     * @param int $userid
     * @return array [{deck, title, count, state (due|waiting|empty), waitseconds, opensat}]
     */
    public function decks(int $userid): array {
        flashcard_initialize($this->flashcard, $userid);
        $titles = ['difficultcards', 'easycards'];
        if ($this->flashcard->decks == 3) {
            $titles = ['difficultcards', 'mediumeffortcards', 'easycards'];
        } else if ($this->flashcard->decks >= 4) {
            $titles = ['difficultcards', 'mediumeffortcards', 'easycards', 'trivialcards'];
        }
        $out = [];
        for ($deck = 1; $deck <= $this->flashcard->decks; $deck++) {
            $wait = flashcard_deck_wait($this->flashcard, $deck, $userid);
            $count = $this->count($deck, $userid);
            $out[] = [
                'deck' => $deck,
                'title' => get_string($titles[$deck - 1], 'flashcard'),
                'count' => $count,
                'state' => $wait === null ? 'empty' : ($wait === 0 ? 'due' : 'waiting'),
                'waitseconds' => (int) $wait,
                'opensat' => $wait ? time() + $wait : 0,
            ];
        }
        return $out;
    }

    /**
     * Cards of one user in one deck.
     *
     * @param int $deck
     * @param int $userid
     * @return int
     */
    public function count(int $deck, int $userid): int {
        global $DB;
        return $DB->count_records('flashcard_card', ['flashcardid' => $this->flashcard->id, 'userid' => $userid, 'deck' => $deck]);
    }

    /**
     * One card as the app draws it: both faces, already flipped when the teacher chose
     * "Flip answers and questions".
     *
     * @param \stdClass $entry flashcard_deckdata row
     * @param int $cardid the user's flashcard_card id (0 for free play)
     * @param int $deck the deck it is in (0 for free play)
     * @return array {cardid, entryid, deck, question {…}, answer {…}}
     */
    public function card(\stdClass $entry, int $cardid = 0, int $deck = 0): array {
        $front = $this->side($entry, 'question', (int) $this->flashcard->questionsmediatype);
        $back = $this->side($entry, 'answer', (int) $this->flashcard->answersmediatype);
        if ($this->flashcard->flipdeck) {
            [$front, $back] = [$back, $front];
        }
        return ['cardid' => $cardid, 'entryid' => (int) $entry->id, 'deck' => $deck, 'question' => $front, 'answer' => $back];
    }

    /**
     * One face of a card.
     *
     * @param \stdClass $entry
     * @param string $side 'question' or 'answer'
     * @param int $mediatype FLASHCARD_MEDIA_*
     * @return array {type, text, imageurl, soundurl, videourl}
     */
    protected function side(\stdClass $entry, string $side, int $mediatype): array {
        $textfield = $side . 'text';
        $text = (string) ($entry->$textfield ?? '');
        $out = [
            'type' => self::MEDIA[$mediatype] ?? 'text',
            'text' => $mediatype == FLASHCARD_MEDIA_TEXT
                ? format_text($text, FORMAT_HTML, ['context' => $this->context]) : '',
            'imageurl' => '',
            'soundurl' => '',
            'videourl' => '',
        ];
        $areas = [
            FLASHCARD_MEDIA_IMAGE => ['imageurl' => 'imagefile'],
            FLASHCARD_MEDIA_SOUND => ['soundurl' => 'soundfile'],
            FLASHCARD_MEDIA_VIDEO => ['videourl' => 'videofile'],
            FLASHCARD_MEDIA_IMAGE_AND_SOUND => ['imageurl' => 'imagefile', 'soundurl' => 'soundfile'],
        ];
        $fs = get_file_storage();
        foreach ($areas[$mediatype] ?? [] as $key => $area) {
            $files = $fs->get_area_files($this->context->id, 'mod_flashcard', $side . $area, $entry->id, 'id', false);
            if ($files) {
                $file = array_pop($files);
                $out[$key] = \moodle_url::make_webservice_pluginfile_url($this->context->id, 'mod_flashcard',
                    $side . $area, $entry->id, '/', $file->get_filename())->out(false);
            }
        }
        return $out;
    }

    /**
     * Record one answer of the caller.
     *
     * Accepted only when the card is the caller's own and still in $deck: "known" moves it
     * up a deck (it stays in the last one), "missed" leaves it; both stamp it, which is what
     * starts the deck's next review delay.
     *
     * @param int $userid
     * @param int $deck the deck the app is playing
     * @param int $cardid flashcard_card id
     * @param bool $known
     * @return array {accepted, deck (where the card is now)}
     */
    public function answer(int $userid, int $deck, int $cardid, bool $known): array {
        global $DB;
        $card = $DB->get_record('flashcard_card', ['id' => $cardid, 'flashcardid' => $this->flashcard->id, 'userid' => $userid]);
        if (!$card) {
            throw new \moodle_exception('errorupdatecard', 'flashcard');
        }
        if ((int) $card->deck !== $deck) {
            return ['accepted' => false, 'deck' => (int) $card->deck];
        }
        $olddeck = (int) $card->deck;
        if ($known && $card->deck < $this->flashcard->decks) {
            $card->deck = $card->deck + 1;
        }
        $card->lastaccessed = time();
        $card->accesscount++;
        $DB->update_record('flashcard_card', $card);
        $this->update_completion($userid, $known && $olddeck == $this->flashcard->decks - 1);
        return ['accepted' => true, 'deck' => (int) $card->deck];
    }

    /**
     * Completion, the same two rules as the web (playview.controller.php): "all good" once
     * every card reached the last deck, otherwise "all viewed" once enough cards were seen.
     *
     * @param int $userid
     * @param bool $reachedlast this answer moved a card into the last deck
     */
    protected function update_completion(int $userid, bool $reachedlast): void {
        global $DB;
        $completion = new \completion_info($this->course);
        if (!$completion->is_enabled($this->cm)) {
            return;
        }
        $all = $DB->count_records('flashcard_deckdata', ['flashcardid' => $this->flashcard->id]);
        if ($this->flashcard->completionallgood) {
            $last = $DB->count_records('flashcard_card',
                ['userid' => $userid, 'flashcardid' => $this->flashcard->id, 'deck' => $this->flashcard->decks]);
            if ($reachedlast && $last == $all) {
                $completion->update_state($this->cm, COMPLETION_COMPLETE, $userid);
            }
        } else if ($this->flashcard->completionallviewed) {
            $seen = $DB->count_records_select('flashcard_card', 'userid = ? AND flashcardid = ? AND accesscount > 0',
                [$userid, $this->flashcard->id]);
            if ($seen >= min($all, $this->flashcard->completionallviewed)) {
                $completion->update_state($this->cm, COMPLETION_COMPLETE, $userid);
            }
        }
    }
}
