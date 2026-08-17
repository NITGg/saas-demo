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
 * Lightweight JSON endpoint used by js/course_cards.js to fetch country-resolved
 * pricing for a batch of courses shown on a catalog/listing page. Read-only,
 * public data — no capability gate. Returns { status, labels, data:{id:card_context} }.
 *
 * @package    local_payments
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

$ids = optional_param('ids', '', PARAM_SEQUENCE);
$data = [];

if ($ids !== '') {
    global $USER;
    $uid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
    $courseids = array_unique(array_filter(array_map('intval', explode(',', $ids))));
    foreach ($courseids as $cid) {
        try {
            // Only courses with an active pricing rule get a badge.
            if (!\local_payments\price_resolver::has_pricing($cid)) {
                continue;
            }
            $data[$cid] = \local_payments\price_resolver::card_context($cid, $uid);
        } catch (\Throwable $e) {
            // Skip any course we cannot price.
            continue;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'labels' => [
        'purchased' => get_string('already_purchased', 'local_payments'),
        'enrolled'  => get_string('already_enrolled', 'local_payments'),
    ],
    'data' => $data,
]);
