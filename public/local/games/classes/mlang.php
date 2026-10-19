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

namespace local_games;

/**
 * Reading and writing the site's {mlang} convention.
 *
 * One translatable value is stored in one column as
 * {mlang en}Math Race{mlang}{mlang ar}سباق الحساب{mlang}. The admin never types
 * that: local_nit_mlang draws one input per installed language over any field it
 * recognises and composes the markup on submit, which is why the corner's forms
 * carry a single field per translatable value and no language switcher.
 *
 * Resolving it back is done here rather than by format_string(), because this
 * site runs with an empty $CFG->stringfilters - the multilang filter is enabled
 * for content but not for strings, so format_string() would hand the raw markup
 * straight to the page. local_jobform and local_nit_subscriptions resolve it the
 * same way for the same reason.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mlang {

    /** @var string Matches one {mlang xx}...{mlang} block. */
    const PATTERN = '/\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}(.*?)\{\s*mlang\s*\}/s';

    /**
     * Compose a stored value from per-language text.
     *
     * The markup is dropped only on a site with a single language pack, where it
     * would carry no information and the value is simply what was typed. On a
     * bilingual site a value written in one language is still tagged with that
     * language, and that tag is what makes it absent from the other: an English
     * word in Word Builder's list must not be offered to a child spelling in
     * Arabic just because nobody translated it.
     *
     * @param array<string, string> $values language code => text
     * @return string
     */
    public static function build(array $values): string {
        $parts = [];
        foreach ($values as $lang => $value) {
            $value = trim((string) $value);
            if ($value !== '' && $lang !== '') {
                $parts[$lang] = $value;
            }
        }

        if (!$parts) {
            return '';
        }
        if (count(self::languages()) < 2) {
            return reset($parts);
        }

        $out = '';
        foreach ($parts as $lang => $value) {
            $out .= '{mlang ' . $lang . '}' . $value . '{mlang}';
        }

        return $out;
    }

    /**
     * Split a stored value into its languages.
     *
     * Text with no markup at all is returned under the empty key, meaning "this
     * is the value in every language" - which is what a plain string written
     * before the site had a second language pack actually means.
     *
     * @param string|null $text
     * @return array<string, string> language code => text
     */
    public static function parse(?string $text): array {
        $raw = (string) $text;

        if (!preg_match_all(self::PATTERN, $raw, $matches, PREG_SET_ORDER)) {
            return $raw === '' ? [] : ['' => $raw];
        }

        $out = [];
        foreach ($matches as $match) {
            $out[strtolower($match[1])] = trim($match[2]);
        }

        return $out;
    }

    /**
     * The value in the language being read.
     *
     * Falls back in the order a reader would want it: the current language, then
     * the language-independent text, then `other` if the author wrote one, then
     * the site default, then whatever there is. Returning something the reader
     * cannot read beats returning nothing at all - except where the caller wants
     * a row to disappear in a language it was never written for, which is what
     * has_language() is for.
     *
     * @param string|null $text stored value
     * @param string|null $lang defaults to the current language
     * @return string
     */
    public static function display(?string $text, ?string $lang = null): string {
        global $CFG;

        $parts = self::parse($text);
        if (!$parts) {
            return '';
        }

        $lang = $lang ?? current_language();

        foreach ([$lang, '', 'other', $CFG->lang ?? 'en'] as $candidate) {
            if (isset($parts[$candidate]) && $parts[$candidate] !== '') {
                return $parts[$candidate];
            }
        }

        return (string) reset($parts);
    }

    /**
     * Whether this value was written in this language at all.
     *
     * The two word banks are not translations of each other - the English list
     * teaches English spelling and the Arabic list teaches Arabic spelling - so
     * a row belonging to one language has to vanish in the other rather than
     * fall back and ask a child to spell a word in a language they are not
     * playing in.
     *
     * @param string|null $text stored value
     * @param string|null $lang defaults to the current language
     * @return bool
     */
    public static function has_language(?string $text, ?string $lang = null): bool {
        $parts = self::parse($text);
        if (!$parts) {
            return false;
        }

        $lang = $lang ?? current_language();

        // Untagged text belongs to every language.
        if (isset($parts[''])) {
            return $parts[''] !== '';
        }

        // Compared against the empty string rather than tested for emptiness:
        // "0" is a perfectly good answer - the freezing point of water is one of
        // the questions the corner ships with - and empty() would call it blank
        // and drop the whole question.
        foreach ([$lang, 'other'] as $candidate) {
            if (isset($parts[$candidate]) && $parts[$candidate] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The languages a translatable field should be written in.
     *
     * Read from local_nit_mlang when it is installed so the corner and the rest
     * of the site always agree on the list; otherwise the site's own installed
     * packs, which is the same answer by a longer route.
     *
     * @return array<string, string> language code => human name, site default first
     */
    public static function languages(): array {
        if (class_exists('\local_nit_mlang\langs')) {
            $langs = \local_nit_mlang\langs::get();
            if ($langs) {
                // That plugin returns a numerically indexed list - each entry
                // carrying its own code, name and writing direction - so it has
                // to be keyed by the code here. Its order, site default first,
                // is the order an author types in and is kept.
                $out = [];
                foreach ($langs as $lang) {
                    $out[$lang['code']] = $lang['name'];
                }
                return $out;
            }
        }

        return get_string_manager()->get_list_of_translations();
    }
}
