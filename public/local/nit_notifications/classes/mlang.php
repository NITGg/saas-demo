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

namespace local_nit_notifications;

/**
 * Notification text in several languages.
 *
 * A title or a text is written once per installed language and stored as
 * multilang markup ("{mlang ar}…{mlang}{mlang en}…{mlang}"), or as plain text
 * when only one language was written. It is resolved to ONE language here, on
 * the server, before anything leaves: each recipient's bell, push and email get
 * plain text in their own language, so no client ever sees the markup.
 *
 * Picking a language: the exact one, then its parent (ar_eg → ar), then the
 * site default, then the first one written.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mlang {

    /** One "{mlang xx}text{mlang}" block. */
    private const BLOCK = '/\{mlang\s+([a-z0-9_\-]+)\s*\}(.*?)\{mlang\}/isu';

    /**
     * The installed languages, the site default first.
     *
     * @return array<string,string> code => name
     */
    public static function languages(): array {
        global $CFG;
        $all = get_string_manager()->get_list_of_translations();
        $default = $CFG->lang ?? 'en';
        if (isset($all[$default])) {
            $all = [$default => $all[$default]] + $all;
        }
        return $all;
    }

    /**
     * Store per-language values: plain text when only one is filled, else markup.
     *
     * @param array<string,string> $bylang code => text (empty ones are dropped)
     * @return string
     */
    public static function compose(array $bylang): string {
        $bylang = array_filter(array_map(fn($t) => trim((string) $t), $bylang), fn($t) => $t !== '');
        if (count($bylang) <= 1) {
            return (string) reset($bylang);
        }
        $out = '';
        foreach ($bylang as $code => $text) {
            $out .= '{mlang ' . $code . '}' . $text . '{mlang}';
        }
        return $out;
    }

    /**
     * Split a stored value into its languages. Plain text comes back under ''.
     *
     * @param string $text
     * @return array<string,string> code => text
     */
    public static function split(string $text): array {
        if (!self::has_markup($text)) {
            return ['' => trim($text)];
        }
        preg_match_all(self::BLOCK, $text, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $block) {
            $out[strtolower($block[1])] = trim($block[2]);
        }
        return $out;
    }

    /**
     * Whether the text holds multilang blocks.
     *
     * @param string $text
     * @return bool
     */
    public static function has_markup(string $text): bool {
        return (bool) preg_match(self::BLOCK, $text);
    }

    /**
     * The text in one language.
     *
     * @param string $text stored value
     * @param string $lang wanted language ('' = the current one)
     * @return string plain text
     */
    public static function resolve(string $text, string $lang = ''): string {
        global $CFG;
        if (!self::has_markup($text)) {
            return trim($text);
        }
        $parts = self::split($text);
        $lang = strtolower($lang !== '' ? $lang : current_language());
        $parent = explode('_', $lang)[0];
        foreach ([$lang, $parent, 'other', strtolower($CFG->lang ?? '')] as $code) {
            if ($code !== '' && isset($parts[$code]) && $parts[$code] !== '') {
                return $parts[$code];
            }
        }
        foreach ($parts as $value) {
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * The language a user reads notifications in.
     *
     * @param \stdClass $user
     * @return string
     */
    public static function user_language(\stdClass $user): string {
        global $CFG;
        return !empty($user->lang) ? (string) $user->lang : (string) ($CFG->lang ?? 'en');
    }
}
