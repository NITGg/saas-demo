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

        // data-nitml: written once in any language, so local_nit_mlang leaves it a single box.
        $mform->addElement('text', 'title', $s('title'), ['size' => 60, 'maxlength' => sender::MAX_TITLE, 'data-nitml' => 'off']);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('textarea', 'body', $s('body'), ['rows' => 6, 'cols' => 60, 'maxlength' => sender::MAX_BODY,
            'data-nitml' => 'off']);
        $mform->setType('body', PARAM_TEXT);
        $mform->addRule('body', null, 'required', null, 'client');

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
        foreach (sender::validate($data) as $field => $key) {
            $a = ['err_titletoolong' => sender::MAX_TITLE, 'err_bodytoolong' => sender::MAX_BODY][$key] ?? null;
            $errors[$field] = get_string($key, 'local_nit_notifications', $a);
        }
        return $errors;
    }
}
