<?php
/**
 * Set the academy licence from the command line.
 *
 * This is what the future provisioner will call after cloning an academy, and
 * it's handy for testing now.
 *
 *   php local/license/cli/set_license.php --tier=demo --enable
 *   php local/license/cli/set_license.php --tier=basic --enable --days=365
 *   php local/license/cli/set_license.php --expiry=2026-12-31
 *   php local/license/cli/set_license.php --disable
 *   php local/license/cli/set_license.php --show
 *
 * With --tier and --days omitted, --days defaults to the tier's own duration.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_license\license;

list($options, $unrecognized) = cli_get_params(
    [
        'tier'    => '',
        'enable'  => false,
        'disable' => false,
        'days'    => '',
        'expiry'  => '',
        'grace'   => '',
        'show'    => false,
        'help'    => false,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Set the academy licence.\n");
    cli_writeln("  --tier=demo|basic|standard|professional");
    cli_writeln("  --enable | --disable      turn enforcement on/off");
    cli_writeln("  --days=N                  expiry = today + N days (default: tier duration)");
    cli_writeln("  --expiry=YYYY-MM-DD       set an explicit expiry date");
    cli_writeln("  --grace=N                 grace days after expiry");
    cli_writeln("  --show                    print current licence and exit");
    exit(0);
}

if ($options['show']) {
    cli_writeln('enabled    : ' . (license::is_enforced() ? 'yes' : 'no'));
    cli_writeln('tier       : ' . license::tier() . ' (' . license::tiername() . ')');
    cli_writeln('videosource: ' . license::video_source());
    cli_writeln('expiry     : ' . (license::expiry() ? userdate(license::expiry()) : 'never'));
    cli_writeln('days left  : ' . (license::expiry() ? license::days_left() : '∞'));
    cli_writeln('features   : ' . (implode(', ', license::tierdef()['features']) ?: 'none'));
    exit(0);
}

// Tier.
if ($options['tier'] !== '') {
    if (!isset(license::TIERS[$options['tier']])) {
        cli_error('Unknown tier: ' . $options['tier'] . ' (demo|basic|standard|professional)');
    }
    set_config('tier', $options['tier'], 'local_license');
    cli_writeln('tier set to ' . $options['tier']);
}

// Enable / disable.
if ($options['enable']) {
    set_config('enabled', 1, 'local_license');
    cli_writeln('enforcement ENABLED');
}
if ($options['disable']) {
    set_config('enabled', 0, 'local_license');
    cli_writeln('enforcement DISABLED');
}

// Grace.
if ($options['grace'] !== '') {
    set_config('gracedays', (int) $options['grace'], 'local_license');
    cli_writeln('grace days set to ' . (int) $options['grace']);
}

// Expiry: explicit date wins; else --days; else the tier's own duration when a tier was set.
if ($options['expiry'] !== '') {
    set_config('expirydate', $options['expiry'], 'local_license');
    cli_writeln('expiry set to ' . $options['expiry']);
} else if ($options['days'] !== '') {
    $date = date('Y-m-d', time() + ((int) $options['days']) * DAYSECS);
    set_config('expirydate', $date, 'local_license');
    cli_writeln('expiry set to ' . $date . ' (+' . (int) $options['days'] . ' days)');
} else if ($options['tier'] !== '') {
    $days = (int) license::TIERS[$options['tier']]['durationdays'];
    $date = date('Y-m-d', time() + $days * DAYSECS);
    set_config('expirydate', $date, 'local_license');
    cli_writeln('expiry set to ' . $date . ' (tier default ' . $days . ' days)');
}

cli_writeln("\nDone. Current licence:");
cli_writeln('  ' . license::tier() . ' · ' . (license::is_enforced() ? 'enforced' : 'off')
    . ' · expires ' . (license::expiry() ? userdate(license::expiry()) : 'never'));
