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
 * "My earnings" for teachers: balance cards, withdrawal requests and every earning
 * (activities sold on their own and live lessons paid with a Flex).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\api\wallet;
use local_nit_finance\local\earnings_page;
use local_nit_finance\local\money;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no earnings');
}

$url = new moodle_url('/local/nit_finance/earnings.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myearnings', 'local_nit_finance'));
$PAGE->set_heading(get_string('myearnings', 'local_nit_finance'));

$userid = (int) $USER->id;
if (!earnings_page::is_teacher($userid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notateacher', 'local_nit_finance'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (optional_param('action', '', PARAM_ALPHA) === 'withdraw' && confirm_sesskey()) {
    $amount = money::to_minor(optional_param('amount', '', PARAM_RAW_TRIMMED));
    $method = optional_param('method', 'bank', PARAM_ALPHA);
    if (!in_array($method, earnings_page::METHODS, true)) {
        $method = 'bank';
    }
    $account = trim(optional_param('account', '', PARAM_TEXT));
    try {
        if ($amount === null) {
            throw new \local_nit_finance\exception\finance_exception('err_badprice');
        }
        wallet::request_withdrawal($userid, $amount, $method, $account);
    } catch (\local_nit_finance\exception\finance_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, get_string('earn_requested', 'local_nit_finance'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_finance/earnings_page', earnings_page::context($userid, $url));
echo $OUTPUT->footer();
