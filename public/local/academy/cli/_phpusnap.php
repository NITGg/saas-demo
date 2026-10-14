<?php
// Throw-away: finish the PHPUnit install (write the snapshot files the install could not write).
define('IGNORE_COMPONENT_CACHE', true);
require_once(__DIR__ . '/../../../lib/clilib.php');
require_once(__DIR__ . '/../../../lib/phpunit/bootstraplib.php');
require_once(__DIR__ . '/../../../lib/testing/lib.php');
require_once(__DIR__ . '/../../../../vendor/autoload.php');
define('CACHE_DISABLE_ALL', true);
define('PHPUNIT_UTIL', true);
require(__DIR__ . '/../../../lib/phpunit/bootstrap.php');
global $CFG;
@mkdir($CFG->dataroot . '/phpunit', $CFG->directorypermissions, true);
$class = new ReflectionClass(\core\test\phpunit\phpunit_util::class);
foreach (['save_original_data_files', 'store_versions_hash', 'store_database_state'] as $m) {
    $method = $class->getMethod($m);
    $method->invoke(null);
    echo "$m done\n";
}
[$code, $msg] = \core\test\phpunit\phpunit_util::testing_ready_problem();
echo "ready problem: $code $msg\n";
