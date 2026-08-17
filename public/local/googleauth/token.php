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

/**
 * Exchange a verified Google ID token for a Moodle web service token.
 *
 * The mobile app performs native Google Sign-In, obtains an ID token, and POSTs it here.
 * We verify the token with Google, map it to a Moodle account (optionally creating one),
 * and return a web service token for the requested external service.
 *
 * POST parameters:
 *   idtoken  (required) - the Google ID token (JWT) from native sign-in.
 *   service  (optional) - external service shortname, defaults to moodle_mobile_app.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
define('REQUIRE_CORRECT_ACCESS', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Emit a JSON error and stop.
 *
 * @param string $code short machine-readable error code
 * @param int $status HTTP status code
 */
function local_googleauth_fail(string $code, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $code]);
    exit;
}

/**
 * Import the user's Google profile photo into Moodle as their user picture.
 *
 * OAuth2/Google users otherwise have no Moodle picture (user.picture = 0), so
 * every profile/site-info API returns the default placeholder even though the
 * person has a Google avatar. We download the verified `picture` URL from the
 * Google ID-token claims and store it as the standard user icon.
 *
 * Only runs when the user has no picture yet, so a manually uploaded photo is
 * never overwritten. SSRF-guarded: only Google-hosted URLs are fetched.
 *
 * @param \stdClass $user       the resolved Moodle user (needs id, picture)
 * @param string|null $pictureurl the `picture` claim from Google
 */
function local_googleauth_sync_picture(\stdClass $user, ?string $pictureurl): void {
    global $CFG, $DB;

    // Diagnostic: every path logs why it did/didn't sync. View on the server with:
    //   docker compose logs moodle --since=3m | grep picsync
    $log = function (string $msg) use ($user) {
        error_log("local_googleauth picsync: userid={$user->id} {$msg}");
    };

    if (empty($pictureurl)) {
        $log('SKIP no picture claim in Google ID token (app likely did not request the profile scope)');
        return;
    }
    if (!empty($user->picture)) {
        $log("SKIP user already has picture={$user->picture}");
        return;
    }

    // SSRF guard: only ever fetch from Google's own image hosts.
    $host = core_text::strtolower((string) parse_url($pictureurl, PHP_URL_HOST));
    $allowed = (substr($host, -strlen('googleusercontent.com')) === 'googleusercontent.com')
        || (substr($host, -strlen('.google.com')) === '.google.com');
    if ($host === '' || !$allowed) {
        $log("SKIP host not allowed: '{$host}'");
        return;
    }

    require_once($CFG->libdir . '/gdlib.php');
    require_once($CFG->libdir . '/filelib.php');

    $tmp = make_request_directory() . '/googlepic';
    $c = new \curl();
    $c->setopt([
        'CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5,
        'CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 3,
    ]);
    $data = $c->get($pictureurl);
    if ($c->get_errno() || $data === '' || $data === false) {
        $log("FAIL download errno={$c->get_errno()} len=" . strlen((string) $data) . " url={$pictureurl}");
        return;
    }
    if (file_put_contents($tmp, $data) === false) {
        $log('FAIL could not write temp file');
        return;
    }
    // Must be a real image.
    if (getimagesize($tmp) === false) {
        $log('FAIL downloaded data is not a recognised image (len=' . strlen((string) $data) . ')');
        @unlink($tmp);
        return;
    }

    $usercontext = \context_user::instance($user->id);
    $newpicture = process_new_icon($usercontext, 'user', 'icon', 0, $tmp);
    @unlink($tmp);

    if ($newpicture) {
        $DB->set_field('user', 'picture', $newpicture, ['id' => $user->id]);
        $user->picture = $newpicture;
        $log("OK stored picture={$newpicture}");
    } else {
        $log('FAIL process_new_icon returned falsy (GD missing or invalid image)');
    }
}

/**
 * Per-IP request throttle. Approximate sliding window backed by MUC; a small
 * over-count under heavy concurrency is acceptable for rate limiting.
 *
 * @param int $maxperminute allowed requests per IP per minute (0 disables)
 */
function local_googleauth_rate_limit(int $maxperminute): void {
    if ($maxperminute <= 0) {
        return;
    }
    $cache = \cache::make('local_googleauth', 'ratelimit');
    $key = 'ip_' . sha1(getremoteaddr());
    $now = time();
    $entry = $cache->get($key);
    if (!is_array($entry) || ($entry['reset'] ?? 0) <= $now) {
        $entry = ['count' => 0, 'reset' => $now + 60];
    }
    $entry['count']++;
    $cache->set($key, $entry);
    if ($entry['count'] > $maxperminute) {
        header('Retry-After: ' . max(1, $entry['reset'] - $now));
        local_googleauth_fail('rate_limited', 429);
    }
}

header('Content-Type: application/json; charset=utf-8');

$config = get_config('local_googleauth');

// CORS allowlist (kept in code, not an admin setting, so it can't be
// misconfigured). Native mobile apps send no Origin header, so this never
// affects them — it only controls which *browser* origins may read the response.
// The site's own origin is always allowed (derived from $CFG->wwwroot; an Origin
// is scheme://host[:port] with no path). Add extra browser origins — e.g. a
// separate web app — to $extraorigins below (no trailing slash).
$wwwrootparts = parse_url($CFG->wwwroot);
$siteorigin = ($wwwrootparts['scheme'] ?? 'https') . '://' . ($wwwrootparts['host'] ?? '')
    . (isset($wwwrootparts['port']) ? ':' . $wwwrootparts['port'] : '');
$extraorigins = [
    // 'https://app.nitg-eg.com',
];
$allowedorigins = array_values(array_filter(array_merge([$siteorigin], $extraorigins)));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedorigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}

// CORS pre-flight: the headers above are enough; answer and stop.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST so the ID token never lands in URLs/logs.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    local_googleauth_fail('method_not_allowed', 405);
}

if (empty($CFG->enablewebservices)) {
    local_googleauth_fail('webservices_disabled', 503);
}

if (empty($config->enabled)) {
    local_googleauth_fail('plugin_disabled', 503);
}

// Throttle abusive callers before doing any real work (server-side crypto + an
// outbound call to Google). Per-IP requests/minute; configurable, 0 disables.
local_googleauth_rate_limit((int) ($config->ratelimit ?? 20));

// Accept the token from form-encoded POST, query string, OR a JSON body.
// Both "idtoken" and "id_token" field names are accepted.
$idtoken = optional_param('idtoken', '', PARAM_RAW);
if ($idtoken === '') {
    $idtoken = optional_param('id_token', '', PARAM_RAW);
}
$serviceshortname = optional_param('service', '', PARAM_ALPHANUMEXT);
if ($idtoken === '') {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            if (!empty($json['idtoken'])) {
                $idtoken = (string)$json['idtoken'];
            } else if (!empty($json['id_token'])) {
                $idtoken = (string)$json['id_token'];
            }
            if ($serviceshortname === '' && !empty($json['service'])) {
                $serviceshortname = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$json['service']);
            }
        }
    }
}
if ($serviceshortname === '') {
    $serviceshortname = 'moodle_mobile_app';
}
if ($idtoken === '') {
    local_googleauth_fail('idtoken_missing', 400);
}

// Configured, accepted audiences (client IDs).
$allowedaud = array_values(array_filter(array_map('trim', explode(',', (string)$config->clientids))));
if (empty($allowedaud)) {
    local_googleauth_fail('no_clientids_configured', 503);
}

// 1) Verify the ID token with Google's tokeninfo endpoint (validates signature + expiry server-side).
$curl = new \curl();
$curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);
$resp = $curl->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idtoken]);
$info = json_decode((string)$resp);

if (!is_object($info) || isset($info->error) || isset($info->error_description)) {
    local_googleauth_fail('invalid_idtoken', 401);
}

// 2) Validate claims ourselves.
$validiss = ['accounts.google.com', 'https://accounts.google.com'];
if (empty($info->iss) || !in_array($info->iss, $validiss, true)) {
    local_googleauth_fail('invalid_issuer', 401);
}
if (empty($info->aud) || !in_array($info->aud, $allowedaud, true)) {
    // Include the received aud so the exact client ID to whitelist is visible.
    http_response_code(401);
    echo json_encode(['error' => 'invalid_audience', 'received_aud' => $info->aud ?? null]);
    exit;
}
if (empty($info->exp) || (int)$info->exp < time()) {
    local_googleauth_fail('token_expired', 401);
}
$emailverified = isset($info->email_verified)
    && ($info->email_verified === true || $info->email_verified === 'true');
if (empty($info->email) || !$emailverified) {
    local_googleauth_fail('email_not_verified', 401);
}

$email = core_text::strtolower(trim($info->email));

// Optional hosted-domain restriction.
if (!empty($config->restrictdomain)) {
    $allowdomains = array_filter(array_map('trim', explode(',', core_text::strtolower($config->restrictdomain))));
    $emaildomain = core_text::strtolower((string)substr(strrchr($email, '@'), 1));
    if (!in_array($emaildomain, $allowdomains, true)) {
        local_googleauth_fail('domain_not_allowed', 403);
    }
}

// 3) Map to a Moodle account by email (must be unique).
$usercount = $DB->count_records('user', [
    'email' => $email,
    'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
]);
if ($usercount > 1) {
    local_googleauth_fail('email_not_unique', 409);
}

$user = $DB->get_record('user', [
    'email' => $email,
    'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
]);

if (!$user) {
    if (empty($config->allowcreate)) {
        local_googleauth_fail('user_not_found', 404);
    }
    // 3b) Auto-create the account.
    $newuser = new stdClass();
    $newuser->auth = !empty($config->newuserauth) ? $config->newuserauth : 'oauth2';
    $newuser->confirmed = 1;
    $newuser->mnethostid = $CFG->mnet_localhost_id;
    $newuser->email = $email;

    $localpart = strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email;
    $base = \core_user::clean_field($localpart, 'username');
    if (empty($base)) {
        $base = 'guser';
    }
    $username = $base;
    $i = 1;
    while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
        $username = $base . $i;
        $i++;
    }
    $newuser->username = $username;
    $newuser->firstname = !empty($info->given_name) ? $info->given_name
        : (!empty($info->name) ? $info->name : 'Google');
    $newuser->lastname = !empty($info->family_name) ? $info->family_name : 'User';
    $newuser->lang = $CFG->lang ?? 'en';

    $newid = user_create_user($newuser, false, true);
    $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
}

// 4) SSO link policy. Only mint a token for accounts whose auth method is
// allowed to be linked via Google. This stops a Google sign-in from taking over
// a pre-existing local (e.g. manual) password account that merely happens to
// share the same verified email — the account is only linkable if it was made
// for SSO. Auto-created users (auth = newuserauth, default oauth2) pass.
$allowedlinkauth = array_filter(array_map('trim', explode(',', (string) ($config->allowedlinkauth ?? 'oauth2'))));
if (empty($allowedlinkauth)) {
    $allowedlinkauth = ['oauth2'];
}
if (!in_array($user->auth, $allowedlinkauth, true)) {
    local_googleauth_fail('auth_not_linkable', 403);
}

// Import the Google avatar as the Moodle user picture (only if they have none).
// Failure here must never block sign-in, so it is best-effort inside the helper.
local_googleauth_sync_picture($user, $info->picture ?? null);

// 5) Standard account gates (mirror /login/token.php).
if (isguestuser($user)) {
    local_googleauth_fail('guest_not_allowed', 403);
}
if (!empty($user->suspended)) {
    local_googleauth_fail('user_suspended', 403);
}
if (empty($user->confirmed)) {
    local_googleauth_fail('user_not_confirmed', 403);
}

$systemcontext = context_system::instance();
if (!empty($CFG->maintenance_enabled)
        && !has_capability('moodle/site:maintenanceaccess', $systemcontext, $user)) {
    local_googleauth_fail('site_maintenance', 503);
}

// 5) Set the current user and mint the web service token.
enrol_check_plugins($user);
\core\session\manager::set_user($user);

$service = $DB->get_record('external_services', ['shortname' => $serviceshortname, 'enabled' => 1]);
if (!$service) {
    local_googleauth_fail('service_not_available', 404);
}

$token = \core_external\util::generate_token_for_current_user($service);
\core_external\util::log_token_request($token);

// generate_token_for_current_user() REUSES the most recent existing external_tokens
// row for this user+service when one exists, returning whatever privatetoken that
// row holds. Rows created via "Site admin > Manage tokens", or carried over in a
// migration, have a NULL privatetoken — and core only ever generates one when it
// *mints a brand-new* row. A NULL privatetoken makes tool_mobile_get_autologin_key
// fail, so the WebView auto-login never works for these users (Google/Apple users
// commonly hit this because their token row predates this flow). Backfill one the
// exact same way core does when it creates a token: random_string(64), persisted.
if (empty($token->privatetoken)) {
    $token->privatetoken = random_string(64);
    $DB->set_field('external_tokens', 'privatetoken', $token->privatetoken, ['id' => $token->id]);
}

$siteadmin = has_capability('moodle/site:config', $systemcontext, $user->id);

$result = new stdClass();
$result->token = $token->token;
// Private token is only returned to non-admins over HTTPS (same policy as core
// /login/token.php: is_https() && !$siteadmin, else null).
$result->privatetoken = (is_https() && !$siteadmin) ? $token->privatetoken : null;
$result->userid = (int)$user->id;

echo json_encode($result);
