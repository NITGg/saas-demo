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
 * "My wallet": balance, code redemption, purchases and history. Teachers also
 * see their earnings wallet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\codes;
use local_nit_finance\local\output;
use local_nit_finance\local\purchases;
use local_nit_finance\local\wallets;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no wallet');
}

$url = new moodle_url('/local/nit_finance/wallet.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mywallet', 'local_nit_finance'));
$PAGE->set_heading(get_string('mywallet', 'local_nit_finance'));

// Redeem a code.
if (optional_param('action', '', PARAM_ALPHA) === 'redeem' && confirm_sesskey()) {
    $input = required_param('code', PARAM_RAW_TRIMMED);
    try {
        $result = codes::redeem((int) $USER->id, $input);
    } catch (\local_nit_finance\exception\finance_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($result['item']) {
        redirect($result['item']->url, get_string('bought', 'local_nit_finance', $result['item']->name),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url, get_string('toppedup', 'local_nit_finance', \local_nit_finance\local\money::format($result['amount'])),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$userid = (int) $USER->id;
$isteacher = $DB->record_exists('nit_wallet', ['ownertype' => wallets::TEACHER, 'userid' => $userid])
    || (class_exists('\local_academy\teacher_manager') && \local_academy\teacher_manager::is_teacher($userid));

$context = [
    'balances' => [output::balance_card(wallets::STUDENT, $userid)],
    'redeem' => output::redeem_form($url),
    'topup' => output::topup_form(),
    'purchases' => output::purchases(purchases::for_user($userid)),
    'history' => output::history(wallets::history(wallets::STUDENT, $userid)),
    'teacherhistory' => [],
    'isteacher' => $isteacher,
];
if ($isteacher) {
    $context['balances'][] = output::balance_card(wallets::TEACHER, $userid);
    $context['teacherhistory'] = output::history(wallets::history(wallets::TEACHER, $userid));
}
$context['haspurchases'] = !empty($context['purchases']);
$context['hashistory'] = !empty($context['history']);
$context['hasteacherhistory'] = !empty($context['teacherhistory']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_finance/wallet_page', $context);
echo $OUTPUT->footer();
