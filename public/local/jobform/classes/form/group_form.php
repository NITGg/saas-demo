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

namespace local_jobform\form;

use local_jobform\mlang;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add / edit a Job Form group (section). Reused by the admin template editor
 * and the per-activity editor; the caller decides where to save.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // The group's own id (0 = new). Named 'groupid' to avoid the mod's 'id' (cmid).
        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', 0);

        $mform->addElement('text', 'name_en', get_string('groupname_en', 'local_jobform'), ['size' => 50]);
        $mform->setType('name_en', PARAM_TEXT);
        $mform->addRule('name_en', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'name_ar', get_string('groupname_ar', 'local_jobform'),
            ['size' => 50, 'dir' => 'rtl']);
        $mform->setType('name_ar', PARAM_TEXT);

        $this->add_action_buttons();
    }

    /**
     * Collapse the per-language inputs into the stored {mlang} name.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->name = mlang::build(['en' => $data->name_en ?? '', 'ar' => $data->name_ar ?? '']);
        }
        return $data;
    }

    /**
     * Prime the form from a stored group record.
     *
     * @param object $group
     * @return void
     */
    public function set_group_data(object $group): void {
        $name = mlang::parse($group->name ?? '');
        $this->set_data([
            'groupid' => $group->id,
            'name_en' => $name['en'],
            'name_ar' => $name['ar'],
        ]);
    }
}
