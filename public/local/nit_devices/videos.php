<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Video protection check: the paid courses whose video lessons are still on Vimeo.
 * Only VdoCipher lessons carry DRM (Widevine / FairPlay) and the viewer's
 * watermark; a Vimeo lesson can be downloaded or screen-recorded more easily, so
 * these are the lessons to move. Nothing is blocked — this page only lists them.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_nit_devices_videos');

$manager = $DB->get_manager();
$hasvimeo = $manager->table_exists('vimeo');
$hasvdo = $manager->table_exists('vdocipher');

// Visible video lessons per course and provider.
$counts = [];
foreach (['vimeo' => $hasvimeo, 'vdocipher' => $hasvdo] as $modname => $exists) {
    if (!$exists) {
        continue;
    }
    $rows = $DB->get_records_sql(
        "SELECT cm.course, COUNT(1) AS n
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
          WHERE cm.deletioninprogress = 0
       GROUP BY cm.course", ['modname' => $modname]);
    foreach ($rows as $row) {
        $counts[(int) $row->course][$modname] = (int) $row->n;
    }
}

// Paid = the course has a price (local_payments pricing).
$ispaid = static function(int $courseid): bool {
    return class_exists('\local_payments\price_resolver') && \local_payments\price_resolver::has_pricing($courseid);
};

$table = new html_table();
$table->head = [get_string('videocheck_course', 'local_nit_devices'), get_string('videocheck_vimeo', 'local_nit_devices'),
    get_string('videocheck_vdocipher', 'local_nit_devices')];
foreach ($counts as $courseid => $count) {
    if (empty($count['vimeo']) || !$ispaid($courseid)) {
        continue;
    }
    $course = get_course($courseid);
    $table->data[] = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
            format_string($course->fullname, true, ['context' => context_course::instance($courseid)])),
        $count['vimeo'],
        $count['vdocipher'] ?? 0,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('videocheck', 'local_nit_devices'));
echo html_writer::tag('p', get_string('videocheck_desc', 'local_nit_devices'));
if (!$table->data) {
    echo $OUTPUT->notification(get_string('videocheck_none', 'local_nit_devices'), \core\output\notification::NOTIFY_SUCCESS);
} else {
    echo $OUTPUT->notification(get_string('videocheck_found', 'local_nit_devices', count($table->data)),
        \core\output\notification::NOTIFY_WARNING);
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
