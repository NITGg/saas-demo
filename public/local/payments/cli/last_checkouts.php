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
 * Diagnostic: print the most recent payment checkouts with the amount the server actually charged
 * (after offer + coupon) vs the original price. Use this to confirm whether a coupon/offer was
 * applied at checkout — the `amount` column is exactly what Kashier is told to charge.
 *
 * Usage (from the Moodle root, or via docker):
 *   php local/payments/cli/last_checkouts.php
 *   php local/payments/cli/last_checkouts.php --limit=20
 *   php local/payments/cli/last_checkouts.php --order=PAY-2026-00012345
 *   php local/payments/cli/last_checkouts.php --user=42
 *
 * Docker (same style as the deploy commands):
 *   docker compose exec moodle php local/payments/cli/last_checkouts.php
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
        'help'  => false,
        'limit' => 10,
        'order' => '',
        'user'  => 0,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    echo "Show recent payment checkouts and the charged (post-discount) amount.\n\n";
    echo "Options:\n";
    echo "  --limit=N     How many rows to show (default 10).\n";
    echo "  --order=ID    Show only this order id (e.g. PAY-2026-00012345).\n";
    echo "  --user=ID     Show only this Moodle user id.\n";
    echo "  -h, --help    This help.\n\n";
    echo "Read the 'amount' vs 'original' columns: amount < original means a discount WAS applied.\n";
    exit(0);
}

$conditions = [];
$params = [];
if ($options['order'] !== '') {
    $conditions[] = 'order_id = :order';
    $params['order'] = $options['order'];
}
if ((int) $options['user'] > 0) {
    $conditions[] = 'userid = :userid';
    $params['userid'] = (int) $options['user'];
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
$limit = max(1, (int) $options['limit']);

$rows = $DB->get_records_sql(
    "SELECT * FROM {local_payments_transactions} {$where} ORDER BY id DESC",
    $params,
    0,
    $limit
);

if (!$rows) {
    echo "No transactions found.\n";
    exit(0);
}

// Header.
printf("%-5s  %-13s  %-8s  %-10s  %-10s  %-9s  %-12s  %-14s  %s\n",
    'id', 'item', 'itemid', 'amount', 'original', 'saved', 'status', 'coupon', 'when');
echo str_repeat('-', 110) . "\n";

foreach ($rows as $r) {
    $meta = json_decode($r->metadata ?? '{}');
    $itemtype = $meta->item_type ?? ($r->courseid ? 'course' : '?');
    $itemid   = $meta->item_id ?? $r->courseid;
    $coupon   = $meta->coupon_code ?? '';
    $original = (float) ($r->original_amount ?? $r->amount);
    $amount   = (float) $r->amount;
    $saved    = round($original - $amount, 2);

    printf("%-5d  %-13s  %-8s  %-10s  %-10s  %-9s  %-12s  %-14s  %s\n",
        $r->id,
        $itemtype,
        (string) $itemid,
        number_format($amount, 2),
        number_format($original, 2),
        number_format($saved, 2),
        $r->status,
        ($coupon !== '' ? $coupon : '(none)'),
        userdate($r->timecreated, '%Y-%m-%d %H:%M'));
}

echo "\nReading it:\n";
echo "  • amount  = what the server told Kashier to charge (this is the price on the Kashier screen).\n";
echo "  • saved>0 = a coupon/offer WAS applied. saved=0 = full price (no discount reached checkout).\n";
