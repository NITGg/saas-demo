<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026092000;
$plugin->requires  = 2022041900;
$plugin->component = 'local_nit_category';
$plugin->dependencies = [
    'local_nit_core' => ANY_VERSION, // \local_nit_core\helper\arabic (search folding).
];
