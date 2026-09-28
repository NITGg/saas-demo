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

namespace local_nit_ai\form;

use local_nit_ai\quizgen\generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The few things the generator cannot work out for itself.
 *
 * Deliberately short. How many questions to write is not asked, because the
 * answer is "as many as the video teaches" and a number in a box would only
 * override that with a guess.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizgen_options extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $defaults = $this->_customdata;

        $mform->addElement('hidden', 'cmid', $defaults['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('text', 'quizname', get_string('quizgen_quizname', 'local_nit_ai'), ['size' => 60]);
        $mform->setType('quizname', PARAM_TEXT);
        $mform->setDefault('quizname', $defaults['quizname']);
        $mform->addRule('quizname', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'language', get_string('quizgen_language', 'local_nit_ai'), [
            'ar' => get_string('langar', 'local_nit_ai'),
            'en' => get_string('langen', 'local_nit_ai'),
        ]);
        $mform->setDefault('language', $defaults['language']);
        $mform->addHelpButton('language', 'quizgen_language', 'local_nit_ai');

        $types = [];
        foreach (generator::TYPES as $type) {
            $types[] = $mform->createElement(
                'advcheckbox',
                'type_' . $type,
                '',
                get_string('quizgen_type_' . $type, 'local_nit_ai')
            );
        }
        $mform->addGroup($types, 'typesgroup', get_string('quizgen_types', 'local_nit_ai'), '<br>', false);
        $mform->addHelpButton('typesgroup', 'quizgen_types', 'local_nit_ai');
        $mform->setDefault('type_multichoice', 1);
        $mform->setDefault('type_truefalse', 1);

        $this->add_action_buttons(true, get_string('quizgen_startbutton', 'local_nit_ai'));
    }

    /**
     * A run with no question types would produce nothing.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $chosen = false;
        foreach (generator::TYPES as $type) {
            $chosen = $chosen || !empty($data['type_' . $type]);
        }

        if (!$chosen) {
            $errors['typesgroup'] = get_string('quizgen_err_notypes', 'local_nit_ai');
        }

        return $errors;
    }
}
