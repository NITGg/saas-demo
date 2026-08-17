<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070106) {
        // Add a "Payment history" entry to the user (avatar) dropdown menu so
        // students can reach their history from any page. This appends to the
        // core customusermenuitems setting, idempotently — existing items are
        // preserved and we never add a duplicate.
        local_payments_add_usermenu_item();
        upgrade_plugin_savepoint(true, 2026070106, 'local', 'payments');
    }

    return true;
}

/**
 * Append the Payment history link to customusermenuitems if not already present.
 */
function local_payments_add_usermenu_item() {
    $item = 'paymenthistory,local_payments|/local/payments/history.php';
    $current = (string) get_config('core', 'customusermenuitems');

    // Already there (match on the URL so a relabelled entry still counts).
    if (strpos($current, '/local/payments/history.php') !== false) {
        return;
    }

    $new = ($current === '') ? $item : rtrim($current, "\n") . "\n" . $item;
    set_config('customusermenuitems', $new);
}
