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

namespace local_jobform;

/**
 * Bilingual (en/ar) value helper using the platform's {mlang} convention.
 *
 * The site stores a translatable value in one field as
 * {mlang en}English{mlang}{mlang ar}عربى{mlang}. The multilang2 filter is not
 * relied upon, so we build and resolve these strings ourselves — matching the
 * approach in local_nit_subscriptions.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mlang {

    /** @var string[] The languages the editor exposes, in output order. */
    const LANGS = ['en', 'ar'];

    /**
     * Build a stored value from per-language inputs.
     *
     * Two or more languages → {mlang} markup; a single language → the raw value.
     *
     * @param array $values lang code => text (e.g. ['en' => 'Name', 'ar' => 'الاسم'])
     * @return string
     */
    public static function build(array $values): string {
        $parts = [];
        foreach (self::LANGS as $lang) {
            $val = trim((string) ($values[$lang] ?? ''));
            if ($val !== '') {
                $parts[$lang] = $val;
            }
        }
        if (count($parts) === 0) {
            return '';
        }
        if (count($parts) === 1) {
            return reset($parts);
        }
        $out = '';
        foreach ($parts as $lang => $val) {
            $out .= '{mlang ' . $lang . '}' . $val . '{mlang}';
        }
        return $out;
    }

    /**
     * Split a stored value into per-language parts for editing.
     *
     * Understands {mlang xx}…{mlang} and legacy <span lang="xx">…</span>. A plain
     * string with no markup is treated as English.
     *
     * @param string|null $text
     * @return array lang code => text, always containing every LANGS key
     */
    public static function parse(?string $text): array {
        $out = array_fill_keys(self::LANGS, '');
        $raw = (string) $text;

        $found = false;
        if (preg_match_all('/\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}(.*?)\{\s*mlang\s*\}/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $code = strtolower($m[1]);
                foreach (self::LANGS as $lang) {
                    if (strpos($code, $lang) === 0) {
                        $out[$lang] = trim($m[2]);
                        $found = true;
                    }
                }
            }
        }
        if ($found) {
            return $out;
        }
        if (preg_match_all('/<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>(.*?)<\/span>/s', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $code = strtolower($m[1]);
                foreach (self::LANGS as $lang) {
                    if (strpos($code, $lang) === 0) {
                        $out[$lang] = trim($m[2]);
                        $found = true;
                    }
                }
            }
        }
        if (!$found) {
            $out['en'] = $raw;
        }
        return $out;
    }

    /**
     * Resolve a stored value to the current language for display.
     *
     * @param string|null $text
     * @return string
     */
    public static function resolve(?string $text): string {
        $raw = (string) $text;
        if (strpos($raw, '{mlang') === false && stripos($raw, '<span') === false) {
            return $raw;
        }
        $parts = self::parse($raw);
        $lang = strtolower(current_language());
        foreach ($parts as $code => $val) {
            if ($val !== '' && strpos($lang, $code) === 0) {
                return $val;
            }
        }
        // Fall back to English, then to the first non-empty value.
        if (!empty($parts['en'])) {
            return $parts['en'];
        }
        foreach ($parts as $val) {
            if ($val !== '') {
                return $val;
            }
        }
        return $raw;
    }

    /**
     * Resolve and escape a value for safe HTML output as a plain string.
     *
     * @param string|null $text
     * @return string
     */
    public static function display(?string $text): string {
        return format_string(self::resolve($text));
    }
}
