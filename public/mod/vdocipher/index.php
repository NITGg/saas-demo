<?php
/**
 * List all VdoCipher Video activities in a course.
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // course id

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url(new moodle_url('/mod/vdocipher/index.php', ['id' => $id]));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'vdocipher'));

$instances = get_all_instances_in_course('vdocipher', $course);
if (empty($instances)) {
    notice(get_string('noinstances', 'vdocipher'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name'), get_string('sectionname', 'format_' . $course->format) ?: get_string('section')];
foreach ($instances as $instance) {
    $link = new moodle_url('/mod/vdocipher/view.php', ['id' => $instance->coursemodule]);
    $name = html_writer::link($link, format_string($instance->name),
        $instance->visible ? [] : ['class' => 'dimmed']);
    $table->data[] = [$name, $instance->section];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
