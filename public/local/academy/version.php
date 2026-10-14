<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_academy';
$plugin->version   = 2026101400; // BUG-039: new lib.php callbacks + hook; the bump refreshes Moodle's callback cache.
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0-quiz';
