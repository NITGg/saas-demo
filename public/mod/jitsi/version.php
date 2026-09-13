<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_jitsi';
$plugin->release = '1.1.0-saas';
$plugin->version = 2026091300;          // SaaS port: multi-tenant rooms, Professional gate, no MinIO/Bunny.
$plugin->requires = 2024100700;         // Moodle 4.5 LTS baseline (matches the platform).
$plugin->supported = [405, 502];
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = [
    'local_academysessions' => ANY_VERSION,   // jitsi_jwt + shared Jitsi/whiteboard config.
];
