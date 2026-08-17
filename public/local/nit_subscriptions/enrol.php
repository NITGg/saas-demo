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
 * Enrol the current user into a course they can access without buying: a FREE course (no pricing),
 * or a course covered by their active subscription (enrolled until the subscription expires).
 *
 * A paid course NOT covered by a subscription is rejected — the user must buy it.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login();
require_sesskey();

$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

// Already enrolled — just go to the course.
if (is_enrolled($context, $USER->id, '', true)) {
    redirect($courseurl);
}

$haspayments = class_exists('\local_payments\price_resolver');
$free = !$haspayments || !\local_payments\price_resolver::has_pricing($courseid);
$covered = $haspayments
    && class_exists('\local_nit_subscriptions\subscription_purchase_manager')
    && \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $USER->id);

if (!$free && !$covered) {
    // Paid course with no subscription coverage — must be purchased.
    redirect(new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]));
}

// Access end date: for a subscription-covered course, end when the subscription expires; free = no end.
$until = 0;
if ($covered) {
    $active = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($USER->id);
    if ($active) {
        $until = (int) $active->expires_at;
    }
}

\local_nit_subscriptions\subscription_purchase_manager::grant_course_access($courseid, $USER->id, $until);

redirect($courseurl, get_string('enrolled', 'local_nit_subscriptions'), null, \core\output\notification::NOTIFY_SUCCESS);
