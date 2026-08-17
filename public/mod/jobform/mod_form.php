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

/**
 * The settings form for a Job Form activity.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Job Form activity settings form.
 */
class mod_jobform_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        global $DB, $COURSE;

        $mform = $this->_form;

        // Activity name.
        $mform->addElement('text', 'name', get_string('jobformname', 'mod_jobform'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Description.
        $this->standard_intro_elements();

        // Availability settings.
        $mform->addElement('header', 'availabilityhdr', get_string('availability', 'mod_jobform'));

        // Linked certificate — the student may only submit after being issued it.
        $certoptions = [0 => get_string('nocertificate', 'mod_jobform')];
        $certoptions += self::get_course_certificates($COURSE->id);
        $mform->addElement('select', 'certid', get_string('linkedcertificate', 'mod_jobform'), $certoptions);
        $mform->addHelpButton('certid', 'linkedcertificate', 'mod_jobform');
        $mform->setDefault('certid', 0);

        // Resubmission.
        $mform->addElement('advcheckbox', 'allowresubmit', get_string('allowresubmit', 'mod_jobform'));
        $mform->addHelpButton('allowresubmit', 'allowresubmit', 'mod_jobform');
        $mform->setDefault('allowresubmit', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * List the customcert activities in a course as instanceid => name.
     *
     * @param int $courseid
     * @return array
     */
    protected static function get_course_certificates(int $courseid): array {
        $list = [];
        // No customcert module installed → nothing to link.
        if (!get_config('mod_customcert', 'version')) {
            return $list;
        }
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_instances_of('customcert') as $cm) {
            $list[$cm->instance] = format_string($cm->name);
        }
        return $list;
    }
}
