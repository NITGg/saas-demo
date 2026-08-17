<?php
/**
 * Diagnose outgoing email. Prints the mail config and attempts a real send so
 * the exact SMTP/mail error is visible — this is what "Tried to send you an
 * email but failed!" on signup comes down to.
 *
 * Usage:
 *   php local/payments/cli/mail_test.php --to=you@example.com
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options) = cli_get_params(['to' => '', 'help' => false], ['h' => 'help']);

if (!empty($options['help'])) {
    echo "Diagnose outgoing email.\n  --to=EMAIL  send a test message to this address (default: site support/admin)\n";
    exit(0);
}

echo "==== Outgoing mail configuration ====\n";
echo "  smtphosts         = " . (empty($CFG->smtphosts) ? '(none — using PHP mail()/sendmail)' : $CFG->smtphosts) . "\n";
echo "  smtpuser          = " . (empty($CFG->smtpuser) ? '(none)' : $CFG->smtpuser) . "\n";
echo "  smtpsecure        = " . (empty($CFG->smtpsecure) ? '(none)' : $CFG->smtpsecure) . "\n";
echo "  smtpmaxbulk       = " . ($CFG->smtpmaxbulk ?? '(default)') . "\n";
echo "  noemailever       = " . (!empty($CFG->noemailever) ? '1  <<< BLOCKS ALL EMAIL' : '0') . "\n";
echo "  divertallemailsto = " . (empty($CFG->divertallemailsto) ? '(none)' : $CFG->divertallemailsto) . "\n";
echo "  noreplyaddress    = " . ($CFG->noreplyaddress ?? '(none)') . "\n";
echo "  supportemail      = " . ($CFG->supportemail ?? '(none)') . "\n";

// Force the real send error to surface.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

$from = \core_user::get_noreply_user();
$to = get_admin();
if (!empty($options['to'])) {
    $to = clone $to;
    $to->email = $options['to'];
}

echo "\n==== Sending test email to {$to->email} ====\n";
$ok = email_to_user(
    $to,
    $from,
    'Mail test — ' . format_string($SITE->fullname),
    'Plain-text test body.',
    '<p>HTML test body.</p>'
);

echo "\nemail_to_user() returned: " . ($ok ? 'TRUE (accepted for delivery)' : 'FALSE (send FAILED — see error above)') . "\n";
echo "\nIf FALSE with no SMTP host set, Moodle tried local mail()/sendmail which\n";
echo "isn't available in the container — set an SMTP host under Site admin >\n";
echo "Server > Email > Outgoing mail configuration (see the guidance).\n";
