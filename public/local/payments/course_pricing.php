<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$priceid = optional_param('priceid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/payments:managecoursepricing', $context);

$PAGE->set_url(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('coursepricing', 'local_payments'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// Handle delete.
if ($action === 'delete' && $priceid && confirm_sesskey()) {
    if ($confirm) {
        $DB->delete_records('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid]);
        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]),
            get_string('pricedeleted', 'local_payments'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteprice', 'local_payments'),
        new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'delete', 'priceid' => $priceid,
            'confirm' => 1, 'sesskey' => sesskey(),
        ]),
        new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid])
    );
    echo $OUTPUT->footer();
    exit;
}

// Handle add/edit form.
if ($action === 'edit' || $action === 'add') {
    $data = null;
    if ($priceid) {
        $data = $DB->get_record('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid], '*', MUST_EXIST);
    }

    $formurl = new moodle_url('/local/payments/course_pricing.php', [
        'courseid' => $courseid,
        'action' => $action,
        'priceid' => $priceid,
    ]);

    $form = new \local_payments\form\course_pricing_form($formurl, [
        'courseid' => $courseid,
        'priceid' => $priceid,
        'data' => $data,
    ]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]));
    }

    if ($formdata = $form->get_data()) {
        // The first price for a course is always the default.
        $isfirst = !$DB->record_exists('local_payments_course_prices', ['courseid' => $courseid]);
        $record = (object) [
            'courseid' => $courseid,
            'country' => $formdata->country,
            'currency' => $formdata->currency,
            'price' => $formdata->price,
            'is_default' => ($isfirst || !empty($formdata->is_default)) ? 1 : 0,
            'is_active' => !empty($formdata->is_active) ? 1 : 0,
            'timemodified' => time(),
        ];

        if ($priceid) {
            $record->id = $priceid;
            $DB->update_record('local_payments_course_prices', $record);
        } else {
            $record->created_by = $USER->id;
            $record->timecreated = time();
            $DB->insert_record('local_payments_course_prices', $record);
        }

        redirect(new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]),
            get_string('pricesaved', 'local_payments'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($priceid ? 'editprice' : 'addprice', 'local_payments'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// List prices.
$prices = $DB->get_records('local_payments_course_prices', ['courseid' => $courseid], 'is_default DESC, country ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursepricing', 'local_payments'));

$addurl = new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid, 'action' => 'add']);
echo html_writer::link($addurl, get_string('addprice', 'local_payments'), ['class' => 'btn btn-primary mb-3']);

if (empty($prices)) {
    echo $OUTPUT->notification(get_string('noprices', 'local_payments'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('country', 'local_payments'),
        get_string('currency', 'local_payments'),
        get_string('price', 'local_payments'),
        get_string('is_default', 'local_payments'),
        get_string('is_active', 'local_payments'),
        get_string('actions', 'local_payments'),
    ];
    $table->attributes['class'] = 'generaltable';

    $countries = get_string_manager()->get_list_of_countries();

    foreach ($prices as $p) {
        $countryname = ($p->country === '*') ? get_string('defaultprice', 'local_payments') : ($countries[$p->country] ?? $p->country);

        $editurl = new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'edit', 'priceid' => $p->id,
        ]);
        $deleteurl = new moodle_url('/local/payments/course_pricing.php', [
            'courseid' => $courseid, 'action' => 'delete', 'priceid' => $p->id, 'sesskey' => sesskey(),
        ]);

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit'))) . ' ' .
            html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

        $table->data[] = [
            $countryname,
            $p->currency,
            number_format((float) $p->price, 2),
            $p->is_default ? get_string('yes') : get_string('no'),
            $p->is_active ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
