<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_vdocipher';
$plugin->version   = 2026081202;
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.2.0-crud';
$plugin->dependencies = [
    'local_academy' => ANY_VERSION, // shared web-service token validation
];
