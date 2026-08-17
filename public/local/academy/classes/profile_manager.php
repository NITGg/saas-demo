<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Current-user profile for the app, with ready-to-use (token-embedded) image URLs.
 */
class profile_manager {

    /**
     * The signed-in user's basic profile. The image URLs are already tokenized
     * (webservice/pluginfile.php + token) so the client can load them directly —
     * no URL rewriting needed on the app side.
     *
     * @param \stdClass $user  the token's user (as set by token_auth::validate)
     * @param string $token    the caller's web-service token
     */
    public static function get_my_profile(\stdClass $user, string $token): array {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());

        $big = new \user_picture($user);
        $big->size = 100; // f3 / large
        $small = new \user_picture($user);
        $small->size = 35; // f2 / small

        // Auth method: lets the app show the "change password" screen only for
        // password-based (manual/email) accounts and hide it for Google/OAuth2.
        $auth = $user->auth ?? 'manual';
        $canchangepassword = false;
        if (($authplugin = get_auth_plugin($auth))) {
            // True only for internal (password) auths — false for oauth2/google.
            $canchangepassword = $authplugin->can_change_password() && $authplugin->is_internal();
        }

        return [
            'userid'               => (int) $user->id,
            'username'             => $user->username ?? '',
            'fullname'             => fullname($user),
            'firstname'            => $user->firstname ?? '',
            'lastname'             => $user->lastname ?? '',
            'email'                => $user->email ?? '',
            'auth'                 => $auth,
            'isgoogle'             => ($auth === 'oauth2'),
            'canchangepassword'    => (bool) $canchangepassword,
            'profileimageurl'      => ws_files::tokenize($big->get_url($page)->out(false), $token),
            'profileimageurlsmall' => ws_files::tokenize($small->get_url($page)->out(false), $token),
        ];
    }
}
