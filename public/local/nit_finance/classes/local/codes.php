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
 * One-time codes, sold by the admin outside the platform.
 *
 * The student pays the admin (cash, transfer…), the admin generates a code for
 * one specific thing — an activity, a whole course, or wallet credit — and the
 * student types the code to get it. Item codes run through the normal sale
 * flow, so the amount the student paid is split between teacher and platform
 * exactly as a wallet purchase would be. Wallet codes only add credit; the
 * split happens later, when that credit is spent.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class codes {

    /** Characters used in codes (no 0/O, 1/I/L look-alikes). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Groups of four characters: XXXX-XXXX-XXXX. */
    private const GROUPS = 3;

    /** Most codes made in one go. */
    public const MAX_BATCH = 500;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_DISABLED = 'disabled';

    /**
     * Make codes for one item.
     *
     * @param string $type cm | course | wallet
     * @param int $itemid activity / course id (0 for wallet)
     * @param int $count how many
     * @param int $amountminor what each code is worth / was paid
     * @param int $timeexpires 0 = never
     * @param string $note admin note (e.g. who bought it)
     * @param int $createdby admin user id
     * @return \stdClass[] the new code rows
     */
    public static function generate(string $type, int $itemid, int $count, int $amountminor, int $timeexpires,
            string $note, int $createdby): array {
        global $DB;
        if ($count < 1 || $count > self::MAX_BATCH) {
            throw new finance_exception('err_codecount', self::MAX_BATCH);
        }
        if ($amountminor < 0 || ($type === catalog::WALLET && $amountminor <= 0)) {
            throw new finance_exception('err_amountpositive');
        }
        $courseid = 0;
        if ($type === catalog::WALLET) {
            $itemid = 0;
        } else {
            $courseid = catalog::item($type, $itemid)->courseid; // Validates the item.
        }
        $batch = 'B' . date('ymdHis') . '-' . random_string(4);
        $now = time();
        $rows = [];
        $transaction = $DB->start_delegated_transaction();
        for ($i = 0; $i < $count; $i++) {
            $row = (object) [
                'code' => self::unique_code(),
                'itemtype' => $type,
                'itemid' => $itemid,
                'courseid' => $courseid,
                'amount_minor' => $amountminor,
                'status' => self::STATUS_ACTIVE,
                'batch' => $batch,
                'note' => \core_text::substr(trim($note), 0, 255),
                'timeexpires' => $timeexpires,
                'createdby' => $createdby,
                'usedby' => 0,
                'timeused' => 0,
                'purchaseid' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $row->id = $DB->insert_record('nit_access_code', $row);
            $rows[] = $row;
        }
        $transaction->allow_commit();
        return $rows;
    }

    /**
     * A new random code not used before.
     *
     * @return string
     */
    private static function unique_code(): string {
        global $DB;
        do {
            $groups = [];
            for ($g = 0; $g < self::GROUPS; $g++) {
                $chunk = '';
                for ($c = 0; $c < 4; $c++) {
                    $chunk .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
                $groups[] = $chunk;
            }
            $code = implode('-', $groups);
        } while ($DB->record_exists('nit_access_code', ['code' => $code]));
        return $code;
    }

    /**
     * A code as the student typed it, in stored form (upper case, dashes).
     *
     * @param string $input
     * @return string '' when it cannot be a code
     */
    public static function normalise(string $input): string {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($clean) !== self::GROUPS * 4) {
            return '';
        }
        return implode('-', str_split($clean, 4));
    }

    /**
     * Use a code.
     *
     * @param int $userid the student
     * @param string $input the code as typed
     * @return array{type:string, item:?\stdClass, amount:int, purchase:?\stdClass}
     */
    public static function redeem(int $userid, string $input): array {
        global $DB;
        $code = self::normalise($input);
        $row = $code === '' ? null : $DB->get_record('nit_access_code', ['code' => $code]);
        if (!$row) {
            throw new finance_exception('err_codeinvalid');
        }
        // One redemption per code at a time, and one purchase per student at a time.
        return wallets::locked('code_' . $row->id, fn() => wallets::locked('student_' . $userid,
            function () use ($userid, $row) {
                global $DB;
                $row = $DB->get_record('nit_access_code', ['id' => $row->id], '*', MUST_EXIST);
                if ($row->status === self::STATUS_USED) {
                    throw new finance_exception('err_codeused');
                }
                if ($row->status !== self::STATUS_ACTIVE) {
                    throw new finance_exception('err_codeinvalid');
                }
                if ($row->timeexpires && $row->timeexpires < time()) {
                    throw new finance_exception('err_codeexpired');
                }

                $item = null;
                if ($row->itemtype !== catalog::WALLET) {
                    $item = catalog::item($row->itemtype, (int) $row->itemid);
                    $owned = purchases::owns($userid, $item->type, $item->id) || access::owns_course($userid, $item->courseid);
                    if ($owned) {
                        // Keep the code for someone else rather than burn it.
                        throw new finance_exception('err_alreadyowned');
                    }
                }

                $transaction = $DB->start_delegated_transaction();
                try {
                    $purchase = null;
                    if ($item) {
                        $purchase = purchases::grant($userid, $item, purchases::METHOD_CODE, (int) $row->amount_minor,
                            (int) $row->id);
                    } else {
                        wallets::move(wallets::STUDENT, $userid, (int) $row->amount_minor, wallets::KIND_TOPUP,
                            ['itemtype' => 'code', 'itemid' => $row->id, 'note' => $row->code]);
                    }
                    $DB->update_record('nit_access_code', (object) [
                        'id' => $row->id,
                        'status' => self::STATUS_USED,
                        'usedby' => $userid,
                        'timeused' => time(),
                        'purchaseid' => $purchase ? $purchase->id : 0,
                        'timemodified' => time(),
                    ]);
                    $transaction->allow_commit();
                } catch (\Throwable $e) {
                    $transaction->rollback($e);
                }
                if ($item) {
                    // After the commit: enrolment notifications must not undo the redemption.
                    purchases::enrol_after_payment($userid, $item->courseid);
                }
                return ['type' => $row->itemtype, 'item' => $item, 'amount' => (int) $row->amount_minor,
                    'purchase' => $purchase];
            }));
    }

    /**
     * Stop an unused code from working.
     *
     * @param int $id
     * @return void
     */
    public static function disable(int $id): void {
        global $DB;
        $DB->set_field_select('nit_access_code', 'status', self::STATUS_DISABLED,
            'id = :id AND status = :active', ['id' => $id, 'active' => self::STATUS_ACTIVE]);
    }
}
