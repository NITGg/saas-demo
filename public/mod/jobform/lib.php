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
 * Library of interface functions and constants for mod_jobform.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the list of features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function jobform_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COLLABORATION,
        default => null,
    };
}

/**
 * Add a new Job Form instance and seed its fields from the global template.
 *
 * @param stdClass $data form data
 * @param mod_jobform_mod_form|null $mform
 * @return int new instance id
 */
function jobform_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->intro = $data->intro ?? '';
    $data->introformat = $data->introformat ?? FORMAT_HTML;
    $data->certid = $data->certid ?? 0;
    $data->allowresubmit = !empty($data->allowresubmit) ? 1 : 0;

    $data->id = $DB->insert_record('jobform', $data);

    // Copy the default template fields into this activity so the admin has a
    // starting point they can then customise for this course only.
    \mod_jobform\instance_manager::seed_from_template($data->id);

    return $data->id;
}

/**
 * Update a Job Form instance.
 *
 * @param stdClass $data form data
 * @param mod_jobform_mod_form|null $mform
 * @return bool
 */
function jobform_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $data->certid = $data->certid ?? 0;
    $data->allowresubmit = !empty($data->allowresubmit) ? 1 : 0;

    return $DB->update_record('jobform', $data);
}

/**
 * Delete a Job Form instance and all of its fields and submissions.
 *
 * @param int $id instance id
 * @return bool
 */
function jobform_delete_instance($id) {
    global $DB;

    if (!$jobform = $DB->get_record('jobform', ['id' => $id])) {
        return false;
    }

    $submissionids = $DB->get_fieldset_select('jobform_submission', 'id', 'jobformid = ?', [$id]);
    if ($submissionids) {
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $DB->delete_records_select('jobform_submission_data', "submissionid $insql", $params);
    }
    $DB->delete_records('jobform_submission', ['jobformid' => $id]);
    $DB->delete_records('jobform_field', ['jobformid' => $id]);
    $DB->delete_records('jobform_group', ['jobformid' => $id]);
    $DB->delete_records('jobform', ['id' => $id]);

    return true;
}
