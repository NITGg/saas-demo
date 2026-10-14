<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
$DB->delete_records('local_nit_devices', ['userid' => 148]);
echo "devices cleared\n";
echo "uploadrepo=" . $DB->get_field_sql("SELECT r.id FROM {repository_instances} r JOIN {repository} t ON t.id=r.typeid WHERE t.type='upload'") . "\n";
