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

namespace local_nit_lessons\local;

use local_nit_finance\local\money;
use local_nit_flex\api\flex;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\service\teacher_service;

/**
 * Builds "My lessons & Flex" for a student: book a lesson, my lessons, packages & Flex,
 * available subscriptions, my subscriptions.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub {

    /** The tabs, in order. */
    const TABS = ['book', 'lessons', 'packages', 'subavailable', 'mysubs'];

    /**
     * Template context for local_nit_lessons/student_hub.
     *
     * @param int $userid
     * @param string $tab
     * @param array $params search, status
     * @return array
     */
    public static function context(int $userid, string $tab, array $params = []): array {
        global $DB;
        $active = purchase::active($userid);
        $ctx = [
            'sesskey' => sesskey(),
            'back' => 'hub',
            'status' => (string) ($params['status'] ?? ''),
            'actionurl' => (new \moodle_url('/local/nit_lessons/action.php'))->out(false),
            'packagesurl' => (new \moodle_url('/local/nit_flex/packages.php'))->out(false),
            'flex' => $active ? $active['remaining_flex'] : 0,
            'hasflex' => $active && $active['remaining_flex'] > 0,
            'package' => $active ? format_string((string) $DB->get_field('nit_package', 'name', ['id' => $active['packageid']]))
                : '',
            'expires' => $active && $active['expires_at'] > 0
                ? userdate($active['expires_at'], get_string('strftimedatefullshort', 'langconfig')) : '',
            'reserved' => $active ? $active['reserved_flex'] : 0,
            'tabs' => [],
        ];
        foreach (self::TABS as $key) {
            $ctx['tabs'][] = [
                'key' => $key,
                'label' => get_string('tab_' . $key, 'local_nit_lessons'),
                'url' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => $key]))->out(false),
                'active' => $key === $tab,
            ];
        }
        $ctx['is' . $tab] = true;
        return array_merge($ctx, call_user_func([self::class, 'tab_' . $tab], $userid, $params));
    }

    /**
     * Tab 1: teachers to book.
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_book(int $userid, array $params): array {
        global $PAGE;
        $search = (string) ($params['search'] ?? '');
        $teachers = [];
        foreach ((new teacher_service())->browse($search) as $t) {
            if ($t['id'] === $userid) {
                continue;
            }
            $picture = new \user_picture($t['user']);
            $picture->size = 100;
            $teachers[] = [
                'id' => $t['id'],
                'name' => $t['fullname'],
                'headline' => format_string($t['headline']),
                'picture' => $picture->get_url($PAGE)->out(false),
                'subjects' => array_map(fn($s) => ['name' => format_string($s), 'value' => $s], $t['subjects']),
                'subjectsjson' => json_encode(array_map(fn($s) => ['name' => format_string($s), 'value' => $s],
                    $t['subjects'])),
                'slots' => json_encode(lesson_view::slots_data($t['id'])),
            ];
        }
        return ['search' => $search, 'teachers' => $teachers, 'hasteachers' => !empty($teachers)];
    }

    /**
     * Tab 2: the student's lessons.
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_lessons(int $userid, array $params): array {
        $status = (string) ($params['status'] ?? '');
        $cards = [];
        foreach (lessons::my_lessons($userid, 'student', $status) as $lesson) {
            $cards[] = lesson_view::card($lesson, 'student');
        }
        return ['filter' => lesson_view::filter($status), 'lessons' => $cards, 'haslessons' => !empty($cards)];
    }

    /**
     * Tab 3: packages, payments and the Flex history.
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_packages(int $userid, array $params): array {
        global $DB;
        $names = $DB->get_records_menu('nit_package', null, '', 'id, name');
        $date = get_string('strftimedatetimeshort', 'langconfig');
        $mine = [];
        foreach (purchase::my_packages($userid) as $p) {
            $mine[] = [
                'name' => format_string($names[$p['packageid']] ?? ''),
                'flex' => get_string('flexusage', 'local_nit_lessons', (object) [
                    'remaining' => $p['remaining_flex'], 'reserved' => $p['reserved_flex'],
                    'used' => $p['consumed_flex'], 'total' => $p['total_flex']]),
                'status' => $p['status'],
                'statuslabel' => get_string('pstat_' . $p['status'], 'local_nit_flex'),
                'paid' => money::format($p['price_paid_minor']),
                'activated' => userdate($p['timeactivated'], $date),
                'expires' => $p['expires_at'] > 0 ? userdate($p['expires_at'], $date)
                    : get_string('neverexpires', 'local_nit_flex'),
            ];
        }
        $payments = [];
        foreach (purchase::payment_history($userid) as $pay) {
            $method = get_string_manager()->string_exists('method_' . $pay['method'], 'local_nit_flex')
                ? get_string('method_' . $pay['method'], 'local_nit_flex') : $pay['method'];
            $payments[] = [
                'date' => userdate($pay['timecreated'], $date),
                'name' => format_string($names[$pay['packageid']] ?? ''),
                'amount' => money::format($pay['amount_minor']),
                'method' => $method,
                'ref' => (string) $pay['transaction_no'],
            ];
        }
        $history = [];
        foreach (flex::history($userid) as $tx) {
            $history[] = [
                'date' => userdate($tx['timecreated'], $date),
                'type' => $tx['type'],
                'typelabel' => get_string('flx_' . $tx['type'], 'local_nit_flex'),
                'change' => ($tx['amount'] > 0 ? '+' : '') . $tx['amount'],
                'balance' => $tx['balance_after'],
                'lesson' => $tx['lessonid'] ? get_string('lessonnum', 'local_nit_lessons', $tx['lessonid']) : '',
            ];
        }
        return [
            'mypackages' => $mine, 'hasmypackages' => !empty($mine),
            'payments' => $payments, 'haspayments' => !empty($payments),
            'history' => $history, 'hashistory' => !empty($history),
        ];
    }

    /**
     * Tab 4: subscription plans to buy (local_nit_subscriptions, paid online).
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_subavailable(int $userid, array $params): array {
        if (!class_exists('\local_nit_subscriptions\subscription_manager')) {
            return ['subs' => [], 'hassubs' => false];
        }
        $hasactive = \local_nit_subscriptions\subscription_purchase_manager::has_active_normal($userid);
        $canonline = method_exists('\local_payments\manager', 'create_subscription_checkout')
            && \local_nit_finance\local\output::online_payment_available();
        $subs = [];
        foreach (\local_nit_subscriptions\subscription_manager::get_subscriptions('active') as $s) {
            $courses = [];
            foreach ($s->courses as $c) {
                $c = (array) $c;
                $courses[] = ['name' => format_string($c['fullname'] ?? '')];
            }
            $subs[] = [
                'id' => (int) $s->id,
                'name' => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->name)),
                'description' => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang(
                    (string) $s->description)),
                'price' => money::format((int) round(((float) $s->price) * 100)),
                'days' => get_string('subdays', 'local_nit_lessons', (int) $s->duration_days),
                'courses' => $courses,
                'disabled' => $hasactive || !$canonline,
            ];
        }
        return ['subs' => $subs, 'hassubs' => !empty($subs), 'subactive' => $hasactive, 'subonline' => $canonline];
    }

    /**
     * Tab 5: the student's subscriptions and their payments.
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_mysubs(int $userid, array $params): array {
        if (!class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            return ['mysubs' => [], 'hasmysubs' => false, 'subpayments' => [], 'hassubpayments' => false];
        }
        $date = get_string('strftimedatefullshort', 'langconfig');
        $mysubs = [];
        foreach (\local_nit_subscriptions\subscription_purchase_manager::get_my_subscriptions($userid) as $s) {
            $mysubs[] = [
                'name' => $s['name'],
                'status' => $s['status'],
                'statuslabel' => get_string('sstat_' . $s['status'], 'local_nit_lessons'),
                'activated' => $s['timeactivated'] ? userdate($s['timeactivated'], $date) : '—',
                'expires' => $s['expires_at'] ? userdate($s['expires_at'], $date) : '—',
                'daysleft' => $s['status'] === 'active' ? $s['remaining_days'] : '—',
                'courses' => implode('، ', array_map(fn($c) => format_string(((array) $c)['fullname'] ?? ''), $s['courses'])),
            ];
        }
        $payments = [];
        foreach (\local_nit_subscriptions\subscription_purchase_manager::get_subscription_payment_history($userid) as $p) {
            $payments[] = [
                'date' => userdate($p['timecreated'], get_string('strftimedatetimeshort', 'langconfig')),
                'name' => $p['name'],
                'amount' => money::format((int) round($p['amount'] * 100)),
                'status' => $p['status'],
                'statuslabel' => get_string_manager()->string_exists('pay_' . $p['status'], 'local_nit_lessons')
                    ? get_string('pay_' . $p['status'], 'local_nit_lessons') : $p['status'],
                'ref' => $p['order_id'],
            ];
        }
        return ['mysubs' => $mysubs, 'hasmysubs' => !empty($mysubs),
            'subpayments' => $payments, 'hassubpayments' => !empty($payments)];
    }
}
