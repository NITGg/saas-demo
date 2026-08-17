<?php
defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'Payments';
$string['subplugintype_paymentprovider'] = 'Payment provider';
$string['subplugintype_paymentprovider_plural'] = 'Payment providers';

// Free-course self-registration.
$string['registerfree'] = 'Register for free';
$string['freecourseintro'] = 'This course is free. Click below to register and start learning.';
$string['freeenrolled'] = 'You are now enrolled. Enjoy the course!';

// Settings.
$string['generalsettings'] = 'Payment settings';
$string['default_country'] = 'Default country';
$string['default_country_desc'] = 'Fallback country code (ISO 3166-1 alpha-2) when detection fails.';
$string['default_currency'] = 'Default currency';
$string['default_currency_desc'] = 'Default currency code (ISO 4217).';
$string['payment_ttl'] = 'Payment timeout (seconds)';
$string['payment_ttl_desc'] = 'How long a pending payment remains valid before auto-expiring. Default: 1800 (30 minutes).';
$string['show_sale_badge'] = 'Show sale badge';
$string['show_sale_badge_desc'] = 'Display sale discount badge on course cards.';
$string['auto_expire_enabled'] = 'Auto-expire pending payments';
$string['auto_expire_enabled_desc'] = 'Automatically expire pending payments after the timeout period.';
$string['manageproviders'] = 'Manage payment providers';
$string['providersettings'] = 'Provider settings';
$string['reports'] = 'Payment reports';

// Course pricing.
$string['coursepricing'] = 'Course pricing';
$string['addprice'] = 'Add pricing rule';
$string['editprice'] = 'Edit pricing rule';
$string['noprices'] = 'No pricing rules configured for this course.';
$string['pricesaved'] = 'Pricing rule saved.';
$string['pricedeleted'] = 'Pricing rule deleted.';
$string['confirmdeleteprice'] = 'Are you sure you want to delete this pricing rule?';
$string['defaultprice'] = 'Default (all countries)';

// Form fields.
$string['country'] = 'Country';
$string['currency'] = 'Currency';
$string['price'] = 'Price';
$string['sale_price'] = 'Sale price';
$string['start_date'] = 'Start date';
$string['end_date'] = 'End date';
$string['is_default'] = 'Default price';
$string['is_active'] = 'Active';
$string['priority'] = 'Priority';
$string['actions'] = 'Actions';

// Validation.
$string['error_price_positive'] = 'Price must be greater than zero.';
$string['error_sale_price_lower'] = 'Sale price must be lower than the regular price.';
$string['error_end_after_start'] = 'End date must be after start date.';
$string['error_one_default'] = 'Only one default price is allowed per course.';
$string['error_one_active_per_country'] = 'There is already an active pricing rule for this country. Deactivate it first, or edit that rule instead.';

// Course display.
$string['enrolled'] = 'Enrolled';
$string['purchased'] = 'Purchased';
$string['sale'] = 'Sale';
$string['buynow'] = 'Subscribe';
$string['buycourse'] = 'Subscribe Now';
$string['free'] = 'Free';
$string['entercourse'] = 'Join';
$string['already_enrolled'] = 'You are enrolled in this course';
$string['already_purchased'] = 'You have purchased this course';
$string['secure_checkout'] = 'Secure checkout powered by trusted payment providers';
$string['covered_by_subscription'] = 'Covered by your subscription';
$string['enroll'] = 'Enroll';
$string['renew_subscription'] = 'Renew your subscription';

// Payment flow.
$string['paymentfor'] = 'Payment for: {$a}';
$string['payment_success'] = 'Payment Successful';
$string['payment_success_message'] = 'Your payment has been processed successfully. You now have access to the course.';
$string['payment_failure'] = 'Payment Failed';
$string['payment_failure_message'] = 'Your payment could not be processed. Please try again or contact support.';
$string['gotocourse'] = 'Go to Course';
$string['viewhistory'] = 'View Payment History';
$string['tryagain'] = 'Try Again';

// History.
$string['paymenthistory'] = 'Payment History';
$string['nopayments'] = 'You have no payment records.';
$string['orderid'] = 'Order ID';
$string['course'] = 'Course';
$string['amount'] = 'Amount';
$string['status'] = 'Status';
$string['paymentmethod'] = 'Payment Method';
$string['invoice'] = 'Invoice';
$string['date'] = 'Date';

// Reports.
$string['total_revenue'] = 'Total Revenue';
$string['total_transactions'] = 'Total Transactions';
$string['pending'] = 'Pending';
$string['failedpayments'] = 'Failed';
$string['refunds'] = 'Refunds';
$string['revenue_by_country'] = 'Revenue by Country';
$string['revenue_by_provider'] = 'Revenue by Provider';
$string['top_selling_courses'] = 'Top Selling Courses';
$string['transactions'] = 'Transactions';
$string['revenue'] = 'Revenue';
$string['provider'] = 'Provider';
$string['purchases'] = 'Purchases';

// Events.
$string['event_payment_completed'] = 'Payment completed';
$string['event_payment_created'] = 'Payment created';
$string['event_payment_failed'] = 'Payment failed';
$string['event_refund_processed'] = 'Refund processed';

// Tasks.
$string['task_expire_pending'] = 'Expire pending payments';

// Messages.
$string['payment_confirmation_subject'] = 'Payment Confirmed: {$a}';
$string['payment_confirmation_body'] = 'Your payment for "{$a->coursename}" has been confirmed.

Amount: {$a->amount} {$a->currency}
Order ID: {$a->order_id}

You now have full access to the course.';
$string['payment_confirmation_html'] = '<p>Your payment for <strong>{$a->coursename}</strong> has been confirmed.</p>
<p>Amount: <strong>{$a->amount} {$a->currency}</strong><br>Order ID: <code>{$a->order_id}</code></p>
<p>You now have full access to the course.</p>';
$string['payment_confirmation_small'] = 'Payment confirmed for {$a}';
$string['messageprovider:payment_confirmation'] = 'Payment confirmation notifications';

// Errors.
$string['nopricefound'] = 'No pricing rule found for this course in your region.';
$string['noproviderfound'] = 'No payment provider available for your region.';
$string['alreadypurchased'] = 'You have already purchased this course.';
$string['alreadyenrolled'] = 'You are already enrolled in this course.';
$string['paymentinitiationfailed'] = 'Payment could not be initiated: {$a}';
$string['transactionnotfound'] = 'Transaction not found.';
$string['enrolpluginnotinstalled'] = 'The enrolment plugin "{$a}" is not installed.';

// Privacy.
$string['privacy:metadata:transactions'] = 'Payment transaction records.';
$string['privacy:metadata:transactions:userid'] = 'The user who made the payment.';
$string['privacy:metadata:transactions:courseid'] = 'The course being purchased.';
$string['privacy:metadata:transactions:amount'] = 'The payment amount.';
$string['privacy:metadata:transactions:currency'] = 'The payment currency.';
$string['privacy:metadata:transactions:status'] = 'The payment status.';
$string['privacy:metadata:transactions:ip_address'] = 'The IP address of the user.';
$string['privacy:metadata:transactions:customer_email'] = 'The email sent to the payment provider.';
$string['privacy:metadata:invoices'] = 'Payment invoice records.';
$string['privacy:metadata:invoices:userid'] = 'The user the invoice belongs to.';
$string['privacy:metadata:invoices:invoice_number'] = 'The invoice number.';
$string['privacy:metadata:invoices:amount'] = 'The invoice amount.';

// Capabilities.
$string['payments:purchasecourse'] = 'Purchase a course';
$string['payments:viewownhistory'] = 'View own payment history';
$string['payments:managecoursepricing'] = 'Manage course pricing';
$string['payments:viewreports'] = 'View payment reports';
$string['payments:managerefunds'] = 'Manage refunds';
$string['payments:manageproviders'] = 'Manage payment providers';
$string['payments:viewalltransactions'] = 'View all transactions';
$string['payments:viewauditlogs'] = 'View audit logs';
