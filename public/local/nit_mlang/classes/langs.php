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

namespace local_nit_mlang;

/**
 * The set of languages the multilang field editor exposes.
 *
 * The list is NOT hard-coded: it is exactly the site's installed language packs
 * (Site administration -> Language -> Language packs), so installing Français
 * turns every enhanced field into a three-language editor with no code change.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class langs {

    /** @var string Cache key for the resolved language list. */
    const CACHEKEY = 'langlist';

    /**
     * The languages to show, in display order.
     *
     * Order: the site default language first (that is the one an author usually
     * types), then the rest alphabetically by code. Each entry carries the code,
     * the human name from the pack, and the pack's own writing direction so an
     * Arabic/Hebrew/Persian input is rendered RTL even on an English page.
     *
     * @return array[] list of ['code' => string, 'name' => string, 'dir' => 'ltr'|'rtl']
     */
    public static function get(): array {
        $cache = \core_cache\cache::make('local_nit_mlang', 'langs');
        $cached = $cache->get(self::CACHEKEY);
        if ($cached !== false) {
            return $cached;
        }

        $sm = get_string_manager();
        $translations = $sm->get_list_of_translations();

        // Drop the "(en)"-style suffix Moodle appends: our UI prints the code itself.
        // The name arrives wrapped in invisible LRM/RLM direction marks (Moodle adds
        // them so the code reads correctly next to an RTL name), so those have to go
        // first or the suffix is never recognised and the code appears twice.
        $langs = [];
        foreach ($translations as $code => $name) {
            $name = preg_replace('/[\x{200E}\x{200F}\x{061C}]/u', '', (string) $name);
            $langs[$code] = [
                'code' => $code,
                'name' => trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name)),
                'dir'  => self::direction($code),
            ];
        }

        // Site default first, then the rest by code.
        $default = get_config('core', 'lang') ?: 'en';
        uksort($langs, function ($a, $b) use ($default) {
            if ($a === $default) {
                return -1;
            }
            if ($b === $default) {
                return 1;
            }
            return strcmp($a, $b);
        });

        $langs = array_values($langs);
        $cache->set(self::CACHEKEY, $langs);
        return $langs;
    }

    /**
     * Writing direction of a language pack.
     *
     * Reads the pack's own `thisdirection` langconfig string, falling back to a
     * short list of known RTL codes when the pack does not declare one.
     *
     * @param string $code language pack code, e.g. 'ar'
     * @return string 'ltr' or 'rtl'
     */
    protected static function direction(string $code): string {
        $sm = get_string_manager();
        if ($sm->string_exists('thisdirection', 'langconfig')) {
            $dir = trim((string) $sm->get_string('thisdirection', 'langconfig', null, $code));
            if ($dir === 'rtl' || $dir === 'ltr') {
                return $dir;
            }
        }
        $rtl = ['ar', 'he', 'fa', 'ur', 'ckb', 'ps', 'sd', 'ug', 'yi', 'dv'];
        return in_array(strtolower(explode('_', $code)[0]), $rtl, true) ? 'rtl' : 'ltr';
    }
}
