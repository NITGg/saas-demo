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
 * TEST-BRANCH backfill: encrypt existing plaintext mdl_user.email values at rest.
 *
 * Part of the "encrypted email + username-only login" experiment. Emails are stored
 * DETERMINISTICALLY encrypted (see \local_nit_core\helper\crypto::encrypt_user_email),
 * so a DB dump / stolen password / curious DBA cannot read them, while exact-equality
 * lookups (forgot-password, duplicate checks) still work. The patched core read paths
 * decrypt transparently for display and sending.
 *
 * Writes the ciphertext directly with set_field (NOT user_update_user) so it does not
 * re-encrypt or fire user_updated events. Idempotent: already-encrypted rows are skipped.
 *
 * SAFE BY DEFAULT: dry-run (reports only). Pass --execute to write.
 *
 * Usage (from the Moodle web root `public/`, or via docker):
 *   php local/nit_core/cli/encrypt_user_emails.php            # dry-run report
 *   php local/nit_core/cli/encrypt_user_emails.php --execute  # actually encrypt
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
    echo "Encrypt existing plaintext mdl_user.email values at rest (deterministic).\n\n";
    echo "Options:\n";
    echo "  --execute     Actually write changes. Without it, this is a DRY-RUN (reports only).\n";
    echo "  -h, --help    This help.\n\n";
    echo "Safe to re-run: rows already encrypted are skipped (idempotent).\n";
    exit(0);
}

$dryrun = empty($options['execute']);

if (!crypto::is_configured()) {
    cli_error("\$CFG->nit_crypto_key is not set (or not 32 bytes). "
        . "Set it in config.php before running this backfill — see local/nit_core/docs/crypto.md.");
}

cli_heading($dryrun ? 'Encrypt user emails — DRY RUN (no changes written)'
                    : 'Encrypt user emails — EXECUTING');

// Stream all real user rows that have an email. Guest/deleted still get encrypted if they
// carry an email; that is harmless. noreply/support are config-based, not table rows.
$rs = $DB->get_recordset_select('user', "email IS NOT NULL AND email <> ''", null, 'id', 'id, email');

$encrypted = 0;
$already = 0;

foreach ($rs as $row) {
    if (crypto::is_encrypted($row->email)) {
        $already++;
        continue;
    }
    if (!$dryrun) {
        // Direct write of the ciphertext — bypasses user_update_user on purpose.
        $DB->set_field('user', 'email', crypto::encrypt_user_email($row->email), ['id' => $row->id]);
    }
    $encrypted++;
}
$rs->close();

cli_writeln('');
cli_writeln('Emails ' . ($dryrun ? 'that WOULD be encrypted' : 'encrypted') . ": {$encrypted}");
cli_writeln("Emails already encrypted (skipped):     {$already}");

if ($dryrun) {
    cli_writeln('');
    cli_writeln('DRY RUN — nothing was written. Re-run with --execute to apply.');
} else {
    cli_writeln('');
    cli_writeln('Done. Emails are now ciphertext at rest; the patched core paths decrypt them for');
    cli_writeln('display and sending, and encrypt lookup values so forgot-password/dedupe still work.');
    cli_writeln('Purge caches is NOT required, but consider logging users out if sessions cache email.');
}

exit(0);
