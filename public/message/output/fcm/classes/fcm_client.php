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

namespace message_fcm;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Firebase Cloud Messaging (FCM) HTTP v1 API Client.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fcm_client {

    /** @var string OAuth2 Scope for FCM v1 */
    const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Resolves the absolute path to the Firebase Service Account JSON credentials file.
     *
     * @return string|null Path to file or null if not found.
     */
    public static function get_credentials_path(): ?string {
        global $CFG;

        $path = get_config('message_fcm', 'serviceaccountfile');
        if (empty($path)) {
            $path = '../firebase-service-account.json';
        }

        // 1. Direct path as configured
        if (file_exists($path)) {
            return realpath($path);
        }

        // 2. Relative to $CFG->dirroot (public directory)
        $relDir = $CFG->dirroot . '/' . ltrim($path, '/\\');
        if (file_exists($relDir)) {
            return realpath($relDir);
        }

        // 3. Check in project root (parent of public)
        $projectRoot = dirname($CFG->dirroot) . '/firebase-service-account.json';
        if (file_exists($projectRoot)) {
            return realpath($projectRoot);
        }

        // 4. Check in moodledata
        $datarootPath = $CFG->dataroot . '/firebase-service-account.json';
        if (file_exists($datarootPath)) {
            return realpath($datarootPath);
        }

        return null;
    }

    /**
     * Reads and parses the Service Account JSON credentials.
     *
     * @return array|null Decoded JSON array or null on error.
     */
    public static function get_credentials_data(): ?array {
        $path = self::get_credentials_path();
        if (!$path || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['private_key']) || empty($data['client_email'])) {
            return null;
        }

        return $data;
    }

    /**
     * Retrieves the Firebase Project ID.
     *
     * @return string|null
     */
    public static function get_project_id(): ?string {
        $configuredId = get_config('message_fcm', 'project_id');
        if (!empty($configuredId)) {
            return trim($configuredId);
        }

        $data = self::get_credentials_data();
        return $data['project_id'] ?? null;
    }

    /**
     * Checks if the FCM service is properly configured with valid credentials.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $data = self::get_credentials_data();
        $projectId = self::get_project_id();
        return !empty($data) && !empty($projectId);
    }

    /**
     * Fetches a valid Google OAuth2 Access Token using Service Account credentials.
     * Caches the token to avoid unnecessary network roundtrips.
     *
     * @return string|null Access token or null on failure.
     */
    public static function get_access_token(): ?string {
        // Check cached token
        $cached = get_config('message_fcm', 'cached_token_data');
        if ($cached) {
            $data = json_decode($cached, true);
            if (!empty($data['token']) && !empty($data['expires_at']) && $data['expires_at'] > (time() + 120)) {
                return $data['token'];
            }
        }

        $credentialsData = self::get_credentials_data();
        if (!$credentialsData) {
            debugging('message_fcm: Cannot fetch access token - missing or invalid credentials file.', DEBUG_DEVELOPER);
            return null;
        }

        try {
            $scopes = [self::FCM_SCOPE];
            $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials($scopes, $credentialsData);
            $tokenInfo = $credentials->fetchAuthToken();

            if (!empty($tokenInfo['access_token'])) {
                $expiresIn = (int)($tokenInfo['expires_in'] ?? 3600);
                set_config('cached_token_data', json_encode([
                    'token' => $tokenInfo['access_token'],
                    'expires_at' => time() + $expiresIn,
                ]), 'message_fcm');

                return $tokenInfo['access_token'];
            }

            debugging('message_fcm: Failed to fetch access token: ' . json_encode($tokenInfo), DEBUG_DEVELOPER);
            return null;
        } catch (\Throwable $e) {
            debugging('message_fcm: Exception fetching access token: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Sends a push notification message to a specific device via FCM HTTP v1.
     *
     * @param object $device Record from mdl_user_devices (has pushid, platform, id, etc.)
     * @param string $title Notification title.
     * @param string $body Notification body text.
     * @param array $data Extra custom key-value pairs.
     * @return array Result array with keys: success (bool), unregistered (bool), response (mixed)
     */
    public static function send_to_device(object $device, string $title, string $body, array $data = []): array {
        global $DB;

        if (empty($device->pushid)) {
            return ['success' => false, 'unregistered' => false, 'error' => 'Empty pushid'];
        }

        $projectId = self::get_project_id();
        $accessToken = self::get_access_token();

        if (empty($projectId) || empty($accessToken)) {
            return ['success' => false, 'unregistered' => false, 'error' => 'FCM not configured or auth failure'];
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Format custom data: all values must be strings for FCM v1
        $stringData = [];
        foreach ($data as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_array($val) || is_object($val)) {
                $stringData[(string)$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
            } else {
                $stringData[(string)$key] = (string)$val;
            }
        }

        $messagePayload = [
            'token' => $device->pushid,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ];

        if (!empty($stringData)) {
            $messagePayload['data'] = (object)$stringData;
        }

        $jsonPayload = json_encode(['message' => $messagePayload], JSON_UNESCAPED_UNICODE);

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=utf-8',
        ]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 4,
            'CURLOPT_CONNECTTIMEOUT' => 3,
        ]);

        $resp = $curl->post($url, $jsonPayload);
        $httpCode = $curl->info['http_code'] ?? 0;

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'unregistered' => false, 'response' => $resp];
        }

        // Check if token is invalid or unregistered
        $isUnregistered = false;
        $decodedResp = json_decode($resp, true);
        $errorCode = $decodedResp['error']['details'][0]['errorCode'] ?? $decodedResp['error']['status'] ?? '';

        // INVALID_ARGUMENT alone is not enough: FCM also returns it for a bad payload, which would wipe every device.
        if ($httpCode === 404 ||
            $errorCode === 'UNREGISTERED' ||
            $errorCode === 'NOT_FOUND' ||
            stripos($resp, 'registration-token-not-registered') !== false ||
            stripos($resp, 'not a valid FCM registration token') !== false ||
            stripos($resp, 'Requested entity was not found') !== false) {
            $isUnregistered = true;

            // Automatically clean up stale token from user_devices table
            try {
                $DB->delete_records('user_devices', ['id' => $device->id]);
            } catch (\Throwable $e) {
                // Ignore DB deletion error
            }
        }

        if (!defined('CLI_SCRIPT')) {
            debugging("message_fcm: FCM push to device {$device->id} failed [HTTP {$httpCode}]: {$resp}", DEBUG_DEVELOPER);
        }

        return [
            'success' => false,
            'unregistered' => $isUnregistered,
            'http_code' => $httpCode,
            'response' => $resp,
        ];
    }
}
