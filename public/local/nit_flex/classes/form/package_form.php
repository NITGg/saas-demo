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
 * Create / edit a Flex package (Arabic + English name and description).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $key) => get_string($key, 'local_nit_flex');

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'packages');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('header', 'pkg', $this->_customdata['title'] ?? $s('newpackage'));
        $mform->addElement('text', 'name_ar', $s('pkgname_ar'), ['size' => 40, 'maxlength' => 100]);
        $mform->setType('name_ar', PARAM_TEXT);
        $mform->addElement('text', 'name_en', $s('pkgname_en'), ['size' => 40, 'maxlength' => 100]);
        $mform->setType('name_en', PARAM_TEXT);
        $mform->addElement('textarea', 'desc_ar', $s('pkgdesc_ar'), ['rows' => 2, 'cols' => 50]);
        $mform->setType('desc_ar', PARAM_TEXT);
        $mform->addElement('textarea', 'desc_en', $s('pkgdesc_en'), ['rows' => 2, 'cols' => 50]);
        $mform->setType('desc_en', PARAM_TEXT);

        $mform->addElement('text', 'flex_count', $s('pkgflex'), ['size' => 6]);
        $mform->setType('flex_count', PARAM_INT);
        $mform->addHelpButton('flex_count', 'pkgflex', 'local_nit_flex');
        $mform->addRule('flex_count', null, 'required', null, 'client');

        $mform->addElement('text', 'price', $s('pkgprice'), ['size' => 8]);
        $mform->setType('price', PARAM_RAW_TRIMMED);
        $mform->addRule('price', null, 'required', null, 'client');

        $mform->addElement('text', 'expiration_days', $s('pkgdays'), ['size' => 6]);
        $mform->setType('expiration_days', PARAM_INT);
        $mform->setDefault('expiration_days', 0);
        $mform->addHelpButton('expiration_days', 'pkgdays', 'local_nit_flex');

        $mform->addElement('advcheckbox', 'active', $s('pkgactive'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons(!empty($this->_customdata['editing']), get_string('savechanges'));
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
        if (trim($data['name_ar'] ?? '') === '' && trim($data['name_en'] ?? '') === '') {
            $errors['name_ar'] = get_string('err_nameflexrequired', 'local_nit_flex');
        }
        if ((int) ($data['flex_count'] ?? 0) <= 0) {
            $errors['flex_count'] = get_string('err_flexpositive', 'local_nit_flex');
        }
        if (money::to_minor((string) ($data['price'] ?? '')) === null) {
            $errors['price'] = get_string('err_badprice', 'local_nit_finance');
        }
        if ((int) ($data['expiration_days'] ?? 0) < 0) {
            $errors['expiration_days'] = get_string('err_daysnegative', 'local_nit_flex');
        }
        return $errors;
    }
}
