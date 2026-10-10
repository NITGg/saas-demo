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
 * Builds "My lessons & Flex" for a student: book a lesson, my lessons, Flex packages to buy,
 * my Flex, available subscriptions, my subscriptions, my wallet and (teachers) my earnings.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub {

    /**
     * The tabs, in order, by group (see GROUPS): book / my lessons / (teachers) the
     * students' requests, Flex packages to buy / my Flex ("packages" — the key older
     * links use), subscriptions to buy / mine, then the wallet and (teachers) the
     * earnings that used to be pages of their own.
     */
    const TABS = ['book', 'lessons', 'teaching', 'flexavailable', 'packages', 'subavailable', 'mysubs', 'wallet',
        'earnings'];

    /** The tab bar's groups, in order: group key => its tabs. */
    const GROUPS = [
        'lessons' => ['book', 'lessons', 'teaching'],
        'flex' => ['flexavailable', 'packages'],
        'subs' => ['subavailable', 'mysubs'],
        'money' => ['wallet', 'earnings'],
    ];

    /** The tabs that need Flex (local_nit_flex switched on). */
    const FLEX_TABS = ['book', 'lessons', 'teaching', 'flexavailable', 'packages'];

    /** Lesson states waiting for the teacher's answer (the count on the requests tab). */
    const TEACHER_TODO = ['pending', 'waiting_teacher'];

    /**
     * The tabs this user has: no Flex tabs while Flex is switched off, the students'
     * requests for live-lesson teachers and "My earnings" for teachers only.
     *
     * @param int $userid
     * @return string[]
     */
    public static function tabs(int $userid): array {
        $flexon = !function_exists('local_nit_flex_enabled') || local_nit_flex_enabled();
        return array_values(array_filter(self::TABS, static function (string $key) use ($userid, $flexon): bool {
            if (!$flexon && in_array($key, self::FLEX_TABS, true)) {
                return false;
            }
            if ($key === 'teaching') {
                return (new teacher_service())->is_teacher($userid);
            }
            if ($key === 'earnings') {
                return class_exists('\local_nit_finance\local\earnings_page')
                    && \local_nit_finance\local\earnings_page::is_teacher($userid);
            }
            return true;
        }));
    }

    /**
     * The address of a tab.
     *
     * @param string $tab
     * @param array $params more query parameters
     * @return \moodle_url
     */
    public static function url(string $tab, array $params = []): \moodle_url {
        return new \moodle_url('/local/nit_lessons/student.php', ['tab' => $tab] + $params);
    }

    /**
     * Template context for local_nit_lessons/student_hub.
     *
     * @param int $userid
     * @param string $tab one of tabs($userid)
     * @param array $params search, status
     * @return array
     */
    public static function context(int $userid, string $tab, array $params = []): array {
        $tabs = self::tabs($userid);
        $flexon = in_array('packages', $tabs, true);
        $active = $flexon ? purchase::active($userid) : null;
        $ctx = [
            'sesskey' => sesskey(),
            // Where a lesson card's action comes back to (action.php): the requests tab or "My lessons".
            'back' => $tab === 'teaching' ? 'teacher' : 'hub',
            'status' => (string) ($params['status'] ?? ''),
            'actionurl' => (new \moodle_url('/local/nit_lessons/action.php'))->out(false),
            'packagesurl' => self::url('flexavailable')->out(false),
            'hasflex' => $active && $active['remaining_flex'] > 0,
            'stats' => self::stats($userid, $tabs, $active),
            'tabgroups' => [],
        ];
        $ctx['hasstats'] = !empty($ctx['stats']);

        $todo = in_array('teaching', $tabs, true) ? self::teacher_todo($userid) : 0;
        foreach (self::GROUPS as $group => $keys) {
            $items = [];
            foreach (array_intersect($keys, $tabs) as $key) {
                $items[] = [
                    'key' => $key,
                    'label' => get_string('tab_' . $key, 'local_nit_lessons'),
                    'url' => self::url($key)->out(false),
                    'active' => $key === $tab,
                    'count' => $key === 'teaching' && $todo > 0 ? $todo : 0,
                ];
            }
            if ($items) {
                $ctx['tabgroups'][] = [
                    'label' => get_string('tabgroup_' . ($group === 'money' && count($items) > 1 ? 'moneyteacher' : $group),
                        'local_nit_lessons'),
                    'tabs' => $items,
                    'active' => in_array($tab, $keys, true),
                ];
            }
        }
        $ctx['is' . $tab] = true;
        return array_merge($ctx, call_user_func([self::class, 'tab_' . $tab], $userid, $params));
    }

    /**
     * The info bar above the tabs (information only): the Flex left (and the
     * package), the wallet balance and, for teachers, the earnings that can be
     * withdrawn.
     *
     * @param int $userid
     * @param string[] $tabs the user's tabs
     * @param array|null $active the active Flex package (purchase::active())
     * @return array[] [{key, label, value, sub}]
     */
    private static function stats(int $userid, array $tabs, ?array $active): array {
        global $DB;
        $stats = [];
        if (in_array('packages', $tabs, true)) {
            $sub = get_string('nopackage', 'local_nit_lessons');
            if ($active) {
                $sub = format_string((string) $DB->get_field('nit_package', 'name', ['id' => $active['packageid']]))
                    . ' · ' . get_string('flexbooked', 'local_nit_lessons', $active['reserved_flex']);
                if ($active['expires_at'] > 0) {
                    $sub .= ' · ' . get_string('expireson', 'local_nit_flex',
                        userdate($active['expires_at'], get_string('strftimedatefullshort', 'langconfig')));
                }
            }
            $stats[] = ['key' => 'flex', 'label' => get_string('flexavailable', 'local_nit_lessons'),
                'value' => (string) ($active ? $active['remaining_flex'] : 0), 'sub' => $sub];
        }
        if (in_array('wallet', $tabs, true) && class_exists('\local_nit_finance\local\wallets')) {
            $stats[] = ['key' => 'wallet', 'label' => get_string('walletbalance', 'local_nit_flex'),
                'value' => money::format(\local_nit_finance\local\wallets::balance(
                    \local_nit_finance\local\wallets::STUDENT, $userid)), 'sub' => ''];
        }
        if (in_array('earnings', $tabs, true)) {
            $summary = \local_nit_finance\api\wallet::teacher($userid);
            $stats[] = ['key' => 'earnings', 'label' => get_string('tab_earnings', 'local_nit_lessons'),
                'value' => money::format((int) $summary['available_balance_minor']),
                'sub' => get_string('earn_available', 'local_nit_finance')];
        }
        return $stats;
    }

    /**
     * How many of a teacher's lessons wait for their answer.
     *
     * @param int $userid
     * @return int
     */
    public static function teacher_todo(int $userid): int {
        $n = 0;
        foreach (self::TEACHER_TODO as $status) {
            $n += count(lessons::my_lessons($userid, 'teacher', $status));
        }
        return $n;
    }

    /**
     * Tab: the students' requests and the booked lessons of a teacher (what used to
     * be "My live lessons", local/nit_lessons/my_lessons.php).
     *
     * @param int $userid
     * @param array $params status
     * @return array
     */
    private static function tab_teaching(int $userid, array $params): array {
        $status = (string) ($params['status'] ?? '');
        $teachers = new teacher_service();
        $cards = [];
        foreach (lessons::my_lessons($userid, 'teacher', $status) as $lesson) {
            $cards[] = lesson_view::card($lesson, 'teacher');
        }
        return [
            'filter' => lesson_view::filter($status),
            'lessons' => $cards,
            'haslessons' => !empty($cards),
            'notbookable' => !$teachers->bookable($userid),
            'profileurl' => (new \moodle_url('/local/academy/profile.php', null, 'lessons'))->out(false),
        ];
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

    /**
     * Tab: the Flex packages to buy — local_nit_flex's "Available packages" page,
     * drawn in the hub (its buy form still posts to local/nit_flex/packages.php).
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_flexavailable(int $userid, array $params): array {
        global $OUTPUT;
        $url = new \moodle_url('/local/nit_flex/packages.php');
        return ['embedhtml' => $OUTPUT->render_from_template('local_nit_flex/packages_page',
            \local_nit_flex\local\packages_page::context($userid, $url))];
    }

    /**
     * Tab: "My wallet" (local_nit_finance; its forms still post to wallet.php).
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_wallet(int $userid, array $params): array {
        global $OUTPUT;
        return ['embedhtml' => $OUTPUT->render_from_template('local_nit_finance/wallet_page',
            \local_nit_finance\local\wallet_page::context($userid, '/local/nit_lessons/student.php?tab=wallet'))];
    }

    /**
     * Tab: "My earnings", teachers only (local_nit_finance; the withdrawal form still
     * posts to earnings.php).
     *
     * @param int $userid
     * @param array $params
     * @return array
     */
    private static function tab_earnings(int $userid, array $params): array {
        global $OUTPUT;
        $url = new \moodle_url('/local/nit_finance/earnings.php');
        return ['embedhtml' => $OUTPUT->render_from_template('local_nit_finance/earnings_page',
            \local_nit_finance\local\earnings_page::context($userid, $url))];
    }
}
