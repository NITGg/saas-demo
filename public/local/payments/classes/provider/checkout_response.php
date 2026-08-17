<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class checkout_response {
    public bool $success;
    public string $checkout_url;
    public string $provider_session_id;
    public string $error_message;
    public array $raw_response;

    public function __construct(bool $success, string $checkout_url = '', string $provider_session_id = '',
            string $error_message = '', array $raw_response = []) {
        $this->success = $success;
        $this->checkout_url = $checkout_url;
        $this->provider_session_id = $provider_session_id;
        $this->error_message = $error_message;
        $this->raw_response = $raw_response;
    }

    public static function success(string $checkout_url, string $session_id, array $raw = []): self {
        return new self(true, $checkout_url, $session_id, '', $raw);
    }

    public static function failure(string $message, array $raw = []): self {
        return new self(false, '', '', $message, $raw);
    }
}
