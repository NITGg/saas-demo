<?php
namespace local_academysessions;

/**
 * Minimal HS256 JWT generator for Jitsi Meet authentication.
 * No external library required — uses only PHP's hash_hmac.
 */
class jitsi_jwt {

    /**
     * Generate a signed JWT token for a Jitsi room.
     *
     * @param string $room       Jitsi room name
     * @param string $name       User display name
     * @param string $email      User email
     * @param bool   $moderator  True for teachers/hosts
     * @return string  Signed JWT string
     */
    public static function generate(string $room, string $name, string $email, bool $moderator): string {
        $app_id     = get_config('local_academysessions', 'jitsi_jwt_app_id')     ?: 'academy_jitsi';
        $app_secret = get_config('local_academysessions', 'jitsi_jwt_app_secret') ?: 'academy_jitsi_secret_2024_change_in_prod';
        // The JWT `sub` must be the Jitsi XMPP domain (prosody muc_mapper_domain_base),
        // NOT the public web host. On the shared server that is "meet.jitsi" — using
        // the public host (academy2026.nitg-eg.com) makes prosody reject the token
        // ("you're not allowed to join this call"). Configurable for other servers.
        $xmpp_domain = get_config('local_academysessions', 'jitsi_xmpp_domain') ?: 'meet.jitsi';

        $header  = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64url(json_encode([
            'iss'  => $app_id,
            'aud'  => $app_id,
            'sub'  => $xmpp_domain,
            'room' => $room,
            'exp'  => time() + 7200,
            'nbf'  => time() - 10,
            'context' => [
                'user' => [
                    'name'      => $name,
                    'email'     => $email,
                    'moderator' => $moderator,
                ],
                'features' => [
                    'recording'     => $moderator,
                    'livestreaming' => $moderator,
                    'screen-sharing' => true,
                    'outbound-call'  => false,
                ],
            ],
        ]));

        $sig = self::b64url(hash_hmac('sha256', "$header.$payload", $app_secret, true));
        return "$header.$payload.$sig";
    }

    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
