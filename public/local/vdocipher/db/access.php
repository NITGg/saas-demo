<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Create / edit / delete VdoCipher videos (upload credentials, delete, list).
    // Granted to editing teachers and managers by default.
    'local/vdocipher:manage' => [
        'riskbitmask'  => RISK_SPAM | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // View / play a VdoCipher video (obtain a playback OTP). Any enrolled user.
    'local/vdocipher:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
