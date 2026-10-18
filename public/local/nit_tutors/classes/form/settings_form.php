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

/**
 * The Settings tab of "Teacher applications" (stored as local_nit_tutors config).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_form extends \moodleform {

    /** Settings and their defaults. */
    const SETTINGS = ['requireapproval' => 1, 'requirevideo' => 1, 'minqualifications' => 1, 'videomaxmb' => 200,
        'allowreapply' => 1];

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $k) => get_string($k, 'local_nit_tutors');
        $mform->addElement('advcheckbox', 'requireapproval', $s('set_requireapproval'));
        $mform->addElement('static', 'requireapproval_desc', '', $s('set_requireapproval_desc'));
        $mform->addElement('advcheckbox', 'requirevideo', $s('set_requirevideo'));
        $mform->addElement('text', 'minqualifications', $s('set_minqualifications'), ['size' => 4]);
        $mform->setType('minqualifications', PARAM_INT);
        $mform->addElement('text', 'videomaxmb', $s('set_videomaxmb'), ['size' => 6]);
        $mform->setType('videomaxmb', PARAM_INT);
        $mform->addElement('static', 'videomaxmb_desc', '', $s('set_videomaxmb_desc'));
        $mform->addElement('advcheckbox', 'allowreapply', $s('set_allowreapply'));
        $this->add_action_buttons(false);
    }

    /**
     * The saved values (defaults where unset).
     *
     * @return \stdClass
     */
    public static function current(): \stdClass {
        $out = new \stdClass();
        foreach (self::SETTINGS as $name => $default) {
            $out->$name = \local_nit_tutors\local\application::setting_int($name, $default);
        }
        return $out;
    }

    /**
     * Store the submitted values.
     *
     * @param \stdClass $data
     */
    public static function store(\stdClass $data): void {
        foreach (self::SETTINGS as $name => $default) {
            $value = (int) ($data->$name ?? $default);
            if ($name === 'minqualifications') {
                $value = max(0, min(10, $value));
            } else if ($name === 'videomaxmb') {
                $value = max(1, $value);
            }
            set_config($name, $value, 'local_nit_tutors');
        }
    }
}
