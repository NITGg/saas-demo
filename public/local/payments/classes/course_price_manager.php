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
 * Per-course, per-country pricing rules (local_payments_course_prices) for the mobile API —
 * the same list / save / delete rules as course_pricing.php + form\course_pricing_form.
 *
 * Capability checks (local/payments:managecoursepricing in the course) are the caller's job.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_price_manager {

    /** @var string[] currencies offered by the pricing form. */
    const CURRENCIES = ['USD', 'EGP', 'EUR', 'GBP', 'SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR'];

    /**
     * The course record, or err_coursenotfound (the site course is not priceable).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function require_course(int $courseid): \stdClass {
        global $DB;
        $course = ($courseid > 0 && $courseid != SITEID)
            ? $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname') : false;
        if (!$course) {
            throw new \moodle_exception('err_coursenotfound', 'local_payments');
        }
        return $course;
    }

    /**
     * A course's pricing rules, default first then by country (as on course_pricing.php).
     *
     * @param int $courseid
     * @return array {course, prices[], currencies[]}
     */
    public static function get_course_prices(int $courseid): array {
        global $DB;
        $course = self::require_course($courseid);
        $countries = get_string_manager()->get_list_of_countries();
        $prices = [];
        foreach ($DB->get_records('local_payments_course_prices', ['courseid' => $courseid],
                'is_default DESC, country ASC') as $p) {
            $prices[] = self::export($p, $countries);
        }
        return [
            'course' => ['id' => (int) $course->id, 'fullname' => format_string($course->fullname)],
            'prices' => $prices,
            'currencies' => self::CURRENCIES,
        ];
    }

    /**
     * Add (priceid 0) or edit a pricing rule, with the form's validation rules:
     * country '*' or an ISO code, a listed currency, price > 0, one default per course, one
     * active rule per country. The first price of a course is always the default.
     *
     * @param int $courseid
     * @param int $priceid 0 = add
     * @param array $data country, currency, price, is_default, is_active
     * @param int $userid creator (on add)
     * @return array the saved rule
     * @throws \moodle_exception err_coursenotfound|err_pricenotfound|err_invalidcountry|err_invalidcurrency|
     *         err_pricepositive|err_onedefault|err_oneactivepercountry
     */
    public static function save_course_price(int $courseid, int $priceid, array $data, int $userid): array {
        global $DB;
        self::require_course($courseid);
        if ($priceid && !$DB->record_exists('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid])) {
            throw new \moodle_exception('err_pricenotfound', 'local_payments');
        }

        $countries = get_string_manager()->get_list_of_countries();
        $country = strtoupper(trim((string) ($data['country'] ?? '*')));
        if ($country === '') {
            $country = '*';
        }
        if ($country !== '*' && !isset($countries[$country])) {
            throw new \moodle_exception('err_invalidcountry', 'local_payments');
        }
        $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new \moodle_exception('err_invalidcurrency', 'local_payments');
        }
        $price = round((float) ($data['price'] ?? 0), 2);
        if ($price <= 0) {
            throw new \moodle_exception('err_pricepositive', 'local_payments');
        }
        $isdefault = !empty($data['is_default']);
        $isactive = !array_key_exists('is_active', $data) || !empty($data['is_active']);

        if ($isdefault && $DB->record_exists_select('local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND id != :id', ['courseid' => $courseid, 'id' => $priceid])) {
            throw new \moodle_exception('err_onedefault', 'local_payments');
        }
        if ($isactive && $DB->record_exists_select('local_payments_course_prices',
                'courseid = :courseid AND country = :country AND is_active = 1 AND id != :id',
                ['courseid' => $courseid, 'country' => $country, 'id' => $priceid])) {
            throw new \moodle_exception('err_oneactivepercountry', 'local_payments');
        }

        // The first price for a course is always the default.
        $isfirst = !$DB->record_exists('local_payments_course_prices', ['courseid' => $courseid]);
        $record = (object) [
            'courseid' => $courseid,
            'country' => $country,
            'currency' => $currency,
            'price' => $price,
            'is_default' => ($isfirst || $isdefault) ? 1 : 0,
            'is_active' => $isactive ? 1 : 0,
            'timemodified' => time(),
        ];
        if ($priceid) {
            $record->id = $priceid;
            $DB->update_record('local_payments_course_prices', $record);
        } else {
            $record->created_by = $userid;
            $record->timecreated = time();
            $priceid = (int) $DB->insert_record('local_payments_course_prices', $record);
        }
        return self::export($DB->get_record('local_payments_course_prices', ['id' => $priceid], '*', MUST_EXIST),
            $countries);
    }

    /**
     * Delete one pricing rule of a course.
     *
     * @param int $courseid
     * @param int $priceid
     * @return bool
     * @throws \moodle_exception err_coursenotfound|err_pricenotfound
     */
    public static function delete_course_price(int $courseid, int $priceid): bool {
        global $DB;
        self::require_course($courseid);
        if (!$DB->record_exists('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid])) {
            throw new \moodle_exception('err_pricenotfound', 'local_payments');
        }
        $DB->delete_records('local_payments_course_prices', ['id' => $priceid, 'courseid' => $courseid]);
        return true;
    }

    /**
     * One rule as API data.
     *
     * @param \stdClass $p
     * @param array $countries code => name
     * @return array
     */
    protected static function export(\stdClass $p, array $countries): array {
        $sale = ($p->sale_price !== null && $p->sale_price !== '') ? round((float) $p->sale_price, 2) : null;
        return [
            'id'           => (int) $p->id,
            'courseid'     => (int) $p->courseid,
            'country'      => (string) $p->country,
            'country_name' => $p->country === '*' ? get_string('defaultprice', 'local_payments')
                : ($countries[$p->country] ?? (string) $p->country),
            'currency'     => (string) $p->currency,
            'price_minor'  => (int) round((float) $p->price * 100),
            'price'        => round((float) $p->price, 2),
            'sale_price_minor' => $sale === null ? 0 : (int) round($sale * 100),
            'sale_price'   => $sale ?? 0.0,
            'start_date'   => (int) ($p->start_date ?? 0),
            'end_date'     => (int) ($p->end_date ?? 0),
            'is_default'   => (bool) $p->is_default,
            'is_active'    => (bool) $p->is_active,
            'timecreated'  => (int) $p->timecreated,
            'timemodified' => (int) $p->timemodified,
        ];
    }
}
