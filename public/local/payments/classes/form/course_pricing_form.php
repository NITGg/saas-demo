<?php
namespace local_payments\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class course_pricing_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'] ?? 0;
        $priceid = $this->_customdata['priceid'] ?? 0;
        $data = $this->_customdata['data'] ?? null;

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'priceid', $priceid);
        $mform->setType('priceid', PARAM_INT);

        // Country.
        $countries = array_merge(
            ['*' => get_string('defaultprice', 'local_payments')],
            get_string_manager()->get_list_of_countries()
        );
        $mform->addElement('select', 'country', get_string('country', 'local_payments'), $countries);
        $mform->setDefault('country', '*');
        $mform->addRule('country', null, 'required', null, 'client');

        // Currency.
        $currencies = [
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
        $mform->addElement('select', 'currency', get_string('currency', 'local_payments'), $currencies);
        $mform->addRule('currency', null, 'required', null, 'client');

        // Price.
        $mform->addElement('text', 'price', get_string('price', 'local_payments'));
        $mform->setType('price', PARAM_FLOAT);
        $mform->addRule('price', null, 'required', null, 'client');
        $mform->addRule('price', null, 'numeric', null, 'client');

        // Is default. The first price added to a course is the default by default.
        global $DB;
        $hasprices = $DB->record_exists('local_payments_course_prices', ['courseid' => $courseid]);
        $mform->addElement('advcheckbox', 'is_default', get_string('is_default', 'local_payments'));
        if (!$hasprices) {
            $mform->setDefault('is_default', 1);
        }

        // Is active.
        $mform->addElement('advcheckbox', 'is_active', get_string('is_active', 'local_payments'));
        $mform->setDefault('is_active', 1);

        $this->add_action_buttons();

        if ($data) {
            $this->set_data($data);
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['price']) || $data['price'] <= 0) {
            $errors['price'] = get_string('error_price_positive', 'local_payments');
        }

        // Validate only one default per course.
        if (!empty($data['is_default'])) {
            global $DB;
            $existing = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND id != :id',
                ['courseid' => $data['courseid'], 'id' => $data['priceid'] ?? 0]
            );
            if ($existing) {
                $errors['is_default'] = get_string('error_one_default', 'local_payments');
            }
        }

        // Validate only one active rule per country (one active price per country).
        if (!empty($data['is_active'])) {
            global $DB;
            $existing = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND country = :country AND is_active = 1 AND id != :id',
                [
                    'courseid' => $data['courseid'],
                    'country' => $data['country'],
                    'id' => $data['priceid'] ?? 0,
                ]
            );
            if ($existing) {
                $errors['country'] = get_string('error_one_active_per_country', 'local_payments');
            }
        }

        return $errors;
    }
}
