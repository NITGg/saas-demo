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

namespace local_nit_finance\service;

use local_nit_core\base\service;
use local_nit_finance\entity\earning;
use local_nit_finance\exception\finance_exception;
use local_nit_finance\local\teacher_share;
use local_nit_finance\local\wallets;

/**
 * Records and reverses the teacher/platform split of live lessons paid with a Flex.
 *
 * The money of a Flex package stays undistributed until a lesson uses one Flex:
 *  - completed lesson: the teacher's share (their own percent, or the default) goes to
 *    the teacher wallet and the rest to the platform wallet;
 *  - student absent / late cancel: the Flex is used but the teacher is not paid, so the
 *    whole value goes to the platform wallet;
 *  - admin reversal: both wallet lines are taken back and the earning is marked reversed.
 *
 * Money model, all in integer minor units:
 *   teacher_amount  = round(flex_value * teacher_percent / 100)
 *   platform_amount = flex_value - teacher_amount   (the two always sum to flex_value)
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class earnings_service extends service {
    /**
     * Record the split for a lesson that used a Flex. Idempotent: an active earning for the
     * lesson is returned unchanged.
     *
     * @param int $lessonid nit_lesson id
     * @param int $teacherid
     * @param int $studentid
     * @param int $purchaseid the package purchase the Flex came from
     * @param int $flexvalueminor value of one Flex (price paid / flex count)
     * @param bool $teacherpaid false when the student missed the lesson: the platform keeps it all
     * @return array the earning summary
     */
    public function distribute(int $lessonid, int $teacherid, int $studentid, int $purchaseid,
            int $flexvalueminor, bool $teacherpaid = true): array {
        $existing = earning::get_record(['source' => earning::SOURCE_LESSON, 'lessonid' => $lessonid,
            'status' => earning::STATUS_ACTIVE]);
        if ($existing) {
            return self::format($existing);
        }
        if ($flexvalueminor < 0) {
            throw new finance_exception('err_notdistributed');
        }

        $percent = $teacherpaid ? teacher_share::percent_for($teacherid) : 0;
        [$teacherminor, $platformminor] = teacher_share::split($flexvalueminor, $percent);

        $earning = new earning(0, (object) [
            'lessonid'              => $lessonid,
            'source'                => earning::SOURCE_LESSON,
            'teacherid'             => $teacherid,
            'studentid'             => $studentid,
            'purchaseid'            => $purchaseid,
            'flex_value_minor'      => $flexvalueminor,
            'teacher_amount_minor'  => $teacherminor,
            'platform_amount_minor' => $platformminor,
            'teacher_percent'       => $percent,
            'platform_percent'      => 100 - $percent,
            'status'                => earning::STATUS_ACTIVE,
        ]);
        $earning->create();

        $ref = ['itemtype' => 'lesson', 'itemid' => $lessonid, 'purchaseid' => $purchaseid,
            'note' => get_string('livelessonnote', 'local_nit_finance', $lessonid)];
        if ($teacherminor > 0) {
            wallets::move(wallets::TEACHER, $teacherid, $teacherminor, wallets::KIND_EARNING, $ref);
        }
        if ($platformminor > 0) {
            wallets::move(wallets::PLATFORM, 0, $platformminor, wallets::KIND_EARNING, $ref);
        }
        return self::format($earning);
    }

    /**
     * Reverse the active earning of a lesson and take both wallet shares back. Returning the
     * Flex and updating the lesson are the caller's job (they own those tables).
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason required
     * @return array the reversed amounts
     */
    public function reverse(int $lessonid, int $adminid, string $reason): array {
        $reason = trim($reason);
        if ($reason === '') {
            throw new finance_exception('err_reasonrequired');
        }
        $earning = earning::get_record(['source' => earning::SOURCE_LESSON, 'lessonid' => $lessonid,
            'status' => earning::STATUS_ACTIVE]);
        if (!$earning) {
            if (earning::record_exists_select('source = :src AND lessonid = :lid AND status = :st',
                    ['src' => earning::SOURCE_LESSON, 'lid' => $lessonid, 'st' => earning::STATUS_REVERSED])) {
                throw new finance_exception('err_alreadyreversed');
            }
            throw new finance_exception('err_earningnotfound');
        }
        $earning->set('status', earning::STATUS_REVERSED);
        $earning->set('reverse_reason', $reason);
        $earning->set('reversedby', $adminid);
        $earning->set('timereversed', time());
        $earning->update();

        $ref = ['itemtype' => 'lesson', 'itemid' => $lessonid, 'purchaseid' => (int) $earning->get('purchaseid'),
            'note' => get_string('livelessonreversed', 'local_nit_finance', $lessonid)];
        $teacherminor = (int) $earning->get('teacher_amount_minor');
        $platformminor = (int) $earning->get('platform_amount_minor');
        // The teacher may already have withdrawn it: the balance can go below zero.
        if ($teacherminor > 0) {
            wallets::move(wallets::TEACHER, (int) $earning->get('teacherid'), -$teacherminor,
                wallets::KIND_ADJUSTMENT, $ref, true);
        }
        if ($platformminor > 0) {
            wallets::move(wallets::PLATFORM, 0, -$platformminor, wallets::KIND_ADJUSTMENT, $ref, true);
        }
        return self::format($earning);
    }

    /**
     * Credit the platform with the value of Flex that expired unused.
     *
     * @param int $userid the student
     * @param int $purchaseid
     * @param int $amountminor
     * @return void
     */
    public function expired_flex(int $userid, int $purchaseid, int $amountminor): void {
        if ($amountminor <= 0) {
            return;
        }
        wallets::move(wallets::PLATFORM, 0, $amountminor, wallets::KIND_EARNING, [
            'itemtype' => 'flexexpiry', 'itemid' => $userid, 'purchaseid' => $purchaseid,
            'note' => get_string('flexexpirednote', 'local_nit_finance', $purchaseid),
        ]);
    }

    /**
     * A teacher's earnings (activity sales and live lessons), newest first.
     *
     * @param int $teacherid
     * @param int $limit
     * @return array
     */
    public function for_teacher(int $teacherid, int $limit = 200): array {
        $rows = earning::get_records(['teacherid' => $teacherid], 'timecreated', 'DESC', 0, $limit);
        return array_map([self::class, 'format'], array_values($rows));
    }

    /**
     * Shape an earning entity as a plain array (money exposed as minor units).
     *
     * @param earning $e
     * @return array
     */
    private static function format(earning $e): array {
        return [
            'id'                    => (int) $e->get('id'),
            'lessonid'              => (int) $e->get('lessonid'),
            'source'                => (string) $e->get('source'),
            'teacherid'             => (int) $e->get('teacherid'),
            'studentid'             => (int) $e->get('studentid'),
            'purchaseid'            => (int) $e->get('purchaseid'),
            'flex_value_minor'      => (int) $e->get('flex_value_minor'),
            'teacher_amount_minor'  => (int) $e->get('teacher_amount_minor'),
            'platform_amount_minor' => (int) $e->get('platform_amount_minor'),
            'teacher_percent'       => (int) $e->get('teacher_percent'),
            'platform_percent'      => (int) $e->get('platform_percent'),
            'status'                => $e->get('status'),
            'timecreated'           => (int) $e->get('timecreated'),
        ];
    }
}
