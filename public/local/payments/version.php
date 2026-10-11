<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_payments';
$plugin->version   = 2026091602; // encrypt+decrypt webhook card/secret fields via local_nit_core crypto
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->dependencies = [
    'local_nit_core' => ANY_VERSION, // provides \local_nit_core\helper\crypto (app-layer encryption)
];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
