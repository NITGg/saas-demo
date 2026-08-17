<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'payment_confirmation' => [
        'capability' => 'local/payments:purchasecourse',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
