<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_jitsi';
$plugin->release = '1.1.0-saas';
$plugin->version = 2026101200;          // waiting room kept by Moodle (jitsi_admission); room attendance log (jitsi_presence).
$plugin->requires = 2024100700;         // Moodle 4.5 LTS baseline (matches the platform).
$plugin->supported = [405, 502];
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = [
    'local_academysessions' => 2026101100,    // jitsi_jwt + shared config; presence stretches, teacher_last_leave.
];
