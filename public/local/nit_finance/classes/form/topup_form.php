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

namespace local_nit_finance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_finance\local\money;

/**
 * Admin: add credit to (or take credit from) a student wallet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topup_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $key) => get_string($key, 'local_nit_finance');

        $mform->addElement('header', 'topup', $s('wallets_topup'));
        $mform->addElement('autocomplete', 'userid', $s('wallets_user'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'noselectionstring' => get_string('choosedots'),
            'valuehtmlcallback' => function($userid) {
                $user = \core_user::get_user((int) $userid);
                return $user ? fullname($user) . ' (' . s($user->email) . ')' : false;
            },
        ]);
        $mform->addRule('userid', null, 'required', null, 'client');

        $mform->addElement('select', 'direction', $s('wallets_direction'), [
            'add' => $s('wallets_add'),
            'take' => $s('wallets_take'),
        ]);
        $mform->addElement('text', 'amount', $s('wallets_amount'), ['size' => 8]);
        $mform->setType('amount', PARAM_RAW_TRIMMED);
        $mform->addRule('amount', null, 'required', null, 'client');

        $mform->addElement('text', 'note', $s('note'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(false, $s('wallets_topup'));
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
        $minor = money::to_minor((string) ($data['amount'] ?? ''));
        if (!$minor) {
            $errors['amount'] = get_string('err_amountpositive', 'local_nit_finance');
        }
        if (empty($data['userid']) || !\core_user::get_user((int) $data['userid'])) {
            $errors['userid'] = get_string('required');
        }
        return $errors;
    }
}
