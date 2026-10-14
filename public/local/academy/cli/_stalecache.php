<?php
// Throw-away: put the callback cache back to what it held before lib.php had the BUG-039 callback.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
$key = $CFG->allversionshash . '_coursemodule_validation_libphp';
$cache = \cache::make('core', 'plugin_functions');
echo "before: " . json_encode($cache->get($key)) . "\n";
$cache->set($key, ['local' => ['nit_finance' => 'local_nit_finance_coursemodule_validation']]);
echo "after:  " . json_encode($cache->get($key)) . "\n";
