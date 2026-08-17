<?php
namespace local_vdocipher;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * Thin wrapper around the VdoCipher REST API.
 *
 * The API secret lives only here (read from plugin config) and is sent as the
 * "Authorization: Apisecret <key>" header. Nothing in this class is ever exposed
 * to a browser or app — callers get back decoded arrays, or an {@see api_exception}.
 *
 * Endpoints covered (VdoCipher REST v… base https://dev.vdocipher.com/api):
 *   GET    /videos                 list_videos()
 *   GET    /videos/{id}            get_video()
 *   PUT    /videos?title=…         get_upload_credentials()   (returns S3 creds + videoId)
 *   DELETE /videos?videos={ids}    delete_videos()
 *   POST   /videos/{id}/otp        create_otp()               (watermarked playback)
 */
class api_client {

    /** @var string API secret. */
    protected $secret;

    /** @var string API base URL, no trailing slash. */
    protected $base;

    /** @var int Network timeout in seconds. */
    protected $timeout = 30;

    /**
     * @param string|null $secret override secret (defaults to plugin config)
     * @param string|null $base   override base URL (defaults to plugin config)
     * @throws api_exception if no secret is configured
     */
    public function __construct(?string $secret = null, ?string $base = null) {
        $this->secret = $secret ?? (string) get_config('local_vdocipher', 'apisecret');
        $configbase   = $base ?? (string) get_config('local_vdocipher', 'apibase');
        $this->base   = rtrim($configbase ?: 'https://dev.vdocipher.com/api', '/');

        if ($this->secret === '') {
            throw new api_exception(get_string('err_nosecret', 'local_vdocipher'));
        }
    }

    /** @return bool whether an API secret has been configured. */
    public static function is_configured(): bool {
        return trim((string) get_config('local_vdocipher', 'apisecret')) !== '';
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * List videos in the account.
     *
     * @param array $params optional query params (e.g. ['page'=>0,'limit'=>40,'q'=>'term'])
     * @return array decoded response (typically ['rows'=>[…], 'count'=>N])
     */
    public function list_videos(array $params = []): array {
        return $this->request('GET', '/videos', $params);
    }

    /**
     * Fetch a single video's metadata / processing status.
     *
     * @param string $videoid
     * @return array decoded video record (includes 'status': ready|Processing|Queued|…)
     */
    public function get_video(string $videoid): array {
        return $this->request('GET', '/videos/' . rawurlencode($videoid));
    }

    /**
     * Obtain S3 upload credentials for a new video.
     *
     * The caller (server, app or Next dashboard) then POSTs the file bytes
     * straight to the returned 'uploadLink' — the bytes never pass through Moodle.
     *
     * @param string $title video title shown in the VdoCipher dashboard
     * @return array ['videoId'=>…, 'clientPayload'=>['uploadLink'=>…, 'policy'=>…, …]]
     */
    public function get_upload_credentials(string $title): array {
        return $this->request('PUT', '/videos', ['title' => $title]);
    }

    /**
     * Delete one or more videos from VdoCipher.
     *
     * @param string[] $videoids
     * @return array decoded response
     */
    public function delete_videos(array $videoids): array {
        $ids = implode(',', array_map('strval', $videoids));
        return $this->request('DELETE', '/videos', ['videos' => $ids]);
    }

    /**
     * Mint a short-lived playback OTP, optionally with a dynamic watermark.
     *
     * @param string $videoid
     * @param int $ttl seconds the OTP stays valid
     * @param string|null $annotate JSON string for the "annotate" watermark param
     * @return array ['otp'=>…, 'playbackInfo'=>…]
     */
    public function create_otp(string $videoid, int $ttl = 300, ?string $annotate = null): array {
        $body = ['ttl' => $ttl];
        if ($annotate !== null && $annotate !== '') {
            $body['annotate'] = $annotate;
        }
        return $this->request('POST', '/videos/' . rawurlencode($videoid) . '/otp', [], $body);
    }

    /**
     * Upload a local file to VdoCipher end-to-end (credentials → S3 push) and
     * return the new video id. Suitable for server-side upload of modest files;
     * very large videos are better uploaded from the VdoCipher dashboard/app.
     *
     * @param string $filepath absolute path to a readable video file
     * @param string $title    title shown in the dashboard
     * @return string the new VdoCipher video id
     * @throws api_exception on any failure
     */
    public function upload(string $filepath, string $title): string {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new api_exception('Upload file not readable: ' . $filepath);
        }

        $creds   = $this->get_upload_credentials($title);
        $videoid = (string) ($creds['videoId'] ?? '');
        $payload = $creds['clientPayload'] ?? [];
        if ($videoid === '' || empty($payload['uploadLink'])) {
            throw new api_exception('Unexpected upload-credentials response', 0, json_encode($creds));
        }

        // Forward EVERY field VdoCipher signed into the policy (policy, key,
        // x-amz-*, success_action_redirect, …) — cherry-picking a subset drops
        // fields the S3 policy requires and the POST is rejected (HTTP 403).
        // The file part must come last.
        $fields = [];
        foreach ($payload as $k => $v) {
            if ($k === 'uploadLink' || $k === 'file' || !is_scalar($v)) {
                continue;
            }
            $fields[$k] = (string) $v;
        }
        // The policy requires BOTH of these fields present (each starts-with "").
        // redirect empty → S3 falls back to success_action_status (201).
        if (!array_key_exists('success_action_redirect', $fields)) {
            $fields['success_action_redirect'] = '';
        }
        if (!array_key_exists('success_action_status', $fields)) {
            $fields['success_action_status'] = '201';
        }
        $fields['file'] = new \CURLFile(realpath($filepath));

        // Give the request room but never let it hang forever (a hung request
        // is killed by the web server → blank 500). Raise PHP's clock to match.
        \core_php_time_limit::raise(300);

        $ch = curl_init($payload['uploadLink']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new api_exception('S3 upload transport error: ' . $err);
        }
        if (!in_array($code, [200, 201, 204], true)) {
            throw new api_exception('S3 upload rejected', $code, (string) $resp);
        }
        return $videoid;
    }

    // ── Transport ────────────────────────────────────────────────────────────

    /**
     * Perform a signed request and return the decoded JSON body.
     *
     * @param string $method HTTP verb
     * @param string $path   path beginning with '/'
     * @param array $query   query-string params
     * @param array|null $body JSON body (for POST/PUT with a payload)
     * @return array decoded response ([] for an empty 2xx body)
     * @throws api_exception on transport error or non-2xx status
     */
    protected function request(string $method, string $path, array $query = [], ?array $body = null): array {
        $url = $this->base . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $curl = new \curl();
        $headers = [
            'Authorization: Apisecret ' . $this->secret,
            'Accept: application/json',
        ];
        $options = [
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FAILONERROR'    => false,
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }
        $curl->setHeader($headers);

        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, $payload ?? '', $options);
                break;
            case 'PUT':
                $response = $curl->put($url, $payload ?? '', $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new api_exception('Unsupported HTTP method ' . $method);
        }

        // Transport-level failure (DNS, timeout, TLS…).
        if ($curl->get_errno()) {
            throw new api_exception($curl->error ?: 'connection error', 0, (string) $response);
        }

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        if ($httpcode < 200 || $httpcode >= 300) {
            $detail = $this->extract_error($response);
            throw new api_exception($detail, $httpcode, (string) $response);
        }

        if ($response === '' || $response === null) {
            return [];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            // 2xx but non-JSON body — return it wrapped so callers still succeed.
            return ['raw' => (string) $response];
        }
        return $decoded;
    }

    /**
     * Pull a human-readable message out of a VdoCipher error body.
     *
     * @param string|null $response
     * @return string
     */
    protected function extract_error(?string $response): string {
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded)) {
            foreach (['message', 'error', 'msg'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }
        $trimmed = trim((string) $response);
        return $trimmed !== '' ? \core_text::substr($trimmed, 0, 300) : 'unknown error';
    }
}
