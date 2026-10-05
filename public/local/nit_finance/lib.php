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
 * Callbacks of local_nit_finance: the lesson price field on activity settings,
 * and the lock on activities the student has not bought.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_nit_finance\local\access;
use local_nit_finance\local\catalog;
use local_nit_finance\local\money;
use local_nit_finance\local\preview;

/**
 * Close activities the student has not bought.
 *
 * Runs at the end of every require_login() — before the activity logs a view
 * or sets completion — so it covers activity pages, the lesson player and web
 * service calls alike. Pages are sent to the buy page (or to the free preview of
 * a video lesson that has one); web services get an error.
 *
 * @param stdClass|int|null $courseorid
 * @param bool $autologinguest
 * @param stdClass|cm_info|null $cm
 * @param bool $setwantsurltome
 * @param bool $preventredirect
 * @return void
 */
function local_nit_finance_after_require_login($courseorid = null, $autologinguest = null, $cm = null,
        $setwantsurltome = null, $preventredirect = null) {
    global $USER;
    if (!$cm || empty($cm->id) || during_initial_install() || !isloggedin() || isguestuser()) {
        return;
    }
    try {
        $open = access::can_open((int) $USER->id, $cm);
    } catch (\dml_exception $e) {
        return; // Tables not installed yet (mid-upgrade).
    }
    if ($open) {
        return;
    }
    if ($preventredirect) {
        throw new \moodle_exception('err_lessonlocked', 'local_nit_finance');
    }
    preview::redirect_if_preview($cm);
    redirect(new moodle_url('/local/nit_finance/buy.php', ['cmid' => $cm->id]));
}

/**
 * Add "Lesson price" to every activity's settings form.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 * @return void
 */
function local_nit_finance_coursemodule_standard_elements($formwrapper, $mform) {
    $course = $formwrapper->get_course();
    if (!has_capability('local/nit_finance:setprice', context_course::instance($course->id))) {
        return;
    }
    $mform->addElement('header', 'local_nit_finance_hdr', get_string('saleheader', 'local_nit_finance'));
    $mform->addElement('text', 'local_nit_finance_price', get_string('lessonprice', 'local_nit_finance'), ['size' => 8]);
    $mform->setType('local_nit_finance_price', PARAM_RAW_TRIMMED);
    $mform->addHelpButton('local_nit_finance_price', 'lessonprice', 'local_nit_finance');
    $cm = $formwrapper->get_coursemodule();
    $price = $cm ? catalog::price((int) $cm->id) : 0;
    $mform->setDefault('local_nit_finance_price', $price > 0 ? money::to_major($price) : '');
    if ($price > 0) {
        $mform->setExpanded('local_nit_finance_hdr');
    }

    // Free preview: the first minutes of a video lesson play for anyone logged in.
    if (!preview::is_video(local_nit_finance_form_modname($formwrapper))) {
        return;
    }
    $mform->addElement('text', 'local_nit_finance_preview', get_string('previewminutes', 'local_nit_finance'), ['size' => 8]);
    $mform->setType('local_nit_finance_preview', PARAM_RAW_TRIMMED);
    $mform->addHelpButton('local_nit_finance_preview', 'previewminutes', 'local_nit_finance');
    $seconds = $cm ? preview::seconds((int) $cm->id) : 0;
    $mform->setDefault('local_nit_finance_preview', preview::format_minutes($seconds));
    if ($seconds > 0) {
        $mform->setExpanded('local_nit_finance_hdr');
    }
}

/**
 * The activity type of a settings form (also when adding a new activity).
 *
 * @param moodleform_mod $formwrapper
 * @return string e.g. "vimeo"
 */
function local_nit_finance_form_modname($formwrapper): string {
    $current = $formwrapper->get_current();
    if (!empty($current->modulename)) {
        return (string) $current->modulename;
    }
    $cm = $formwrapper->get_coursemodule();
    return $cm && !empty($cm->modname) ? (string) $cm->modname : '';
}

/**
 * Validate the lesson price.
 *
 * @param moodleform_mod $formwrapper
 * @param array $data
 * @return array errors
 */
function local_nit_finance_coursemodule_validation($formwrapper, $data) {
    $errors = [];
    $value = trim((string) ($data['local_nit_finance_price'] ?? ''));
    if ($value !== '' && money::to_minor($value) === null) {
        $errors['local_nit_finance_price'] = get_string('err_badprice', 'local_nit_finance');
    }
    if (array_key_exists('local_nit_finance_preview', $data)
            && preview::parse_minutes((string) $data['local_nit_finance_preview']) === null) {
        $errors['local_nit_finance_preview'] = get_string('err_badpreview', 'local_nit_finance', preview::MAX_MINUTES);
    }
    return $errors;
}

/**
 * Save the lesson price.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_nit_finance_coursemodule_edit_post_actions($data, $course) {
    if (!property_exists($data, 'local_nit_finance_price') || empty($data->coursemodule)
            || !has_capability('local/nit_finance:setprice', context_course::instance($course->id))) {
        return $data;
    }
    $minor = money::to_minor((string) $data->local_nit_finance_price) ?? 0;
    catalog::set_price((int) $data->coursemodule, (int) $course->id, $minor);
    if (property_exists($data, 'local_nit_finance_preview')) {
        $seconds = preview::parse_minutes((string) $data->local_nit_finance_preview) ?? 0;
        preview::set((int) $data->coursemodule, (int) $course->id, $seconds);
    }
    return $data;
}

/**
 * Remove the price of a deleted activity.
 *
 * @param stdClass $cm
 * @return void
 */
function local_nit_finance_pre_course_module_delete($cm) {
    global $DB;
    $DB->delete_records('nit_item_price', ['itemtype' => catalog::CM, 'itemid' => $cm->id]);
    preview::delete((int) $cm->id);
}
