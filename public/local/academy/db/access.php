<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/academy:manageplatform' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
