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

namespace local_payments\local\hooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Course edit-form hook callbacks: a two-price pricing accordion embedded directly
 * in the course create/edit settings form.
 *
 * The whole feature rides on core_course's form hooks — no Moodle core is touched.
 * The two prices are stored in the SAME table the resolver already reads
 * (local_payments_course_prices): one row for the admin "default country" and one
 * "*" fallback row for every other country. The price_resolver picks the
 * country-specific row for a buyer in the default country and falls back to the
 * default ("*") row for everyone else, so no resolver change is needed either.
 *
 * @package    local_payments
 */
class course_form {

    /** The 10 currencies offered, matching the standalone pricing form. */
    private static function currencies(): array {
        return [
            'USD' => 'USD - US Dollar',
            'EGP' => 'EGP - Egyptian Pound',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'SAR' => 'SAR - Saudi Riyal',
            'AED' => 'AED - UAE Dirham',
            'KWD' => 'KWD - Kuwaiti Dinar',
            'BHD' => 'BHD - Bahraini Dinar',
            'QAR' => 'QAR - Qatari Riyal',
            'OMR' => 'OMR - Omani Rial',
        ];
    }

    /** The admin "default country" (ISO alpha-2, uppercased), the home-price country. */
    private static function default_country(): string {
        $cc = strtoupper((string) get_config('local_payments', 'default_country'));
        $cc = preg_replace('/[^A-Z]/', '', $cc);
        return substr($cc !== '' ? $cc : 'EG', 0, 2);
    }

    /**
     * The course id for the form (0/1 for a new/site course). Read from the form
     * wrapper's course object — the hidden 'id' element is added by core only AFTER
     * the after_form_definition hook fires, so it can't be relied on here.
     */
    private static function courseid(\course_edit_form $wrapper): int {
        $course = $wrapper->get_course();
        return (int) ($course->id ?? 0);
    }

    /**
     * Context to check the pricing capability against: the course context for an
     * existing course, else the target category context for a new one, else system.
     */
    private static function pricing_context(\course_edit_form $wrapper): \context {
        $course = $wrapper->get_course();
        $courseid = (int) ($course->id ?? 0);
        if ($courseid > 1) {
            return \context_course::instance($courseid);
        }
        $catid = (int) ($course->category ?? 0);
        if ($catid > 0) {
            try {
                return \context_coursecat::instance($catid);
            } catch (\dml_missing_record_exception $e) {
                // Fall through to system context.
            }
        }
        return \context_system::instance();
    }

    /**
     * Add the pricing accordion to the course settings form.
     *
     * @param \core_course\hook\after_form_definition $hook
     */
    public static function after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $mform = $hook->mform;

        if (!has_capability('local/payments:managecoursepricing', self::pricing_context($hook->formwrapper))) {
            return;
        }

        $homecc = self::default_country();
        $countrynames = get_string_manager()->get_list_of_countries();
        $homename = $countrynames[$homecc] ?? $homecc;

        $homecur = $homecc === 'EG' ? 'EGP' : ((string) get_config('local_payments', 'default_currency') ?: 'USD');
        $othercur = (string) get_config('local_payments', 'default_currency') ?: 'USD';

        // Collapsible header == the "accordion" (collapsed by default).
        $mform->addElement('header', 'lp_pricing_hdr', get_string('coursepricing', 'local_payments'));
        $mform->setExpanded('lp_pricing_hdr', false);

        $mform->addElement('static', 'lp_pricing_desc', '', get_string('pricing_section_desc', 'local_payments'));

        // Paid toggle — unticked means the course is free (no active price rows).
        $mform->addElement('advcheckbox', 'lp_paid', get_string('paidcourse', 'local_payments'), '', [], [0, 1]);
        $mform->setDefault('lp_paid', 0);

        // Default-country price.
        $mform->addElement('text', 'lp_price_home',
            get_string('price_home', 'local_payments', $homename), ['size' => 10]);
        $mform->setType('lp_price_home', PARAM_FLOAT);
        $mform->disabledIf('lp_price_home', 'lp_paid', 'notchecked');

        $mform->addElement('select', 'lp_currency_home',
            get_string('currency_home', 'local_payments', $homename), self::currencies());
        $mform->setDefault('lp_currency_home', $homecur);
        $mform->disabledIf('lp_currency_home', 'lp_paid', 'notchecked');

        // Other-countries price.
        $mform->addElement('text', 'lp_price_other',
            get_string('price_other', 'local_payments'), ['size' => 10]);
        $mform->setType('lp_price_other', PARAM_FLOAT);
        $mform->disabledIf('lp_price_other', 'lp_paid', 'notchecked');

        $mform->addElement('select', 'lp_currency_other',
            get_string('currency_other', 'local_payments'), self::currencies());
        $mform->setDefault('lp_currency_other', $othercur);
        $mform->disabledIf('lp_currency_other', 'lp_paid', 'notchecked');
    }

    /**
     * Prefill the fields from the two existing rows when editing a course.
     *
     * @param \core_course\hook\after_form_definition_after_data $hook
     */
    public static function after_form_definition_after_data(
            \core_course\hook\after_form_definition_after_data $hook): void {
        global $DB;

        $mform = $hook->mform;
        // On a validation-failed redisplay the form is submitted — keep what the user typed.
        if ($mform->isSubmitted() || !$mform->elementExists('lp_paid')) {
            return;
        }

        $courseid = self::courseid($hook->formwrapper);
        if ($courseid <= 1) {
            return;
        }

        $homecc = self::default_country();
        $home = $DB->get_record('local_payments_course_prices',
            ['courseid' => $courseid, 'country' => $homecc, 'is_active' => 1], '*', IGNORE_MULTIPLE);
        $other = $DB->get_record('local_payments_course_prices',
            ['courseid' => $courseid, 'country' => '*', 'is_active' => 1], '*', IGNORE_MULTIPLE);

        $mform->getElement('lp_paid')->setValue(($home || $other) ? 1 : 0);
        if ($home) {
            $mform->getElement('lp_price_home')->setValue($home->price);
            $mform->getElement('lp_currency_home')->setValue($home->currency);
        }
        if ($other) {
            $mform->getElement('lp_price_other')->setValue($other->price);
            $mform->getElement('lp_currency_other')->setValue($other->currency);
        }
    }

    /**
     * Validate the prices when the course is marked paid.
     *
     * @param \core_course\hook\after_form_validation $hook
     */
    public static function after_form_validation(\core_course\hook\after_form_validation $hook): void {
        $data = $hook->get_data();
        if (empty($data['lp_paid'])) {
            return; // Free course — nothing to validate.
        }
        $errors = [];
        foreach (['lp_price_home', 'lp_price_other'] as $field) {
            $v = $data[$field] ?? '';
            if ($v === '' || !is_numeric($v) || (float) $v <= 0) {
                $errors[$field] = get_string('error_price_positive', 'local_payments');
            }
        }
        if ($errors) {
            $hook->add_errors($errors);
        }
    }

    /**
     * Persist the two prices after the course is saved (create or update).
     *
     * @param \core_course\hook\after_form_submission $hook
     */
    public static function after_form_submission(\core_course\hook\after_form_submission $hook): void {
        global $DB, $USER;

        $data = $hook->get_data();
        // Our fields are only present when the section was rendered (capability check passed).
        if (!isset($data->lp_paid)) {
            return;
        }
        $courseid = (int) ($data->id ?? 0);
        if ($courseid <= 1) {
            return;
        }
        // Re-check capability on the now-existing course context (defence in depth).
        if (!has_capability('local/payments:managecoursepricing', \context_course::instance($courseid))) {
            return;
        }

        $now = time();

        // Unticked → make the course free: deactivate every active price row.
        if (empty($data->lp_paid)) {
            $DB->set_field('local_payments_course_prices', 'is_active', 0,
                ['courseid' => $courseid, 'is_active' => 1]);
            return;
        }

        $upsert = function (string $country, float $price, string $currency, int $isdefault)
                use ($DB, $courseid, $now, $USER): int {
            $existing = $DB->get_record('local_payments_course_prices',
                ['courseid' => $courseid, 'country' => $country], '*', IGNORE_MULTIPLE);
            if ($existing) {
                $existing->price = $price;
                $existing->currency = $currency;
                $existing->is_active = 1;
                $existing->is_default = $isdefault;
                $existing->timemodified = $now;
                $DB->update_record('local_payments_course_prices', $existing);
                return (int) $existing->id;
            }
            return (int) $DB->insert_record('local_payments_course_prices', (object) [
                'courseid' => $courseid,
                'country' => $country,
                'currency' => $currency,
                'price' => $price,
                'is_default' => $isdefault,
                'is_active' => 1,
                'priority' => 0,
                'created_by' => (int) $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        };

        // Home-country row (country-specific) + "*" fallback row (the default, is_default=1).
        $homeid = $upsert(self::default_country(), (float) $data->lp_price_home,
            (string) $data->lp_currency_home, 0);
        $otherid = $upsert('*', (float) $data->lp_price_other,
            (string) $data->lp_currency_other, 1);

        // "Two prices only": retire (deactivate) any other rows — e.g. legacy per-country
        // rows from the old multi-country page — and clear stray default flags, so exactly
        // these two govern the price. Non-destructive: rows are kept, just is_active=0.
        $DB->execute(
            "UPDATE {local_payments_course_prices}
                SET is_active = 0, is_default = 0, timemodified = ?
              WHERE courseid = ? AND id NOT IN (?, ?)",
            [$now, $courseid, $homeid, $otherid]
        );
    }
}
