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
 * Turn off "Require email verification" on OAuth 2 login issuers.
 *
 * Google (and other OAuth providers) already verify the user's email before
 * returning it, so Moodle's own email re-confirmation just adds useless steps:
 * a "check your email" page, a confirmation link, and a second sign-in. With
 * confirmation off, a new OAuth user is created already-confirmed and logged in
 * immediately (see auth/oauth2/classes/auth.php, the requireconfirmation=false
 * branch).
 *
 * This is what the provisioner should call after cloning/restoring an academy,
 * so every new SaaS tenant defaults to the frictionless flow WITHOUT editing
 * Moodle core (the core default lives in lib/classes/oauth2/issuer.php and must
 * stay untouched so tenants remain upgradeable). It is idempotent: running it
 * again is a no-op once the issuers are already set.
 *
 * The requireconfirmation flag is stored per-issuer in the database, so it also
 * survives Moodle upgrades once applied.
 *
 *   php local/academy/cli/oauth2_no_confirmation.php --show
 *   php local/academy/cli/oauth2_no_confirmation.php            # all login issuers
 *   php local/academy/cli/oauth2_no_confirmation.php --name=Google
 *   php local/academy/cli/oauth2_no_confirmation.php --dry-run
 *
 * @package    local_academy
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use core\oauth2\api as oauth2_api;

list($options, $unrecognized) = cli_get_params(
    [
        'name'    => '',      // Only issuers whose name matches this (case-insensitive). Empty = all.
        'show'    => false,   // Just print current state, change nothing.
        'dry-run' => false,   // Report what would change, but don't save.
        'help'    => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    cli_writeln("Turn off \"Require email verification\" on OAuth 2 login issuers.\n");
    cli_writeln("Removes the email-confirmation steps from OAuth (e.g. Google) sign-in so new");
    cli_writeln("users are created confirmed and logged in immediately. Idempotent.\n");
    cli_writeln("Options:");
    cli_writeln("  --name=NAME   Only issuers whose name matches NAME (case-insensitive). Default: all login issuers.");
    cli_writeln("  --show        Print each issuer's current setting and exit (no changes).");
    cli_writeln("  --dry-run     Show what would change without saving.");
    cli_writeln("  -h, --help    This help.\n");
    cli_writeln("Examples:");
    cli_writeln("  php local/academy/cli/oauth2_no_confirmation.php --show");
    cli_writeln("  php local/academy/cli/oauth2_no_confirmation.php");
    cli_writeln("  php local/academy/cli/oauth2_no_confirmation.php --name=Google");
    exit(0);
}

// Login-capable issuers only (skip service-only ones — confirmation is irrelevant there).
$issuers = oauth2_api::get_all_issuers(false);

$namefilter = trim((string) $options['name']);
if ($namefilter !== '') {
    $needle = core_text::strtolower($namefilter);
    $issuers = array_values(array_filter($issuers, function ($issuer) use ($needle) {
        return strpos(core_text::strtolower($issuer->get('name')), $needle) !== false;
    }));
}

if (empty($issuers)) {
    if ($namefilter !== '') {
        cli_writeln("No login-enabled OAuth 2 issuer matches name '{$namefilter}'. Nothing to do.");
    } else {
        cli_writeln("No login-enabled OAuth 2 issuers found. Nothing to do.");
    }
    exit(0);
}

// --show: report and exit.
if ($options['show']) {
    cli_writeln("OAuth 2 login issuers (requireconfirmation):");
    foreach ($issuers as $issuer) {
        $state = $issuer->get('requireconfirmation') ? 'ON  (email verification required)'
                                                      : 'OFF (auto-login, no confirmation)';
        cli_writeln(sprintf("  [%d] %-20s %s", $issuer->get('id'), $issuer->get('name'), $state));
    }
    exit(0);
}

$dryrun = (bool) $options['dry-run'];
$changed = 0;
$already = 0;

foreach ($issuers as $issuer) {
    if (!$issuer->get('requireconfirmation')) {
        $already++;
        cli_writeln(sprintf("  [%d] %-20s already OFF", $issuer->get('id'), $issuer->get('name')));
        continue;
    }

    if ($dryrun) {
        cli_writeln(sprintf("  [%d] %-20s would set OFF", $issuer->get('id'), $issuer->get('name')));
        $changed++;
        continue;
    }

    $issuer->set('requireconfirmation', 0);
    $issuer->update();
    $changed++;
    cli_writeln(sprintf("  [%d] %-20s set OFF", $issuer->get('id'), $issuer->get('name')));
}

if ($dryrun) {
    cli_writeln("\nDry run: {$changed} issuer(s) would change, {$already} already off. No changes saved.");
    exit(0);
}

// Persistent updates are reflected on the next request; purge caches to be safe.
if ($changed > 0) {
    purge_all_caches();
}

cli_writeln("\nDone: {$changed} issuer(s) updated, {$already} already off.");
exit(0);
