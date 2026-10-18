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

use local_nit_tutors\local\badge;

/**
 * Add / edit one teacher badge.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badge_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $k) => get_string($k, 'local_nit_tutors');
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', $s('badge_name'), ['size' => 60]);
        $mform->setType('name', PARAM_RAW_TRIMMED);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'badge_name', 'local_nit_tutors');
        $mform->addElement('textarea', 'description', $s('badge_description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_RAW);
        $colors = [];
        foreach (badge::COLORS as $c) {
            $colors[$c] = $s('color_' . $c);
        }
        $mform->addElement('select', 'color', $s('badge_color'), $colors);
        $mform->addElement('advcheckbox', 'active', $s('field_active'));
        $mform->setDefault('active', 1);

        $mform->addElement('header', 'h_method', $s('badge_method'));
        $mform->setExpanded('h_method');
        $mform->addElement('select', 'method', $s('badge_method'), [
            badge::METHOD_AUTO => $s('badgemethod_auto'),
            badge::METHOD_MANUAL => $s('badgemethod_manual'),
        ]);
        $mform->addElement('static', 'rules_help', '', $s('badge_rules_help'));
        $mform->hideIf('rules_help', 'method', 'eq', badge::METHOD_MANUAL);
        $mform->addElement('text', 'minrating', $s('badge_minrating'), ['size' => 4, 'dir' => 'ltr']);
        $mform->setType('minrating', PARAM_FLOAT);
        $mform->setDefault('minrating', 0);
        $mform->hideIf('minrating', 'method', 'eq', badge::METHOD_MANUAL);
        $mform->addElement('text', 'minreviews', $s('badge_minreviews'), ['size' => 4, 'dir' => 'ltr']);
        $mform->setType('minreviews', PARAM_INT);
        $mform->setDefault('minreviews', 0);
        $mform->hideIf('minreviews', 'method', 'eq', badge::METHOD_MANUAL);
        $mform->addElement('text', 'minlessons', $s('badge_minlessons'), ['size' => 4, 'dir' => 'ltr']);
        $mform->setType('minlessons', PARAM_INT);
        $mform->setDefault('minlessons', 0);
        $mform->hideIf('minlessons', 'method', 'eq', badge::METHOD_MANUAL);
        $mform->addElement('text', 'maxabsence', $s('badge_maxabsence'), ['size' => 4, 'dir' => 'ltr']);
        $mform->setType('maxabsence', PARAM_FLOAT);
        $mform->setDefault('maxabsence', 100);
        $mform->hideIf('maxabsence', 'method', 'eq', badge::METHOD_MANUAL);
        $this->add_action_buttons();
    }

    /**
     * Validation: rules in range.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float) $data['minrating'] < 0 || (float) $data['minrating'] > 5) {
            $errors['minrating'] = get_string('err_rating', 'local_nit_tutors');
        }
        if ((float) $data['maxabsence'] < 0 || (float) $data['maxabsence'] > 100) {
            $errors['maxabsence'] = get_string('err_percent', 'local_nit_tutors');
        }
        foreach (['minreviews', 'minlessons'] as $k) {
            if ((int) $data[$k] < 0) {
                $errors[$k] = get_string('err_negative', 'local_nit_tutors');
            }
        }
        return $errors;
    }
}
