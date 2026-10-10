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
 * "Available packages": the Flex packages a student can buy, paid from the wallet or online.
 * Shown as a tab of the student hub (local/nit_lessons/student.php?tab=flexavailable).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_flex\api\purchase;
use local_nit_flex\local\packages_page;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot buy packages');
}

$url = new moodle_url('/local/nit_flex/packages.php');
// Shown as the "باقات الفلكسات المتاحة" tab of the student hub; this page keeps the buy action.
$back = file_exists($CFG->dirroot . '/local/nit_lessons/student.php')
    ? new moodle_url('/local/nit_lessons/student.php', ['tab' => 'flexavailable']) : $url;
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('availablepackages', 'local_nit_flex'));
$PAGE->set_heading(get_string('availablepackages', 'local_nit_flex'));
local_nit_flex_require_enabled();

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'buy' && confirm_sesskey()) {
    require_capability('local/nit_flex:purchase', context_system::instance());
    $packageid = required_param('packageid', PARAM_INT);
    $coupon = trim(optional_param('coupon', '', PARAM_TEXT));
    $method = optional_param('method', 'wallet', PARAM_ALPHA);
    $hub = new moodle_url('/local/nit_lessons/student.php', ['tab' => 'packages']);
    try {
        if ($method === 'online') {
            $checkout = packages_page::start_online_checkout((int) $USER->id, $packageid, $coupon);
            redirect($checkout);
        }
        purchase::buy_with_wallet((int) $USER->id, $packageid, $coupon);
    } catch (moodle_exception $e) {
        redirect($back, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($hub, get_string('msg_package_purchased', 'local_nit_flex'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($back->compare($url, URL_MATCH_BASE) === false) {
    redirect($back);
}

$PAGE->requires->strings_for_js(['couponapplied', 'couponcheckfailed', 'buysummary'], 'local_nit_flex');
$PAGE->requires->strings_for_js(['amountwithcurrency'], 'local_nit_finance');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_nit_flex/packages_page', packages_page::context((int) $USER->id, $url));
echo $OUTPUT->footer();
