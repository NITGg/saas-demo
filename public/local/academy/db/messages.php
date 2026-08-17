<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Message providers for local_academy.
 */
$messageproviders = [
    // Welcome message sent to a new user the first time they log in after
    // confirming their email signup.
    'welcome' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
