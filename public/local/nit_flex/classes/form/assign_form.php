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

namespace local_nit_flex\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_finance\local\money;

/**
 * Admin gives a package to a student (paid offline, or charged to the student wallet).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $key) => get_string($key, 'local_nit_flex');

        $mform->addElement('hidden', 'tab', 'assign');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('header', 'assignhdr', $s('assignpackage'));
        $mform->addElement('autocomplete', 'studentid', $s('student'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user((int) $userid);
                return $user ? fullname($user) . ' (' . s($user->email) . ')' : false;
            },
        ]);
        $mform->addRule('studentid', null, 'required', null, 'client');

        $mform->addElement('select', 'packageid', $s('package'), $this->_customdata['packages'] ?? []);
        $mform->addRule('packageid', null, 'required', null, 'client');

        $mform->addElement('text', 'amount', $s('assignamount'), ['size' => 8]);
        $mform->setType('amount', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('amount', 'assignamount', 'local_nit_flex');

        $mform->addElement('select', 'method', $s('assignmethod'), [
            'offline' => $s('paymethod_offline'),
            'bank' => $s('paymethod_bank'),
            'cash' => $s('paymethod_cash'),
            'wallet' => $s('paymethod_wallet'),
        ]);
        $mform->addHelpButton('method', 'assignmethod', 'local_nit_flex');

        $mform->addElement('text', 'reference', $s('reference'), ['size' => 40, 'maxlength' => 200]);
        $mform->setType('reference', PARAM_TEXT);

        $this->add_action_buttons(false, $s('assignpackage'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['studentid']) || !\core_user::get_user((int) $data['studentid'])) {
            $errors['studentid'] = get_string('required');
        }
        $amount = trim((string) ($data['amount'] ?? ''));
        if ($amount !== '' && money::to_minor($amount) === null) {
            $errors['amount'] = get_string('err_badprice', 'local_nit_finance');
        }
        return $errors;
    }
}
