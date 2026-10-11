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
 * Backfill: encrypt existing plaintext webhook secret columns in-place.
 *
 * Encrypts `card_info`, `source_of_funds` and `signature_keys` in
 * `local_payments_webhooks` using \local_nit_core\helper\crypto, with the same
 * per-field context the live writer uses, so they decrypt correctly afterwards.
 *
 * SAFE BY DEFAULT: runs as a dry-run (reports only). Pass --execute to write.
 * Idempotent: already-encrypted values are skipped, so it is safe to re-run.
 *
 * Usage (from the Moodle root, or via docker):
 *   php local/payments/cli/encrypt_webhooks.php                 # dry-run report
 *   php local/payments/cli/encrypt_webhooks.php --execute       # actually encrypt
 *   docker compose exec -u www-data moodle php local/payments/cli/encrypt_webhooks.php --execute
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help'    => false,
        'execute' => false,
        'limit'   => 0,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    echo "Encrypt existing plaintext webhook secret columns (card_info, source_of_funds, signature_keys).\n\n";
    echo "Options:\n";
    echo "  --execute     Actually write changes. Without it, this is a DRY-RUN (reports only).\n";
    echo "  --limit=N     Process at most N rows (0 = all, default).\n";
    echo "  -h, --help    This help.\n\n";
    echo "Safe to re-run: rows already encrypted are skipped.\n";
    exit(0);
}

// The fields to encrypt, mapped to their crypto context — the SAME source of
// truth the writer and reader use, so decrypt() works afterwards.
$fields = \local_payments\manager::ENCRYPTED_WEBHOOK_FIELDS;

$dryrun = empty($options['execute']);
$limit  = max(0, (int) $options['limit']);

if (!\local_nit_core\helper\crypto::is_configured()) {
    cli_error("\$CFG->nit_crypto_key is not set (or not 32 bytes). "
        . "Set it in config.php before running this backfill — see local/nit_core/docs/crypto.md.");
}

cli_heading($dryrun ? 'Encrypt webhook secrets — DRY RUN (no changes written)'
                    : 'Encrypt webhook secrets — EXECUTING');

$select = 'card_info IS NOT NULL OR source_of_funds IS NOT NULL OR signature_keys IS NOT NULL';
$rs = $DB->get_recordset_select('local_payments_webhooks', $select, null, 'id',
    'id, ' . implode(', ', array_keys($fields)));

$rows = 0;
$encrypted = 0;
$already = 0;
$empty = 0;

foreach ($rs as $row) {
    if ($limit && $rows >= $limit) {
        break;
    }
    $rows++;

    foreach ($fields as $field => $context) {
        $val = $row->$field;
        if ($val === null || $val === '') {
            $empty++;
            continue;
        }
        if (\local_nit_core\helper\crypto::is_encrypted($val)) {
            $already++;
            continue;
        }
        if (!$dryrun) {
            $DB->set_field('local_payments_webhooks', $field,
                \local_nit_core\helper\crypto::encrypt($val, $context), ['id' => $row->id]);
        }
        $encrypted++;
    }

    if ($rows % 500 === 0) {
        cli_writeln("  scanned {$rows} rows…");
    }
}
$rs->close();

cli_writeln('');
cli_writeln("Rows scanned:            {$rows}");
cli_writeln("Fields " . ($dryrun ? 'that WOULD be encrypted' : 'encrypted') . ": {$encrypted}");
cli_writeln("Fields already encrypted (skipped): {$already}");
cli_writeln("Fields empty/null (skipped):        {$empty}");

if ($dryrun) {
    cli_writeln('');
    cli_writeln('DRY RUN — nothing was written. Re-run with --execute to apply.');
} else {
    cli_writeln('');
    cli_writeln('Done. Encrypted fields are now ciphertext; reads must use crypto::decrypt().');
}

exit(0);
