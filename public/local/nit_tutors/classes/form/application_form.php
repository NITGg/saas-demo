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

namespace local_nit_tutors\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_tutors\local\fields;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\video;

/**
 * The teacher's profile / application form (/local/nit_tutors/application.php): about you,
 * subjects, intro video, qualifications and the admin's application fields.
 *
 * Custom data: userid, editable (false while the application waits for review),
 * qualcount (rows to draw), subjectoptions [value => label].
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class application_form extends \moodleform {

    /**
     * Options of the video file manager.
     *
     * @return array
     */
    public static function video_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => video::max_bytes(), 'accepted_types' => video::ACCEPTED];
    }

    /**
     * Options of a certificate / file-answer file manager.
     *
     * @return array
     */
    public static function file_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 10 * 1024 * 1024, 'accepted_types' => qualifications::ACCEPTED];
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $c = $this->_customdata;
        $s = fn(string $k, $a = null) => get_string($k, 'local_nit_tutors', $a);

        // About you.
        $mform->addElement('header', 'h_about', $s('form_about'));
        $mform->setExpanded('h_about');
        $mform->addElement('text', 'headline', $s('form_headline'), ['maxlength' => 255, 'size' => 60]);
        $mform->setType('headline', PARAM_TEXT);
        $mform->addHelpButton('headline', 'form_headline', 'local_nit_tutors');
        $mform->addElement('textarea', 'bio', $s('form_bio'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('bio', PARAM_TEXT);
        $mform->addElement('autocomplete', 'subjects', $s('form_subjects'), $c['subjectoptions'],
            ['multiple' => true, 'noselectionstring' => $s('form_subjects_none')]);

        // Intro video.
        $mform->addElement('header', 'h_video', $s('form_video'));
        $mform->setExpanded('h_video');
        $mform->addElement('static', 'videohelp', '', $s('form_video_help'));
        $mform->addElement('select', 'videotype', $s('form_videotype'), [
            '' => $s('form_videotype_none'),
            video::UPLOAD => $s('form_videotype_upload'),
            video::LINK => $s('form_videotype_link'),
        ]);
        $mform->addElement('filemanager', 'videofile', $s('form_videofile'), null, self::video_options());
        $mform->hideIf('videofile', 'videotype', 'neq', video::UPLOAD);
        $mform->addElement('text', 'videourl', $s('form_videourl'), ['size' => 60, 'dir' => 'ltr',
            'placeholder' => 'https://www.youtube.com/watch?v=…']);
        $mform->setType('videourl', PARAM_RAW_TRIMMED);
        $mform->hideIf('videourl', 'videotype', 'neq', video::LINK);
        if (!empty($c['videostatus'])) {
            $mform->addElement('static', 'videostate', $s('form_videostate'), $c['videostatus']);
        }

        // Qualifications.
        $mform->addElement('header', 'h_quals', $s('form_quals'));
        $mform->setExpanded('h_quals');
        $mform->addElement('static', 'qualhelp', '', $s('form_quals_help'));
        $repeat = [
            $mform->createElement('hidden', 'qualid', 0),
            $mform->createElement('static', 'qualstate', ''),
            $mform->createElement('text', 'qualtitle', $s('form_qualtitle'), ['size' => 50]),
            $mform->createElement('text', 'qualinstitution', $s('form_qualinstitution'), ['size' => 50]),
            $mform->createElement('text', 'qualyear', $s('form_qualyear'), ['size' => 6, 'dir' => 'ltr']),
            $mform->createElement('filemanager', 'qualfile', $s('form_qualfile'), null, self::file_options()),
            $mform->createElement('advcheckbox', 'qualdelete', '', $s('form_qualdelete')),
        ];
        $options = [
            'qualid' => ['type' => PARAM_INT],
            'qualtitle' => ['type' => PARAM_TEXT],
            'qualinstitution' => ['type' => PARAM_TEXT],
            'qualyear' => ['type' => PARAM_INT],
        ];
        $this->repeat_elements($repeat, max(1, (int) $c['qualcount']), $options, 'qualrepeats', 'qualadd', 1,
            $s('form_qualadd'), true);

        // The admin's fields.
        $defs = fields::all();
        if ($defs) {
            $mform->addElement('header', 'h_more', $s('form_more'));
            $mform->setExpanded('h_more');
            foreach ($defs as $f) {
                $name = 'field_' . $f->id;
                $label = format_string($f->name);
                switch ($f->datatype) {
                    case 'textarea':
                        $mform->addElement('textarea', $name, $label, ['rows' => 4, 'cols' => 60]);
                        $mform->setType($name, PARAM_TEXT);
                        break;
                    case 'select':
                        $opts = ['' => get_string('choosedots')];
                        foreach (fields::options($f) as $o) {
                            $opts[$o] = format_string($o);
                        }
                        $mform->addElement('select', $name, $label, $opts);
                        break;
                    case 'date':
                        $mform->addElement('date_selector', $name, $label, ['optional' => !$f->required]);
                        break;
                    case 'checkbox':
                        $mform->addElement('advcheckbox', $name, $label);
                        break;
                    case 'file':
                        $mform->addElement('filemanager', $name, $label, null, self::file_options());
                        break;
                    default:
                        $mform->addElement('text', $name, $label, ['size' => 50] + ($f->datatype === 'text' ? [] : ['dir' => 'ltr']));
                        $mform->setType($name, $f->datatype === 'number' ? PARAM_RAW_TRIMMED : PARAM_TEXT);
                }
                if (trim((string) $f->description) !== '') {
                    $mform->addElement('static', $name . '_desc', '', format_text($f->description, FORMAT_PLAIN));
                }
            }
        }

        if (!empty($c['editable'])) {
            $buttons = [$mform->createElement('submit', 'savebutton', $s('form_save'))];
            if (!empty($c['cansubmit'])) {
                $buttons[] = $mform->createElement('submit', 'submitbutton', $s('form_submit'));
            }
            $buttons[] = $mform->createElement('cancel');
            $mform->addGroup($buttons, 'buttonar', '', [' '], false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            $mform->hardFreeze();
        }
    }

    /**
     * Validation: a bad link, a qualification without a title, a bad answer.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['videotype'] ?? '') === video::LINK && video::embed_url((string) ($data['videourl'] ?? '')) === null) {
            $errors['videourl'] = get_string('err_badvideourl', 'local_nit_tutors');
        }
        foreach ((array) ($data['qualtitle'] ?? []) as $i => $title) {
            $delete = !empty($data['qualdelete'][$i]);
            $other = trim((string) ($data['qualinstitution'][$i] ?? '')) !== '' || !empty($data['qualyear'][$i]);
            if (!$delete && trim((string) $title) === '' && $other) {
                $errors["qualtitle[$i]"] = get_string('err_qualtitle', 'local_nit_tutors');
            }
        }
        foreach (fields::all() as $f) {
            $name = 'field_' . $f->id;
            if ($f->datatype === 'file' || !array_key_exists($name, $data)) {
                continue;
            }
            try {
                fields::clean($f, $data[$name]);
            } catch (\moodle_exception $e) {
                $errors[$name] = $e->getMessage();
            }
        }
        return $errors;
    }
}
