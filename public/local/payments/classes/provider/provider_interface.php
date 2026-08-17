<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

interface provider_interface {

    /**
     * Create a checkout session with the provider.
     *
     * @param payment_request $request
     * @return checkout_response
     */
    public function initialize_payment(payment_request $request): checkout_response;

    /**
     * Verify a payment server-side after callback.
     *
     * @param string $provider_reference Provider session ID or transaction ID.
     * @return verification_result
     */
    public function verify_payment(string $provider_reference): verification_result;

    /**
     * Process an incoming webhook payload.
     *
     * @param string $payload Raw request body.
     * @param array $headers Request headers.
     * @return webhook_result
     */
    public function handle_webhook(string $payload, array $headers): webhook_result;

    /**
     * Refund a completed payment.
     *
     * @param string $provider_order_id
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @return refund_result
     */
    public function refund(string $provider_order_id, float $amount, string $currency, string $reason = ''): refund_result;

    /**
     * Void a payment (full reversal before settlement).
     *
     * @param string $provider_order_id
     * @param string $reason
     * @return refund_result
     */
    public function void_payment(string $provider_order_id, string $reason = ''): refund_result;

    /**
     * Fetch transaction details from provider.
     *
     * @param string $provider_reference
     * @return transaction_info
     */
    public function get_transaction(string $provider_reference): transaction_info;

    public function supports_refund(): bool;
    public function supports_void(): bool;
    public function supports_recurring(): bool;
    public function supported_currencies(): array;
    public function supported_countries(): array;

    /**
     * Get the provider's unique name slug.
     * @return string
     */
    public function get_name(): string;
}
