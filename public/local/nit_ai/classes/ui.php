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

namespace local_nit_ai;

/**
 * The two pieces of UI the assistant adds to an activity page: the teacher's
 * review panel, and the student's chat drawer.
 *
 * A module opts in by calling these from its view page; nothing is injected
 * behind a module's back.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {

    /**
     * Temporary kill switch for the student's "Ask about the lesson" drawer.
     *
     * Hard-coded on request: the feature is not to be shown to students for
     * now, but nothing else changes — teachers keep the review panel, the
     * transcript stays stored and approved, and the ask web service is
     * untouched. Flip this to false to bring the drawer back.
     */
    const CHAT_DRAWER_HIDDEN = false;

    /**
     * Temporary kill switch for the "Smart assistant" section of the activity
     * settings form.
     *
     * Hard-coded on request, and a hide rather than a removal: the section is
     * not drawn, so the form neither loads the stored transcript into a draft
     * area nor submits the switches — and api::save_from_module() honours the
     * same flag, because a save that ran without those fields would read them
     * as "off, no file" and wipe what a teacher already uploaded and approved.
     * Everything stored stays exactly as it is. Flip this to false to bring
     * the section back.
     */
    const FORM_HIDDEN = false;

    /**
     * Temporary kill switch for the teacher's "Smart assistant — transcript
     * review" panel on the activity page.
     *
     * Hard-coded on request. Only the panel goes: the approve endpoint, the
     * quiz generator page and every stored row stay as they are. Flip this to
     * false to bring the panel back.
     */
    const REVIEW_PANEL_HIDDEN = false;

    /**
     * Add the assistant's fields to an activity form.
     *
     * Three controls, and only one of them is a question the teacher has to
     * think about — the other two are the switch and the file. Everything else
     * about the transcript is detected and shown back for confirmation.
     *
     * @param \MoodleQuickForm $mform
     * @param object|null $cm the course module being edited, null while creating
     * @return void
     */
    public static function add_form_elements(\MoodleQuickForm $mform, ?object $cm = null): void {
        if (self::FORM_HIDDEN) {
            return;
        }

        $mform->addElement('header', 'nitaiheader', get_string('formheader', 'local_nit_ai'));

        $mform->addElement('advcheckbox', 'nitai_enabled', get_string('enabled', 'local_nit_ai'));
        $mform->addHelpButton('nitai_enabled', 'enabled', 'local_nit_ai');
        $mform->setDefault('nitai_enabled', 0);

        $mform->addElement(
            'filemanager',
            'nitai_transcript',
            get_string('transcriptfile', 'local_nit_ai'),
            null,
            self::filemanager_options()
        );
        $mform->addHelpButton('nitai_transcript', 'transcriptfile', 'local_nit_ai');

        $mform->addElement('advcheckbox', 'nitai_onscreen', get_string('hasonscreen', 'local_nit_ai'));
        $mform->addHelpButton('nitai_onscreen', 'hasonscreen', 'local_nit_ai');
        $mform->setDefault('nitai_onscreen', 0);
        $mform->hideIf('nitai_onscreen', 'nitai_enabled', 'notchecked');

        self::add_quizgen_element($mform, $cm);
    }

    /**
     * The way from the transcript to a quiz, offered where the transcript was
     * uploaded.
     *
     * Only ever a link out. The form on this page has unsaved changes in it, and
     * a button that generated questions from a file the teacher has just replaced
     * but not yet saved would describe the wrong video — so the wizard is a
     * separate page working from what is stored, and says so.
     *
     * @param \MoodleQuickForm $mform
     * @param object|null $cm
     * @return void
     */
    protected static function add_quizgen_element(\MoodleQuickForm $mform, ?object $cm): void {
        if (!$cm || empty($cm->id)) {
            return; // Creating the activity: there is no transcript to work from yet.
        }

        $context = \context_module::instance((int) $cm->id);
        if (!has_capability('local/nit_ai:generatequiz', $context)) {
            return;
        }

        $blockers = quizgen\generator::blockers($cm, $context);

        if ($blockers) {
            // Say why rather than hiding it: "there is no button" is the one
            // thing a teacher cannot troubleshoot.
            $html = \html_writer::tag('p', reset($blockers), ['class' => 'text-muted mb-0']);
        } else {
            $url = new \moodle_url('/local/nit_ai/quizgen.php', ['cmid' => (int) $cm->id]);
            $html = \html_writer::link($url, get_string('quizgen_button', 'local_nit_ai'), [
                'class'  => 'btn btn-secondary',
                'target' => '_blank',
                'rel'    => 'noopener',
            ]) . \html_writer::tag('p', get_string('quizgen_buttonnote', 'local_nit_ai'), [
                'class' => 'text-muted small mt-2 mb-0',
            ]);
        }

        $mform->addElement('static', 'nitai_quizgen', get_string('quizgen_formlabel', 'local_nit_ai'), $html);
    }

    /**
     * Fill the form with what is already stored: the file back into a draft
     * area, and the two switches.
     *
     * @param array $defaults form defaults, by reference
     * @param \context|null $context module context, null while creating
     * @param int $cmid 0 while creating
     * @return void
     */
    public static function prepare_form_defaults(array &$defaults, ?\context $context, int $cmid): void {
        if (self::FORM_HIDDEN) {
            return; // No elements to fill, and no reason to copy the file into a draft area.
        }

        $draftitemid = file_get_submitted_draft_itemid('nitai_transcript');

        file_prepare_draft_area(
            $draftitemid,
            $context ? $context->id : null,
            'local_nit_ai',
            api::FILEAREA,
            0,
            self::filemanager_options()
        );
        $defaults['nitai_transcript'] = $draftitemid;

        $record = $cmid ? api::get($cmid) : null;
        $defaults['nitai_enabled'] = $record ? (int) $record->enabled : 0;
        $defaults['nitai_onscreen'] = $record ? (int) $record->hasonscreen : 0;
    }

    /**
     * One transcript file, of the formats we can read.
     *
     * @return array
     */
    protected static function filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.vtt', '.srt', '.json', '.txt'],
        ];
    }

    /**
     * The teacher's review panel: what we read from the transcript, every check
     * that ran, and the approve button.
     *
     * Returns '' for anyone without the manage capability, so a module can call
     * it unconditionally.
     *
     * @param object $cm cm_info or course_modules record
     * @param \context $context module context
     * @return string HTML
     */
    public static function review_panel(object $cm, \context $context): string {
        global $OUTPUT;

        if (self::REVIEW_PANEL_HIDDEN) {
            return '';
        }

        if (!has_capability('local/nit_ai:manage', $context)) {
            return '';
        }

        $describe = source::describe($cm);
        $status = api::status((int) $cm->id, $describe['ref'], $describe['length']);

        if ($status === null) {
            return $OUTPUT->render_from_template('local_nit_ai/review_panel', [
                'empty' => true,
                'notranscript' => get_string('notranscript', 'local_nit_ai'),
                'title' => get_string('reviewtitle', 'local_nit_ai'),
            ]);
        }

        $record = $status['record'];

        return $OUTPUT->render_from_template('local_nit_ai/review_panel', self::quizgen_context($cm, $context) + [
            'empty' => false,
            'title' => get_string('reviewtitle', 'local_nit_ai'),
            'intro' => get_string('reviewintro', 'local_nit_ai'),
            'approved' => (bool) $record->approved,
            'enabled' => (bool) $record->enabled,
            'ready' => $status['ready'],
            'statustext' => self::status_text($record, $status),
            'facts' => self::facts($record, $describe['length']),
            'problems' => $status['problems'],
            'hasproblems' => !empty($status['problems']),
            'problemstitle' => get_string('problemsfound', 'local_nit_ai'),
            'canapprove' => !$record->approved && !$status['stale'],
            'approvelabel' => get_string('approve', 'local_nit_ai'),
            'approveurl' => (new \moodle_url('/local/nit_ai/approve.php', [
                'cmid' => (int) $cm->id,
                'sesskey' => sesskey(),
            ]))->out(false),
        ]);
    }

    /**
     * The quiz half of the review panel: the way to generate one, and what
     * happened last time somebody did.
     *
     * Kept beside the transcript because that is where a teacher already comes
     * to see whether the AI has what it needs. A quiz written from a transcript
     * that has since been replaced is the failure worth naming here — it looks
     * fine and asks about a video nobody is watching.
     *
     * @param object $cm
     * @param \context $context
     * @return array template context
     */
    protected static function quizgen_context(object $cm, \context $context): array {
        if (!has_capability('local/nit_ai:generatequiz', $context)) {
            return ['showquizgen' => false];
        }

        $blockers = quizgen\generator::blockers($cm, $context);
        $lastrun = quizgen\run::last_created((int) $cm->id);
        $lastquiz = null;

        if ($lastrun && $lastrun->quizcmid) {
            $describe = source::describe($cm);
            $quizcm = get_coursemodule_from_id('quiz', (int) $lastrun->quizcmid, 0, false, IGNORE_MISSING);

            if ($quizcm) {
                $lastquiz = [
                    'name'  => format_string($quizcm->name),
                    'url'   => (new \moodle_url('/mod/quiz/view.php', ['id' => (int) $quizcm->id]))->out(false),
                    'stale' => $describe['ref'] !== '' && $lastrun->sourceref !== ''
                        && $describe['ref'] !== $lastrun->sourceref,
                ];
            }
        }

        return [
            'showquizgen'    => true,
            'quizgenready'   => empty($blockers),
            'quizgenblocked' => $blockers ? reset($blockers) : '',
            'quizgenurl'     => (new \moodle_url('/local/nit_ai/quizgen.php', ['cmid' => (int) $cm->id]))->out(false),
            'quizgenlabel'   => get_string('quizgen_button', 'local_nit_ai'),
            'quizgentitle'   => get_string('quizgen_paneltitle', 'local_nit_ai'),
            'lastquiz'       => $lastquiz,
            'haslastquiz'    => $lastquiz !== null,
            'lastquizlabel'  => get_string('quizgen_lastquiz', 'local_nit_ai'),
            'lastquizstale'  => get_string('quizgen_lastquizstale', 'local_nit_ai'),
        ];
    }

    /**
     * One line saying where this transcript stands.
     *
     * @param \stdClass $record
     * @param array $status
     * @return string
     */
    protected static function status_text(\stdClass $record, array $status): string {
        if (!$record->enabled) {
            return get_string('notenabled', 'local_nit_ai');
        }
        if ($status['ready']) {
            return get_string('approved', 'local_nit_ai');
        }
        // Approved but still not running: something below is blocking it, and
        // saying "waiting for your approval" would send the teacher hunting for
        // a button that is correctly absent.
        if ($record->approved) {
            return get_string('approvedblocked', 'local_nit_ai');
        }
        return get_string('notapproved', 'local_nit_ai');
    }

    /**
     * The detected facts, as label/value rows.
     *
     * @param \stdClass $record
     * @param int $videolength
     * @return array
     */
    protected static function facts(\stdClass $record, int $videolength): array {
        $langkey = 'lang' . $record->lang;
        $facts = [
            [
                'label' => get_string('detectedformat', 'local_nit_ai'),
                'value' => \core_text::strtoupper($record->format),
            ],
            [
                'label' => get_string('detectedlanguage', 'local_nit_ai'),
                'value' => get_string_manager()->string_exists($langkey, 'local_nit_ai')
                    ? get_string($langkey, 'local_nit_ai')
                    : get_string('langunknown', 'local_nit_ai'),
            ],
            [
                'label' => get_string('detectedtimestamps', 'local_nit_ai'),
                'value' => $record->hastimestamps ? get_string('yes') : get_string('no'),
                'warn'  => !$record->hastimestamps,
            ],
        ];

        if ($record->hastimestamps) {
            $facts[] = [
                'label' => get_string('detectedsegments', 'local_nit_ai'),
                'value' => (string) $record->segmentcount,
            ];
            $facts[] = [
                'label' => get_string('detectedlast', 'local_nit_ai'),
                'value' => helper::timecode((int) $record->lasttimestamp),
            ];
            $facts[] = [
                'label' => get_string('videolength', 'local_nit_ai'),
                'value' => $videolength > 0
                    ? helper::timecode($videolength)
                    : get_string('lengthunknown', 'local_nit_ai'),
            ];
        }

        return $facts;
    }

    /**
     * The student's chat drawer, plus the JS that drives it.
     *
     * Returns '' whenever the assistant is not available, so a module can call
     * it unconditionally.
     *
     * @param object $cm cm_info or course_modules record
     * @param \context $context module context
     * @return string HTML
     */
    public static function chat_drawer(object $cm, \context $context): string {
        global $OUTPUT, $PAGE;

        if (self::CHAT_DRAWER_HIDDEN) {
            return '';
        }

        $describe = source::describe($cm);
        if (!api::is_available($cm, $context, $describe['ref'])) {
            return '';
        }

        $record = api::get((int) $cm->id);

        // Plain scripts rather than AMD: this codebase deliberately avoids the
        // grunt build step (see mod_vdocipher's uploader).
        $adapter = $record->hastimestamps ? source::adapter($cm) : '';
        if ($adapter !== '') {
            $PAGE->requires->js(new \moodle_url('/local/nit_ai/js/player_' . $adapter . '.js'));
        }
        $PAGE->requires->js(new \moodle_url('/local/nit_ai/js/chat.js'));

        return $OUTPUT->render_from_template('local_nit_ai/chat_drawer', [
            'cmid' => (int) $cm->id,
            'adapter' => $adapter,
            'title' => get_string('chattitle', 'local_nit_ai'),
            'intro' => get_string('chatintro', 'local_nit_ai'),
            'placeholder' => get_string('chatplaceholder', 'local_nit_ai'),
            'send' => get_string('chatsend', 'local_nit_ai'),
            'disclaimer' => get_string('chatdisclaimer', 'local_nit_ai'),
            'strthinking' => get_string('chatthinking', 'local_nit_ai'),
            'strerror' => get_string('err_aifailed', 'local_nit_ai'),
            'strjump' => get_string('jumpto', 'local_nit_ai', '{time}'),
        ]);
    }
}
