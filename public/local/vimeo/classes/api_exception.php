<?php
namespace local_vimeo;

defined('MOODLE_INTERNAL') || die();

/**
 * Thrown when a Vimeo REST call fails (transport error or non-2xx status).
 *
 * Carries the HTTP status and raw response body so the diagnostics page and the
 * server logs can show exactly what Vimeo returned.
 */
class api_exception extends \moodle_exception {

    /** @var int HTTP status code (0 for a transport-level failure). */
    public $httpcode;

    /** @var string Raw response body from Vimeo, if any. */
    public $response;

    /**
     * @param string $detail   human-readable detail (transport error or API message)
     * @param int $httpcode    HTTP status, or 0 for a transport failure
     * @param string $response  raw response body
     */
    public function __construct(string $detail, int $httpcode = 0, string $response = '') {
        $this->httpcode = $httpcode;
        $this->response = $response;
        $a = $httpcode ? "HTTP {$httpcode}: {$detail}" : $detail;
        parent::__construct('err_apifailed', 'local_vimeo', '', $a);
    }
}
