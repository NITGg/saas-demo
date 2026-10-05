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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin reporting over local_payments_transactions for the mobile API: the revenue report
 * (same figures as report.php) and a filtered, paged transaction list.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_manager {

    /** @var string[] every transaction status the list can be filtered by. */
    const STATUSES = [
        status_machine::PENDING, status_machine::COMPLETED, status_machine::FAILED,
        status_machine::CANCELLED, status_machine::EXPIRED, status_machine::TIMED_OUT,
        status_machine::REFUNDED, status_machine::PARTIALLY_REFUNDED, status_machine::VOIDED,
        status_machine::CHARGEBACK, status_machine::DUPLICATE,
    ];

    /**
     * A money amount in both minor units (int) and major units (float).
     *
     * @param float|string|null $amount major units as stored (decimal, 2 places)
     * @param string $currency
     * @param string $prefix key prefix, e.g. 'revenue' → revenue_minor / revenue
     * @return array
     */
    public static function money($amount, string $currency, string $prefix = 'amount'): array {
        $major = round((float) $amount, 2);
        return [
            $prefix . '_minor' => (int) round($major * 100),
            $prefix => $major,
            'currency' => $currency,
        ];
    }

    /**
     * The revenue report — the same totals and breakdowns as /local/payments/report.php, with
     * per-currency totals added (the web total sums across currencies).
     *
     * @return array
     */
    public static function get_revenue_report(): array {
        global $DB;
        $completed = ['status' => status_machine::COMPLETED];

        $total = $DB->get_record_sql(
            "SELECT COUNT(*) AS total_transactions, SUM(amount) AS total_revenue
               FROM {local_payments_transactions} WHERE status = :status", $completed);

        $bycurrency = $DB->get_records_sql(
            "SELECT currency, COUNT(*) AS cnt, SUM(amount) AS revenue
               FROM {local_payments_transactions} WHERE status = :status
           GROUP BY currency ORDER BY revenue DESC", $completed);

        $bycountry = $DB->get_recordset_sql(
            "SELECT country, currency, COUNT(*) AS cnt, SUM(amount) AS revenue
               FROM {local_payments_transactions} WHERE status = :status
           GROUP BY country, currency ORDER BY revenue DESC", $completed);

        $byprovider = $DB->get_recordset_sql(
            "SELECT p.id AS providerid, p.display_name, t.currency, COUNT(*) AS cnt, SUM(t.amount) AS revenue
               FROM {local_payments_transactions} t
               JOIN {local_payments_providers} p ON p.id = t.provider_id
              WHERE t.status = :status
           GROUP BY p.id, p.display_name, t.currency ORDER BY revenue DESC", $completed);

        $topcourses = $DB->get_recordset_sql(
            "SELECT c.id AS courseid, c.fullname, t.currency, COUNT(*) AS purchases, SUM(t.amount) AS revenue
               FROM {local_payments_transactions} t
               JOIN {course} c ON c.id = t.courseid
              WHERE t.status = :status
           GROUP BY c.id, c.fullname, t.currency ORDER BY revenue DESC", $completed, 0, 20);

        $out = [
            'total_transactions' => (int) ($total->total_transactions ?? 0),
            // Sum across currencies, as on report.php. Prefer revenue_by_currency for display.
            'total_revenue'      => round((float) ($total->total_revenue ?? 0), 2),
            'revenue_by_currency' => [],
            'failed_count'       => $DB->count_records('local_payments_transactions',
                ['status' => status_machine::FAILED]),
            'refund_count'       => $DB->count_records('local_payments_transactions',
                ['status' => status_machine::REFUNDED]),
            'pending_count'      => $DB->count_records_select('local_payments_transactions',
                'status = :status AND expires_at > :now', ['status' => status_machine::PENDING, 'now' => time()]),
            'by_country'  => [],
            'by_provider' => [],
            'top_courses' => [],
        ];
        foreach ($bycurrency as $r) {
            $out['revenue_by_currency'][] = ['count' => (int) $r->cnt]
                + self::money($r->revenue, (string) $r->currency, 'revenue');
        }
        foreach ($bycountry as $r) {
            $out['by_country'][] = ['country' => (string) $r->country, 'count' => (int) $r->cnt]
                + self::money($r->revenue, (string) $r->currency, 'revenue');
        }
        $bycountry->close();
        foreach ($byprovider as $r) {
            $out['by_provider'][] = ['providerid' => (int) $r->providerid,
                'provider' => format_string($r->display_name), 'count' => (int) $r->cnt]
                + self::money($r->revenue, (string) $r->currency, 'revenue');
        }
        $byprovider->close();
        foreach ($topcourses as $r) {
            $out['top_courses'][] = ['courseid' => (int) $r->courseid, 'course' => format_string($r->fullname),
                'purchases' => (int) $r->purchases]
                + self::money($r->revenue, (string) $r->currency, 'revenue');
        }
        $topcourses->close();
        return $out;
    }

    /**
     * All transactions (every user, every item type), newest first, with optional filters.
     *
     * @param array $filters status (string), userid (int), courseid (int), datefrom / dateto (unix, on timecreated)
     * @param int $page 0-based
     * @param int $perpage
     * @return array {total, page, perpage, transactions[]}
     * @throws \moodle_exception err_invalidstatus
     */
    public static function get_transactions(array $filters, int $page = 0, int $perpage = 20): array {
        global $DB;

        $where = ['1 = 1'];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) {
                throw new \moodle_exception('err_invalidstatus', 'local_payments');
            }
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if (!empty($filters['userid'])) {
            $where[] = 't.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['courseid'])) {
            $where[] = 't.courseid = :courseid';
            $params['courseid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['datefrom'])) {
            $where[] = 't.timecreated >= :datefrom';
            $params['datefrom'] = (int) $filters['datefrom'];
        }
        if (!empty($filters['dateto'])) {
            $where[] = 't.timecreated <= :dateto';
            $params['dateto'] = (int) $filters['dateto'];
        }
        $wheresql = implode(' AND ', $where);

        $total = $DB->count_records_sql("SELECT COUNT(*) FROM {local_payments_transactions} t WHERE $wheresql",
            $params);

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT t.id, t.order_id, t.userid, t.courseid, t.amount, t.original_amount, t.currency, t.status,
                    t.payment_method_type, t.country, t.metadata, t.reject_reason, t.timecreated, t.timemodified,
                    p.name AS provider, p.display_name AS provider_display, c.fullname AS coursename,
                    u.email, $userfields
               FROM {local_payments_transactions} t
          LEFT JOIN {local_payments_providers} p ON p.id = t.provider_id
          LEFT JOIN {course} c ON c.id = t.courseid
          LEFT JOIN {user} u ON u.id = t.userid
              WHERE $wheresql
           ORDER BY t.timecreated DESC, t.id DESC", $params, $page * $perpage, $perpage);

        $list = [];
        foreach ($rows as $r) {
            $meta = json_decode((string) ($r->metadata ?? ''), true) ?: [];
            $itemtype = (string) ($meta['item_type'] ?? ((int) $r->courseid > 0 ? 'course' : ''));
            $currency = (string) $r->currency;
            $list[] = [
                'id'             => (int) $r->id,
                'order_id'       => (string) $r->order_id,
                'userid'         => (int) $r->userid,
                'user_fullname'  => $r->email !== null ? fullname($r) : '',
                'user_email'     => (string) ($r->email ?? ''),
                'item_type'      => $itemtype,
                'item_id'        => (int) ($meta['item_id'] ?? $r->courseid),
                'courseid'       => (int) $r->courseid,
                'course_name'    => $r->coursename !== null ? format_string($r->coursename) : '',
                'item_name'      => self::item_name($itemtype, $meta, $r),
                'amount_minor'   => (int) round((float) $r->amount * 100),
                'amount'         => round((float) $r->amount, 2),
                'original_amount_minor' => (int) round((float) ($r->original_amount ?? $r->amount) * 100),
                'original_amount' => round((float) ($r->original_amount ?? $r->amount), 2),
                'currency'       => $currency,
                'status'         => (string) $r->status,
                'provider'       => (string) ($r->provider ?? ''),
                'provider_display' => $r->provider_display !== null ? format_string($r->provider_display) : '',
                'payment_method' => (string) ($r->payment_method_type ?? ''),
                'country'        => (string) ($r->country ?? ''),
                'coupon_code'    => (string) ($meta['coupon_code'] ?? ''),
                'reject_reason'  => (string) ($r->reject_reason ?? ''),
                'timecreated'    => (int) $r->timecreated,
                'timemodified'   => (int) $r->timemodified,
            ];
        }
        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'transactions' => $list];
    }

    /**
     * A readable name for what a transaction paid for.
     *
     * @param string $itemtype
     * @param array $meta decoded metadata
     * @param \stdClass $row
     * @return string
     */
    protected static function item_name(string $itemtype, array $meta, \stdClass $row): string {
        if ($itemtype === 'course' && $row->coursename !== null) {
            return format_string($row->coursename);
        }
        foreach (['subscription_name', 'package_name', 'course_name', 'name'] as $key) {
            if (!empty($meta[$key]) && is_string($meta[$key])) {
                return format_string($meta[$key]);
            }
        }
        return '';
    }
}
