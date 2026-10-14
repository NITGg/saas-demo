<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_vimeo';
$plugin->version   = 2026101300; // access_token/client_secret shown decrypted / stored encrypted via local_nit_core
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'local_academy'  => ANY_VERSION, // shared web-service token validation
    'local_nit_core' => ANY_VERSION, // provides the encrypted admin setting class
];
