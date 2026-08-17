<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$lang = optional_param('lang', current_language() === 'ar' ? 'ar' : 'en', PARAM_ALPHA);
$couponcode = optional_param('coupon_code', '', PARAM_TEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Use require_login() without $course so unenrolled users (who are here
// specifically to purchase access) aren't bounced to the native enrolment
// page by Moodle's can_access_course() check.
require_login();
require_capability('local/payments:purchasecourse', $context);
// State-changing (creates a transaction + opens a gateway session), so require a
// valid sesskey — stops a logged-in victim being lured to checkout.php?courseid=X.
require_sesskey();

// No active pricing — course is free, nothing to check out.
if (!\local_payments\price_resolver::has_pricing($courseid)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$PAGE->set_url(new moodle_url('/local/payments/checkout.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('buycourse', 'local_payments'));
$PAGE->set_pagelayout('standard');

try {
    $result = \local_payments\manager::create_checkout($courseid, $USER->id, null, $lang, $couponcode);
    redirect(new moodle_url($result->checkout_url));
} catch (\Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $courseid]));
    echo $OUTPUT->footer();
}
