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
