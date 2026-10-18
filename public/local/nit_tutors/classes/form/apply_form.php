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

use local_nit_tutors\local\signup;

/**
 * "Join as a teacher": the account part (name, email, phone, password, terms).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $k) => get_string($k, 'local_nit_tutors');
        $mform->addElement('text', 'firstname', get_string('firstname'), ['size' => 30]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');
        $mform->addElement('text', 'lastname', get_string('lastname'), ['size' => 30]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('email'), ['size' => 30, 'dir' => 'ltr', 'autocomplete' => 'email']);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addElement('text', 'phone', $s('apply_phone'), ['size' => 20, 'dir' => 'ltr', 'autocomplete' => 'tel']);
        $mform->setType('phone', PARAM_RAW_TRIMMED);
        $mform->addRule('phone', null, 'required', null, 'client');
        $mform->addElement('passwordunmask', 'password', get_string('password'), ['autocomplete' => 'new-password']);
        $mform->setType('password', PARAM_RAW);
        $mform->addRule('password', null, 'required', null, 'client');
        if ($policy = \local_academy\local\registration::password_policy_text()) {
            $mform->addElement('static', 'policy', '', $policy);
        }
        $termsurl = new \moodle_url('/local/nit_pages/page.php', ['p' => 'terms', 'lang' => current_language()]);
        $mform->addElement('advcheckbox', 'agree', '',
            get_string('apply_agree', 'local_nit_tutors', $termsurl->out(false)));
        $this->add_action_buttons(false, $s('apply_create'));
    }

    /**
     * Validation through the sign-up rules.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return parent::validation($data, $files) + signup::validate(signup::clean($data), !empty($data['agree']));
    }
}
