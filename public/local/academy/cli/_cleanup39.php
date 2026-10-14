<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
$cms = $DB->get_records_sql("SELECT cm.id, r.name FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
    JOIN {resource} r ON r.id = cm.instance WHERE cm.course = 61 AND (r.name LIKE 'E2E %' OR r.name = 'virus')");
foreach ($cms as $cm) { course_delete_module($cm->id); echo "deleted {$cm->id} {$cm->name}\n"; }
