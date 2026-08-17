<?php
namespace local_payments\event;

defined('MOODLE_INTERNAL') || die();

class payment_created extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_payments_transactions';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('event_payment_created', 'local_payments');
    }

    public function get_description() {
        return "User {$this->userid} created payment {$this->objectid}.";
    }
}
