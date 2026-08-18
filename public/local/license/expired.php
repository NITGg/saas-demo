<?php
/**
 * The "academy expired — upgrade" landing page shown to everyone (except admins,
 * who are let through by the hook) once the licence lapses.
 */

require(__DIR__ . '/../../config.php');

use local_license\license;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/license/expired.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('expired_title', 'local_license'));
$PAGE->set_heading(format_string($SITE->fullname));

echo $OUTPUT->header();

echo html_writer::start_div('', ['style' => 'max-width:640px;margin:3rem auto;text-align:center;']);
echo html_writer::tag('div', '⏳', ['style' => 'font-size:3rem;line-height:1;margin-bottom:1rem;']);
echo $OUTPUT->heading(get_string('expired_heading', 'local_license'));
echo html_writer::tag('p',
    get_string('expired_body', 'local_license', license::tiername()),
    ['style' => 'font-size:1.1rem;color:#6c757d;margin:1rem 0;']);
echo html_writer::tag('p', get_string('expired_contact', 'local_license'),
    ['style' => 'margin-bottom:1.5rem;']);
echo html_writer::link(new moodle_url('/login/logout.php', ['sesskey' => sesskey()]),
    get_string('logout'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
