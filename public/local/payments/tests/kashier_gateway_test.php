<?php
namespace local_payments;

use local_payments\provider\payment_request;

/**
 * Unit tests for {@see \paymentprovider_kashier\gateway::initialize_payment()}
 * without credentials: Kashier answers an empty key with a bare "HTTP 401",
 * so the gateway names the missing settings instead and makes no request.
 *
 * @package    local_payments
 * @covers     \paymentprovider_kashier\gateway
 */
final class kashier_gateway_test extends \advanced_testcase {

    private function gateway(): \paymentprovider_kashier\gateway {
        return new \paymentprovider_kashier\gateway((object) [
            'id' => 1, 'name' => 'Kashier', 'plugin_name' => 'paymentprovider_kashier',
        ]);
    }

    private function request(): payment_request {
        return new payment_request(['order_id' => 'ORD-1', 'amount' => 100, 'currency' => 'EGP',
            'userid' => 2, 'courseid' => 2, 'transaction_id' => 7]);
    }

    public function test_missing_test_keys_are_named(): void {
        $this->resetAfterTest();
        set_config('payment_mode', 'test', 'paymentprovider_kashier');
        set_config('test_merchant_id', 'MID-1-2', 'paymentprovider_kashier');
        // Live keys do not count in test mode.
        set_config('live_api_key', 'live-key', 'paymentprovider_kashier');

        $response = $this->gateway()->initialize_payment($this->request());

        $this->assertFalse($response->success);
        $this->assertStringContainsString('TEST credentials are not set', $response->error_message);
        $this->assertStringContainsString('test_api_key', $response->error_message);
        $this->assertStringContainsString('test_secret_key', $response->error_message);
        $this->assertStringNotContainsString('test_merchant_id', $response->error_message);
    }

    public function test_missing_live_keys_are_named(): void {
        $this->resetAfterTest();
        set_config('payment_mode', 'live', 'paymentprovider_kashier');

        $response = $this->gateway()->initialize_payment($this->request());

        $this->assertFalse($response->success);
        $this->assertStringContainsString('live_merchant_id, live_api_key, live_secret_key', $response->error_message);
    }
}
