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

namespace local_nit_finance\local;

use local_nit_finance\exception\finance_exception;

/**
 * Buying from a teacher.
 *
 * Every sale — paid from the student wallet or unlocked with a code the
 * student paid for offline — goes through {@see self::grant()}: it records
 * what the student now owns, splits the amount between the teacher (their own
 * percent, or the default) and the platform, credits both wallets, records the
 * teacher's earning (so the existing withdrawal flow sees it) and enrols the
 * student in the course when they were not enrolled yet.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purchases {

    /** Paid from the student wallet. */
    public const METHOD_WALLET = 'wallet';
    /** Unlocked with a code. */
    public const METHOD_CODE = 'code';

    /**
     * Does the student already own this item?
     *
     * @param int $userid
     * @param string $type
     * @param int $itemid
     * @return bool
     */
    public static function owns(int $userid, string $type, int $itemid): bool {
        global $DB;
        return $DB->record_exists('nit_purchase',
            ['userid' => $userid, 'itemtype' => $type, 'itemid' => $itemid, 'status' => 'active']);
    }

    /**
     * Buy one activity with the student wallet.
     *
     * @param int $userid
     * @param int $cmid
     * @return \stdClass the purchase
     */
    public static function buy_with_wallet(int $userid, int $cmid): \stdClass {
        return wallets::locked('student_' . $userid, function () use ($userid, $cmid) {
            global $DB;
            $item = catalog::item(catalog::CM, $cmid);
            if ($item->priceminor <= 0) {
                throw new finance_exception('err_notforsale');
            }
            if (self::owns($userid, $item->type, $item->id) || access::owns_course($userid, $item->courseid)) {
                throw new finance_exception('err_alreadyowned');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $purchase = self::grant($userid, $item, self::METHOD_WALLET, $item->priceminor);
                wallets::move(wallets::STUDENT, $userid, -$item->priceminor, wallets::KIND_PURCHASE,
                    ['itemtype' => $item->type, 'itemid' => $item->id, 'purchaseid' => $purchase->id, 'note' => $item->name]);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            self::enrol_after_payment($userid, $item->courseid);
            return $purchase;
        });
    }

    /**
     * Give the student an item and share its amount between teacher and platform.
     *
     * The caller holds the student's lock and runs inside a transaction, and
     * calls {@see self::ensure_enrolled()} after committing: enrolment sends
     * notifications, which must not be able to undo a payment.
     *
     * @param int $userid the student
     * @param \stdClass $item from catalog::item()
     * @param string $method wallet | code
     * @param int $amountminor what the student paid
     * @param int $codeid the code used, if any
     * @return \stdClass the purchase
     */
    public static function grant(int $userid, \stdClass $item, string $method, int $amountminor, int $codeid = 0): \stdClass {
        global $DB;
        $now = time();
        $teacherid = (int) $item->teacherid;
        $percent = teacher_share::percent_for($teacherid);
        [$teachershare, $platformshare] = teacher_share::split(max(0, $amountminor), $percent);

        // 1 = this purchase is what gets the student into the course (see access::course_state()).
        $enrolled = is_enrolled(\context_course::instance($item->courseid), $userid, '', true) ? 0 : 1;

        $purchase = (object) [
            'userid' => $userid,
            'itemtype' => $item->type,
            'itemid' => $item->id,
            'courseid' => $item->courseid,
            'teacherid' => $teacherid,
            'amount_minor' => max(0, $amountminor),
            'teacher_amount_minor' => $teachershare,
            'platform_amount_minor' => $platformshare,
            'teacher_percent' => $percent,
            'method' => $method,
            'codeid' => $codeid,
            'enrolled' => $enrolled,
            'status' => 'active',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $old = $DB->get_record('nit_purchase', ['userid' => $userid, 'itemtype' => $item->type, 'itemid' => $item->id]);
        if ($old) {
            // Bought again after an admin revoked it.
            $purchase->id = $old->id;
            $DB->update_record('nit_purchase', $purchase);
        } else {
            $purchase->id = $DB->insert_record('nit_purchase', $purchase);
        }

        $ref = ['itemtype' => $item->type, 'itemid' => $item->id, 'purchaseid' => $purchase->id, 'note' => $item->name];
        if ($teachershare > 0) {
            wallets::move(wallets::TEACHER, $teacherid, $teachershare, wallets::KIND_EARNING, $ref);
            // The teacher's earning, as the withdrawal flow counts it.
            $DB->insert_record('nit_earning', (object) [
                'lessonid' => $item->type === catalog::CM ? $item->id : 0,
                'source' => 'cm',
                'teacherid' => $teacherid,
                'studentid' => $userid,
                'purchaseid' => $purchase->id,
                'flex_value_minor' => $purchase->amount_minor,
                'teacher_amount_minor' => $teachershare,
                'platform_amount_minor' => $platformshare,
                'teacher_percent' => $percent,
                'platform_percent' => 100 - $percent,
                'status' => 'active',
                'usermodified' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        if ($platformshare > 0) {
            wallets::move(wallets::PLATFORM, 0, $platformshare, wallets::KIND_EARNING, $ref);
        }
        return $purchase;
    }

    /**
     * Enrol the student in the course (manual enrolment, student role) when
     * they have no active enrolment yet. Called after a purchase is committed,
     * and again by the buy page if an owned item's course is not open to them.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool true when this call enrolled them
     */
    public static function ensure_enrolled(int $userid, int $courseid): bool {
        global $DB;
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid, '', true)) {
            return false;
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            throw new finance_exception('err_noenrol');
        }
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instanceid = $plugin->add_default_instance(get_course($courseid));
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        // Clears a suspended/expired enrolment too.
        $plugin->enrol_user($instance, $userid, $roleid, time(), 0, ENROL_USER_ACTIVE);
        return true;
    }

    /**
     * Enrol after a committed payment. The money is already settled, so a
     * failure here only logs: the buy page retries the enrolment the next
     * time the student opens the item.
     *
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    public static function enrol_after_payment(int $userid, int $courseid): void {
        try {
            self::ensure_enrolled($userid, $courseid);
        } catch (\Throwable $e) {
            debugging('local_nit_finance: enrolment after payment failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    /**
     * A student's purchases, newest first, with item names.
     *
     * @param int $userid
     * @return array
     */
    public static function for_user(int $userid): array {
        global $DB;
        $out = [];
        $rows = $DB->get_records('nit_purchase', ['userid' => $userid, 'status' => 'active'], 'timecreated DESC, id DESC');
        foreach ($rows as $row) {
            try {
                $item = catalog::item($row->itemtype, (int) $row->itemid);
            } catch (\moodle_exception $e) {
                continue; // The activity or course was deleted.
            }
            $out[] = (object) [
                'id' => (int) $row->id,
                'item' => $item,
                'amount_minor' => (int) $row->amount_minor,
                'method' => $row->method,
                'timecreated' => (int) $row->timecreated,
            ];
        }
        return $out;
    }
}
