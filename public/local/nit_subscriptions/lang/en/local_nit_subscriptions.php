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
 * English strings for local_nit_subscriptions. Labels match the reference local_academy plugin.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Subscriptions';
$string['managesubscriptions'] = 'Manage subscriptions';
$string['managecourses']       = 'Manage courses';
$string['privacy:metadata'] = 'The NIT Subscriptions plugin stores subscription plans and course-access rules defined by administrators; it does not store personal data by itself.';

// Manage courses (single-course purchases: list + "Unbuy").
$string['mc_desc']            = 'Users who bought a single course. Use "Unbuy" to unenrol a user and revoke the purchase.';
$string['mc_col_course']      = 'Course';
$string['mc_col_purchased']   = 'Purchased';
$string['mc_none']            = 'No course purchases yet.';
$string['mc_status_enrolled'] = 'Enrolled';
$string['mc_status_norole']   = 'No access';
$string['mc_unbuy']           = 'Unbuy';
$string['mc_unbuy_title']     = 'Revoke course purchase';
$string['mc_unbuy_confirm']   = 'Unenrol <b>{$a->user}</b> from <b>{$a->course}</b> and revoke this purchase?';
$string['mc_unbuy_refund']    = 'Mark this purchase as refunded';
$string['mc_unbuy_success']   = 'The course purchase was revoked.';
$string['mc_course_deleted']  = '(deleted course)';
$string['mc_txn_notfound']    = 'Purchase not found.';
$string['mc_not_active']      = 'This purchase is not active and cannot be revoked.';

// Shared UI.
$string['ui_refresh']      = 'Refresh';
$string['ui_loading']      = 'Loading…';
$string['ui_save']         = 'Save';
$string['ui_cancel']       = 'Cancel';
$string['ui_edit']         = 'Edit';
$string['ui_delete']       = 'Delete';
$string['ui_activate']     = 'Activate';
$string['ui_deactivate']   = 'Deactivate';
$string['ui_active']       = 'Active';
$string['ui_never']        = 'Never';
$string['ui_optional']     = '(optional)';
$string['ui_remove']       = 'Remove';
$string['ui_search']       = 'Search';
$string['ui_pager_info']   = 'Showing {from}–{to} of {total}';

// Package-shared column/field labels reused by the subscriptions page.
$string['pkg_col_id']        = 'ID';
$string['pkg_col_name']      = 'Name';
$string['pkg_col_price']     = 'Price';
$string['pkg_col_status']    = 'Status';
$string['pkg_col_actions']   = 'Actions';
$string['pkg_col_user']      = 'User';
$string['pkg_col_pricepaid'] = 'Price Paid';
$string['pkg_col_expiresat'] = 'Expires At';
$string['pkg_field_name']    = 'Name';
$string['pkg_field_price']   = 'Price (EGP)';
$string['pkg_field_name_en'] = 'Name (English)';
$string['pkg_field_name_ar'] = 'Name (Arabic)';
$string['pkg_field_desc_en'] = 'Description (English)';
$string['pkg_field_desc_ar'] = 'Description (Arabic)';
$string['pkg_unassign_paid'] = ' — <strong>{$a}</strong> paid';

// Subscription plans.
$string['sub_plans_heading']  = 'Subscription plans';
$string['sub_new']            = 'New subscription';
$string['sub_col_days']       = 'Days';
$string['sub_col_courses']    = 'Courses';
$string['sub_col_subscription'] = 'Subscription';
$string['sub_field_desc']     = 'Description (optional)';
$string['sub_field_days']     = 'Number of days';
$string['sub_field_b2b']      = 'B2B purchase available';
$string['sub_seat_options']   = 'Seat options';
$string['sub_seat_options_help'] = 'Add one or more user-capacity options, each with its own discount %. The B2B price is calculated as (normal price × seats) − discount.';
$string['sub_col_seats']      = 'Seats';
$string['sub_col_discount']   = 'Discount %';
$string['sub_col_b2bprice']   = 'B2B price';
$string['sub_seat_add']       = 'Add seat option';
$string['sub_b2b_badge']      = 'B2B';

// Course availability.
$string['sub_courseavail_heading'] = 'Course subscription availability';
$string['sub_courseavail_desc']    = 'Choose courses and append them to a specific subscription.';
$string['sub_target']              = 'Target Subscription:';
$string['sub_select_placeholder']  = 'Select a subscription...';
$string['sub_save_courses']        = 'Save courses to subscription';
$string['sub_courses_search']      = 'Search courses…';
$string['sub_selectall']           = 'Select all';
$string['sub_clear']               = 'Clear';

// User subscriptions.
$string['sub_usersubs_heading']    = 'User Subscriptions';
$string['sub_usersubs_desc']       = 'Manage active and expired user subscriptions.';
$string['sub_unsub_title']         = 'Unsubscribe user';
$string['sub_unsub_refund']        = 'Refund payment to student';
$string['sub_unsubscribe']         = 'Unsubscribe';
$string['sub_none_admin']          = 'No subscriptions yet.';
$string['sub_inactive']            = 'Inactive';
$string['sub_edit_titled']         = 'Edit subscription #{$a}';
$string['sub_updated']             = 'Subscription updated.';
$string['sub_created']             = 'Subscription created.';
$string['sub_activated']           = 'Activated.';
$string['sub_deactivated']         = 'Deactivated.';
$string['sub_deleted']             = 'Deleted.';
$string['sub_confirm_delete']      = 'Delete this subscription? Only possible if it was never purchased. This cannot be undone.';
$string['sub_no_categories']       = 'No categories with courses found.';
$string['sub_select_target']       = 'Please select a target subscription.';
$string['sub_courses_assigned']    = 'Courses assigned successfully.';
$string['sub_no_usersubs']         = 'No user subscriptions found.';
$string['sub_unsub_confirm']       = 'Unsubscribe <strong>{$a->user}</strong> from <strong>{$a->name}</strong>{$a->price}? This cannot be undone.';
$string['sub_unsub_success']       = 'User unsubscribed successfully.';

// Subscription statuses.
$string['sstat_active']         = 'Active';
$string['sstat_expired']        = 'Expired';
$string['sstat_cancelled']      = 'Cancelled';
$string['sstat_pending']        = 'Pending';
$string['sstat_payment_failed'] = 'Payment failed';

// Errors.
$string['err_subnamerequired']  = 'Subscription name is required';
$string['err_subnameempty']     = 'Subscription name cannot be empty';
$string['err_pricenegative']    = 'Price cannot be negative';
$string['err_durationpositive'] = 'Number of days must be greater than zero';
$string['err_subnotfound']      = 'Subscription not found';
$string['err_subhaspurchases']  = 'This subscription has purchase records and cannot be deleted. Deactivate it instead.';
$string['err_coursenotfound']   = 'Course not found';
$string['err_seatspositive']    = 'Number of seats must be greater than zero';
$string['err_discountrange']    = 'Discount percentage must be between 0 and 100';
$string['err_status']           = 'Status must be "active" or "inactive"';
$string['err_postrequired']     = 'This action requires POST';
$string['err_permissiondenied'] = 'Permission denied';
$string['err_unknownfunction']  = 'Unknown function';
$string['err_requestfailed']    = 'Request failed';
$string['err_sessionexpired']   = 'Session expired — please reload the page and log in again.';
$string['err_paymentsunavailable'] = 'The payment gateway is not available on this site.';
$string['err_alreadyhassubscription'] = 'You already have an active subscription.';
$string['err_checkoutfailed']   = '{$a}';

// Buy modal (home-page block checkout).
$string['sub_confirm_title']   = 'Confirm your subscription';
$string['sub_confirm_intro']   = 'You are about to subscribe to this plan. You will be taken to secure checkout to complete the payment.';
$string['sub_duration_label']  = 'Duration';
$string['sub_total_label']     = 'Total';
$string['sub_coupon_label']    = 'Coupon';
$string['sub_coupon_apply']    = 'Apply';
$string['sub_discount_label']  = 'Discount';
$string['sub_secure_kashier']  = 'Secure payment via Kashier';
$string['sub_proceed_payment'] = 'Proceed to payment';
$string['sub_buy']             = 'Subscribe';
$string['enrolled']            = 'You are now enrolled in this course.';
