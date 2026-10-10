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
 * "My lessons & Flex" (student): book a lesson, my lessons, Flex packages to buy, my Flex,
 * available subscriptions, my subscriptions, my wallet and (teachers) my earnings.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_lessons\local\hub;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no lessons');
}

// The tabs this user has (no Flex tabs while Flex is off, "My earnings" for teachers).
$tabs = hub::tabs((int) $USER->id);
$tab = optional_param('tab', $tabs[0], PARAM_ALPHA);
if (!in_array($tab, $tabs, true)) {
    $tab = $tabs[0];
}
$url = new moodle_url('/local/nit_lessons/student.php', ['tab' => $tab]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studenthub', 'local_nit_lessons'));
$PAGE->set_heading(get_string('studenthub', 'local_nit_lessons'));

// Buy a subscription plan online (tab 4).
if (optional_param('action', '', PARAM_ALPHA) === 'buysub' && confirm_sesskey()) {
    try {
        $checkout = \local_payments\manager::create_subscription_checkout(required_param('subscriptionid', PARAM_INT),
            (int) $USER->id, null, current_language(), 'normal', 0, '', '/local/nit_lessons/student.php?tab=mysubs');
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url($checkout->checkout_url));
}

$PAGE->requires->strings_for_js(['noslots', 'picktimefirst', 'field_note', 'field_completenote', 'field_reason',
    'field_reasonoptional'], 'local_nit_lessons');
if ($tab === 'flexavailable') {
    // The buy dialog of local_nit_flex/packages_page.
    $PAGE->requires->strings_for_js(['couponapplied', 'couponcheckfailed', 'buysummary'], 'local_nit_flex');
    $PAGE->requires->strings_for_js(['amountwithcurrency'], 'local_nit_finance');
}

$params = [
    'search' => optional_param('search', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_ALPHAEXT),
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_lessons/student_hub', hub::context((int) $USER->id, $tab, $params));
echo $OUTPUT->footer();
