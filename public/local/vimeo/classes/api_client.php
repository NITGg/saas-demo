<?php
namespace local_vimeo;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * Thin wrapper around the Vimeo REST API (https://developer.vimeo.com/api).
 *
 * The access token lives only here (read from plugin config) and is sent as the
 * "Authorization: bearer <token>" header. Nothing in this class is ever exposed
 * to a browser or app — callers get back decoded arrays, or an {@see api_exception}.
 *
 * Unlike VdoCipher (S3 form-POST + playback OTP), Vimeo uses:
 *   - resumable "tus" uploads: create the video, then PATCH the bytes to a
 *     pre-signed upload_link (no OTP, no S3 policy);
 *   - domain-private embeds: no per-view token — the video's privacy is set to
 *     embed-whitelist and the academy domain is whitelisted once, at upload.
 *
 * Endpoints covered (base https://api.vimeo.com):
 *   POST   /me/videos                              create_upload()   (tus)
 *   GET    /videos/{id}?fields=…                    get_video()
 *   GET    /me/videos?fields=…                      list_videos()
 *   PUT    /videos/{id}/privacy/domains/{domain}    whitelist_domain()
 *   DELETE /videos/{id}                             delete_video()
 */
class api_client {

    /** @var string Vimeo access token. */
    protected $token;

    /** @var string API base URL, no trailing slash. */
    protected $base;

    /** @var int Network timeout in seconds. */
    protected $timeout = 30;

    /** Default API base URL. */
    const DEFAULT_BASE = 'https://api.vimeo.com';

    /** Vimeo API version pinned in the Accept header. */
    const API_VERSION = '3.4';

    /**
     * @param string|null $token override access token (defaults to plugin config)
     * @param string|null $base  override base URL (defaults to plugin config)
     * @throws api_exception if no access token is configured
     */
    public function __construct(?string $token = null, ?string $base = null) {
        $this->token = $token ?? (string) get_config('local_vimeo', 'access_token');
        $configbase  = $base ?? (string) get_config('local_vimeo', 'apibase');
        $this->base  = rtrim($configbase ?: self::DEFAULT_BASE, '/');

        if ($this->token === '') {
            throw new api_exception(get_string('err_notoken', 'local_vimeo'));
        }
    }

    /** @return bool whether an access token has been configured. */
    public static function is_configured(): bool {
        return trim((string) get_config('local_vimeo', 'access_token')) !== '';
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * List videos in the account (used by diagnostics).
     *
     * @param array $params optional query params (e.g. ['page'=>1,'per_page'=>5])
     * @return array decoded response (typically ['data'=>[…], 'total'=>N])
     */
    public function list_videos(array $params = []): array {
        $params += ['fields' => 'uri,name,duration,transcode.status'];
        return $this->request('GET', '/me/videos', $params);
    }

    /**
     * Fetch a single video's metadata / processing status.
     *
     * @param string $videoid Vimeo numeric id
     * @return array decoded video record (uri,name,duration,transcode.status,privacy)
     */
    public function get_video(string $videoid): array {
        return $this->request('GET', '/videos/' . rawurlencode($videoid), [
            'fields' => 'uri,name,duration,transcode.status,privacy',
        ]);
    }

    /**
     * Create a new video and obtain a resumable (tus) upload link.
     *
     * The caller then PATCHes the file bytes straight to the returned
     * upload_link — the bytes never pass through Moodle. The video is created
     * private (view:disable) with embed:whitelist so only whitelisted domains
     * can play it; call {@see whitelist_domain()} afterwards.
     *
     * @param int $size  exact size of the file to upload, in bytes
     * @param string $title  video title shown in the Vimeo dashboard
     * @return array ['videoid'=>…, 'uri'=>'/videos/…', 'upload_link'=>…, 'raw'=><full body>]
     */
    public function create_upload(int $size, string $title): array {
        $body = [
            'upload'  => ['approach' => 'tus', 'size' => (string) $size],
            'name'    => $title,
            'privacy' => ['view' => 'disable', 'embed' => 'whitelist'],
        ];
        $result = $this->request('POST', '/me/videos', [], $body);

        $uri     = (string) ($result['uri'] ?? '');
        $videoid = $uri !== '' ? basename($uri) : '';
        $link    = (string) ($result['upload']['upload_link'] ?? '');
        if ($videoid === '' || $link === '') {
            throw new api_exception('Unexpected create-video response', 0, json_encode($result));
        }

        return [
            'videoid'     => $videoid,
            'uri'         => $uri,
            'upload_link' => $link,
            'raw'         => $result,
        ];
    }

    /**
     * Add a domain to a video's embed whitelist.
     *
     * Requires the video's privacy.embed to be "whitelist" (set at creation).
     * The domain is the bare host, e.g. "academy.example.com" — no scheme/path.
     *
     * @param string $videoid
     * @param string $domain
     * @return array decoded response ([] on success)
     */
    public function whitelist_domain(string $videoid, string $domain): array {
        $domain = trim($domain);
        if ($domain === '') {
            return [];
        }
        return $this->request('PUT',
            '/videos/' . rawurlencode($videoid) . '/privacy/domains/' . rawurlencode($domain));
    }

    /**
     * Delete a video from Vimeo.
     *
     * @param string $videoid
     * @return array decoded response ([] on success)
     */
    public function delete_video(string $videoid): array {
        return $this->request('DELETE', '/videos/' . rawurlencode($videoid));
    }

    /**
     * Upload a local file to Vimeo end-to-end (create → tus PATCH) and return the
     * new video id. Suitable for server-side upload of modest files; very large
     * videos are better uploaded from the client/app straight to the tus link.
     *
     * @param string $filepath absolute path to a readable video file
     * @param string $title    title shown in the dashboard
     * @return string the new Vimeo video id
     * @throws api_exception on any failure
     */
    public function upload(string $filepath, string $title): string {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new api_exception('Upload file not readable: ' . $filepath);
        }
        $size = (int) filesize($filepath);
        if ($size <= 0) {
            throw new api_exception('Upload file is empty: ' . $filepath);
        }

        $created     = $this->create_upload($size, $title);
        $videoid     = $created['videoid'];
        $uploadlink  = $created['upload_link'];

        $this->tus_patch($uploadlink, $filepath, $size);

        return $videoid;
    }

    // ── tus transport ─────────────────────────────────────────────────────────

    /**
     * PATCH a whole file to a tus upload link in one shot (offset 0).
     *
     * The upload_link is pre-signed by Vimeo, so it carries no Authorization
     * header. Suitable for modest files that fit within the request timeout;
     * chunked/resumable uploads for very large files belong on the client.
     *
     * @param string $uploadlink the tus endpoint from create_upload()
     * @param string $filepath   absolute path to the file
     * @param int $size          exact byte size (matches the size sent at create)
     * @throws api_exception if the server's reported offset != file size
     */
    protected function tus_patch(string $uploadlink, string $filepath, int $size): void {
        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new api_exception('Could not open upload file: ' . $filepath);
        }

        // A big file needs a longer clock; never let it hang forever.
        \core_php_time_limit::raise(600);

        $ch = curl_init($uploadlink);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Tus-Resumable: 1.0.0',
            'Upload-Offset: 0',
            'Content-Type: application/offset+octet-stream',
        ]);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $handle);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 580);

        $response = curl_exec($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrsize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err      = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($err) {
            throw new api_exception('tus upload transport error: ' . $err);
        }
        // tus PATCH returns 204 No Content with an Upload-Offset header on success.
        if ($code < 200 || $code >= 300) {
            throw new api_exception('tus upload rejected', $code, (string) $response);
        }

        $headers = \core_text::substr((string) $response, 0, $hdrsize);
        $offset  = null;
        if (preg_match('/Upload-Offset:\s*(\d+)/i', $headers, $m)) {
            $offset = (int) $m[1];
        }
        if ($offset !== null && $offset !== $size) {
            throw new api_exception(
                "tus upload incomplete: server received {$offset} of {$size} bytes", $code);
        }
    }

    // ── Signed transport ────────────────────────────────────────────────────

    /**
     * Perform an authenticated request and return the decoded JSON body.
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
            'Authorization: bearer ' . $this->token,
            'Accept: application/vnd.vimeo.*+json;version=' . self::API_VERSION,
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
            case 'PATCH':
                $options['CURLOPT_CUSTOMREQUEST'] = 'PATCH';
                $response = $curl->post($url, $payload ?? '', $options);
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
     * Pull a human-readable message out of a Vimeo error body.
     *
     * Vimeo errors look like {"error":"…","developer_message":"…","error_code":N}.
     *
     * @param string|null $response
     * @return string
     */
    protected function extract_error(?string $response): string {
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded)) {
            foreach (['developer_message', 'error', 'message'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    return $decoded[$key];
                }
            }
            if (!empty($decoded['invalid_parameters']) && is_array($decoded['invalid_parameters'])) {
                $parts = [];
                foreach ($decoded['invalid_parameters'] as $p) {
                    $parts[] = ($p['field'] ?? '') . ': ' . ($p['developer_message'] ?? ($p['error'] ?? ''));
                }
                return implode('; ', $parts);
            }
        }
        $trimmed = trim((string) $response);
        return $trimmed !== '' ? \core_text::substr($trimmed, 0, 300) : 'unknown error';
    }
}
