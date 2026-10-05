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
 * Job Form external (web service) functions for the mobile app / students.
 *
 * @package    mod_jobform
 * @category   external
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;
use core_course\external\helper_for_get_mods_by_courses;
use local_jobform\field_types;
use local_jobform\mlang;
use mod_jobform\instance_manager;
use mod_jobform\group_manager;
use mod_jobform\submission_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * External functions for mod_jobform.
 */
class mod_jobform_external extends external_api {

    /**
     * Resolve a course-module id to [course, cm, jobform], with a clear error.
     *
     * `get_course_and_cm_from_cmid` throws a cryptic "Can't find data record"
     * (dml_missing_record) when the cmid does not exist, and a different error
     * when it exists but is not a Job Form. Both are turned into one actionable
     * message so the mobile developer knows the cmid is wrong.
     *
     * @param int $cmid
     * @return array [stdClass $course, cm_info $cm, stdClass $jobform]
     */
    protected static function resolve_cm(int $cmid): array {
        global $DB;
        try {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'jobform');
            $jobform = $DB->get_record('jobform', ['id' => $cm->instance], '*', MUST_EXIST);
        } catch (\moodle_exception $e) {
            throw new moodle_exception('errorinvalidcmid', 'mod_jobform', '', $cmid);
        }
        return [$course, $cm, $jobform];
    }

    // ---------------------------------------------------------------------
    // get_jobforms_by_courses — list the Job Form activities in courses.
    // ---------------------------------------------------------------------

    /**
     * Parameters for get_jobforms_by_courses.
     *
     * @return external_function_parameters
     */
    public static function get_jobforms_by_courses_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Array of course ids (empty for all the user\'s courses)', VALUE_DEFAULT, []),
            'lang' => new external_value(PARAM_LANG,
                'Language for the returned names (e.g. "ar"). Defaults to the user\'s language.',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return the Job Form activities the student can see in the given courses.
     *
     * @param int[] $courseids
     * @return array
     */
    public static function get_jobforms_by_courses(array $courseids = [], string $lang = ''): array {
        $params = self::validate_parameters(self::get_jobforms_by_courses_parameters(),
            ['courseids' => $courseids, 'lang' => $lang]);
        if (!empty($params['lang'])) {
            force_current_language($params['lang']);
        }
        $warnings = [];
        $returned = [];

        $mycourses = [];
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        if (!empty($params['courseids'])) {
            [$courses, $warnings] = util::validate_courses($params['courseids'], $mycourses);
            $instances = get_all_instances_in_courses('jobform', $courses);
            foreach ($instances as $instance) {
                $context = context_module::instance($instance->coursemodule);
                helper_for_get_mods_by_courses::format_name_and_intro($instance, 'mod_jobform');
                $returned[] = [
                    'id'            => $instance->id,
                    'coursemodule'  => $instance->coursemodule,
                    'course'        => $instance->course,
                    'name'          => $instance->name,
                    'intro'         => $instance->intro,
                    'introformat'   => $instance->introformat,
                    'certid'        => (int) $instance->certid,
                    'allowresubmit' => (int) $instance->allowresubmit,
                ];
            }
        }

        return ['jobforms' => $returned, 'warnings' => $warnings];
    }

    /**
     * Returns for get_jobforms_by_courses.
     *
     * @return external_single_structure
     */
    public static function get_jobforms_by_courses_returns(): external_single_structure {
        return new external_single_structure([
            'jobforms' => new external_multiple_structure(new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'Activity instance id'),
                'coursemodule'  => new external_value(PARAM_INT, 'Course module id'),
                'course'        => new external_value(PARAM_INT, 'Course id'),
                'name'          => new external_value(PARAM_RAW, 'Activity name'),
                'intro'         => new external_value(PARAM_RAW, 'Activity description'),
                'introformat'   => new external_value(PARAM_INT, 'Description format'),
                'certid'        => new external_value(PARAM_INT, 'Linked customcert instance id (0 = none)'),
                'allowresubmit' => new external_value(PARAM_INT, '1 if the student may edit and resend'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // view_jobform — log a view and update completion.
    // ---------------------------------------------------------------------

    /**
     * Parameters for view_jobform.
     *
     * @return external_function_parameters
     */
    public static function view_jobform_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Trigger the course_module_viewed event and update completion.
     *
     * @param int $cmid
     * @return array
     */
    public static function view_jobform(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::view_jobform_parameters(), ['cmid' => $cmid]);

        [$course, $cm, $jobform] = self::resolve_cm($params['cmid']);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:view', $context);

        $event = \mod_jobform\event\course_module_viewed::create([
            'objectid' => $jobform->id,
            'context'  => $context,
        ]);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('jobform', $jobform);
        $event->trigger();

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        return ['status' => true, 'warnings' => []];
    }

    /**
     * Returns for view_jobform.
     *
     * @return external_single_structure
     */
    public static function view_jobform_returns(): external_single_structure {
        return new external_single_structure([
            'status'   => new external_value(PARAM_BOOL, 'True on success'),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // get_form — fields, groups, the certificate gate and any saved answers.
    // ---------------------------------------------------------------------

    /**
     * Parameters for get_form.
     *
     * @return external_function_parameters
     */
    public static function get_form_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'lang' => new external_value(PARAM_LANG,
                'Language for the returned labels/options (e.g. "ar" or "en"). '
                . 'Defaults to the user\'s language.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return everything the student app needs to render and fill the form.
     *
     * @param int $cmid
     * @return array
     */
    public static function get_form(int $cmid, string $lang = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_form_parameters(),
            ['cmid' => $cmid, 'lang' => $lang]);

        [$course, $cm, $jobform] = self::resolve_cm($params['cmid']);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:view', $context);

        // Resolve the bilingual labels/options in the requested language.
        if (!empty($params['lang'])) {
            force_current_language($params['lang']);
        }

        [$intro, $introformat] = util::format_text($jobform->intro, $jobform->introformat,
            $context, 'mod_jobform', 'intro', 0);

        // Groups.
        $groups = [];
        foreach (group_manager::get_groups($jobform->id) as $group) {
            $groups[] = [
                'id'        => (int) $group->id,
                'name'      => mlang::resolve($group->name),
                'sortorder' => (int) $group->sortorder,
            ];
        }

        // Fields.
        $fields = [];
        foreach (instance_manager::get_fields($jobform->id) as $field) {
            $config = field_types::decode_config($field->configdata);
            $options = [];
            if (field_types::has_options($field->type)) {
                foreach ($config['options'] as $raw) {
                    $options[] = ['value' => $raw, 'label' => mlang::resolve($raw)];
                }
            }
            $fields[] = [
                'id'         => (int) $field->id,
                'name'       => mlang::resolve($field->name),
                'type'       => $field->type,
                'required'   => (int) $field->required,
                'groupid'    => (int) $field->groupid,
                'sortorder'  => (int) $field->sortorder,
                'multiple'   => $config['multiple'] ? 1 : 0,
                'fixedvalue' => mlang::resolve($config['fixedvalue']),
                'options'    => $options,
            ];
        }

        // Certificate gate + current submission.
        $hascert = submission_manager::has_certificate($jobform, $USER->id);
        $submission = submission_manager::get_submission($jobform->id, $USER->id);
        $issubmitted = $submission && $submission->status === submission_manager::STATUS_SUBMITTED;
        $locked = $issubmitted && empty($jobform->allowresubmit);

        $answers = [];
        if ($submission) {
            foreach (submission_manager::get_answers($submission->id) as $fieldid => $value) {
                $answers[] = ['fieldid' => (int) $fieldid, 'value' => (string) $value];
            }
        }

        return [
            'jobform' => [
                'id'            => (int) $jobform->id,
                'cmid'          => (int) $cm->id,
                'name'          => format_string($jobform->name),
                'intro'         => $intro,
                'introformat'   => (int) $introformat,
                'allowresubmit' => (int) $jobform->allowresubmit,
            ],
            'access' => [
                'cansubmit'    => ($hascert && !$locked) ? 1 : 0,
                'certrequired' => $hascert ? 0 : 1,
                'locked'       => $locked ? 1 : 0,
            ],
            'groups'  => $groups,
            'fields'  => $fields,
            'submission' => [
                'status'       => $submission ? $submission->status : '',
                'timemodified' => $submission ? (int) $submission->timemodified : 0,
                'answers'      => $answers,
            ],
            'warnings' => [],
        ];
    }

    /**
     * Returns for get_form.
     *
     * @return external_single_structure
     */
    public static function get_form_returns(): external_single_structure {
        return new external_single_structure([
            'jobform' => new external_single_structure([
                'id'            => new external_value(PARAM_INT, 'Activity instance id'),
                'cmid'          => new external_value(PARAM_INT, 'Course module id'),
                'name'          => new external_value(PARAM_RAW, 'Activity name'),
                'intro'         => new external_value(PARAM_RAW, 'Activity description (formatted)'),
                'introformat'   => new external_value(PARAM_INT, 'Description format'),
                'allowresubmit' => new external_value(PARAM_INT, '1 if the student may edit and resend'),
            ]),
            'access' => new external_single_structure([
                'cansubmit'    => new external_value(PARAM_INT, '1 if the student may fill in and send now'),
                'certrequired' => new external_value(PARAM_INT, '1 if blocked pending the linked certificate'),
                'locked'       => new external_value(PARAM_INT, '1 if already sent and resubmission is off'),
            ]),
            'groups' => new external_multiple_structure(new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Group id'),
                'name'      => new external_value(PARAM_RAW, 'Group name in the user\'s language'),
                'sortorder' => new external_value(PARAM_INT, 'Display order'),
            ])),
            'fields' => new external_multiple_structure(new external_single_structure([
                'id'         => new external_value(PARAM_INT, 'Field id'),
                'name'       => new external_value(PARAM_RAW, 'Field label in the user\'s language'),
                'type'       => new external_value(PARAM_ALPHA, 'text|number|email|phone|date|checkbox|url|select|fixed'),
                'required'   => new external_value(PARAM_INT, '1 if required'),
                'groupid'    => new external_value(PARAM_INT, 'Group id (0 = ungrouped)'),
                'sortorder'  => new external_value(PARAM_INT, 'Display order'),
                'multiple'   => new external_value(PARAM_INT, '1 if a dropdown accepts multiple selections'),
                'fixedvalue' => new external_value(PARAM_RAW, 'Read-only value for the "fixed" type'),
                'options'    => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value to send back when chosen'),
                    'label' => new external_value(PARAM_RAW, 'Label in the user\'s language'),
                ]), 'Dropdown options', VALUE_DEFAULT, []),
            ])),
            'submission' => new external_single_structure([
                'status'       => new external_value(PARAM_ALPHA, 'draft | submitted | empty if none'),
                'timemodified' => new external_value(PARAM_INT, 'Last change time (0 if none)'),
                'answers'      => new external_multiple_structure(new external_single_structure([
                    'fieldid' => new external_value(PARAM_INT, 'Field id'),
                    'value'   => new external_value(PARAM_RAW, 'Stored value'),
                ])),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // submit_form — validate and store the student's answers.
    // ---------------------------------------------------------------------

    /**
     * Parameters for submit_form.
     *
     * @return external_function_parameters
     */
    public static function submit_form_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'answers' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_INT, 'Field id'),
                'value'   => new external_value(PARAM_RAW,
                    'Answer value. For a multi-select send a JSON array of chosen option values, '
                    . 'e.g. ["val1","val2"]. For a checkbox send "1" or "0". For a date send a unix timestamp.'),
            ]), 'The answers to store'),
            'draft' => new external_value(PARAM_BOOL, 'True to save as a draft (skips required checks)',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Validate and store the student's answers.
     *
     * @param int $cmid
     * @param array $answers
     * @param bool $draft
     * @return array
     */
    public static function submit_form(int $cmid, array $answers, bool $draft = false): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_form_parameters(),
            ['cmid' => $cmid, 'answers' => $answers, 'draft' => $draft]);

        [$course, $cm, $jobform] = self::resolve_cm($params['cmid']);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:submit', $context);

        // Certificate gate.
        if (!submission_manager::has_certificate($jobform, $USER->id)) {
            throw new moodle_exception('certificaterequired', 'mod_jobform');
        }
        // Locked (already sent, resubmission off).
        $existing = submission_manager::get_submission($jobform->id, $USER->id);
        if ($existing && $existing->status === submission_manager::STATUS_SUBMITTED
                && empty($jobform->allowresubmit)) {
            throw new moodle_exception('alreadysubmitted', 'mod_jobform');
        }

        $fields = instance_manager::get_fields($jobform->id);

        // Map incoming answers by field id.
        $incoming = [];
        foreach ($params['answers'] as $answer) {
            $incoming[(int) $answer['fieldid']] = (string) $answer['value'];
        }

        [$errors, $values] = self::validate_and_normalize($fields, $incoming, !empty($params['draft']));
        if ($errors) {
            return ['status' => false, 'submissionid' => 0, 'errors' => $errors, 'warnings' => []];
        }

        $status = !empty($params['draft'])
            ? submission_manager::STATUS_DRAFT
            : submission_manager::STATUS_SUBMITTED;
        $submissionid = submission_manager::save($jobform->id, $USER->id, $values, $status);

        return ['status' => true, 'submissionid' => $submissionid, 'errors' => [], 'warnings' => []];
    }

    /**
     * Returns for submit_form.
     *
     * @return external_single_structure
     */
    public static function submit_form_returns(): external_single_structure {
        return new external_single_structure([
            'status'       => new external_value(PARAM_BOOL, 'True if the answers were stored'),
            'submissionid' => new external_value(PARAM_INT, 'The submission id (0 if not stored)'),
            'errors' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_INT, 'Field the error belongs to'),
                'message' => new external_value(PARAM_RAW, 'Validation message'),
            ]), 'Per-field validation errors when status is false'),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Teacher: get_submissions — the activity's submissions, paged.
    // ---------------------------------------------------------------------

    /**
     * Resolve a submission id to [course, cm, jobform, submission], with a clear error.
     *
     * @param int $submissionid
     * @return array [stdClass $course, cm_info $cm, stdClass $jobform, stdClass $submission]
     */
    protected static function resolve_submission(int $submissionid): array {
        global $DB;
        $submission = $DB->get_record('jobform_submission', ['id' => $submissionid]);
        if (!$submission) {
            throw new moodle_exception('errorsubmissionnotfound', 'mod_jobform', '', $submissionid);
        }
        $cmrec = get_coursemodule_from_instance('jobform', $submission->jobformid);
        if (!$cmrec) {
            throw new moodle_exception('errorsubmissionnotfound', 'mod_jobform', '', $submissionid);
        }
        [$course, $cm, $jobform] = self::resolve_cm((int) $cmrec->id);
        return [$course, $cm, $jobform, $submission];
    }

    /**
     * The user block shared by the teacher functions.
     *
     * @param stdClass $user needs id, picture, imagealt, email + name fields
     * @param context $context
     * @return array
     */
    protected static function export_user(stdClass $user, context $context): array {
        global $PAGE;
        $picture = new user_picture($user);
        $picture->size = 1; // Big.
        return [
            'id'              => (int) $user->id,
            'fullname'        => fullname($user, has_capability('moodle/site:viewfullnames', $context)),
            'profileimageurl' => $picture->get_url($PAGE)->out(false),
        ];
    }

    /**
     * Structure of the user block.
     *
     * @return external_single_structure
     */
    protected static function user_structure(): external_single_structure {
        return new external_single_structure([
            'id'              => new external_value(PARAM_INT, 'User id'),
            'fullname'        => new external_value(PARAM_RAW, 'Full name'),
            'profileimageurl' => new external_value(PARAM_URL, 'Profile picture URL'),
        ]);
    }

    /**
     * Parameters for get_submissions.
     *
     * @return external_function_parameters
     */
    public static function get_submissions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'    => new external_value(PARAM_INT, 'Course module id'),
            'page'    => new external_value(PARAM_INT, 'Page number (0-based)', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Submissions per page (1..100)', VALUE_DEFAULT, 20),
            'status'  => new external_value(PARAM_ALPHA,
                'submitted (default — what the web Submissions tab lists) | draft | all', VALUE_DEFAULT, 'submitted'),
        ]);
    }

    /**
     * List the activity's submissions for a teacher, newest first.
     *
     * @param int $cmid
     * @param int $page
     * @param int $perpage
     * @param string $status
     * @return array
     */
    public static function get_submissions(int $cmid, int $page = 0, int $perpage = 20,
            string $status = 'submitted'): array {
        $params = self::validate_parameters(self::get_submissions_parameters(),
            ['cmid' => $cmid, 'page' => $page, 'perpage' => $perpage, 'status' => $status]);

        [$course, $cm, $jobform] = self::resolve_cm($params['cmid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:viewsubmissions', $context);

        $status = in_array($params['status'], [submission_manager::STATUS_SUBMITTED,
            submission_manager::STATUS_DRAFT, 'all'], true) ? $params['status'] : submission_manager::STATUS_SUBMITTED;
        $page = max(0, $params['page']);
        $perpage = max(1, min(100, $params['perpage']));

        $result = submission_manager::list_submissions((int) $jobform->id, $status, $page, $perpage);
        $submissions = [];
        foreach ($result['rows'] as $row) {
            $user = clone $row;
            $user->id = $row->userid;
            $submissions[] = [
                'id'           => (int) $row->id,
                'user'         => self::export_user($user, $context),
                'status'       => $row->status,
                'timecreated'  => (int) $row->timecreated,
                'timemodified' => (int) $row->timemodified,
            ];
        }

        return [
            'jobform'     => ['id' => (int) $jobform->id, 'cmid' => (int) $cm->id, 'name' => format_string($jobform->name)],
            'total'       => $result['total'],
            'page'        => $page,
            'perpage'     => $perpage,
            'candelete'   => has_capability('mod/jobform:viewsubmissions', $context) ? 1 : 0,
            'submissions' => $submissions,
            'warnings'    => [],
        ];
    }

    /**
     * Returns for get_submissions.
     *
     * @return external_single_structure
     */
    public static function get_submissions_returns(): external_single_structure {
        return new external_single_structure([
            'jobform' => new external_single_structure([
                'id'   => new external_value(PARAM_INT, 'Activity instance id'),
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'name' => new external_value(PARAM_RAW, 'Activity name'),
            ]),
            'total'       => new external_value(PARAM_INT, 'Submissions matching the status filter'),
            'page'        => new external_value(PARAM_INT, 'Page returned'),
            'perpage'     => new external_value(PARAM_INT, 'Page size used'),
            'candelete'   => new external_value(PARAM_INT, '1 if the caller may delete submissions'),
            'submissions' => new external_multiple_structure(new external_single_structure([
                'id'           => new external_value(PARAM_INT, 'Submission id'),
                'user'         => self::user_structure(),
                'status'       => new external_value(PARAM_ALPHA, 'draft | submitted'),
                'timecreated'  => new external_value(PARAM_INT, 'First saved'),
                'timemodified' => new external_value(PARAM_INT, 'Last changed / sent'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Teacher: get_submission — one submission with labelled answers.
    // ---------------------------------------------------------------------

    /**
     * Parameters for get_submission.
     *
     * @return external_function_parameters
     */
    public static function get_submission_parameters(): external_function_parameters {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'Submission id'),
            'lang' => new external_value(PARAM_LANG,
                'Language for the labels / values (e.g. "ar"). Defaults to the user\'s language.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * One submission with every field's label and formatted value (teacher view).
     *
     * @param int $submissionid
     * @param string $lang
     * @return array
     */
    public static function get_submission(int $submissionid, string $lang = ''): array {
        global $DB;
        $params = self::validate_parameters(self::get_submission_parameters(),
            ['submissionid' => $submissionid, 'lang' => $lang]);

        [$course, $cm, $jobform, $submission] = self::resolve_submission($params['submissionid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:viewsubmissions', $context);

        if (!empty($params['lang'])) {
            force_current_language($params['lang']);
        }

        $user = $DB->get_record('user', ['id' => $submission->userid]);
        $groups = [];
        foreach (group_manager::get_groups($jobform->id) as $group) {
            $groups[] = [
                'id'        => (int) $group->id,
                'name'      => mlang::resolve($group->name),
                'sortorder' => (int) $group->sortorder,
            ];
        }

        return [
            'submission' => [
                'id'           => (int) $submission->id,
                'cmid'         => (int) $cm->id,
                'jobformid'    => (int) $jobform->id,
                'status'       => $submission->status,
                'timecreated'  => (int) $submission->timecreated,
                'timemodified' => (int) $submission->timemodified,
                'user'         => $user
                    ? self::export_user($user, $context)
                    : ['id' => (int) $submission->userid, 'fullname' => '', 'profileimageurl' => ''],
            ],
            'groups'   => $groups,
            'answers'  => submission_manager::get_answer_rows((int) $jobform->id, (int) $submission->id),
            'warnings' => [],
        ];
    }

    /**
     * Returns for get_submission.
     *
     * @return external_single_structure
     */
    public static function get_submission_returns(): external_single_structure {
        return new external_single_structure([
            'submission' => new external_single_structure([
                'id'           => new external_value(PARAM_INT, 'Submission id'),
                'cmid'         => new external_value(PARAM_INT, 'Course module id'),
                'jobformid'    => new external_value(PARAM_INT, 'Activity instance id'),
                'status'       => new external_value(PARAM_ALPHA, 'draft | submitted'),
                'timecreated'  => new external_value(PARAM_INT, 'First saved'),
                'timemodified' => new external_value(PARAM_INT, 'Last changed / sent'),
                'user'         => new external_single_structure([
                    'id'              => new external_value(PARAM_INT, 'User id'),
                    'fullname'        => new external_value(PARAM_RAW, 'Full name'),
                    'profileimageurl' => new external_value(PARAM_RAW, 'Profile picture URL'),
                ]),
            ]),
            'groups' => new external_multiple_structure(new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Group id'),
                'name'      => new external_value(PARAM_RAW, 'Group name in the requested language'),
                'sortorder' => new external_value(PARAM_INT, 'Display order'),
            ])),
            'answers' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_INT, 'Field id'),
                'name'    => new external_value(PARAM_RAW, 'Field label in the requested language'),
                'type'    => new external_value(PARAM_ALPHA, 'text|number|email|phone|date|checkbox|url|select|fixed'),
                'groupid' => new external_value(PARAM_INT, 'Group id (0 = ungrouped)'),
                'value'   => new external_value(PARAM_RAW, 'Stored value (raw)'),
                'display' => new external_value(PARAM_RAW, 'Formatted value to show (plain text)'),
            ]), 'One row per current field of the activity, in field order'),
            'warnings' => new external_warnings(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Teacher: delete_submission — same rule as the web Submissions tab.
    // ---------------------------------------------------------------------

    /**
     * Parameters for delete_submission.
     *
     * @return external_function_parameters
     */
    public static function delete_submission_parameters(): external_function_parameters {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'Submission id'),
        ]);
    }

    /**
     * Permanently delete a submission and its answers (needs mod/jobform:viewsubmissions,
     * exactly like the delete action of view.php's Submissions tab).
     *
     * @param int $submissionid
     * @return array
     */
    public static function delete_submission(int $submissionid): array {
        $params = self::validate_parameters(self::delete_submission_parameters(), ['submissionid' => $submissionid]);

        [$course, $cm, $jobform, $submission] = self::resolve_submission($params['submissionid']);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/jobform:viewsubmissions', $context);

        submission_manager::delete_submission((int) $submission->id, (int) $jobform->id);

        return ['status' => true, 'warnings' => []];
    }

    /**
     * Returns for delete_submission.
     *
     * @return external_single_structure
     */
    public static function delete_submission_returns(): external_single_structure {
        return new external_single_structure([
            'status'   => new external_value(PARAM_BOOL, 'True when deleted'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Validate the incoming answers and normalise them into stored values.
     *
     * Mirrors the web form (mod_jobform\form\entry_form).
     *
     * @param object[] $fields the activity's fields
     * @param array $incoming fieldid => raw value
     * @param bool $draft when true, skip required-field checks
     * @return array{0: array, 1: array} [errors, values]
     */
    protected static function validate_and_normalize(array $fields, array $incoming, bool $draft): array {
        $errors = [];
        $values = [];

        foreach ($fields as $field) {
            $type = $field->type;
            $config = field_types::decode_config($field->configdata);
            $raw = $incoming[$field->id] ?? '';

            // Fixed values are always the admin's value; ignore anything sent.
            if ($type === field_types::TYPE_FIXED) {
                $values[$field->id] = $config['fixedvalue'];
                continue;
            }

            // Normalise per type.
            switch ($type) {
                case field_types::TYPE_CHECKBOX:
                    $values[$field->id] = !empty($raw) && $raw !== '0' ? '1' : '0';
                    break;
                case field_types::TYPE_DATE:
                    $values[$field->id] = (string) (int) $raw;
                    break;
                case field_types::TYPE_SELECT:
                    if ($config['multiple']) {
                        $decoded = json_decode($raw, true);
                        $decoded = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
                        $values[$field->id] = json_encode($decoded);
                    } else {
                        $values[$field->id] = (string) $raw;
                    }
                    break;
                default:
                    $values[$field->id] = trim((string) $raw);
                    break;
            }

            if ($draft) {
                continue;
            }

            // Required check.
            $empty = $type === field_types::TYPE_SELECT && $config['multiple']
                ? ($values[$field->id] === '' || $values[$field->id] === '[]')
                : ($values[$field->id] === '' || $values[$field->id] === '0' && $type === field_types::TYPE_DATE);
            if (!empty($field->required) && $type !== field_types::TYPE_CHECKBOX && $empty) {
                $errors[] = ['fieldid' => (int) $field->id, 'message' => get_string('required')];
                continue;
            }

            // Format checks (only when a value was provided).
            $value = $values[$field->id];
            if ($value === '') {
                continue;
            }
            if ($type === field_types::TYPE_NUMBER && !is_numeric($value)) {
                $errors[] = ['fieldid' => (int) $field->id, 'message' => get_string('errornotnumber', 'mod_jobform')];
            } else if ($type === field_types::TYPE_EMAIL && !validate_email($value)) {
                $errors[] = ['fieldid' => (int) $field->id, 'message' => get_string('errornotemail', 'mod_jobform')];
            } else if ($type === field_types::TYPE_URL && !preg_match('#^https?://#i', $value)) {
                $errors[] = ['fieldid' => (int) $field->id, 'message' => get_string('errornoturl', 'mod_jobform')];
            } else if ($type === field_types::TYPE_PHONE) {
                $digits = preg_replace('/\D+/', '', $value);
                if (!preg_match('/^\+?[0-9\s().-]+$/', $value) || strlen($digits) < 7 || strlen($digits) > 15) {
                    $errors[] = ['fieldid' => (int) $field->id, 'message' => get_string('errornotphone', 'mod_jobform')];
                }
            }
        }

        return [$errors, $values];
    }
}
