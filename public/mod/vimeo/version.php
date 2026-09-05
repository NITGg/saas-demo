<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_vimeo';
$plugin->version   = 2026090500;
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'local_vimeo' => ANY_VERSION, // API client, mapping table, embed playback
];
