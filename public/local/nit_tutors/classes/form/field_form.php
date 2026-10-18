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

/**
 * Add / edit one application field.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $k) => get_string($k, 'local_nit_tutors');
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', $s('field_name'), ['size' => 60]);
        $mform->setType('name', PARAM_RAW_TRIMMED);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'field_name', 'local_nit_tutors');
        $mform->addElement('text', 'shortname', $s('field_shortname'), ['size' => 30, 'dir' => 'ltr']);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $types = [];
        foreach (fields::TYPES as $t) {
            $types[$t] = $s('type_' . $t);
        }
        $mform->addElement('select', 'datatype', $s('field_type'), $types);
        $mform->addElement('textarea', 'options', $s('field_options'), ['rows' => 6, 'cols' => 50]);
        $mform->setType('options', PARAM_RAW);
        $mform->hideIf('options', 'datatype', 'neq', 'select');
        $mform->addElement('textarea', 'description', $s('field_description'), ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_RAW);
        $mform->addElement('advcheckbox', 'required', $s('field_required'));
        $mform->addElement('advcheckbox', 'public', $s('field_public'));
        $mform->addHelpButton('public', 'field_public', 'local_nit_tutors');
        $mform->hideIf('public', 'datatype', 'eq', 'file');
        $mform->addElement('advcheckbox', 'filter', $s('field_filter'));
        $mform->addHelpButton('filter', 'field_filter', 'local_nit_tutors');
        $mform->hideIf('filter', 'datatype', 'neq', 'select');
        $mform->addElement('advcheckbox', 'active', $s('field_active'));
        $mform->setDefault('active', 1);
        $this->add_action_buttons();
    }

    /**
     * Validation: unique shortname; a select needs options.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        if ($DB->record_exists_select(fields::TABLE, 'shortname = ? AND id <> ?', [$data['shortname'], (int) $data['id']])) {
            $errors['shortname'] = get_string('err_shortnametaken', 'local_nit_tutors');
        }
        if ($data['datatype'] === 'select' && trim((string) $data['options']) === '') {
            $errors['options'] = get_string('err_nooptions', 'local_nit_tutors');
        }
        return $errors;
    }
}
