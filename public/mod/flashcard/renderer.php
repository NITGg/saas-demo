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
 * @package     mod_flashcard
 * @category    mod
 * @author      Gustav Delius
 * @author      Tomas Muraz
 * @author      Valery Fremaux
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * Master renderer
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/repository/lib.php');

class mod_flashcard_renderer extends plugin_renderer_base {

    public function filepicker($elname, $value, $contextid, $filearea, $cardid, $maxbytes, $acceptedtypes = '*') {
        global $COURSE, $PAGE;

        $str = '';

        // No existing area info provided - let's use fresh new draft area.
        if ($value) {
            $draftitemid = file_get_submitted_draft_itemid($filearea);
            $maxbytes = 100000;
            $options = array('subdirs' => 0, 'maxbytes' => $maxbytes, 'maxfiles' => 1);
            file_prepare_draft_area($draftitemid, $contextid, 'mod_flashcard', $filearea, $cardid, $options);
        } else {
            $draftitemid = file_get_unused_draft_itemid();
        }

        if ($COURSE->id == SITEID) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($COURSE->id);
        }

        $args = new stdClass();
        // Need these three to filter repositories list.
        $args->accepted_types = $acceptedtypes;
        $args->return_types = FILE_INTERNAL;
        $args->itemid = $draftitemid;
        $args->maxbytes = $maxbytes;
        $args->buttonname = $elname.'choose';
        $args->elementname = $elname;
        $id = $elname;

        $fp = new file_picker($args);
        $options = $fp->options;
        $options->context = $PAGE->context;
        $str .= $this->output->render($fp);
        $str .= '<input type="hidden" name="'.$elname.'" id="'.$id.'" value="'.$draftitemid.'" class="filepickerhidden"/>';

        $module = array(
            'name' => 'form_filepicker',
            'fullpath' => '/lib/form/filepicker.js',
            'requires' => array('core_filepicker', 'node', 'node-event-simulate')
        );

        $PAGE->requires->js_init_call('M.form_filepicker.init', array($fp->options), true, $module);

        $params = array(
            'env' => 'filepicker',
            'action' => 'browse',
            'itemid' => $draftitemid,
            'subdirs' => 0,
            'maxbytes' => $options->maxbytes,
            'maxfiles' => 1,
            'ctx_id' => $PAGE->context->id,
            'course' => $PAGE->course->id,
            'sesskey' => sesskey(),
        );
        $nonjsfilepicker = new moodle_url('/repository/draftfiles_manager.php', $params);

        // Non js file picker.
        $str .= '<noscript>';
        $str .= '<div>';
        $str .= '<object type="text/html" data="'.$nonjsfilepicker.'" height="160" width="600" style="border:1px solid #000">';
        $str .= '</object>';
        $str .= '</div>';
        $str .= '</noscript>';

        return $str;
    }

    /**
     * prints a deck depending on deck status
     * @param reference $cm the coursemodule
     * @param int $deck the deck number
     */
    public function print_deck(&$flashcard, &$cm, $deck) {

        $str = '';

        $emptydeckurl = $this->output->image_url('emptydeck', 'flashcard');
        if (!empty($flashcard->customreviewemptyfileid)) {
            $emptydeckurl = flashcard_get_file_url($flashcard->customreviewemptyfileid);
        }

        $decktoreviewurl = $this->output->image_url('enableddeck', 'flashcard');
        if (!empty($flashcard->customreviewfileid)) {
            $decktoreviewurl = flashcard_get_file_url($flashcard->customreviewfileid);
        }

        $deckreviewedurl = $this->output->image_url('disableddeck', 'flashcard');
        if (!empty($flashcard->customreviewedfileid)) {
            $deckreviewedurl = flashcard_get_file_url($flashcard->customreviewedfileid);
        }

        if ($deck == 0) {
            $str .= '<img src="'.$emptydeckurl.'"/>';
        }

        if ($deck > 0) {
            $params = array('view' => 'play', 'id' => $cm->id, 'deck' => $deck, 'what' => 'initialize');
            $linkurl = new moodle_url('/mod/flashcard/view.php', $params);
            $img = '<img src="'.$decktoreviewurl.'"/>';
            $str .= '<a href="'.$linkurl.'" title="'.get_string('playwithme', 'flashcard').'">'.$img.'</a>';
        }

        if ($deck < 0) {
            $deck = -$deck;
            $params = array('view' => 'play', 'id' => $cm->id, 'deck' => $deck, 'what' => 'initialize');
            $linkurl = new moodle_url('/mod/flashcard/view.php', $params);
            $img = '<img src="'.$deckreviewedurl.'"/>';
            $str .= '<a href="'.$linkurl.'" title="'.get_string('reinforce', 'flashcard').'">'.$img.'</a>';
        }

        return $str;
    }

    /**
     * prints the deck status for use in teacher's overview
     * @param reference $flashcard the flashcard object
     * @param int $userid the user for which printing status
     * @param object $status a status object to be filled by the function
     */
    public function print_deck_status(&$flashcard, $userid, &$status) {

        $template = new StdClass();

        for ($i = 0; $i < $flashcard->decks; $i++) {
            $cardsettpl = new StdClass;
            if ($status->decks[$i]->count) {
                $cardsettpl->hascards = true;
                $image = ($status->decks[$i]->reactivate) ? 'topenabled' : 'topdisabled';
                $cardsettpl->height = $status->decks[$i]->count * 3;
                $title = get_string('cardsindeck', 'flashcard', $status->decks[$i]->count);
                $cardsettpl->icon = $this->output->pix_icon($image, $title, 'flashcard');

                $dayslateness = floor((time() - $status->decks[$i]->lastaccess) / DAYSECS);

                $delaykey = "deck{$i}_delay";
                if (empty($flashcard->$delaykey)) {
                    $flashcard->$delaykey = 0;
                }
                $timetoreview = round(max(0, ($status->decks[$i]->lastaccess + ($flashcard->$delaykey * HOURSECS) - time()) / DAYSECS));
                $strtimetoreview = get_string('timetoreview', 'flashcard', $timetoreview);
                for ($j = 0; $j < min($dayslateness, floor($flashcard->$delaykey / 24)); $j++) {
                    $clocktpl = new StdClass;
                    $clocktpl->latemark = $this->output->pix_icon('clock', $strtimetoreview, 'flashcard', array('valign' => 'bottom'));
                    $cardsettpl->clocks[] = $clocktpl;
                }
                if ($dayslateness < $flashcard->$delaykey / 24) {
                    for (; $j < $flashcard->$delaykey / 24; $j++) {
                        $clocktpl = new StdClass;
                        $clocktpl->latemark = $this->output->pix_icon('shadowclock', $strtimetoreview, 'flashcard', array('valign' => 'bottom'));
                        $cardsettpl->clocks[] = $clocktpl;
                    }
                } else if ($dayslateness > $flashcard->$delaykey / 24) {
                    // Deck 1 has no release limit as cards can stay here as long as not viewed.
                    for ($j = 0; $j < min($dayslateness - floor($flashcard->$delaykey / 24), 4); $j++) {
                        $clocktpl = new StdClass;
                        $clocktpl->latemark = $this->output->pix_icon('overtime', $strtimetoreview, 'flashcard', array('valign' => 'bottom'));
                        $cardsettpl->clocks[] = $clocktpl;
                    }
                }
            } else {
                $cardsettpl->hascards = false;
            }
            $template->cardset[] = $cardsettpl;
        }

        $options['id'] = $flashcard->cm->id;
        $options['view'] = 'summary';
        $options['what'] = 'reset';
        $options['userid'] = $userid;
        $template->resetbutton = $this->output->single_button(new moodle_url('/mod/flashcard/view.php', $options), get_string('reset'));

        return $this->output->render_from_template('mod_flashcard/cardstatus', $template);
    }

    /**
     * prints some statistic counters about decks
     * @param reference $flashcard
     * @param int $userid
     * @uses $USER
     * @uses $DB
     */
    public function print_deckcounts($flashcard, $userid = 0) {
        global $USER, $DB;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $sql = "
            SELECT
                MIN(accesscount) AS minaccess,
                MAX(accesscount) AS maxaccess,
                AVG(accesscount) AS avgaccess,
                SUM(accesscount) AS sumaccess
            FROM
                {flashcard_card}
            WHERE
                flashcardid = ? AND
                userid = ?
        ";

        $rec = $DB->get_record_sql($sql, array($flashcard->id, $userid));

        $template = $rec;

        return $this->output->render_from_template('mod_flashcard/deckcounts', $template);
    }

    /**
     * prints an image on card side.
     * @param reference $flashcard the flashcard object
     * @param string $imagename
     * @param boolean $return
     * @uses $CFG
     * @uses $COURSE
     */
    public function print_image(&$flashcard, $imagefileid) {
        global $CFG;

        $fs = get_file_storage();

        // New way : probably no effective fielids storage needed anymore.
        $cm = get_coursemodule_from_instance('flashcard', $flashcard->id);
        $context = context_module::instance($cm->id);
        $contextid = $context->id;
        list($filearea, $itemid) = explode('/', $imagefileid);
        $imagefiles = $fs->get_area_files($context->id, 'mod_flashcard', $filearea, $itemid);

        if (empty($imagefiles)) {
            $imagefileurl = $this->output->image_url('notfound', 'flashcard');
            $imagehtml = '<img src="'.$imagefileurl.'" width="100%" height="100%" />';
            return $imagehtml;
        }

        $imagefile = array_pop($imagefiles);
        $filename = $imagefile->get_filename();

        $magic = rand(0, 100000);
        if (empty($htmlname)) {
            $htmlname = "bell_{$magic}";
        }

        $imagefileurl = $CFG->wwwroot."/pluginfile.php/{$contextid}/mod_flashcard/{$filearea}/{$itemid}/{$filename}";

        $imagehtml = '<img src="'.$imagefileurl.'" width="100%" height="100%" />';

        return $imagehtml;
    }

    /**
     * The stored file of a card side, with its URL.
     *
     * @param stdClass $flashcard
     * @param string $fileid "<filearea>/<itemid>"
     * @return array [stored_file|null, string url]
     */
    protected function card_file($flashcard, string $fileid): array {
        $cm = get_coursemodule_from_instance('flashcard', $flashcard->id);
        $context = context_module::instance($cm->id);
        [$filearea, $itemid] = explode('/', $fileid);
        $files = get_file_storage()->get_area_files($context->id, 'mod_flashcard', $filearea, $itemid, 'id', false);
        if (!$files) {
            return [null, ''];
        }
        $file = array_pop($files);
        $url = moodle_url::make_pluginfile_url($context->id, 'mod_flashcard', $filearea, $itemid,
            '/', $file->get_filename());
        return [$file, $url->out(false)];
    }

    /**
     * plays a soundcard
     *
     * The browser's own <audio> player. The upstream one was a Flash applet (dewplayer) for
     * mp3 and an <embed> for anything else; no current browser runs either.
     *
     * @param reference $flashcard
     * @param string $soundfileid "<filearea>/<itemid>"
     * @param string $autostart 'true' to start playing as soon as the side is shown
     * @param string $htmlname id prefix, kept for the "_player" id the old scripts used
     */
    public function play_sound(&$flashcard, $soundfileid, $autostart = 'false', $htmlname = '') {
        [$file, $url] = $this->card_file($flashcard, $soundfileid);
        if (!$file) {
            return $this->output->pix_icon('notfound', '', 'flashcard');
        }
        $attrs = [
            'class' => 'nit-fc-media nit-fc-audio',
            'controls' => 'controls',
            'preload' => 'metadata',
            'src' => $url,
            'data-autoplay' => ($autostart === 'true' || $autostart === true) ? 1 : 0,
        ];
        if ($htmlname) {
            $attrs['id'] = $htmlname . '_player';
        }
        // Clicks on the player must not flip the card.
        $attrs['onclick'] = 'event.stopPropagation();';
        return html_writer::tag('audio', html_writer::link($url, s($file->get_filename())), $attrs);
    }

    /**
     * plays a video card
     *
     * The browser's own <video> player. The upstream flowplayer needed a global jQuery
     * (gone in Moodle 5) and Flash as a fallback, so it never started.
     *
     * @param reference $flashcard
     * @param string $videofileid "<filearea>/<itemid>"
     * @param string $autostart 'true' to start playing as soon as the side is shown
     * @param string $htmlname id prefix
     * @param bool $thumb a small preview (card editor)
     */
    public function play_video(&$flashcard, $videofileid, $autostart = 'false', $htmlname = '', $thumb = false) {
        [$file, $url] = $this->card_file($flashcard, $videofileid);
        if (!$file) {
            return $this->output->pix_icon('notfound', '', 'flashcard');
        }
        $attrs = [
            'class' => 'nit-fc-media nit-fc-video' . ($thumb ? ' is-thumb' : ''),
            'controls' => 'controls',
            'preload' => 'metadata',
            'playsinline' => 'playsinline',
            'data-autoplay' => ($autostart === 'true' || $autostart === true) ? 1 : 0,
            'onclick' => 'event.stopPropagation();',
        ];
        if ($htmlname) {
            $attrs['id'] = $htmlname . '_player';
        }
        $source = html_writer::empty_tag('source', ['src' => $url, 'type' => $file->get_mimetype()]);
        return html_writer::tag('video', $source . html_writer::link($url, s($file->get_filename())), $attrs);
    }

    public function print_custom_url(&$flashcard, $filearea, $itemid) {
        global $CFG;

        // New way : probably no effective fieldids storage needed anymore.
        $cm = get_coursemodule_from_instance('flashcard', $flashcard->id);
        $context = context_module::instance($cm->id);
        $contextid = $context->id;

        $fs = get_file_storage();

        $customfiles = $fs->get_area_files($context->id, 'mod_flashcard', $filearea, 0);
        if (empty($customfiles)) {
            return;
        }
        $customfile = array_pop($customfiles);
        $filename = $customfile->get_filename();

        $url = $CFG->wwwroot."/pluginfile.php/{$contextid}/mod_flashcard/{$filearea}/{$itemid}/{$filename}";
        return $url;
    }

    /**
     * prints a graphical represnetation of decks, proportionnaly to card count
     * @param reference $flashcard
     * @param object $card
     */
    public function print_cardcounts(&$flashcard, $card) {

        $template = new Stdclass;
        $template->width0 = (1 + @$card->deck[0]);
        $template->width1 = (1 + @$card->deck[1]);

        if ($flashcard->decks >= 3) {
            $template->width2 = (1 + @$card->deck[2]);
        }
        if ($flashcard->decks >= 4) {
            $template->width3 = (1 + @$card->deck[3]);
        }

        return $this->output->render_from_template('mod_flashcard/cardcounts', $template);
    }

    public function playview(&$flashcard, &$cm, &$cards, &$subquestions, ?int $remaining = null) {
        global $DB;

        $thisurl = new moodle_url('/mod/flashcard/view.php');
        $deck = required_param('deck', PARAM_INT);

        $random = rand(0, count($subquestions) - 1);
        $subquestion = $DB->get_record('flashcard_deckdata', array('id' => $subquestions[$random]->entryid));

        $back = 'question';
        $front = 'answer';

        if ($flashcard->flipdeck) {
            // Flip card side values.
            $tmp = $subquestion->answertext;
            $subquestion->answertext = $subquestion->questiontext;
            $subquestion->questiontext = $tmp;
            $back = 'answer';
            $front = 'question';

            // Flip media types.
            $tmp = $flashcard->answersmediatype;
            $flashcard->answersmediatype = $flashcard->questionsmediatype;
            $flashcard->questionsmediatype = $tmp;
        }

        $autoplay = ($flashcard->audiostart) ? 'true' : 'false';

        // The two faces, in the same media order the original markup used.
        if ($flashcard->questionsmediatype == FLASHCARD_MEDIA_IMAGE) {
            $fronthtml = $this->print_image($flashcard, "{$back}imagefile/{$subquestion->id}");
        } else if ($flashcard->questionsmediatype == FLASHCARD_MEDIA_SOUND) {
            $fronthtml = $this->play_sound($flashcard, "{$back}soundfile/{$subquestion->id}", $autoplay, 'bell_q');
        } else if ($flashcard->questionsmediatype == FLASHCARD_MEDIA_VIDEO) {
            $fronthtml = $this->play_video($flashcard, "{$back}videofile/{$subquestion->id}", $autoplay, 'bell_q');
        } else if ($flashcard->questionsmediatype == FLASHCARD_MEDIA_IMAGE_AND_SOUND) {
            $fronthtml = $this->print_image($flashcard, "{$back}imagefile/{$subquestion->id}") . '<br/>'
                . $this->play_sound($flashcard, "{$back}soundfile/{$subquestion->id}", $autoplay, 'bell_q');
        } else {
            $fronthtml = format_text($subquestion->questiontext, FORMAT_HTML);
        }

        if ($flashcard->answersmediatype == FLASHCARD_MEDIA_IMAGE) {
            $backhtml = $this->print_image($flashcard, "{$front}imagefile/{$subquestion->id}");
        } else if ($flashcard->answersmediatype == FLASHCARD_MEDIA_SOUND) {
            $backhtml = $this->play_sound($flashcard, "{$front}soundfile/{$subquestion->id}", $autoplay, 'bell_a');
        } else if ($flashcard->answersmediatype == FLASHCARD_MEDIA_VIDEO) {
            $backhtml = $this->play_video($flashcard, "{$front}videofile/{$subquestion->id}", $autoplay, 'bell_a');
        } else if ($flashcard->answersmediatype == FLASHCARD_MEDIA_IMAGE_AND_SOUND) {
            $backhtml = $this->print_image($flashcard, "{$front}imagefile/{$subquestion->id}") . '<br/>'
                . $this->play_sound($flashcard, "{$front}soundfile/{$subquestion->id}", $autoplay, 'bell_a');
        } else {
            $backhtml = format_text($subquestion->answertext, FORMAT_HTML);
        }

        // Both answers post back to view.php by its absolute path: the activity also runs
        // inside local_academy's player iframe on a site under a sub-path (/jitsi_life),
        // where a relative "view.php" resolved outside the site.
        $answerurl = new moodle_url('/mod/flashcard/view.php');
        $common = [
            'id' => $cm->id,
            'view' => 'play',
            'deck' => $deck,
            'cardid' => $subquestions[$random]->cardid,
            'sesskey' => sesskey(),
        ];
        $hidden = function(array $params): array {
            $out = [];
            foreach ($params as $name => $value) {
                $out[] = ['name' => $name, 'value' => $value];
            }
            return $out;
        };

        $template = (object) [
            'globals' => json_encode([
                'qtype' => (string) $flashcard->questionsmediatype,
                'atype' => (string) $flashcard->answersmediatype,
                'maxitems' => count($cards),
            ]),
            'extracss' => $flashcard->extracss,
            'instructions' => get_string('instructions', 'flashcard'),
            'deck' => $deck,
            'remaining' => $remaining ?? count($subquestions),
            'fronthtml' => $fronthtml,
            'backhtml' => $backhtml,
            'frontclass' => ($flashcard->questionsmediatype == FLASHCARD_MEDIA_VIDEO) ? 'flashcard-question-video' : '',
            'backclass' => ($flashcard->answersmediatype == FLASHCARD_MEDIA_VIDEO) ? 'flashcard-answer-video' : '',
            'frontbg' => $this->print_custom_url($flashcard, 'customback', 0),
            'backbg' => $this->print_custom_url($flashcard, 'customfront', 0),
            'actionurl' => $answerurl->out(false),
            'gotparams' => $hidden($common + ['what' => 'igotit']),
            'failedparams' => $hidden($common + ['what' => 'ifailed']),
            'backurl' => (new moodle_url('/mod/flashcard/view.php', ['id' => $cm->id, 'view' => 'checkdecks']))->out(false),
        ];

        return $this->output->render_from_template('mod_flashcard/play', $template);
    }

    /**
     * The end of a Leitner round: what the student knew (moved up a deck), what they
     * missed (stays, back at the deck's next review), and when that is.
     *
     * @param stdClass $flashcard
     * @param stdClass $cm
     * @param int $deck
     * @param array $results ['known' => int[], 'missed' => int[]] from round::results()
     * @return string
     */
    public function round_done($flashcard, $cm, int $deck, array $results): string {
        global $DB, $USER;

        $words = function(array $cardids) use ($DB): array {
            if (!$cardids) {
                return [];
            }
            [$insql, $params] = $DB->get_in_or_equal($cardids);
            $rows = $DB->get_records_sql("SELECT c.id, d.questiontext, d.answertext
                                            FROM {flashcard_card} c
                                            JOIN {flashcard_deckdata} d ON d.id = c.entryid
                                           WHERE c.id $insql", $params);
            $out = [];
            foreach ($cardids as $id) {
                if (isset($rows[$id])) {
                    $out[] = [
                        'question' => format_string(strip_tags($rows[$id]->questiontext)),
                        'answer' => format_string(strip_tags($rows[$id]->answertext)),
                    ];
                }
            }
            return $out;
        };

        $known = $words($results['known']);
        $missed = $words($results['missed']);
        $wait = flashcard_deck_wait($flashcard, $deck, $USER->id);
        $next = $deck < $flashcard->decks ? $deck + 1 : $deck;

        $template = (object) [
            'deck' => $deck,
            'known' => $known,
            'knowncount' => count($known),
            'missed' => $missed,
            'missedcount' => count($missed),
            'score' => get_string('roundscore', 'flashcard', ['known' => count($known), 'missed' => count($missed)]),
            'knownnote' => get_string($deck < $flashcard->decks ? 'roundknownmoved' : 'roundknownkept', 'flashcard', $next),
            'nextreview' => ($wait !== null) ? get_string('roundnextreview', 'flashcard', format_time(max($wait, MINSECS))) : '',
            'decksurl' => (new moodle_url('/mod/flashcard/view.php', ['id' => $cm->id, 'view' => 'checkdecks']))->out(false),
            'freeplayurl' => ($flashcard->models & FLASHCARD_MODEL_FREEUSE)
                ? (new moodle_url('/mod/flashcard/view.php', ['id' => $cm->id, 'view' => 'freeplay']))->out(false) : '',
        ];
        return $this->output->render_from_template('mod_flashcard/round_done', $template);
    }

    public function finishtable() {

        $template = new StdClass;
        return $this->output->render_from_template('mod_flashcard/finished', $template);
    }

    public function freebuttons(&$subquestions) {
        global $COURSE;

        $template = new StdClass;
        $template->courseurl = new moodle_url('/course/view.php', array('id' => $COURSE->id));
        // The counter starts at the full set; js/module.js counts it down on "Remove".
        $template->subquestionscount = count($subquestions);

        return $this->output->render_from_template('mod_flashcard/freebuttons', $template);
    }

    public function check_decks(&$flashcard, &$cm, &$decks) {
        global $USER;

        $canpreview = has_capability('mod/flashcard:manage', context_module::instance($cm->id));

        // Deck titles by position: the first deck is always the hard one and the last the
        // easy one; a 3- or 4-deck set adds the middle and trivial ones in between.
        $titles = ['difficultcards', 'easycards'];
        if ($flashcard->decks == 3) {
            $titles = ['difficultcards', 'mediumeffortcards', 'easycards'];
        } else if ($flashcard->decks >= 4) {
            $titles = ['difficultcards', 'mediumeffortcards', 'easycards', 'trivialcards'];
        }

        $template = new StdClass;
        $template->boostrapdivider = 12 / $flashcard->decks;
        $template->decks = [];

        for ($i = 0; $i < $flashcard->decks; $i++) {
            if (empty($decks->decks[$i]->count)) {
                $decks->decks[$i] = $decks->decks[$i] ?? new StdClass;
                $decks->decks[$i]->count = 0;
            }
            $count = (int) $decks->decks[$i]->count;
            $reactivate = !empty($decks->decks[$i]->reactivate);

            // The same three states print_deck() draws: empty, due for review, reviewed.
            $state = $count == 0 ? 0 : ($reactivate ? $i + 1 : -($i + 1));

            $decktpl = new StdClass;
            $decktpl->number = $i + 1;
            $decktpl->header = $this->output->heading(get_string($titles[$i], 'flashcard'));
            $decktpl->title = get_string($titles[$i], 'flashcard');
            $decktpl->strdeck = get_string('cardsindeck', 'flashcard', $count);
            $decktpl->count = $count;
            $decktpl->deck = $this->print_deck($flashcard, $cm, $state);
            $decktpl->isempty = ($count == 0);
            $decktpl->isdue = ($count > 0 && $reactivate);
            $decktpl->isreviewed = ($count > 0 && !$reactivate);
            $decktpl->stack = array_fill(0, min($count, 3), true);

            // A reviewed deck is closed to students until its review time comes round
            // (playview.php refuses it too); a teacher may still open it to preview.
            if ($decktpl->isreviewed) {
                $wait = flashcard_deck_wait($flashcard, $i + 1, $USER->id);
                $decktpl->waitlabel = get_string('deckavailablein', 'flashcard', format_time(max((int) $wait, MINSECS)));
            }
            $canopen = $decktpl->isdue || ($decktpl->isreviewed && $canpreview);
            if ($canopen) {
                $params = ['view' => 'play', 'id' => $cm->id, 'deck' => $i + 1, 'what' => 'initialize'];
                $decktpl->playurl = (new moodle_url('/mod/flashcard/view.php', $params))->out(false);
                $decktpl->playlabel = get_string($decktpl->isdue ? 'playwithme' : 'deckpreview', 'flashcard');
            }
            $template->decks[] = $decktpl;
        }

        return $this->output->render_from_template('mod_flashcard/decks', $template);
    }

    /**
     * The teacher's follow-up (view=summary): the students tab or the words tab.
     *
     * @param stdClass $flashcard
     * @param stdClass $cm
     * @param string $page 'bycards' for the words, anything else for the students
     * @param int $groupid
     * @return string
     */
    public function report($flashcard, $cm, string $page, int $groupid = 0): string {
        $report = new \mod_flashcard\local\report($flashcard, $cm);
        $base = ['id' => $cm->id, 'view' => 'summary'];
        $decks = (int) $flashcard->decks;
        $text = function($html): string {
            return format_string(strip_tags((string) $html));
        };

        $template = (object) [
            'studentsurl' => (new moodle_url('/mod/flashcard/view.php', $base + ['page' => 'byusers']))->out(false),
            'wordsurl' => (new moodle_url('/mod/flashcard/view.php', $base + ['page' => 'bycards']))->out(false),
            'isstudents' => $page !== 'bycards',
            'iswords' => $page === 'bycards',
        ];

        if ($page === 'bycards') {
            $rows = $report->words($groupid);
            $template->words = [];
            foreach ($rows as $row) {
                $seen = $row->hard + $row->learning + $row->mastered;
                $template->words[] = [
                    'question' => $text($row->question),
                    'answer' => $text($row->answer),
                    'hard' => $row->hard,
                    'learning' => $row->learning,
                    'mastered' => $row->mastered,
                    'unseen' => $row->unseen,
                    'tries' => $row->tries,
                    'hardpercent' => $seen ? (int) round(100 * $row->hard / $seen) : 0,
                    'learningpercent' => $seen ? (int) round(100 * $row->learning / $seen) : 0,
                    'masteredpercent' => $seen ? (int) round(100 * $row->mastered / $seen) : 0,
                    'ishard' => $row->hard > 0,
                ];
            }
            $template->haswords = !empty($template->words);
            $template->hasmiddle = $decks > 2;
        } else {
            $rows = $report->students($groupid);
            $template->students = [];
            $counts = ['due' => 0, 'notstarted' => 0, 'total' => count($rows)];
            foreach ($rows as $row) {
                $deckchips = [];
                foreach ($row->decks as $deck => $count) {
                    $deckchips[] = ['deck' => $deck, 'count' => $count, 'ishard' => $deck == 1];
                }
                $hard = [];
                foreach ($row->hard as $word) {
                    $hard[] = ['question' => $text($word->question), 'answer' => $text($word->answer), 'tries' => $word->tries];
                }
                if (!$row->started) {
                    $counts['notstarted']++;
                } else if ($row->isdue) {
                    $counts['due']++;
                }
                $resetparams = $base + ['page' => 'byusers', 'what' => 'reset', 'userid' => $row->user->id, 'sesskey' => sesskey()];
                $template->students[] = [
                    'picture' => $this->output->user_picture($row->user, ['size' => 40, 'link' => false]),
                    'fullname' => fullname($row->user),
                    'started' => $row->started,
                    'percent' => $row->percent,
                    'mastered' => $row->mastered,
                    'total' => $row->total,
                    'masteredtext' => get_string('reportmastered', 'flashcard', (object) ['mastered' => $row->mastered, 'total' => $row->total]),
                    'decks' => $deckchips,
                    'hard' => $hard,
                    'hardcount' => count($hard),
                    'unseen' => $row->unseen,
                    'lastseen' => $row->lastseen ? userdate($row->lastseen, get_string('strftimedatetimeshort', 'langconfig')) : '',
                    'badge' => $this->student_badge($row),
                    'reseturl' => (new moodle_url('/mod/flashcard/view.php', $resetparams))->out(false),
                ];
            }
            $template->hasstudents = !empty($template->students);
            $template->legend = $this->badge_legend();
            $template->summary = get_string('reportsummary', 'flashcard', (object) $counts);
        }

        return $this->output->render_from_template('mod_flashcard/report', $template);
    }

    /**
     * The one status badge on a student's card in the report, worded so a teacher needs no
     * legend: what the student should do, which deck, and since or until when.
     *
     * @param stdClass $row a row of \mod_flashcard\local\report::students()
     * @return array {class, text, hint}
     */
    protected function student_badge(stdClass $row): array {
        $decks = implode(get_string('listsep', 'langconfig') . ' ', $row->duedecks ?? []);
        if (!$row->started) {
            return ['class' => 'is-idle', 'text' => get_string('badgeidle', 'flashcard'),
                'hint' => get_string('badgeidle_help', 'flashcard')];
        }
        if ($row->isoverdue) {
            return ['class' => 'is-late',
                'text' => get_string('badgelate', 'flashcard', (object) ['days' => $row->latedays, 'decks' => $decks]),
                'hint' => get_string('badgelate_help', 'flashcard')];
        }
        if ($row->isdue) {
            return ['class' => 'is-due', 'text' => get_string('badgedue', 'flashcard', $decks),
                'hint' => get_string('badgedue_help', 'flashcard')];
        }
        if ($row->total && $row->mastered >= $row->total) {
            return ['class' => 'is-ok', 'text' => get_string('badgemastered', 'flashcard'),
                'hint' => get_string('badgemastered_help', 'flashcard')];
        }
        $next = $row->nextwait !== null ? format_time(max($row->nextwait, MINSECS)) : '';
        return ['class' => 'is-ok', 'text' => get_string('badgeontrack', 'flashcard', $next),
            'hint' => get_string('badgeontrack_help', 'flashcard')];
    }

    /**
     * The legend above the students tab: every badge a student card can carry.
     *
     * @return array [{class, text, hint}]
     */
    protected function badge_legend(): array {
        $legend = [];
        foreach (['is-late' => 'badgelate', 'is-due' => 'badgedue', 'is-ok' => 'badgeontrack',
                'is-idle' => 'badgeidle'] as $class => $key) {
            $legend[] = ['class' => $class, 'text' => get_string($key . '_legend', 'flashcard'),
                'hint' => get_string($key . '_help', 'flashcard')];
        }
        return $legend;
    }
}
