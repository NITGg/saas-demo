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
use local_nit_finance\config;
use local_nit_finance\entity\earning;
use local_nit_finance\exception\finance_exception;

/**
 * Records and reverses the teacher/platform revenue split for completed lessons.
 *
 * US-FN-1-4 (distribute on complete) and the finance half of US-FN-1-5 (reverse).
 *
 * Money model, all in integer minor units:
 *   teacher_amount  = round(flex_value * teacher_percent / 100)
 *   platform_amount = flex_value - teacher_amount   (platform absorbs the remainder,
 *                                                    so the two always sum to flex_value)
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class earnings_service extends service {
    /**
     * Record the split for a just-completed lesson. Idempotent: if an active earning already
     * exists for the lesson it is returned unchanged.
     *
     * @param int $lessonid
     * @param int $teacherid
     * @param int $studentid
     * @param int $purchaseid the purchase whose Flex was consumed
     * @param int $flexvalueminor value of one Flex in minor units (price_paid_minor / flex_count)
     * @return array the earning summary
     */
    public function distribute(int $lessonid, int $teacherid, int $studentid, int $purchaseid,
            int $flexvalueminor): array {
        $existing = earning::get_record(['lessonid' => $lessonid, 'status' => earning::STATUS_ACTIVE]);
        if ($existing) {
            return self::format($existing);
        }
        if ($flexvalueminor < 0) {
            throw new finance_exception('err_notdistributed');
        }

        $teacherpercent  = config::teacher_percent();
        $platformpercent = config::platform_percent();

        $teacherminor  = (int) round($flexvalueminor * $teacherpercent / 100);
        $platformminor = $flexvalueminor - $teacherminor;

        $earning = new earning(0, (object) [
            'lessonid'              => $lessonid,
            'teacherid'             => $teacherid,
            'studentid'             => $studentid,
            'purchaseid'            => $purchaseid,
            'flex_value_minor'      => $flexvalueminor,
            'teacher_amount_minor'  => $teacherminor,
            'platform_amount_minor' => $platformminor,
            'teacher_percent'       => $teacherpercent,
            'platform_percent'      => $platformpercent,
            'status'                => earning::STATUS_ACTIVE,
        ]);
        $earning->create();
        return self::format($earning);
    }

    /**
     * Reverse the active earning for a lesson (finance side of US-FN-1-5). Returning the Flex to the
     * student and updating the lesson are the caller's responsibility (they own those tables).
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
        $earning = earning::get_record(['lessonid' => $lessonid, 'status' => earning::STATUS_ACTIVE]);
        if (!$earning) {
            if (earning::record_exists_select('lessonid = :lid AND status = :st',
                    ['lid' => $lessonid, 'st' => earning::STATUS_REVERSED])) {
                throw new finance_exception('err_alreadyreversed');
            }
            throw new finance_exception('err_earningnotfound');
        }
        $earning->set('status', earning::STATUS_REVERSED);
        $earning->set('reverse_reason', $reason);
        $earning->set('reversedby', $adminid);
        $earning->set('timereversed', time());
        $earning->update();
        return self::format($earning);
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
