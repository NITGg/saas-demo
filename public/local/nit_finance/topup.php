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
 * Top up the student wallet online: starts the payment gateway checkout
 * (local_payments → Kashier: card, mobile wallets…) and sends the student there.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\local\money;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests have no wallet');
}
require_sesskey();

$walleturl = new moodle_url('/local/nit_finance/wallet.php');
// A preset button sends "amount"; the typed field sends "customamount".
$typed = optional_param('amount', '', PARAM_RAW_TRIMMED);
$amount = money::to_minor($typed !== '' ? $typed : optional_param('customamount', '', PARAM_RAW_TRIMMED));
// Where to come back after paying: a local page only (e.g. the lesson's buy page).
$return = optional_param('return', '', PARAM_LOCALURL);
$returnpath = $return !== '' ? str_replace($CFG->wwwroot, '', (new moodle_url($return))->out(false)) : '';

if ($amount === null || $amount <= 0) {
    redirect($walleturl, get_string('err_amountpositive', 'local_nit_finance'), null,
        \core\output\notification::NOTIFY_ERROR);
}

try {
    $checkout = \local_payments\manager::create_wallet_topup_checkout($amount / 100, (int) $USER->id,
        current_language() === 'ar' ? 'ar' : 'en', $returnpath);
} catch (\moodle_exception $e) {
    redirect($walleturl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}
redirect($checkout->checkout_url);
