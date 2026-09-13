<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_academysessions';
$plugin->version   = 2026091302;        // + VdoCipher recording (vdocipher_videoid); Jitsi+Excalidraw.
$plugin->requires  = 2024100700;        // Moodle 4.5 LTS baseline (matches the platform).
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0-saas';
