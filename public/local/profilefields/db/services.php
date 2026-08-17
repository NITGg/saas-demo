<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_profilefields_get_profile_fields' => [
        'classname'   => 'local_profilefields\external\get_profile_fields',
        'methodname'  => 'execute',
        'description' => 'Get all custom user profile fields with their option values (for menu fields).',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
