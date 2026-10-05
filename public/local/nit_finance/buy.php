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
 * Unlock one lesson: pay from the wallet or use a code.
 *
 * Students land here when they open a lesson they have not bought.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\access;
use local_nit_finance\local\catalog;
use local_nit_finance\local\codes;
use local_nit_finance\local\money;
use local_nit_finance\local\output;
use local_nit_finance\local\preview;
use local_nit_finance\local\purchases;
use local_nit_finance\local\wallets;

$cmid = required_param('cmid', PARAM_INT);

// Site-level login only: the student may not be enrolled in the course yet,
// and a course/activity login here would loop back to this page.
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot buy');
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
$coursecontext = context_course::instance($course->id);
if ((!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext))
        || (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext))) {
    throw new moodle_exception('err_itemnotfound', 'local_nit_finance');
}

$userid = (int) $USER->id;
$url = new moodle_url('/local/nit_finance/buy.php', ['cmid' => $cm->id]);
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$item = catalog::item(catalog::CM, (int) $cm->id);

$PAGE->set_url($url);
// A personal checkout page: no course tabs or course index around it.
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('buytitle', 'local_nit_finance') . ': ' . $item->name);
$PAGE->set_heading($item->coursename);

// Already open: go straight to the lesson.
$state = access::course_state($userid, (int) $course->id);
if (access::cm_open($state, (int) $cm->id)) {
    if (!empty($state['owned'][$cm->id]) || $state['ownscourse']) {
        // Bought, but the enrolment after the payment did not happen: retry it.
        purchases::ensure_enrolled($userid, (int) $course->id);
    }
    if (is_enrolled($coursecontext, $userid, '', true)) {
        redirect($item->url);
    }
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    try {
        if ($action === 'buy') {
            purchases::buy_with_wallet($userid, (int) $cm->id);
            redirect($item->url, get_string('bought', 'local_nit_finance', $item->name),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($action === 'redeem') {
            $result = codes::redeem($userid, required_param('code', PARAM_RAW_TRIMMED));
            if ($result['item']) {
                redirect($result['item']->url, get_string('bought', 'local_nit_finance', $result['item']->name),
                    null, \core\output\notification::NOTIFY_SUCCESS);
            }
            redirect($url, get_string('toppedup', 'local_nit_finance', money::format($result['amount'])),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (finance_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$balance = wallets::balance(wallets::STUDENT, $userid);
$priced = $item->priceminor > 0;
$coursebuyurl = access::course_has_price((int) $course->id)
    ? new moodle_url('/local/payments/buy.php', ['courseid' => $course->id]) : null;
$previewseconds = preview::for_user($userid, $cm, $state);

$context = [
    'coursename' => $item->coursename,
    'name' => $item->name,
    'priced' => $priced,
    'price' => money::format($item->priceminor),
    'balance' => money::format($balance),
    'canpay' => $priced && $balance >= $item->priceminor,
    'paylabel' => get_string('paywithwallet', 'local_nit_finance', money::format($item->priceminor)),
    'action' => $url->out_omit_querystring(),
    'cmid' => (int) $cm->id,
    'sesskey' => sesskey(),
    'redeem' => output::redeem_form($url),
    // Not enough credit: top up online and come back here.
    'topup' => $priced && $balance < $item->priceminor ? output::topup_form($url->out_as_local_url(false)) : null,
    'courseurl' => $courseurl->out(false),
    'coursebuyurl' => $coursebuyurl ? $coursebuyurl->out(false) : '',
    'walleturl' => (new moodle_url('/local/nit_finance/wallet.php'))->out(false),
    'previewurl' => $previewseconds > 0 ? preview::url((int) $cm->id)->out(false) : '',
    'previewlabel' => $previewseconds > 0
        ? get_string('watchpreview', 'local_nit_finance', preview::label($previewseconds)) : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_finance/buy_page', $context);
echo $OUTPUT->footer();
