<?php
namespace paymentprovider_kashier;

use local_payments\provider\base_provider;
use local_payments\provider\payment_request;
use local_payments\provider\checkout_response;
use local_payments\provider\verification_result;
use local_payments\provider\webhook_result;
use local_payments\provider\refund_result;
use local_payments\provider\transaction_info;

defined('MOODLE_INTERNAL') || die();

class gateway extends base_provider {

    private function get_api_key(): string {
        return $this->get_setting('api_key', '');
    }

    private function get_secret_key(): string {
        return $this->get_setting('secret_key', '');
    }

    private function get_merchant_id(): string {
        return $this->get_setting('merchant_id', '');
    }

    private function get_refund_base_url(): string {
        return rtrim($this->get_setting('refund_base_url', 'https://fep.kashier.io'), '/');
    }

    private function get_auth_headers(): array {
        return [
            'Authorization' => $this->get_secret_key(),
            'api-key' => $this->get_api_key(),
        ];
    }

    /**
     * POST /v3/payment/sessions — create a hosted checkout session.
     */
    public function initialize_payment(payment_request $request): checkout_response {
        $base_url = $this->get_base_url() ?: 'https://api.kashier.io';

        $body = [
            'amount' => (string) $request->amount,
            'currency' => $request->currency,
            'display' => $request->display_lang,
            'merchantId' => $this->get_merchant_id(),
            'order' => $request->order_id,
            'type' => 'one-time',
            'allowedMethods' => $this->get_setting('allowed_methods', 'card,wallet'),
            'enable3DS' => (bool) $this->get_setting('enable_3ds', true),
            'serverWebhook' => $request->webhook_url,
            'customer' => [
                'reference' => $request->customer_reference,
                'email' => $request->customer_email,
            ],
            'metaData' => array_merge($request->metadata, [
                'transaction_id' => $request->transaction_id,
                'courseid' => $request->courseid,
                'moodle_order_id' => $request->order_id,
            ]),
        ];

        if (!empty($request->success_url)) {
            $body['merchantRedirect'] = $request->success_url;
        }
        if (!empty($request->failure_url)) {
            $body['failureRedirect'] = true; // Kashier expects a boolean, not a URL
        }

        $max_attempts = (int) $this->get_setting('max_failure_attempts', 3);
        if ($max_attempts > 0) {
            $body['maxFailureAttempts'] = $max_attempts;
        }

        $this->log('info', 'Creating Kashier session', [
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);

        $result = $this->http_request('POST', "{$base_url}/v3/payment/sessions", $this->get_auth_headers(), $body);

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Kashier session creation failed', [
                'http_code' => $result['http_code'],
                'response' => $result['body'],
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure(
                'Kashier session creation failed: HTTP ' . $result['http_code'],
                $result['body']
            );
        }

        $session_id = $result['body']['_id'] ?? '';
        $session_url = $result['body']['sessionUrl'] ?? '';

        if (empty($session_url)) {
            $this->log('error', 'Kashier response missing sessionUrl', [
                'response' => $result['body'],
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure('Missing sessionUrl in Kashier response', $result['body']);
        }

        $this->log('info', 'Kashier session created', [
            'session_id' => $session_id,
            'transaction_id' => $request->transaction_id,
        ]);

        return checkout_response::success($session_url, $session_id, $result['body']);
    }

    /**
     * GET /v3/payment/sessions/:sessionId/payment — verify session payment status.
     */
    public function verify_payment(string $provider_reference): verification_result {
        $base_url = $this->get_base_url() ?: 'https://api.kashier.io';

        $result = $this->http_request(
            'GET',
            "{$base_url}/v3/payment/sessions/{$provider_reference}/payment",
            $this->get_auth_headers()
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Kashier payment verification failed', [
                'session_id' => $provider_reference,
                'http_code' => $result['http_code'],
            ]);
            return new verification_result([
                'verified' => false,
                'error_message' => 'Verification request failed: HTTP ' . $result['http_code'],
                'raw_response' => $result['body'],
            ]);
        }

        $data = $result['body'];
        $status = $data['status'] ?? $data['response']['status'] ?? '';
        $is_success = (strtoupper($status) === 'SUCCESS' || strtoupper($status) === 'CAPTURED');

        return new verification_result([
            'verified' => $is_success,
            'status' => $status,
            'amount' => (float) ($data['amount'] ?? $data['response']['amount'] ?? 0),
            'currency' => $data['currency'] ?? $data['response']['currency'] ?? '',
            'provider_txn_id' => $data['transactionId'] ?? $data['response']['transactionId'] ?? '',
            'provider_order_id' => $data['kashierOrderId'] ?? $data['response']['kashierOrderId'] ?? '',
            'payment_method_type' => $data['method'] ?? '',
            'raw_response' => $data,
        ]);
    }

    /**
     * Verify webhook signature and extract data.
     *
     * Signature algorithm (from Kashier docs + tawreedat implementation):
     * 1. Sort data.signatureKeys alphabetically
     * 2. Build querystring: key1=urlencode(val1)&key2=urlencode(val2)
     * 3. HMAC-SHA256 with API Key
     * 4. Compare hex digest to x-kashier-signature header
     */
    public function handle_webhook(string $payload, array $headers): webhook_result {
        $data = json_decode($payload, true);
        if (empty($data)) {
            return new webhook_result([
                'signature_valid' => false,
                'processed' => false,
                'error_message' => 'Invalid JSON payload',
            ]);
        }

        $signature = $headers['x-kashier-signature'] ?? $headers['X-Kashier-Signature'] ?? '';
        $event_type = $data['event'] ?? '';
        $webhook_data = $data['data'] ?? [];

        $sig_valid = $this->verify_signature($webhook_data, $signature);

        if (!$sig_valid) {
            $this->log('warning', 'Kashier webhook signature verification failed', [
                'event' => $event_type,
                'merchant_order_id' => $webhook_data['merchantOrderId'] ?? '',
            ]);
        }

        return new webhook_result([
            'signature_valid' => $sig_valid,
            'processed' => $sig_valid,
            'event_type' => $event_type,
            'merchant_order_id' => $webhook_data['merchantOrderId'] ?? '',
            'provider_order_id' => $webhook_data['kashierOrderId'] ?? '',
            'provider_txn_id' => $webhook_data['transactionId'] ?? '',
            'order_reference' => $webhook_data['orderReference'] ?? '',
            'status' => $webhook_data['status'] ?? '',
            'amount' => (float) ($webhook_data['amount'] ?? 0),
            'currency' => $webhook_data['currency'] ?? '',
            'payment_method' => $webhook_data['method'] ?? '',
            'channel' => $webhook_data['channel'] ?? '',
            'response_code' => $webhook_data['transactionResponseCode'] ?? '',
            'response_message' => is_array($webhook_data['transactionResponseMessage'] ?? null)
                ? json_encode($webhook_data['transactionResponseMessage'])
                : ($webhook_data['transactionResponseMessage'] ?? ''),
            'card_info' => $webhook_data['card'] ?? [],
            'source_of_funds' => $webhook_data['sourceOfFunds'] ?? [],
            'signature_keys' => $webhook_data['signatureKeys'] ?? [],
            'metadata' => $webhook_data['metaData'] ?? [],
        ]);
    }

    private function verify_signature(array $data, string $signature): bool {
        if (empty($signature) || empty($data['signatureKeys'])) {
            return false;
        }

        try {
            $sorted_keys = $data['signatureKeys'];
            sort($sorted_keys);

            $parts = [];
            foreach ($sorted_keys as $key) {
                $value = $data[$key] ?? '';
                $parts[] = $key . '=' . rawurlencode((string) $value);
            }
            $message = implode('&', $parts);

            $calculated = hash_hmac('sha256', $message, $this->get_api_key());

            return hash_equals($calculated, $signature);
        } catch (\Exception $e) {
            $this->log('error', 'Signature verification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * PUT /v3/orders/:orderId/ with apiOperation=REFUND
     */
    public function refund(string $provider_order_id, float $amount, string $currency, string $reason = ''): refund_result {
        $refund_url = $this->get_refund_base_url() ?: 'https://fep.kashier.io';

        $body = [
            'apiOperation' => 'REFUND',
            'reason' => $reason ?: 'Customer refund',
            'transaction' => [
                'amount' => $amount,
            ],
        ];

        $this->log('info', 'Initiating Kashier refund', [
            'provider_order_id' => $provider_order_id,
            'amount' => $amount,
        ]);

        $result = $this->http_request(
            'PUT',
            "{$refund_url}/v3/orders/{$provider_order_id}/",
            $this->get_auth_headers(),
            $body
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Kashier refund request failed', [
                'provider_order_id' => $provider_order_id,
                'http_code' => $result['http_code'],
                'response' => $result['body'],
            ]);
            return new refund_result([
                'success' => false,
                'error_message' => 'Refund request failed: HTTP ' . $result['http_code'],
                'raw_response' => $result['body'],
            ]);
        }

        $response = $result['body']['response'] ?? [];
        $status = $response['status'] ?? '';
        $success = in_array(strtoupper($status), ['REFUNDED', 'PARTIALLY_REFUNDED']);

        if (!$success) {
            $this->log('error', 'Kashier refund declined', [
                'provider_order_id' => $provider_order_id,
                'gateway_code' => $response['gatewayCode'] ?? '',
                'status' => $status,
            ]);
        }

        return new refund_result([
            'success' => $success,
            'status' => $status,
            'provider_refund_id' => $response['transactionId'] ?? '',
            'gateway_code' => $response['gatewayCode'] ?? '',
            'amount' => (float) ($response['amount'] ?? $amount),
            'currency' => $response['currency'] ?? $currency,
            'error_message' => $success ? '' : "Refund declined: " . ($response['gatewayCode'] ?? $status),
            'raw_response' => $result['body'],
        ]);
    }

    /**
     * PUT /v3/orders/:orderId/ with apiOperation=VOID
     */
    public function void_payment(string $provider_order_id, string $reason = ''): refund_result {
        $refund_url = $this->get_refund_base_url() ?: 'https://fep.kashier.io';

        $body = [
            'apiOperation' => 'VOID',
            'reason' => $reason ?: 'Order cancelled',
        ];

        $this->log('info', 'Initiating Kashier void', ['provider_order_id' => $provider_order_id]);

        $result = $this->http_request(
            'PUT',
            "{$refund_url}/v3/orders/{$provider_order_id}/",
            $this->get_auth_headers(),
            $body
        );

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Kashier void request failed', [
                'provider_order_id' => $provider_order_id,
                'http_code' => $result['http_code'],
            ]);
            return new refund_result([
                'success' => false,
                'error_message' => 'Void request failed: HTTP ' . $result['http_code'],
                'raw_response' => $result['body'],
            ]);
        }

        $response = $result['body']['response'] ?? [];
        $status = $response['status'] ?? '';
        $res_result = $response['result'] ?? '';
        $success = ($res_result === 'SUCCESS' || in_array(strtoupper($status), ['VOIDED', 'CANCELLED']));

        return new refund_result([
            'success' => $success,
            'status' => $status,
            'provider_refund_id' => $response['transactionId'] ?? '',
            'gateway_code' => $response['gatewayCode'] ?? '',
            'amount' => (float) ($response['amount'] ?? 0),
            'currency' => $response['currency'] ?? '',
            'error_message' => $success ? '' : "Void declined: " . ($response['gatewayCode'] ?? $status),
            'raw_response' => $result['body'],
        ]);
    }

    /**
     * GET /v3/payment/sessions/:sessionId/payment
     */
    public function get_transaction(string $provider_reference): transaction_info {
        $result = $this->verify_payment($provider_reference);
        return new transaction_info([
            'found' => !empty($result->provider_txn_id),
            'status' => $result->status,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'provider_txn_id' => $result->provider_txn_id,
            'provider_order_id' => $result->provider_order_id,
            'payment_method_type' => $result->payment_method_type,
            'raw_response' => $result->raw_response,
        ]);
    }

    public function supports_refund(): bool {
        return true;
    }

    public function supports_void(): bool {
        return true;
    }

    public function supported_currencies(): array {
        return ['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR'];
    }

    public function supported_countries(): array {
        return ['EG', 'SA', 'AE', 'KW', 'BH', 'QA', 'OM'];
    }
}
