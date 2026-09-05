<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Create / edit / delete Vimeo videos (upload, delete, list, attach).
    // Granted to editing teachers and managers by default.
    'local/vimeo:manage' => [
        'riskbitmask'  => RISK_SPAM | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // View / play a Vimeo video (obtain the embed URL). Any enrolled user.
    'local/vimeo:view' => [
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
