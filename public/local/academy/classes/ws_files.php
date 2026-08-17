<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for returning file URLs that a web-service client can load directly.
 */
class ws_files {

    /**
     * Make a Moodle file URL fetchable by a token-authenticated client.
     *
     * Protected files are served by /pluginfile.php, which requires a browser
     * session. A mobile/web client instead uses /webservice/pluginfile.php with
     * its token appended. Public URLs (e.g. the theme default avatar via
     * theme/image.php) are returned unchanged.
     *
     * @param string $url   the URL as Moodle generated it
     * @param string $token the caller's web-service token
     * @return string a URL the client can load as-is
     */
    public static function tokenize(string $url, string $token): string {
        if ($token === '' || strpos($url, '/pluginfile.php') === false) {
            return $url;
        }
        $url = str_replace('/pluginfile.php', '/webservice/pluginfile.php', $url);
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'token=' . $token;
        return $url;
    }
}
