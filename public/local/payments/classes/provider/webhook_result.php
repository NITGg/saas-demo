<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class webhook_result {
    public bool $signature_valid;
    public bool $processed;
    public string $event_type;
    public string $merchant_order_id;
    public string $provider_order_id;
    public string $provider_txn_id;
    public string $order_reference;
    public string $status;
    public float $amount;
    public string $currency;
    public string $payment_method;
    public string $channel;
    public string $response_code;
    public string $response_message;
    public array $card_info;
    public array $source_of_funds;
    public array $signature_keys;
    public array $metadata;
    public string $error_message;

    public function __construct(array $data) {
        $this->signature_valid = $data['signature_valid'] ?? false;
        $this->processed = $data['processed'] ?? false;
        $this->event_type = $data['event_type'] ?? '';
        $this->merchant_order_id = $data['merchant_order_id'] ?? '';
        $this->provider_order_id = $data['provider_order_id'] ?? '';
        $this->provider_txn_id = $data['provider_txn_id'] ?? '';
        $this->order_reference = $data['order_reference'] ?? '';
        $this->status = $data['status'] ?? '';
        $this->amount = (float) ($data['amount'] ?? 0);
        $this->currency = $data['currency'] ?? '';
        $this->payment_method = $data['payment_method'] ?? '';
        $this->channel = $data['channel'] ?? '';
        $this->response_code = $data['response_code'] ?? '';
        $this->response_message = $data['response_message'] ?? '';
        $this->card_info = $data['card_info'] ?? [];
        $this->source_of_funds = $data['source_of_funds'] ?? [];
        $this->signature_keys = $data['signature_keys'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
        $this->error_message = $data['error_message'] ?? '';
    }
}
