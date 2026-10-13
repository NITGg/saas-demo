<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_academysessions';
$plugin->version   = 2026101300;        // jitsi/jibri secrets shown decrypted / stored encrypted via local_nit_core.
$plugin->requires  = 2024100700;        // Moodle 4.5 LTS baseline (matches the platform).
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0-saas';
$plugin->dependencies = [
    'local_nit_core' => ANY_VERSION,    // provides the encrypted admin setting class.
];
