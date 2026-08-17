<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'country_detection' => [
        'mode' => cache_store::MODE_APPLICATION,
        // Keys are IP addresses (dots, and colons for IPv6), which are not valid
        // "simple keys" — let Moodle hash them instead. Values are plain strings.
        'simpledata' => true,
        'ttl' => 86400, // 24 hours.
    ],
];
