<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);
$PAGE->set_url('/mod/jitsi/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'jitsi'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('modulenameplural', 'jitsi'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'jitsi'));

$jitsisessions = get_all_instances_in_course('jitsi', $course);

if (empty($jitsisessions)) {
    notice(get_string('nonewmodules', 'jitsi'), new moodle_url('/course/view.php', ['id' => $id]));
}

$table              = new html_table();
$table->attributes  = ['class' => 'generaltable mod_index'];
$table->head        = [get_string('sessionname', 'jitsi')];
$table->align       = ['left'];

foreach ($jitsisessions as $jitsi) {
    $link = html_writer::link(
        new moodle_url('/mod/jitsi/view.php', ['id' => $jitsi->coursemodule]),
        format_string($jitsi->name)
    );
    $table->data[] = [$link];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
