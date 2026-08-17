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
 * English strings for local_nit_commerce. Labels match the reference local_academy plugin.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Commerce';
$string['managecoupons'] = 'Manage coupons';
$string['manageoffers']  = 'Manage offers';
$string['privacy:metadata'] = 'The NIT Commerce plugin stores discount coupons and offers defined by administrators; it does not store personal data by itself.';

// Shared UI.
$string['ui_refresh']      = 'Refresh';
$string['ui_loading']      = 'Loading…';
$string['ui_save']         = 'Save';
$string['ui_cancel']       = 'Cancel';
$string['ui_active']       = 'Active';
$string['ui_activate']     = 'Activate';
$string['ui_deactivate']   = 'Deactivate';
$string['ui_edit']         = 'Edit';
$string['ui_delete']       = 'Delete';
$string['ui_never']        = 'Never';
$string['ui_optional']     = '(optional)';
$string['ui_pager_info']   = 'Showing {from}–{to} of {total}';
$string['pkg_col_status']  = 'Status';
$string['pkg_col_actions'] = 'Actions';
$string['pkg_field_name_en'] = 'Name (English)';
$string['pkg_field_name_ar'] = 'Name (Arabic)';
$string['sub_inactive']    = 'Inactive';

// Discount scope "all of type" labels.
$string['scope_all_course']       = 'All courses';
$string['scope_all_package']      = 'All packages';
$string['scope_all_subscription'] = 'All subscriptions';
$string['scope_all_program']      = 'All programs';

// Coupons.
$string['cpn_new']         = 'Create coupon';
$string['cpn_none']        = 'No coupons yet.';
$string['cpn_col_code']    = 'Code';
$string['cpn_col_type']    = 'Type';
$string['cpn_col_value']   = 'Value';
$string['cpn_col_scope']   = 'Applies to';
$string['cpn_col_usage']   = 'Usage';
$string['cpn_col_dates']   = 'Valid';
$string['cpn_col_max']     = 'Max discount';
$string['cpn_field_code']  = 'Coupon code';
$string['cpn_field_dtype'] = 'Discount type';
$string['cpn_field_value'] = 'Discount value';
$string['cpn_field_max']   = 'Max discount amount';
$string['cpn_field_utype'] = 'Usage type';
$string['cpn_field_limit'] = 'Usage limit';
$string['cpn_field_start'] = 'Start date';
$string['cpn_field_end']   = 'End date';
$string['cpn_field_scope'] = 'Applicable items';
$string['cpn_type_percent'] = 'Percentage';
$string['cpn_type_fixed']   = 'Fixed';
$string['cpn_usage_once']     = 'One-time';
$string['cpn_usage_multiple'] = 'Multiple use';
$string['cpn_scope_courses']       = 'Courses';
$string['cpn_scope_packages']      = 'Packages';
$string['cpn_scope_subscriptions'] = 'Subscriptions';
$string['cpn_scope_programs']      = 'Programs';
$string['cpn_scope_all']      = 'All';
$string['cpn_scope_specific'] = 'Selected';
$string['cpn_created']     = 'Coupon created';
$string['cpn_updated']     = 'Coupon updated';
$string['cpn_activated']   = 'Coupon activated';
$string['cpn_deactivated'] = 'Coupon deactivated';
$string['cpn_deleted']     = 'Coupon deleted';
$string['cpn_confirm_delete'] = 'Delete this coupon? This cannot be undone.';
$string['cpn_edit_titled']    = 'Edit coupon {$a}';
$string['cpn_scope_required'] = 'Select at least one applicable item.';
$string['cpn_unlimited']      = 'Unlimited';
$string['cpn_used_count']     = 'Used {$a}';

// Offers.
$string['ofr_new']        = 'Create offer';
$string['ofr_none']       = 'No offers yet.';
$string['ofr_col_name']   = 'Name';
$string['ofr_field_name'] = 'Offer name';
$string['ofr_created']     = 'Offer created';
$string['ofr_updated']     = 'Offer updated';
$string['ofr_activated']   = 'Offer activated';
$string['ofr_deactivated'] = 'Offer deactivated';
$string['ofr_deleted']     = 'Offer deleted';
$string['ofr_confirm_delete'] = 'Delete this offer? This cannot be undone.';
$string['ofr_edit_titled']    = 'Edit offer {$a}';
$string['ofr_delete_title']   = 'Delete offer';

// Errors.
$string['err_itemtype']            = 'Invalid item type.';
$string['err_itemnotfound']        = 'The requested item was not found.';
$string['err_discounttype']        = 'Discount type must be percentage or fixed.';
$string['err_discountvalue']       = 'Discount value cannot be negative.';
$string['err_discountpercent']     = 'A percentage discount must be between 0 and 100.';
$string['err_maxdiscount']         = 'Max discount cannot be negative.';
$string['err_daterange']           = 'The end date must be after the start date.';
$string['err_usagetype']           = 'Usage type must be one-time or multiple.';
$string['err_status']              = 'Status must be "active" or "inactive"';
$string['err_couponcoderequired']  = 'A coupon code is required.';
$string['err_couponcodetaken']     = 'That coupon code is already in use.';
$string['err_couponnotfound']      = 'Coupon not found.';
$string['err_couponinactive']      = 'This coupon is not active.';
$string['err_couponnotstarted']    = 'This coupon is not valid yet.';
$string['err_couponexpired']       = 'This coupon has expired.';
$string['err_couponnotapplicable'] = 'This coupon does not apply to this item.';
$string['err_couponusedup']        = 'This coupon has reached its usage limit.';
$string['err_couponalreadyusedbyuser'] = 'You have already used this coupon.';
$string['err_couponbusy'] = 'This coupon is being processed by another request. Please try again in a moment.';
$string['cleanupreservations'] = 'Release abandoned coupon reservations';
$string['err_couponhasusages']     = 'This coupon has been used and can only be deactivated.';
$string['err_offernamerequired']   = 'An offer name is required.';
$string['err_offernotfound']       = 'Offer not found.';
$string['err_offerhasusages']      = 'This offer has been used and can only be deactivated.';
$string['err_postrequired']        = 'This action requires POST';
$string['err_permissiondenied']    = 'Permission denied';
$string['err_unknownfunction']     = 'Unknown function';
$string['err_requestfailed']       = 'Request failed';
$string['err_sessionexpired']      = 'Session expired — please reload the page and log in again.';

// Shared checkout modal (course/subscription buy).
$string['co_title']         = 'Confirm your purchase';
$string['co_intro']         = 'You will be taken to secure checkout to complete the payment.';
$string['co_total']         = 'Total';
$string['co_offer']         = 'Offer';
$string['co_coupon']        = 'Coupon';
$string['co_apply']         = 'Apply';
$string['co_discount']      = 'Discount';
$string['co_secure']        = 'Secure payment via Kashier';
$string['co_proceed']       = 'Proceed to payment';
$string['co_cancel']        = 'Cancel';
$string['co_loading']       = 'Loading…';
$string['co_coupon_failed'] = 'Could not apply coupon.';
$string['co_currency']      = 'EGP';
$string['co_buy']           = 'Buy now';
