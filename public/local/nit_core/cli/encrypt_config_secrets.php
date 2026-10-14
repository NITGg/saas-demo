<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backfill: encrypt existing plaintext plugin CONFIG secrets in-place.
 *
 * Encrypts selected plugin settings (stored by get_config/set_config) using
 * \local_nit_core\helper\crypto, so a DB dump / stolen password / curious DBA
 * cannot read them. The consuming code reads these via crypto::config_secret(),
 * which decrypts an encrypted value and passes a legacy plaintext value through
 * unchanged — so it works both before AND after this backfill runs.
 *
 * SAFE BY DEFAULT: runs as a dry-run (reports only). Pass --execute to write.
 * Idempotent: already-encrypted values are skipped, so it is safe to re-run.
 *
 * NOTE: these settings now use \local_nit_core\admin\admin_setting_encryptedpasswordunmask,
 * which encrypts on save and decrypts for display. So editing one via the admin UI
 * keeps it encrypted at rest — you no longer need to re-run this CLI afterwards.
 * This backfill is mainly for existing/legacy plaintext values. No cache purge needed.
 *
 * Usage (from the Moodle web root `public/`, or via docker):
 *   php local/nit_core/cli/encrypt_config_secrets.php               # dry-run report
 *   php local/nit_core/cli/encrypt_config_secrets.php --execute     # actually encrypt
 *   docker compose exec -u www-data moodle php local/nit_core/cli/encrypt_config_secrets.php --execute
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_core\helper\crypto;

list($options, $unrecognized) = cli_get_params(
    [
        'help'    => false,
        'execute' => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    echo "Encrypt existing plaintext plugin config secrets at rest in the DB.\n\n";
    echo "Covers these settings:\n";
    echo "  local_vdocipher:          apisecret\n";
    echo "  local_vimeo:              access_token, client_secret\n";
    echo "  local_academysessions:    jitsi_jwt_app_secret, jibri_notify_key\n";
    echo "  paymentprovider_kashier:  live_api_key, live_secret_key, test_api_key, test_secret_key\n\n";
    echo "Options:\n";
    echo "  --execute     Actually write changes. Without it, this is a DRY-RUN (reports only).\n";
    echo "  -h, --help    This help.\n\n";
    echo "Safe to re-run: values already encrypted are skipped (idempotent).\n\n";
    echo "After encrypting, the consuming code reads these via \\local_nit_core\\helper\\crypto::config_secret(),\n";
    echo "which decrypts them transparently, so the plugins keep working. No cache purge is required.\n\n";
    echo "These settings use admin_setting_encryptedpasswordunmask (encrypt-on-save, decrypt-on-display),\n";
    echo "so editing one via the admin UI keeps it encrypted — no need to re-run this CLI after an edit.\n";
    exit(0);
}

// Fixed map of plugin => [setting names] to encrypt. Keep in sync with the read
// sites that call crypto::config_secret() for the same plugin+name.
$targets = [
    'local_vdocipher'         => ['apisecret'],
    'local_vimeo'             => ['access_token', 'client_secret'],
    'local_academysessions'   => ['jitsi_jwt_app_secret', 'jibri_notify_key'],
    'paymentprovider_kashier' => ['live_api_key', 'live_secret_key', 'test_api_key', 'test_secret_key'],
];

$dryrun = empty($options['execute']);

if (!crypto::is_configured()) {
    cli_error("\$CFG->nit_crypto_key is not set (or not 32 bytes). "
        . "Set it in config.php before running this backfill — see local/nit_core/docs/crypto.md.");
}

cli_heading($dryrun ? 'Encrypt plugin config secrets — DRY RUN (no changes written)'
                    : 'Encrypt plugin config secrets — EXECUTING');

$encrypted = 0;
$already = 0;
$empty = 0;

foreach ($targets as $plugin => $names) {
    foreach ($names as $name) {
        $value = get_config($plugin, $name);

        // Not set, false, null or empty string → nothing to encrypt.
        if ($value === false || $value === null || !is_string($value) || $value === '') {
            $empty++;
            cli_writeln("  [empty]     {$plugin} / {$name}");
            continue;
        }

        if (crypto::is_encrypted($value)) {
            $already++;
            cli_writeln("  [encrypted] {$plugin} / {$name} (already — skipped)");
            continue;
        }

        if (!$dryrun) {
            set_config($name, crypto::encrypt($value), $plugin);
        }
        $encrypted++;
        cli_writeln("  [" . ($dryrun ? 'would' : 'wrote') . "]     {$plugin} / {$name}");
    }
}

cli_writeln('');
cli_writeln('Settings ' . ($dryrun ? 'that WOULD be encrypted' : 'encrypted') . ": {$encrypted}");
cli_writeln("Settings already encrypted (skipped): {$already}");
cli_writeln("Settings empty/unset (skipped):       {$empty}");

if ($dryrun) {
    cli_writeln('');
    cli_writeln('DRY RUN — nothing was written. Re-run with --execute to apply.');
} else {
    cli_writeln('');
    cli_writeln('Done. Encrypted settings are now ciphertext; the consuming code reads them via');
    cli_writeln('crypto::config_secret(), so the plugins keep working. No cache purge is required.');
}

exit(0);
