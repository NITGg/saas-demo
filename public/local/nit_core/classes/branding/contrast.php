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

/**
 * WCAG relative-luminance + contrast helper.
 *
 * Used to pick an AA-safe foreground for any admin-chosen colour, so a custom
 * brand colour can never produce an unreadable button.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class contrast {
    /** @var string Dark ink foreground. */
    private const INK = '#171b22';

    /** @var string Light foreground. */
    private const WHITE = '#ffffff';

    /**
     * Return the AA-safe foreground (dark ink or white) for a background.
     *
     * @param string $bghex background colour hex
     * @return string foreground colour hex
     */
    public static function safe_foreground(string $bghex): string {
        $onwhite = self::ratio(self::INK, $bghex);
        $onink = self::ratio(self::WHITE, $bghex);
        return $onink >= $onwhite ? self::WHITE : self::INK;
    }

    /**
     * WCAG contrast ratio between two colours (1..21).
     *
     * @param string $fg foreground hex
     * @param string $bg background hex
     * @return float
     */
    public static function ratio(string $fg, string $bg): float {
        $l1 = self::luminance($fg);
        $l2 = self::luminance($bg);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * WCAG relative luminance of a hex colour.
     *
     * @param string $hex colour hex
     * @return float
     */
    private static function luminance(string $hex): float {
        [$r, $g, $b] = self::rgb($hex);
        $channels = array_map(static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Convert a hex colour to an [r, g, b] triple.
     *
     * @param string $hex colour hex (3 or 6 digits, optional leading #)
     * @return int[]
     */
    private static function rgb(string $hex): array {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            // Unparseable — treat as mid-grey so callers still get a usable value.
            $hex = '808080';
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
