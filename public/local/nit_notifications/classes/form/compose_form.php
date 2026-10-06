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

namespace local_nit_notifications\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_notifications\audience;
use local_nit_notifications\mlang;
use local_nit_notifications\sender;

/**
 * Write a notification: who gets it, its type, title, text, an optional link
 * and whether to email it too. The next step shows how many users it reaches.
 *
 * customdata: audiences (key => label), courses (id => name; 0 = every course I manage).
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compose_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $key) => get_string($key, 'local_nit_notifications');

        $mform->addElement('select', 'audience', $s('audience'), $this->_customdata['audiences']);
        $mform->addRule('audience', null, 'required', null, 'client');

        $mform->addElement('select', 'courseid', get_string('course'), $this->_customdata['courses']);
        $mform->setType('courseid', PARAM_INT);
        foreach (array_keys($this->_customdata['audiences']) as $key) {
            if (!audience::is_course_audience($key)) {
                $mform->hideIf('courseid', 'audience', 'eq', $key);
            }
        }

        $types = [];
        foreach (sender::TYPES as $type) {
            $types[$type] = $s('type_' . $type);
        }
        $mform->addElement('select', 'type', $s('type'), $types);

        // A title and a text per installed language, the site language first and
        // required; each recipient gets their own language (see \local_nit_notifications\mlang).
        // data-nitml: these boxes are already per language, so local_nit_mlang leaves them alone.
        $languages = mlang::languages();
        $many = count($languages) > 1;
        $first = true;
        foreach ($languages as $code => $name) {
            $dir = get_string_manager()->get_string('thisdirection', 'langconfig', null, $code);
            $suffix = $many ? ' — ' . $name : '';
            $attrs = ['dir' => $dir, 'lang' => $code, 'data-nitml' => 'off'];

            $mform->addElement('text', 'title_' . $code, $s('title') . $suffix,
                $attrs + ['size' => 60, 'maxlength' => sender::MAX_TITLE]);
            $mform->setType('title_' . $code, PARAM_TEXT);
            $mform->addElement('textarea', 'body_' . $code, $s('body') . $suffix,
                $attrs + ['rows' => 5, 'cols' => 60, 'maxlength' => sender::MAX_BODY]);
            $mform->setType('body_' . $code, PARAM_TEXT);
            if ($first) {
                $mform->addRule('title_' . $code, null, 'required', null, 'client');
                $mform->addRule('body_' . $code, null, 'required', null, 'client');
                if ($many) {
                    $mform->addElement('static', 'langnote', '', $s('langnote'));
                }
                $first = false;
            }
        }

        $mform->addElement('text', 'url', $s('link'), ['size' => 60, 'placeholder' => 'https://…']);
        $mform->setType('url', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('url', 'link', 'local_nit_notifications');

        $mform->addElement('advcheckbox', 'email', $s('alsoemail'));

        $this->add_action_buttons(false, $s('next'));
    }

    /**
     * Validation: the same rules as the sender (rights, lengths, link).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $first = array_key_first(mlang::languages());
        foreach (sender::validate(self::compose((array) $data) + $data) as $field => $key) {
            $a = ['err_titletoolong' => sender::MAX_TITLE, 'err_bodytoolong' => sender::MAX_BODY][$key] ?? null;
            // Title / text errors show under the site language's box.
            $errors[in_array($field, ['title', 'body'], true) ? $field . '_' . $first : $field] =
                get_string($key, 'local_nit_notifications', $a);
        }
        return $errors;
    }

    /**
     * The stored title and text from the per-language boxes (multilang markup, or
     * plain text when only one language was written).
     *
     * @param array $data submitted form data
     * @return array{title:string, body:string}
     */
    public static function compose(array $data): array {
        $out = ['title' => [], 'body' => []];
        foreach (array_keys(mlang::languages()) as $code) {
            $out['title'][$code] = (string) ($data['title_' . $code] ?? '');
            $out['body'][$code] = (string) ($data['body_' . $code] ?? '');
        }
        return ['title' => mlang::compose($out['title']), 'body' => mlang::compose($out['body'])];
    }
}
