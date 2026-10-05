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
 * Strings for local_nit_finance.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Finance';
$string['local_nit_finance:manage'] = 'Manage NIT platform finances and withdrawals';

// Admin page.
$string['financialreports'] = 'Financial Reports';
$string['platformwallet'] = 'Platform Wallet';
$string['currentmoney'] = 'Current money';
$string['undistributedmoney'] = 'Undistributed package money';
$string['teachersmoney'] = 'Teachers\' money';
$string['platformearnings'] = 'Platform earnings';
$string['totalpaidout'] = 'Total paid out';
$string['withdrawals'] = 'Withdrawal requests';
$string['nowithdrawals'] = 'There are no withdrawal requests.';
$string['teacher'] = 'Teacher';
$string['amount'] = 'Amount';
$string['method'] = 'Method';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['pay'] = 'Mark as paid';

// Statuses.
$string['status_pending'] = 'Pending';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';
$string['status_paid'] = 'Paid';

// Errors.
$string['err_amountpositive'] = 'The amount must be greater than zero.';
$string['err_busy'] = 'Another withdrawal request for this teacher is being processed. Please try again in a moment.';
$string['err_insufficientbalance'] = 'The requested amount exceeds the available balance.';
$string['err_withdrawalnotfound'] = 'Withdrawal request not found.';
$string['err_withdrawalstate'] = 'The withdrawal is not in a state that allows this action.';
$string['err_reasonrequired'] = 'A reason is required.';
$string['err_badaction'] = 'Unknown action.';
$string['err_notdistributed'] = 'The lesson could not be distributed (no valid purchase).';
$string['err_lessonnotfound'] = 'Lesson not found.';
$string['err_earningnotfound'] = 'No active earning found for this lesson.';
$string['err_alreadyreversed'] = 'This earning has already been reversed.';

// Privacy.
$string['privacy:metadata:nit_earning'] = 'Revenue split records crediting a teacher for a completed lesson.';
$string['privacy:metadata:nit_earning:teacherid'] = 'The teacher credited by the earning.';
$string['privacy:metadata:nit_earning:teacher_amount_minor'] = 'The teacher\'s share, in minor currency units.';
$string['privacy:metadata:nit_earning:timecreated'] = 'When the earning was recorded.';
$string['privacy:metadata:nit_withdrawal'] = 'Teacher requests to withdraw earned money.';
$string['privacy:metadata:nit_withdrawal:teacherid'] = 'The teacher requesting the withdrawal.';
$string['privacy:metadata:nit_withdrawal:amount_minor'] = 'The requested amount, in minor currency units.';
$string['privacy:metadata:nit_withdrawal:account'] = 'The payout account details supplied by the teacher.';
$string['privacy:metadata:nit_withdrawal:timecreated'] = 'When the request was made.';

// Wallets, lesson purchases and codes.
$string['local_nit_finance:setprice'] = 'Set the price of an activity sold on its own';
$string['amountwithcurrency'] = '{$a} EGP';
$string['financecategory'] = 'Finance & wallets';
$string['earningsettings'] = 'Earning split';
$string['teacherpercent'] = 'Default teacher share (%)';
$string['teacherpercent_desc'] = 'Share of every sale that goes to the teacher\'s wallet; the platform keeps the rest. A teacher with their own value in the profile field "teacherpercent" gets that value instead.';
$string['walletsadmin'] = 'Wallets';
$string['codesadmin'] = 'Codes';
$string['mywallet'] = 'My wallet';
$string['saleheader'] = 'Sell this lesson on its own';
$string['lessonprice'] = 'Lesson price (EGP)';
$string['lessonprice_help'] = 'Students can buy this activity on its own for this price, from their wallet or with a code. Leave empty to make it free for students enrolled in the course.';
$string['wallet_student'] = 'Wallet balance';
$string['wallet_teacher'] = 'Teacher earnings';
$string['wallet_platform'] = 'Platform wallet';
$string['redeemcode'] = 'Have a code?';
$string['redeemcode_desc'] = 'Type the code you got after paying, to unlock the lesson or add credit to your wallet.';
$string['codeplaceholder'] = 'XXXX-XXXX-XXXX';
$string['redeem'] = 'Use code';
$string['mypurchases'] = 'My purchases';
$string['nopurchases'] = 'You have not bought anything yet.';
$string['transactions'] = 'Wallet history';
$string['notransactions'] = 'No transactions yet.';
$string['date'] = 'Date';
$string['details'] = 'Details';
$string['balanceafter'] = 'Balance';
$string['open'] = 'Open';
$string['method_wallet'] = 'Wallet';
$string['method_code'] = 'Code';
$string['kind_topup'] = 'Credit added';
$string['kind_purchase'] = 'Purchase';
$string['kind_earning'] = 'Earning';
$string['kind_withdrawal'] = 'Withdrawal';
$string['kind_adjustment'] = 'Adjustment';
$string['buytitle'] = 'Unlock this lesson';
$string['buyintro'] = 'This lesson is sold on its own. Pay from your wallet or use a code.';
$string['lessonlockedcourse'] = 'This lesson is part of the full course. Buy the course to open it.';
$string['price'] = 'Price';
$string['yourbalance'] = 'Your balance';
$string['paywithwallet'] = 'Pay {$a} from my wallet';
$string['notenoughbalance'] = 'Your balance is not enough. Add credit with a code, then come back.';
$string['or'] = 'or';
$string['buycourse'] = 'Buy the full course';
$string['bought'] = 'Done! "{$a}" is now unlocked.';
$string['toppedup'] = '{$a} was added to your wallet.';
$string['backtocourse'] = 'Back to the course';
$string['itemtype_cm'] = 'Lesson';
$string['itemtype_course'] = 'Course';
$string['itemtype_wallet'] = 'Wallet credit';
$string['codes_generate'] = 'Make codes';
$string['codes_type'] = 'Code unlocks';
$string['codes_cm'] = 'Lesson';
$string['codes_course'] = 'Course';
$string['codes_amount'] = 'Amount paid (EGP)';
$string['codes_amount_help'] = 'What the student paid you for each code. For a lesson or course it is shared between teacher and platform when the code is used; for wallet credit it is added to the student\'s wallet. For a lesson left empty, the lesson price is used.';
$string['codes_count'] = 'How many codes';
$string['codes_expires'] = 'Expires';
$string['codes_note'] = 'Note';
$string['codes_created'] = '{$a} code(s) created.';
$string['codes_download'] = 'Download CSV';
$string['codes_list'] = 'All codes';
$string['codes_none'] = 'No codes yet.';
$string['code'] = 'Code';
$string['item'] = 'Item';
$string['usedby'] = 'Used by';
$string['created'] = 'Created';
$string['disable'] = 'Disable';
$string['codestatus_active'] = 'Not used';
$string['codestatus_used'] = 'Used';
$string['codestatus_disabled'] = 'Disabled';
$string['allstatuses'] = 'All statuses';
$string['filter'] = 'Filter';
$string['wallets_topup'] = 'Add or take credit';
$string['wallets_user'] = 'Student';
$string['wallets_amount'] = 'Amount (EGP)';
$string['wallets_direction'] = 'Action';
$string['wallets_add'] = 'Add credit';
$string['wallets_take'] = 'Take credit';
$string['wallets_done'] = 'Wallet updated. New balance: {$a}';
$string['wallets_teachers'] = 'Teachers';
$string['wallets_students'] = 'Student wallets';
$string['wallets_ownpercent'] = 'Share';
$string['wallets_default'] = '{$a}% (default)';
$string['wallets_ledger'] = 'History';
$string['wallets_studentstotal'] = 'Students\' credit';
$string['wallets_teacherstotal'] = 'Teachers\' earnings';
$string['wallets_nostudents'] = 'No student wallets yet.';
$string['wallets_noteachers'] = 'No teacher earnings yet.';
$string['name'] = 'Name';
$string['balance'] = 'Balance';
$string['note'] = 'Note';
$string['lessonsforsale'] = 'Or buy single lessons';
$string['free'] = 'Free';
$string['buy'] = 'Buy';
$string['owned'] = 'Unlocked';

$string['err_insufficientwallet'] = 'Your wallet balance is not enough.';
$string['err_walletbusy'] = 'Another payment of yours is in progress. Please try again in a moment.';
$string['err_itemnotfound'] = 'This item no longer exists.';
$string['err_notforsale'] = 'This lesson is not sold on its own.';
$string['err_alreadyowned'] = 'You already have access to this.';
$string['err_noenrol'] = 'Manual enrolment is disabled, so the course cannot be opened for you.';
$string['err_codeinvalid'] = 'This code is not valid.';
$string['err_codeused'] = 'This code has already been used.';
$string['err_codeexpired'] = 'This code has expired.';
$string['err_codecount'] = 'You can make between 1 and {$a} codes at a time.';
$string['err_badprice'] = 'Enter a price in pounds, e.g. 50 or 49.50.';
$string['err_lessonlocked'] = 'This lesson is locked. Buy it to open it.';
$string['err_chooseitem'] = 'Choose what the code unlocks.';

$string['privacy:metadata:nit_wallet'] = 'Wallet balances of students and teachers.';
$string['privacy:metadata:nit_wallet:userid'] = 'The wallet owner.';
$string['privacy:metadata:nit_wallet:balance_minor'] = 'The balance, in minor currency units.';
$string['privacy:metadata:nit_purchase'] = 'Items a student bought or unlocked.';
$string['privacy:metadata:nit_purchase:userid'] = 'The student.';
$string['privacy:metadata:nit_purchase:amount_minor'] = 'The amount paid, in minor currency units.';
$string['privacy:metadata:nit_access_code'] = 'Codes sold offline and who used them.';
$string['privacy:metadata:nit_access_code:usedby'] = 'The student who used the code.';

// Online top-up.
$string['topuptitle'] = 'Top up online';
$string['topupdesc'] = 'Pay by card, mobile wallet (Vodafone Cash…) or Fawry; the amount is added to your wallet as soon as the payment succeeds.';
$string['topupamount'] = 'Amount (EGP)';
$string['topupbutton'] = 'Top up';
$string['topupnow'] = 'Top up your wallet online';

// Teacher "My earnings" page and live-lesson earnings.
$string['myearnings'] = 'My earnings';
$string['notateacher'] = 'This page is for teachers.';
$string['earn_available'] = 'Available balance';
$string['earn_total'] = 'Total earned';
$string['earn_pending'] = 'Pending withdrawals';
$string['earn_withdrawn'] = 'Total withdrawn';
$string['earn_request'] = 'Withdraw earnings';
$string['earn_nothingtowithdraw'] = 'You have no balance to withdraw yet.';
$string['earn_amount'] = 'Amount (EGP)';
$string['earn_method'] = 'Payout method';
$string['earn_method_bank'] = 'Bank transfer';
$string['earn_method_wallet'] = 'Mobile wallet';
$string['earn_method_cash'] = 'Cash';
$string['earn_account'] = 'Account / payout details';
$string['earn_account_ph'] = 'IBAN, wallet phone number or a note';
$string['earn_send'] = 'Send request';
$string['earn_requested'] = 'Your withdrawal request was sent. The platform will review it.';
$string['earn_withdrawals'] = 'My withdrawals';
$string['earn_nowithdrawals'] = 'No withdrawals yet.';
$string['earn_list'] = 'My earnings';
$string['earn_none'] = 'No earnings yet.';
$string['earn_student'] = 'Student';
$string['earn_value'] = 'Value';
$string['earn_share'] = 'Your share';
$string['earn_sharevalue'] = '{$a->amount} ({$a->percent}%)';
$string['earn_source_lesson'] = 'Live lesson #{$a}';
$string['earn_status_active'] = 'Counted';
$string['earn_status_reversed'] = 'Reversed';
$string['livelessonnote'] = 'Live lesson #{$a}';
$string['livelessonreversed'] = 'Live lesson #{$a} reversed';
$string['flexexpirednote'] = 'Unused Flex expired (package purchase #{$a})';
$string['itemtype_package'] = 'Lesson package';

// Mobile API (api.php).
$string['err_notateacher'] = 'Only teachers have an earnings wallet.';
$string['err_coursenotfound'] = 'Course not found.';
$string['err_paymentunavailable'] = 'Online payment is not available right now.';
$string['err_topuprange'] = 'The top-up amount must be between {$a->min} and {$a->max}.';
$string['err_ordernotfound'] = 'Top-up payment not found.';
$string['err_badmethod'] = 'Choose a payout method: bank, wallet or cash.';
$string['err_badstatus'] = 'Unknown status.';
$string['err_badwallettype'] = 'Unknown wallet type.';
$string['err_usernotfound'] = 'User not found.';
$string['err_codenotfound'] = 'Code not found.';
$string['err_codenotactive'] = 'Only an unused code can be disabled.';
$string['err_badexpiry'] = 'Enter a future expiry date (YYYY-MM-DD), or 0 for no expiry.';
$string['err_amountnonzero'] = 'Enter an amount other than zero, e.g. 50 or -50.';
