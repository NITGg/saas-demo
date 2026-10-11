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
 * Pay for a whole course or a subscription plan from the student wallet.
 *
 * Posted by the checkout dialog (course) and the student hub (subscription).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\wallet_checkout;

$type = required_param('type', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);
$coupon = optional_param('coupon_code', '', PARAM_TEXT);
$return = optional_param('returnurl', '', PARAM_LOCALURL);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot buy');
}
require_sesskey();
$PAGE->set_url(new moodle_url('/local/nit_finance/walletpay.php'));
$PAGE->set_context(context_system::instance());

$userid = (int) $USER->id;
if ($type === 'course') {
    $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    require_capability('local/payments:purchasecourse', $context);
    $back = $return !== '' ? new moodle_url($return) : new moodle_url('/local/payments/buy.php', ['courseid' => $id]);
    try {
        wallet_checkout::buy_course($userid, $id, $coupon);
    } catch (moodle_exception $e) {
        redirect($back, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    $name = format_string($course->fullname, true, ['context' => $context]);
    redirect(new moodle_url('/course/view.php', ['id' => $id]), get_string('bought', 'local_nit_finance', $name),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($type === wallet_checkout::SUBSCRIPTION) {
    $back = $return !== '' ? new moodle_url($return) : new moodle_url('/local/nit_lessons/student.php', ['tab' => 'subavailable']);
    try {
        wallet_checkout::buy_subscription($userid, $id, $coupon);
    } catch (moodle_exception $e) {
        redirect($back, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url('/local/nit_lessons/student.php', ['tab' => 'mysubs']),
        get_string('subbought', 'local_nit_finance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

throw new moodle_exception('err_itemnotfound', 'local_nit_finance');
