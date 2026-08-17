<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_install() {
    global $DB;

    // Seed Kashier provider record.
    if (!$DB->record_exists('local_payments_providers', ['name' => 'kashier'])) {
        $DB->insert_record('local_payments_providers', (object) [
            'name' => 'kashier',
            'display_name' => 'Kashier',
            'plugin_name' => 'paymentprovider_kashier',
            'enabled' => 0,
            'priority' => 100,
            'supported_countries' => json_encode(['EG', 'SA', 'AE', 'KW', 'BH', 'QA', 'OM']),
            'supported_currencies' => json_encode(['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED']),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    return true;
}
