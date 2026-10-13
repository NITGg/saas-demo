<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\whatsapp;

use local_nit_core\helper\crypto;

/**
 * Meta's WhatsApp Cloud API with this academy's own number: send texts and
 * templates, mark messages read, check the token, link the WhatsApp account to
 * the app, verify webhook signatures and read webhook payloads. The token and
 * the app secret are stored encrypted in the plugin settings and never shown.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloud {
    /** Graph API host. */
    private const GRAPH = 'https://graph.facebook.com';

    /** Cloud API text limit. */
    public const MAX_TEXT = 4096;

    /** Graph API version when none is set. */
    public const DEFAULT_VERSION = 'v23.0';

    /** @var \GuzzleHttp\Client */
    private \GuzzleHttp\Client $http;

    /**
     * Constructor.
     *
     * @param array $cfg ['phonenumberid', 'wabaid', 'token', 'version']
     * @param \GuzzleHttp\Client|null $http tests pass one with a mock handler
     */
    public function __construct(
        /** @var array */
        private array $cfg,
        ?\GuzzleHttp\Client $http = null
    ) {
        $this->http = $http ?? new \core\http_client(['timeout' => 15, 'http_errors' => false]);
    }

    /**
     * The academy's client (\core\di, so tests can replace it), or null until set up.
     *
     * @return self|null
     */
    public static function instance(): ?self {
        return self::config() ? \core\di::get(self::class) : null;
    }

    /**
     * The academy's WhatsApp settings; null until the number id and the token are set.
     *
     * @return array|null
     */
    public static function config(): ?array {
        $phone = trim((string) get_config('local_nit_assistant', 'wa_phonenumberid'));
        $token = trim((string) crypto::config_secret('local_nit_assistant', 'wa_token'));
        if ($phone === '' || $token === '') {
            return null;
        }
        return [
            'phonenumberid' => $phone,
            'wabaid' => trim((string) get_config('local_nit_assistant', 'wa_wabaid')),
            'token' => $token,
            'version' => trim((string) get_config('local_nit_assistant', 'wa_graphversion')) ?: self::DEFAULT_VERSION,
        ];
    }

    /**
     * The phone number id this client sends from.
     *
     * @return string
     */
    public function phone_number_id(): string {
        return (string) $this->cfg['phonenumberid'];
    }

    /**
     * Ask Meta whether the token works for this number (expired temporary tokens are the usual failure).
     *
     * @return array ['ok' => true, 'number', 'name'] or ['ok' => false, 'error']
     */
    public function check_token(): array {
        $r = $this->request('GET', $this->cfg['phonenumberid'] . '?fields=display_phone_number,verified_name');
        return $r['ok'] ? ['ok' => true, 'number' => (string) ($r['json']['display_phone_number'] ?? ''),
            'name' => (string) ($r['json']['verified_name'] ?? '')] : $r;
    }

    /**
     * Apps subscribed to the WhatsApp account (Meta only delivers messages to them).
     *
     * @return array ['ok' => true, 'apps' => [{id, name}]] or ['ok' => false, 'error']
     */
    public function subscription(): array {
        if ($this->cfg['wabaid'] === '') {
            return ['ok' => false, 'error' => 'Set the WhatsApp Business Account ID first.'];
        }
        $r = $this->request('GET', $this->cfg['wabaid'] . '/subscribed_apps');
        if (!$r['ok']) {
            return $r;
        }
        $apps = array_map(static fn($d) => ['id' => (string) ($d['whatsapp_business_api_data']['id'] ?? ''),
            'name' => (string) ($d['whatsapp_business_api_data']['name'] ?? '')], $r['json']['data'] ?? []);
        return ['ok' => true, 'apps' => $apps];
    }

    /**
     * Link the token's app to the WhatsApp account.
     *
     * @return array ['ok' => bool, 'error'?]
     */
    public function subscribe(): array {
        if ($this->cfg['wabaid'] === '') {
            return ['ok' => false, 'error' => 'Set the WhatsApp Business Account ID first.'];
        }
        $r = $this->request('POST', $this->cfg['wabaid'] . '/subscribed_apps');
        return $r['ok'] && ($r['json']['success'] ?? true) !== false ? ['ok' => true] : ($r['ok'] ? ['ok' => false,
            'error' => 'Meta refused'] : $r);
    }

    /**
     * Send a text (split if long).
     *
     * @param string $to E.164
     * @param string $text
     * @return string[] WhatsApp message ids
     * @throws \moodle_exception when Meta refuses
     */
    public function send_text(string $to, string $text): array {
        $ids = [];
        foreach (self::split_text($text) as $part) {
            $r = $this->request('POST', $this->cfg['phonenumberid'] . '/messages', [
                'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => ltrim($to, '+'),
                'type' => 'text', 'text' => ['body' => $part, 'preview_url' => true],
            ]);
            if (!$r['ok']) {
                throw new \moodle_exception('error_wasend', 'local_nit_assistant', '', $r['error']);
            }
            if (!empty($r['json']['messages'][0]['id'])) {
                $ids[] = (string) $r['json']['messages'][0]['id'];
            }
        }
        return $ids;
    }

    /**
     * Send an approved template (needed outside the 24-hour window; hello_world exists on every account).
     *
     * @param string $to E.164
     * @param string $name
     * @param string $lang e.g. en_US, ar
     * @param string[] $params body parameters
     * @return string|null message id
     * @throws \moodle_exception
     */
    public function send_template(string $to, string $name, string $lang, array $params = []): ?string {
        // Meta rejects template parameters with new lines, tabs or 4+ spaces in a row.
        $params = array_map(static fn($p) => \core_text::substr(trim(preg_replace('/\s+/u', ' ', $p)), 0, 1000), $params);
        $template = ['name' => $name, 'language' => ['code' => $lang]];
        if ($params) {
            $template['components'] = [['type' => 'body', 'parameters' => array_map(static fn($t) => ['type' => 'text',
                'text' => $t], $params)]];
        }
        $r = $this->request(
            'POST',
            $this->cfg['phonenumberid'] . '/messages',
            ['messaging_product' => 'whatsapp', 'to' => ltrim($to, '+'), 'type' => 'template', 'template' => $template]
        );
        if (!$r['ok']) {
            throw new \moodle_exception('error_wasend', 'local_nit_assistant', '', $r['error']);
        }
        return $r['json']['messages'][0]['id'] ?? null;
    }

    /**
     * Blue ticks on the user's message. Best-effort.
     *
     * @param string $messageid
     * @return void
     */
    public function mark_read(string $messageid): void {
        $this->request(
            'POST',
            $this->cfg['phonenumberid'] . '/messages',
            ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $messageid]
        );
    }

    /**
     * One Graph API call.
     *
     * @param string $method
     * @param string $path after the version
     * @param array|null $body JSON body
     * @return array ['ok' => true, 'json'] or ['ok' => false, 'error']
     */
    private function request(string $method, string $path, ?array $body = null): array {
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->cfg['token']], 'http_errors' => false, 'timeout' => 15];
        if ($body !== null) {
            $options['json'] = $body;
        }
        try {
            $res = $this->http->request($method, self::GRAPH . '/' . $this->cfg['version'] . '/' . $path, $options);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $json = json_decode((string) $res->getBody(), true) ?: [];
        if ($res->getStatusCode() >= 400 || isset($json['error'])) {
            return ['ok' => false, 'error' => (string) ($json['error']['message'] ?? 'HTTP ' . $res->getStatusCode())];
        }
        return ['ok' => true, 'json' => $json];
    }

    /**
     * Webhook signature: X-Hub-Signature-256 = "sha256=" + HMAC-SHA256(app secret, raw body).
     *
     * @param string $raw
     * @param string|null $header
     * @param string|null $secret default: the stored app secret
     * @return bool
     */
    public static function verify_signature(string $raw, ?string $header, ?string $secret = null): bool {
        $secret ??= (string) crypto::config_secret('local_nit_assistant', 'wa_appsecret');
        if ($secret === '' || $header === null || !str_starts_with($header, 'sha256=')) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $raw, $secret), strtolower(substr($header, 7)));
    }

    /**
     * Meta's wa_id ("201001234567") → "+201001234567".
     *
     * @param string $waid
     * @return string
     */
    public static function to_e164(string $waid): string {
        return '+' . preg_replace('/\D+/', '', $waid);
    }

    /**
     * Long text split on paragraph / line / word boundaries under the limit.
     *
     * @param string $text
     * @param int $max
     * @return string[]
     */
    public static function split_text(string $text, int $max = self::MAX_TEXT): array {
        $parts = [];
        $rest = trim($text);
        while (\core_text::strlen($rest) > $max) {
            $head = \core_text::substr($rest, 0, $max);
            $cut = false;
            foreach (["\n\n", "\n", ' '] as $sep) {
                $pos = \core_text::strrpos($head, $sep);
                if ($pos !== false && $pos >= $max / 2) {
                    $cut = $pos;
                    break;
                }
            }
            $cut = $cut === false ? $max : $cut;
            $parts[] = trim(\core_text::substr($rest, 0, $cut));
            $rest = trim(\core_text::substr($rest, $cut));
        }
        if ($rest !== '') {
            $parts[] = $rest;
        }
        return $parts;
    }

    /**
     * The users' messages in a webhook call (texts, buttons, list replies; other media with text null).
     *
     * @param array $payload decoded JSON
     * @return array list of ['id', 'from', 'name', 'type', 'text', 'time', 'phonenumberid']
     */
    public static function parse_webhook(array $payload): array {
        $out = [];
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return $out;
        }
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $v = $change['value'] ?? [];
                foreach ($v['messages'] ?? [] as $m) {
                    if (empty($m['id']) || empty($m['from'])) {
                        continue;
                    }
                    $name = null;
                    foreach ($v['contacts'] ?? [] as $c) {
                        if (($c['wa_id'] ?? '') === $m['from']) {
                            $name = $c['profile']['name'] ?? null;
                        }
                    }
                    $type = (string) ($m['type'] ?? 'unknown');
                    $text = match ($type) {
                        'text' => (string) ($m['text']['body'] ?? ''),
                        'button' => (string) ($m['button']['text'] ?? ''),
                        'interactive' => (string) ($m['interactive']['button_reply']['title']
                            ?? $m['interactive']['list_reply']['title'] ?? ''),
                        default => null,
                    };
                    $out[] = [
                        'id' => (string) $m['id'],
                        'from' => self::to_e164((string) $m['from']),
                        'name' => $name,
                        'type' => $type,
                        'text' => $text,
                        'time' => (int) ($m['timestamp'] ?? 0) ?: time(),
                        'phonenumberid' => $v['metadata']['phone_number_id'] ?? null,
                    ];
                }
            }
        }
        return $out;
    }
}
