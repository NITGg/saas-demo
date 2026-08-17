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
 * Count and (optionally) backfill NULL/empty privatetoken values on permanent
 * web-service tokens.
 *
 * Background: tool_mobile_get_autologin_key (used by the app to open WebView
 * pages already signed in) needs the token's `privatetoken`. Core only ever
 * generates one when it MINTS a brand-new token row; rows created via
 * "Site admin > Manage tokens", or carried over in a migration, have a NULL
 * privatetoken. Any user whose reused token row has a NULL privatetoken cannot
 * auto-login into the WebView.
 *
 * This tool fills those gaps the exact same way core does (random_string(64)),
 * WITHOUT deleting or rotating the token itself — so existing sessions/clients
 * keep working, they simply gain a usable privatetoken.
 *
 * Usage:
 *   # Report only (default) — how many permanent tokens have no privatetoken:
 *   php local/googleauth/cli/backfill_privatetokens.php
 *
 *   # Actually backfill them:
 *   php local/googleauth/cli/backfill_privatetokens.php --execute
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help'    => false,
        'execute' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($options['help']) {
    echo "Backfill NULL privatetoken values on permanent web-service tokens.\n\n";
    echo "Options:\n";
    echo "  --execute   Perform the backfill. Without it, only reports the count (dry run).\n";
    echo "  -h, --help  Print this help.\n\n";
    echo "Example:\n";
    echo "  php local/googleauth/cli/backfill_privatetokens.php --execute\n";
    exit(0);
}

// EXTERNAL_TOKEN_PERMANENT (== 0) is defined in lib/moodlelib.php, always loaded
// via config.php. Use the constant rather than a literal so we never guess wrong.
$permanent = defined('EXTERNAL_TOKEN_PERMANENT') ? EXTERNAL_TOKEN_PERMANENT : 0;

$select = "(privatetoken IS NULL OR privatetoken = '') AND tokentype = :tt";
$params = ['tt' => $permanent];

$total   = $DB->count_records('external_tokens', ['tokentype' => $permanent]);
$missing = $DB->count_records_select('external_tokens', $select, $params);

cli_writeln("Permanent web-service tokens total : {$total}");
cli_writeln("...with NULL/empty privatetoken    : {$missing}");

if ($missing == 0) {
    cli_writeln("Nothing to do.");
    exit(0);
}

if (empty($options['execute'])) {
    cli_writeln("");
    cli_writeln("Dry run — no changes made. Re-run with --execute to backfill.");
    exit(0);
}

$rs = $DB->get_recordset_select('external_tokens', $select, $params, '', 'id');
$done = 0;
foreach ($rs as $row) {
    $DB->set_field('external_tokens', 'privatetoken', random_string(64), ['id' => $row->id]);
    $done++;
}
$rs->close();

cli_writeln("Backfilled {$done} token(s) with a fresh privatetoken.");
exit(0);
