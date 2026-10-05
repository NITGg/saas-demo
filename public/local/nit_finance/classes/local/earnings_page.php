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

use local_nit_finance\api\wallet;
use local_nit_finance\entity\earning;

/**
 * Builds the "My earnings" page for a teacher.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class earnings_page {

    /** Payout methods a teacher can ask for. */
    public const METHODS = ['bank', 'wallet', 'cash'];

    /**
     * Teachers (any course) and anyone who already earned money see the page.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_teacher(int $userid): bool {
        global $DB;
        if (class_exists('\local_academy\teacher_manager') && \local_academy\teacher_manager::is_teacher($userid)) {
            return true;
        }
        return $DB->record_exists('nit_earning', ['teacherid' => $userid])
            || $DB->record_exists('nit_wallet', ['ownertype' => wallets::TEACHER, 'userid' => $userid]);
    }

    /**
     * Template context for local_nit_finance/earnings_page.
     *
     * @param int $userid
     * @param \moodle_url $url the page, for the withdrawal form
     * @return array
     */
    public static function context(int $userid, \moodle_url $url): array {
        $summary = wallet::teacher($userid);
        $cards = [];
        foreach ([
            'earn_available' => $summary['available_balance_minor'],
            'earn_total' => $summary['total_earned_minor'],
            'earn_pending' => $summary['pending_withdrawals_minor'],
            'earn_withdrawn' => $summary['total_withdrawn_minor'],
        ] as $key => $minor) {
            $cards[] = [
                'label' => get_string($key, 'local_nit_finance'),
                'amount' => money::format((int) $minor),
                'main' => $key === 'earn_available',
                'negative' => $minor < 0,
            ];
        }

        $methods = [];
        foreach (self::METHODS as $method) {
            $methods[] = ['value' => $method, 'label' => get_string('earn_method_' . $method, 'local_nit_finance')];
        }

        $withdrawals = [];
        foreach (wallet::teacher_withdrawals($userid) as $w) {
            $note = $w['status'] === 'rejected' ? (string) $w['reason'] : (string) $w['reference'];
            $withdrawals[] = [
                'date' => userdate($w['timecreated'], get_string('strftimedatetimeshort', 'langconfig')),
                'amount' => money::format($w['amount_minor']),
                'method' => get_string('earn_method_' . (in_array($w['method'], self::METHODS, true) ? $w['method'] : 'bank'),
                    'local_nit_finance'),
                'status' => $w['status'],
                'statuslabel' => get_string('status_' . $w['status'], 'local_nit_finance'),
                'note' => $note,
            ];
        }

        $earnings = [];
        foreach (wallet::teacher_earnings($userid) as $e) {
            $earnings[] = [
                'date' => userdate($e['timecreated'], get_string('strftimedatetimeshort', 'langconfig')),
                'item' => self::item_label($e),
                'student' => self::user_name($e['studentid']),
                'value' => money::format($e['flex_value_minor']),
                'share' => get_string('earn_sharevalue', 'local_nit_finance', (object) [
                    'amount' => money::format($e['teacher_amount_minor']),
                    'percent' => $e['teacher_percent'],
                ]),
                'status' => $e['status'],
                'statuslabel' => get_string('earn_status_' . $e['status'], 'local_nit_finance'),
            ];
        }

        return [
            'cards' => $cards,
            'action' => $url->out(false),
            'sesskey' => sesskey(),
            'methods' => $methods,
            'canwithdraw' => $summary['available_balance_minor'] > 0,
            'available' => money::to_major(max(0, (int) $summary['available_balance_minor'])),
            'withdrawals' => $withdrawals,
            'haswithdrawals' => !empty($withdrawals),
            'earnings' => $earnings,
            'hasearnings' => !empty($earnings),
        ];
    }

    /**
     * What an earning was for: the activity sold, or the live lesson and its subject.
     *
     * @param array $e formatted earning
     * @return string
     */
    private static function item_label(array $e): string {
        global $DB;
        if ($e['source'] === earning::SOURCE_LESSON) {
            $subject = $DB->get_manager()->table_exists('nit_lesson')
                ? (string) $DB->get_field('nit_lesson', 'subject', ['id' => $e['lessonid']]) : '';
            $label = get_string('earn_source_lesson', 'local_nit_finance', $e['lessonid']);
            return $subject !== '' ? $label . ' · ' . format_string($subject) : $label;
        }
        try {
            return catalog::item(catalog::CM, (int) $e['lessonid'])->name;
        } catch (\moodle_exception $ex) {
            return get_string('itemtype_cm', 'local_nit_finance');
        }
    }

    /**
     * A user's full name, or a dash.
     *
     * @param int $userid
     * @return string
     */
    private static function user_name(int $userid): string {
        $user = $userid ? \core_user::get_user($userid) : null;
        return $user && !$user->deleted ? fullname($user) : '—';
    }
}
