<?php
namespace local_payments\event;

defined('MOODLE_INTERNAL') || die();

class payment_completed extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_payments_transactions';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('event_payment_completed', 'local_payments');
    }

    public function get_description() {
        return "User {$this->userid} completed payment {$this->objectid} for course {$this->other['courseid']}: " .
            "{$this->other['amount']} {$this->other['currency']} via {$this->other['provider']}.";
    }

    public function get_url() {
        return new \moodle_url('/local/payments/history.php');
    }
}
