<?php
defined('MOODLE_INTERNAL') || die();

// Recording sync/cleanup tasks (MinIO/Bunny) were removed for the SaaS; recording
// upload to VdoCipher lands in a later phase. Only the session lifecycle task (auto
// start/end of scheduled live sessions) remains — it needs no external store.
$tasks = array(
    array(
        'classname' => 'local_academysessions\task\session_lifecycle',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),
);
