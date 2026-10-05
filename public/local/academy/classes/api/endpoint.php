<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_academy\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared plumbing for every token-authenticated mobile JSON endpoint
 * (`/local/<plugin>/api.php`). One envelope, one auth, one error vocabulary:
 *
 *   GET|POST /local/<plugin>/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<stable code>"}
 *
 * HTTP 401 is reserved for a missing/dead token (authrequired / invalidtoken) so
 * the app can end the session; HTTP 403 for a suspended / expired academy
 * (siteunavailable). Everything else is HTTP 200.
 *
 * Usage in an api.php:
 *
 *     define('NO_MOODLE_COOKIES', true);
 *     require(__DIR__ . '/../../config.php');
 *     use local_academy\api\endpoint as api;
 *     api::boot();
 *     $user = api::authenticate();
 *     api::run(function (string $function) use ($user) {
 *         switch ($function) {
 *             case 'x': return [...];
 *         }
 *         return api::unknown();
 *     });
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class endpoint {

    /** @var string the raw token of the current request ('' before authenticate()). */
    protected static $token = '';

    /**
     * Start the response: JSON content type, no caching, buffer stray output.
     */
    public static function boot(): void {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        ob_start();
    }

    /**
     * Emit a JSON payload and stop, dropping any stray output first.
     *
     * @param array $payload
     * @param int $http
     */
    public static function emit(array $payload, int $http = 200): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($http);
        }
        // PRESERVE_ZERO_FRACTION: a float stays a float (4.0, not 4) so typed
        // clients (Dart/Kotlin/Swift) always get the same JSON type for a field.
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    /**
     * Successful response.
     *
     * @param mixed $data
     */
    public static function success($data): void {
        self::emit(['status' => 'success', 'data' => $data]);
    }

    /**
     * Failure response.
     *
     * @param string $errorcode stable machine-readable code
     * @param string $message readable, ready to show
     * @param int $http 401 only when the credentials are dead, 403 for a locked academy
     */
    public static function fail(string $errorcode, string $message, int $http = 200): void {
        self::emit(['status' => 'fail', 'error' => $message, 'errorcode' => $errorcode], $http);
    }

    /**
     * Unknown function name.
     */
    public static function unknown(): void {
        self::fail('unknownfunction', get_string('err_unknownfunction', 'local_academy'));
    }

    /**
     * Reject non-POST requests for state-changing calls.
     */
    public static function require_post(): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            self::fail('postrequired', get_string('err_postrequired', 'local_academy'));
        }
    }

    /**
     * The token sent with the request: `token` (our endpoints) or `wstoken` (Moodle
     * style) — the same web-service token either way.
     *
     * @return string
     */
    public static function request_token(): string {
        $token = optional_param('token', '', PARAM_ALPHANUM);
        if ($token === '') {
            $token = optional_param('wstoken', '', PARAM_ALPHANUM);
        }
        return $token;
    }

    /**
     * Validate the web-service token and become its user.
     *
     * @param bool $required false = anonymous calls allowed (returns null without a token)
     * @return \stdClass|null the authenticated user
     */
    public static function authenticate(bool $required = true): ?\stdClass {
        global $USER;
        $token = self::request_token();
        if ($token === '') {
            if (!$required) {
                return null;
            }
            self::fail('authrequired', get_string('err_authrequired', 'local_academy'), 401);
        }
        $user = \local_academy\token_auth::validate($token);
        if (!$user) {
            self::fail('invalidtoken', get_string('err_invalidtoken', 'local_academy'), 401);
        }
        \core\session\manager::set_user($user);
        self::$token = $token;

        if (self::site_locked((int) $user->id)) {
            self::fail('siteunavailable', get_string('err_siteunavailable', 'local_academy'), 403);
        }
        return $USER;
    }

    /**
     * @return string the validated token of this request ('' when anonymous)
     */
    public static function token(): string {
        return self::$token;
    }

    /**
     * Whether this request uses the shared pre-login token (getsettings.php →
     * admin_token / user_token): the caller is a visitor, not that token's owner.
     *
     * @return bool
     */
    public static function is_shared_token(): bool {
        if (self::$token === '') {
            return false;
        }
        $shared = array_filter([
            (string) get_config('local_multitopics', 'admin_token'),
            (string) get_config('local_multitopics', 'user_token'),
        ]);
        return in_array(self::$token, $shared, true);
    }

    /**
     * The viewer of a public page: the token's user, or 0 for a visitor (shared
     * token). A visitor continues as the guest user so the page shows only what a
     * visitor of the website sees.
     *
     * @return int user id, 0 = visitor
     */
    public static function public_viewer(): int {
        global $USER;
        if (self::is_shared_token()) {
            \core\session\manager::set_user(guest_user());
            return 0;
        }
        return (int) $USER->id;
    }

    /**
     * Whether the academy is locked (suspended, or expired past grace) for this
     * user. Mirrors local_license's page lock, which skips JSON endpoints; site
     * admins stay through so the owner can still read the licence status.
     *
     * @param int $userid
     * @return bool
     */
    public static function site_locked(int $userid): bool {
        if (!class_exists('\local_license\license')) {
            return false;
        }
        if ($userid && is_siteadmin($userid)) {
            return false;
        }
        return \local_license\license::is_suspended() || \local_license\license::is_expired();
    }

    /**
     * Fail unless the current user has a capability.
     *
     * @param string $capability
     * @param \context|null $context system when null
     */
    public static function require_capability(string $capability, ?\context $context = null): void {
        if (!has_capability($capability, $context ?? \context_system::instance())) {
            self::fail('nopermissions', get_string('err_nopermission', 'local_academy'));
        }
    }

    /**
     * Read a JSON-encoded request parameter as an array.
     *
     * @param string $name
     * @param bool $required
     * @return array
     */
    public static function json_param(string $name, bool $required = true): array {
        $raw = $required ? required_param($name, PARAM_RAW) : optional_param($name, '', PARAM_RAW);
        if ($raw === '' && !$required) {
            return [];
        }
        $value = json_decode($raw, true);
        if (!is_array($value)) {
            self::fail('invalidparameter', get_string('err_invalidjson', 'local_academy', $name));
        }
        return $value;
    }

    /**
     * Map an exception to [errorcode, message]. Business errors (moodle_exception
     * thrown by our managers) carry translated, safe messages and are shown;
     * database / coding errors are hidden behind a generic message.
     *
     * @param \Throwable $e
     * @return array{0:string,1:string}
     */
    public static function describe(\Throwable $e): array {
        if ($e instanceof \required_capability_exception || $e instanceof \require_login_exception) {
            return ['nopermissions', get_string('err_nopermission', 'local_academy')];
        }
        if ($e instanceof \dml_exception || $e instanceof \coding_exception) {
            return ['internalerror', get_string('err_internal', 'local_academy')];
        }
        if ($e instanceof \moodle_exception) {
            // Our string ids are err_<code>; the app sees just <code>.
            $code = preg_replace('/^err_/', '', (string) ($e->errorcode ?: 'error'));
            return [$code, $e->getMessage()];
        }
        return ['internalerror', get_string('err_internal', 'local_academy')];
    }

    /**
     * Dispatch the request: call $handler with the `function` name, answer its
     * return value as success, and turn exceptions into the fail envelope.
     *
     * @param callable $handler function(string $function): mixed
     */
    public static function run(callable $handler): void {
        $function = optional_param('function', '', PARAM_ALPHANUMEXT);
        try {
            $data = $handler($function);
        } catch (\Throwable $e) {
            debugging('mobile api error (' . $function . '): ' . $e->getMessage(), DEBUG_DEVELOPER);
            [$code, $message] = self::describe($e);
            self::fail($code, $message);
            return;
        }
        self::success($data);
    }

    /**
     * Make a pluginfile URL loadable by the app (webservice/pluginfile.php + token).
     *
     * @param string|\moodle_url|null $url
     * @return string
     */
    public static function file_url($url): string {
        if ($url === null || $url === '') {
            return '';
        }
        return \local_academy\ws_files::tokenize((string) ($url instanceof \moodle_url ? $url->out(false) : $url),
            self::$token);
    }

    /**
     * Paging params with sane bounds.
     *
     * @param int $default per-page default
     * @param int $max per-page ceiling
     * @return array{0:int,1:int} [page, perpage]
     */
    public static function paging(int $default = 20, int $max = 100): array {
        $page = max(0, optional_param('page', 0, PARAM_INT));
        $perpage = optional_param('perpage', $default, PARAM_INT);
        $perpage = max(1, min($max, $perpage));
        return [$page, $perpage];
    }
}
