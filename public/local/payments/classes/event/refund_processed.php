<?php
namespace local_payments\event;

defined('MOODLE_INTERNAL') || die();

class refund_processed extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_payments_refunds';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('event_refund_processed', 'local_payments');
    }

    public function get_description() {
        return "Refund {$this->objectid} processed for transaction {$this->other['transaction_id']}.";
    }
}
