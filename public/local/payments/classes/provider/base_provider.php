<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

abstract class base_provider implements provider_interface {

    protected string $plugin_name;
    protected \stdClass $provider_record;

    public function __construct(\stdClass $provider_record) {
        $this->provider_record = $provider_record;
        $this->plugin_name = $provider_record->plugin_name;
    }

    public function get_name(): string {
        return $this->provider_record->name;
    }

    protected function get_setting(string $key, $default = null) {
        $value = get_config($this->plugin_name, $key);
        return ($value !== false) ? $value : $default;
    }

    protected function get_encrypted_setting(string $key): string {
        $value = get_config($this->plugin_name, $key);
        if ($value === false || $value === '') {
            return '';
        }
        // In Moodle 3.11 there is no \core\encryption — settings stored via
        // admin_setting_configpasswordunmask are plain text in DB.
        return $value;
    }

    protected function is_sandbox(): bool {
        return (bool) $this->get_setting('sandbox_mode', true);
    }

    protected function get_base_url(): string {
        return rtrim($this->get_setting('base_url', ''), '/');
    }

    protected function log(string $level, string $message, array $context = []): void {
        global $DB;
        $DB->insert_record('local_payments_logs', (object) [
            'provider_id' => $this->provider_record->id,
            'transaction_id' => $context['transaction_id'] ?? null,
            'level' => $level,
            'message' => substr($message, 0, 500),
            'context' => json_encode($context),
            'timecreated' => time(),
        ]);
    }

    protected function http_request(string $method, string $url, array $headers = [], $body = null,
            int $timeout = 30): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        foreach ($headers as $key => $value) {
            $curl->setHeader("$key: $value");
        }

        $response = null;
        $jsonbody = ($body !== null) ? json_encode($body) : '';

        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url);
                break;
            case 'POST':
                $curl->setHeader('Content-Type: application/json');
                $response = $curl->post($url, $jsonbody);
                break;
            case 'PUT':
                $curl->setHeader('Content-Type: application/json');
                $response = $curl->put($url, $jsonbody);
                break;
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $decoded = json_decode($response, true);

        return [
            'http_code' => $httpcode,
            'body' => $decoded ?? [],
            'raw' => $response,
            'error' => $curl->get_errno() ? $curl->error : '',
        ];
    }

    public function supports_recurring(): bool {
        return false;
    }
}
