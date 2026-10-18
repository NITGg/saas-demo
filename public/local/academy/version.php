<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_academy';
$plugin->version   = 2026101900; // New after_config hook (site defaults after a CLI install); the bump refreshes the hook cache.
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0-quiz';
