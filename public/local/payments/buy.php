<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login();
require_capability('local/payments:purchasecourse', $context);

// Already actively enrolled — send straight to the course. An expired
// enrolment (lapsed subscription/package) must NOT short-circuit here, so the
// student can re-purchase or re-enrol rather than bouncing to a course they
// can no longer access.
if (is_enrolled($context, $USER->id, '', true)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$action = optional_param('action', '', PARAM_ALPHA);

// Check for active subscription coverage.
$can_enroll_via_sub = false;
$activesub = null;
if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')
        && class_exists('\local_nit_subscriptions\subscription_manager')) {
    $activesub = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($USER->id);
    if ($activesub) {
        $covered_courses = \local_nit_subscriptions\subscription_manager::courses_for_subscription($activesub->subscriptionid);
        if (in_array($courseid, $covered_courses)) {
            $can_enroll_via_sub = true;
        }
    }
}

// Handle enroll action. Gated above on the user actually holding an active
// subscription that covers this course, plus sesskey — grant_course_access then
// enrols them for the subscription's remaining lifetime.
if ($can_enroll_via_sub && $action === 'enroll') {
    require_sesskey();
    \local_nit_subscriptions\subscription_purchase_manager::grant_course_access(
        $courseid, $USER->id, (int) $activesub->expires_at);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

// No active pricing — course is free. Let the student register themselves with
// one click (there is no paid checkout to show, and core self-enrolment may not
// be enabled on the course).
if (!\local_payments\price_resolver::has_pricing($courseid) && !$can_enroll_via_sub) {
    if ($action === 'enrollfree') {
        require_sesskey();
        \local_payments\enrollment_handler::enrol_user($USER->id, $courseid, 5);
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('freeenrolled', 'local_payments'));
    }

    $PAGE->set_url(new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]));
    $PAGE->set_context($context);
    $PAGE->set_title($course->fullname);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('standard');

    echo $OUTPUT->header();
    if (!empty($course->summary)) {
        echo $OUTPUT->box(format_text($course->summary, $course->summaryformat), 'generalbox mb-3');
    }
    echo html_writer::tag('p', get_string('freecourseintro', 'local_payments'), ['class' => 'lead']);
    $enrollurl = new moodle_url('/local/payments/buy.php',
        ['courseid' => $courseid, 'action' => 'enrollfree', 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($enrollurl, get_string('registerfree', 'local_payments'), 'post');
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_url(new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

// NIT: shared checkout modal (coupon + auto offer) for the course buy button.
$nitcommercelib = $CFG->dirroot . '/local/nit_commerce/lib.php';
$nitcheckout = file_exists($nitcommercelib);
if ($nitcheckout) {
    require_once($nitcommercelib);
    $PAGE->requires->js(new moodle_url('/local/nit_commerce/checkout_modal.js'), true);
}

echo $OUTPUT->header();

if (!empty($course->summary)) {
    echo $OUTPUT->box(format_text($course->summary, $course->summaryformat), 'generalbox mb-3');
}

try {
    $pricing = \local_payments\price_resolver::resolve($courseid, $USER->id);
    $is_purchased = \local_payments\price_resolver::is_purchased($courseid, $USER->id);

    $templatedata = [
        'courseid'       => $courseid,
        'is_enrolled'    => false,
        'is_purchased'   => $is_purchased,
        'price'          => number_format((float) $pricing->price, 2),
        'sale_price'     => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
        'original_price' => number_format((float) $pricing->original_price, 2),
        'currency'       => $pricing->currency,
        'is_sale_active' => (bool) $pricing->is_sale_active,
        'discount_pct'   => (int) $pricing->discount_pct,
        'buy_url'        => (new moodle_url('/local/payments/checkout.php', ['courseid' => $courseid, 'sesskey' => sesskey()]))->out(false),
        'can_enroll_via_sub' => $can_enroll_via_sub,
        'enroll_url'     => (new moodle_url('/local/payments/buy.php', ['courseid' => $courseid, 'action' => 'enroll', 'sesskey' => sesskey()]))->out(false),
    ];

    echo $OUTPUT->render_from_template('local_payments/course_page_price', $templatedata);

    // NIT: intercept the checkout link → open the coupon/offer modal → proceed with the coupon.
    if ($nitcheckout) {
        $costr = local_nit_commerce_string_map([
            'co_title', 'co_intro', 'co_total', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
            'co_secure', 'co_proceed', 'co_cancel', 'co_loading', 'co_coupon_failed', 'co_currency',
        ]);
        echo html_writer::script('window.NIT_CO = ' . json_encode([
            'wwwroot'  => $CFG->wwwroot,
            'sesskey'  => sesskey(),
            'commerce' => '/local/nit_commerce/api.php',
            'str'      => $costr,
            'courseid' => (int) $courseid,
            'name'     => format_string($course->fullname),
            'price'    => (float) $pricing->price,
        ]) . ';');
        echo html_writer::script(<<<'JS'
(function () {
    function init() {
        if (!window.NitCheckout || !window.NIT_CO) { return; }
        NitCheckout.init(window.NIT_CO);
        document.addEventListener('click', function (ev) {
            var a = ev.target.closest('a[href*="/local/payments/checkout.php"], [data-nit-buy-course]');
            if (!a) { return; }
            var href = a.getAttribute('href');
            if (!href) { return; }
            ev.preventDefault();
            NitCheckout.open({
                itemType: 'course',
                itemId: window.NIT_CO.courseid,
                name: window.NIT_CO.name,
                price: window.NIT_CO.price,
                proceed: function (code) {
                    window.location.href = href + (href.indexOf('?') >= 0 ? '&' : '?') + 'coupon_code=' + encodeURIComponent(code);
                }
            });
        });
    }
    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
JS
        );
    }
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification(get_string('nopricefound', 'local_payments'), 'info');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();
