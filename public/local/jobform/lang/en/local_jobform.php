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
 * Strings for local_jobform.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Job Form';
$string['jobform:manage'] = 'Manage the Job Form field template and view submitted forms';

// Admin page.
$string['managejobform'] = 'Manage Job Form';
$string['tabfields'] = 'Fields';
$string['tabsubmissions'] = 'Submitted forms';
$string['templatefieldsheading'] = 'Default fields';
$string['templatefieldsintro'] = 'These are the default fields a new Job Form activity starts from. Editing an activity later only changes that activity\'s copy.';

// Fields table.
$string['addfield'] = 'Add field';
$string['editfield'] = 'Edit field';
$string['nofields'] = 'No fields have been defined yet.';
$string['fieldname'] = 'Field label';
$string['fieldname_en'] = 'Field label (English)';
$string['fieldname_ar'] = 'Field label (Arabic)';
$string['fieldname_ar_help'] = 'Optional. If you fill in both languages, the label is stored as a bilingual {mlang} value and each student sees it in their own language. Leave blank to use the English label for everyone.';
$string['fieldgroup'] = 'Group';
$string['fieldgroup_help'] = 'Optional. Pick a group so this field is shown together with the others in that group, under one heading on the form. Choose "No group" to keep the field ungrouped. Create groups with the "Add group" button.';

// Groups.
$string['groups'] = 'Groups:';
$string['nogroups'] = 'No groups yet — add one to organise fields into sections.';
$string['nogroup'] = 'No group';
$string['addgroup'] = 'Add group';
$string['editgroup'] = 'Edit group';
$string['addfieldtogroup'] = 'Add a field to this group';
$string['nofieldsingroup'] = 'No fields in this group yet.';
$string['groupname_en'] = 'Group name (English)';
$string['groupname_ar'] = 'Group name (Arabic)';
$string['confirmdeletegroup'] = 'Are you sure you want to delete this group? Its fields will become ungrouped (they are not deleted).';
$string['invalidgroup'] = 'Invalid group.';
$string['usedefaultfields'] = 'Use default fields';
$string['fieldtype'] = 'Field type';
$string['fielddetails'] = 'Details';
$string['fieldrequired'] = 'Required';
$string['fieldoptions'] = 'Dropdown options';
$string['fieldoptions_help'] = 'Enter one option per line. The student picks from these values. To translate an option, put its Arabic on the same line number in the Arabic box; lines are paired by position.';
$string['fieldoptions_en'] = 'Dropdown options (English)';
$string['fieldoptions_ar'] = 'Dropdown options (Arabic)';
$string['optionn'] = 'Option {no}';
$string['optionenglish'] = 'English';
$string['optionarabic'] = 'Arabic';
$string['addoption'] = 'Add option';
$string['deleteoption'] = 'Remove';
$string['fieldmultiple'] = 'Allow multiple selection';
$string['fieldsingle'] = 'Single selection';
$string['fieldfixedvalue'] = 'Fixed value';
$string['fieldfixedvalue_help'] = 'A read-only value set by you (the admin). The student can see it but cannot change it — it is submitted as-is. Fill in both languages to show each student the value in their own language.';
$string['fieldfixedvalue_en'] = 'Fixed value (English)';
$string['fieldfixedvalue_ar'] = 'Fixed value (Arabic)';
$string['actions'] = 'Actions';
$string['confirmdeletefield'] = 'Are you sure you want to delete this field?';

// Field types.
$string['fieldtype_text'] = 'Text';
$string['fieldtype_number'] = 'Number';
$string['fieldtype_email'] = 'Email';
$string['fieldtype_phone'] = 'Phone';
$string['fieldtype_date'] = 'Date';
$string['fieldtype_checkbox'] = 'Checkbox';
$string['fieldtype_url'] = 'Link (URL)';
$string['fieldtype_select'] = 'Dropdown list';
$string['fieldtype_fixed'] = 'Fixed value';

// Validation / errors.
$string['erroroptionsrequired'] = 'Enter at least one option for a dropdown field.';
$string['errorfixedvaluerequired'] = 'Enter the fixed value.';
$string['invalidfield'] = 'Invalid field.';
$string['invalidsubmission'] = 'Invalid submission.';

// Submissions.
$string['jobform'] = 'Job Form';
$string['nosubmissions'] = 'No forms have been submitted yet.';
$string['modnotinstalled'] = 'The Job Form activity module is not installed yet, so there are no submissions to show.';
$string['student'] = 'Student';
$string['submittedon'] = 'Submitted on';
$string['viewsubmission'] = 'View submission';
$string['answers'] = 'Answers';
$string['answer'] = 'Answer';
$string['noanswers'] = 'This submission has no answers.';
$string['deletedfield'] = '(deleted field)';
$string['confirmdeletesubmission'] = 'Are you sure you want to permanently delete this submitted form?';
$string['submissiondeleted'] = 'The submitted form has been deleted.';

// Privacy.
$string['privacy:metadata'] = 'The Job Form manager plugin stores only the field template definitions and does not store personal data. Submitted forms are stored by the Job Form activity.';
