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

namespace local_nit_core\branding;

use local_nit_core\api\config;

/**
 * Branding resolver — (active preset + admin overrides) → resolved token set.
 *
 * Returns pure data (semantic tokens). It never emits CSS and knows nothing
 * about Bootstrap variable names or the theme: the theme maps these semantic
 * values onto its own variables. This keeps the resolver theme-agnostic.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver {
    /** @var array<string,string> Allowlisted font keys → CSS font stacks. */
    private const FONTS = [
        'sans'    => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'rounded' => '"Nunito", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'serif'   => 'Georgia, "Iowan Old Style", "Times New Roman", serif',
    ];

    /**
     * Resolve the active brand into a semantic token set.
     *
     * @return array{preset:string,primary:string,onprimary:string,accent:string,font:string,density:string}
     */
    public static function resolve(): array {
        $cfg = config::manager();

        $presetkey = $cfg->get_string('brand_preset', preset_repository::DEFAULT_PRESET);
        $preset = preset_repository::get($presetkey);

        // Primary: admin override, else the preset default.
        $primary = self::normalise_hex((string) $cfg->get_string('brand_primary', ''));
        if ($primary === '') {
            $primary = $preset->primary();
        }

        // Accent: admin override only (optional).
        $accent = self::normalise_hex((string) $cfg->get_string('brand_accent', ''));

        // Font: admin override key, else the preset font; mapped to a stack.
        $fontkey = trim((string) $cfg->get_string('brand_font', ''));
        if ($fontkey === '' || !isset(self::FONTS[$fontkey])) {
            $fontkey = $preset->font();
        }
        $font = self::FONTS[$fontkey] ?? self::FONTS['sans'];

        return [
            'preset'    => $preset->key(),
            'primary'   => $primary,
            'onprimary' => contrast::safe_foreground($primary),
            'accent'    => $accent,
            'font'      => $font,
            'density'   => $preset->density(),
        ];
    }

    /**
     * Normalise a colour value to a leading-hash hex, or '' when unset/invalid.
     *
     * @param string $value raw setting value
     * @return string
     */
    private static function normalise_hex(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ($value[0] !== '#') {
            $value = '#' . $value;
        }
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) ? strtolower($value) : '';
    }
}
