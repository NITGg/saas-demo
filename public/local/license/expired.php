<?php
/**
 * The "academy expired / suspended" notice shown to everyone (except admins, who
 * are let through by the hook) once the licence lapses or the academy is
 * suspended from the dashboard.
 *
 * PUBLIC by design — it must NOT call require_login(): a not-logged-in visitor
 * would be bounced to /login, which together with the suspend redirect hook
 * produced an ERR_TOO_MANY_REDIRECTS loop.
 */

require(__DIR__ . '/../../config.php');

use local_license\license;

// Same page serves the expiry lock and the admin "suspend" lock.
$suspended = (optional_param('reason', '', PARAM_ALPHA) === 'suspended');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/license/expired.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string($suspended ? 'suspended_title' : 'expired_title', 'local_license'));
$PAGE->set_heading(format_string($SITE->fullname));

// A "Contact us" action: prefer the academy's configured support email.
$support = trim((string) ($CFG->supportemail ?? ''));

echo $OUTPUT->header();

echo html_writer::start_div('', [
    'style' => 'max-width:640px;margin:3rem auto;padding:2.5rem 1.5rem;text-align:center;'
        . 'background:var(--nit-brand-surface,#fff);border:1px solid var(--nit-brand-borderprimary,#e5e7eb);'
        . 'border-radius:16px;',
]);
echo html_writer::tag('div', $suspended ? '⛔' : '⏳',
    ['style' => 'font-size:3.25rem;line-height:1;margin-bottom:1rem;']);
echo $OUTPUT->heading(get_string($suspended ? 'suspended_heading' : 'expired_heading', 'local_license'));
echo html_writer::tag('p',
    $suspended
        ? get_string('suspended_body', 'local_license')
        : get_string('expired_body', 'local_license', license::tiername()),
    ['style' => 'font-size:1.1rem;color:var(--nit-brand-textsecondary,#6c757d);margin:1rem 0 1.75rem;']);

echo html_writer::start_div('', ['style' => 'display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;']);
if ($support !== '') {
    // Primary CTA — email the academy's support address.
    echo html_writer::link('mailto:' . s($support),
        get_string('contact_btn', 'local_license'),
        ['class' => 'btn btn-primary', 'style' => 'padding:.6rem 1.6rem;']);
} else {
    echo html_writer::tag('span', get_string('expired_contact', 'local_license'),
        ['style' => 'color:var(--nit-brand-textsecondary,#6c757d);']);
}
// Secondary — let a logged-in user sign out.
if (isloggedin() && !isguestuser()) {
    echo html_writer::link(new moodle_url('/login/logout.php', ['sesskey' => sesskey()]),
        get_string('logout'), ['class' => 'btn btn-secondary', 'style' => 'padding:.6rem 1.6rem;']);
}
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
