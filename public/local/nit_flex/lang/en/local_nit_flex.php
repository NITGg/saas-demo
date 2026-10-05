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
 * English strings for local_nit_flex.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Lesson packages (Flex)';
$string['local_nit_flex:managepackages'] = 'Manage lesson packages';
$string['local_nit_flex:purchase'] = 'Buy a lesson package';
$string['messageprovider:expiry'] = 'Your lesson package is about to end';
$string['task_expiry'] = 'Lesson packages: expiry reminders and closing';
$string['feature_unavailable'] = 'Lesson packages are not part of your current plan.';

// Admin.
$string['admincategory'] = 'Lesson packages & live lessons';
$string['managepackages'] = 'Manage lesson packages';
$string['packagesettings'] = 'Package settings';
$string['expiryreminderdays'] = 'Expiry reminder (days)';
$string['expiryreminderdays_desc'] = 'How many days before a package ends to remind a student who still has Flex left. 0 turns the reminder off.';
$string['tab_packages'] = 'Packages';
$string['tab_students'] = 'Students\' packages';
$string['newpackage'] = 'New package';
$string['editpackage'] = 'Edit package';
$string['pkgname_ar'] = 'Name (Arabic)';
$string['pkgname_en'] = 'Name (English)';
$string['pkgdesc_ar'] = 'Description (Arabic)';
$string['pkgdesc_en'] = 'Description (English)';
$string['pkgflex'] = 'Number of Flex';
$string['pkgflex_help'] = 'One Flex = one 1:1 live lesson with any teacher. The value of a Flex (price ÷ number of Flex) is shared between the teacher and the platform when the lesson is completed.';
$string['pkgprice'] = 'Price (EGP)';
$string['pkgdays'] = 'Valid for (days)';
$string['pkgdays_help'] = 'Counted from the purchase. 0 = never expires. Flex left when the package ends expires and its value goes to the platform.';
$string['pkgactive'] = 'Available for sale';
$string['package'] = 'Package';
$string['buyers'] = 'Purchases';
$string['activate'] = 'Activate';
$string['deactivate'] = 'Deactivate';
$string['inactive'] = 'Not for sale';
$string['confirmdelete'] = 'Delete this package?';
$string['assignpackage'] = 'Assign a package';
$string['assignintro'] = 'Give a package to a student who paid outside the site, or charge it to their wallet. A student can hold one active package at a time.';
$string['student'] = 'Student';
$string['assignamount'] = 'Amount paid (EGP)';
$string['assignamount_help'] = 'Leave empty to use the package price. This amount sets the value of each Flex for the teachers\' earnings.';
$string['assignmethod'] = 'Payment';
$string['assignmethod_help'] = '"From the student\'s wallet" takes the amount from the student wallet now. The other choices only record a payment made outside the site.';
$string['paymethod_offline'] = 'Paid offline';
$string['paymethod_bank'] = 'Bank transfer';
$string['paymethod_cash'] = 'Cash';
$string['paymethod_wallet'] = 'From the student\'s wallet';
$string['reference'] = 'Reference / note';
$string['assigned'] = 'Package assigned to {$a->student} ({$a->flex} Flex).';
$string['flexcolumn'] = 'Flex';
$string['flexleftshort'] = '{$a->remaining} left · {$a->reserved} booked / {$a->total}';
$string['paid'] = 'Paid';
$string['expires'] = 'Ends';
$string['refundunused'] = 'Refund unused Flex to wallet';
$string['unassign'] = 'Unassign';
$string['confirmunassign'] = 'Cancel this package? Its remaining Flex will be removed.';
$string['unassigned'] = 'The package was cancelled.';
$string['unassignedrefund'] = 'The package was cancelled and {$a} went back to the student\'s wallet.';
$string['nopurchases'] = 'No student has a package yet.';
$string['refundnote'] = 'Refund of unused Flex';

// Student: available packages.
$string['availablepackages'] = 'Available packages';
$string['packagesintro'] = 'A package gives you Flex: each Flex books one live 1:1 lesson with the teacher you choose.';
$string['walletbalance'] = 'Wallet balance';
$string['topupwallet'] = 'Top up wallet';
$string['activepackage'] = 'Your package';
$string['flexleft'] = '{$a->remaining} of {$a->total} Flex left';
$string['expireson'] = 'Ends: {$a}';
$string['onlyonepackage'] = 'You can hold one package at a time. Buy a new one when this package is used up or ends.';
$string['booklesson'] = 'Book a lesson';
$string['flexunit'] = 'Flex';
$string['validdays'] = 'Valid for {$a} days';
$string['neverexpires'] = 'Never expires';
$string['perflex'] = '{$a} per lesson';
$string['buypackage'] = 'Buy package';
$string['nopackages'] = 'No packages are available right now.';
$string['buysummary'] = 'You get {$a} Flex (one live lesson each).';
$string['coupon'] = 'Coupon code';
$string['applycoupon'] = 'Apply';
$string['couponapplied'] = 'Coupon applied.';
$string['couponcheckfailed'] = 'The coupon could not be checked. Try again.';
$string['price'] = 'Price';
$string['discount'] = 'Discount';
$string['total'] = 'Total';
$string['paywallet'] = 'Pay from my wallet';
$string['notenoughwallet'] = 'Your wallet balance ({$a}) is not enough.';
$string['payonline'] = 'Pay online (card, wallet, Fawry)';
$string['msg_package_purchased'] = 'Package bought. Your Flex is ready — book your first lesson.';

// Statuses and history.
$string['pstat_active'] = 'Active';
$string['pstat_fully_used'] = 'Used up';
$string['pstat_expired'] = 'Ended';
$string['pstat_cancelled'] = 'Cancelled';
$string['pstat_pending'] = 'Pending';
$string['flx_reserve'] = 'Booked';
$string['flx_consume'] = 'Used';
$string['flx_return'] = 'Returned';
$string['flx_purchase'] = 'Bought';
$string['flx_assign'] = 'Assigned';
$string['flx_expire'] = 'Expired';
$string['flx_adjust'] = 'Removed';
$string['method_wallet'] = 'Wallet';
$string['method_online'] = 'Online';
$string['method_offline'] = 'Offline';
$string['method_bank'] = 'Bank transfer';
$string['method_cash'] = 'Cash';
$string['method_refund'] = 'Refund';
$string['method_admin_assigned'] = 'Assigned';

// Messages.
$string['msg_expiry_subject'] = 'Your package "{$a->package}" ends on {$a->date}';
$string['msg_expiry_body'] = 'You still have {$a->flex} Flex in "{$a->package}". It ends on {$a->date}: book your lessons before then, unused Flex expires.';

// Errors.
$string['err_notfound'] = 'Not found.';
$string['err_packagenotavailable'] = 'This package is not available.';
$string['err_alreadyhaspackage'] = 'You already have an active package. Use it up before buying another.';
$string['err_studentnotfound'] = 'Student not found.';
$string['err_studenthaspackage'] = 'This student already has an active package.';
$string['err_packageinuse'] = 'This package was bought before, so it cannot be deleted. Deactivate it instead.';
$string['err_nameflexrequired'] = 'Enter a name.';
$string['err_flexpositive'] = 'Enter a number of Flex greater than zero.';
$string['err_daysnegative'] = 'Days cannot be negative.';
$string['err_noflex'] = 'You have no Flex left. Buy a package first.';
$string['err_noonline'] = 'Online payment is not available right now. Pay from your wallet instead.';
$string['err_freeonwallet'] = 'This package is free with your discount: choose "Pay from my wallet".';

// Privacy.
$string['privacy:metadata:nit_package_purchase'] = 'A student\'s purchases of Flex packages.';
$string['privacy:metadata:nit_package_purchase:userid'] = 'The student who owns the purchase.';
$string['privacy:metadata:nit_package_purchase:price_paid_minor'] = 'The amount paid, in minor currency units.';
$string['privacy:metadata:nit_package_purchase:timecreated'] = 'When the purchase was made.';
$string['privacy:metadata:nit_payment'] = 'Payment transactions for package purchases.';
$string['privacy:metadata:nit_payment:userid'] = 'The paying user.';
$string['privacy:metadata:nit_payment:amount_minor'] = 'The amount paid, in minor currency units.';
$string['privacy:metadata:nit_payment:timecreated'] = 'When the payment was recorded.';
$string['privacy:metadata:nit_flex_tx'] = 'The Flex balance ledger for a student.';
$string['privacy:metadata:nit_flex_tx:userid'] = 'The student whose balance changed.';
$string['privacy:metadata:nit_flex_tx:amount'] = 'The signed change to the Flex balance.';
$string['privacy:metadata:nit_flex_tx:timecreated'] = 'When the change happened.';
