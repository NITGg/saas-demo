<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class refund_result {
    public bool $success;
    public string $status;
    public string $provider_refund_id;
    public string $gateway_code;
    public float $amount;
    public string $currency;
    public string $error_message;
    public array $raw_response;

    public function __construct(array $data) {
        $this->success = $data['success'] ?? false;
        $this->status = $data['status'] ?? '';
        $this->provider_refund_id = $data['provider_refund_id'] ?? '';
        $this->gateway_code = $data['gateway_code'] ?? '';
        $this->amount = (float) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? '';
        $this->error_message = $data['error_message'] ?? '';
        $this->raw_response = $data['raw_response'] ?? [];
    }
}
