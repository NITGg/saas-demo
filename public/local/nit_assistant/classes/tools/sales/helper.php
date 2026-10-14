<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\tools\sales;

use local_nit_assistant\tools\context;

/**
 * Small helpers shared by the sales tools: text in the conversation's language,
 * plain-text excerpts, links and buttons.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class helper {
    /** Currency of plans, packages and the wallet. */
    public const EGP = 'EGP';

    /**
     * The $lang part of "{mlang ar}…{mlang}{mlang en}…{mlang}" text (else English, else the
     * first part); text without markup comes back as it is.
     *
     * @param string|null $raw
     * @param string $lang
     * @return string
     */
    public static function ml(?string $raw, string $lang): string {
        $raw = (string) $raw;
        if (!preg_match_all('/\{mlang\s+([a-z_]+)\}(.*?)\{mlang\}/su', $raw, $m, PREG_SET_ORDER)) {
            return trim($raw);
        }
        $parts = [];
        foreach ($m as [, $code, $text]) {
            $parts[$code] ??= $text;
        }
        return trim($parts[$lang] ?? $parts['en'] ?? $parts['other'] ?? reset($parts));
    }

    /**
     * Plain text, shortened at a word boundary.
     *
     * @param string|null $html
     * @param int $max characters
     * @return string
     */
    public static function plain(?string $html, int $max = 300): string {
        $text = trim(preg_replace('/\s+/u', ' ', html_to_text((string) $html, 0, false)));
        if (\core_text::strlen($text) <= $max) {
            return $text;
        }
        $cut = \core_text::substr($text, 0, $max);
        $space = \core_text::strrpos($cut, ' ');
        return rtrim($space > $max * 0.6 ? \core_text::substr($cut, 0, $space) : $cut, ' ,.،') . '…';
    }

    /**
     * Absolute URL of a page on this site.
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    public static function url(string $path, array $params = []): string {
        return (new \moodle_url($path, $params))->out(false);
    }

    /**
     * Add a button under the reply.
     *
     * @param context $ctx
     * @param string $stringid lang string id (action_…)
     * @param string $url
     * @param mixed $a string placeholder
     * @return void
     */
    public static function button(context $ctx, string $stringid, string $url, mixed $a = null): void {
        $label = get_string_manager()->get_string($stringid, 'local_nit_assistant', $a, $ctx->lang);
        $ctx->actions[] = ['type' => 'link', 'label' => \core_text::substr($label, 0, 60), 'url' => $url];
    }

    /**
     * Load a plugin's lib.php if the plugin is installed.
     *
     * @param string $plugin e.g. nit_subscriptions
     * @return bool
     */
    public static function lib(string $plugin): bool {
        global $CFG;
        $file = "{$CFG->dirroot}/local/{$plugin}/lib.php";
        if (!file_exists($file)) {
            return false;
        }
        require_once($file);
        return true;
    }

    /**
     * Is a commerce feature on this academy's licence?
     *
     * @param string $feature offers | coupons
     * @return bool
     */
    public static function commerce(string $feature): bool {
        return class_exists('\local_nit_commerce\discount_manager') && self::lib('nit_commerce')
            && function_exists('local_nit_commerce_feature') && local_nit_commerce_feature($feature);
    }

    /**
     * Round money for display.
     *
     * @param float|int|string $amount
     * @return float
     */
    public static function money(float|int|string $amount): float {
        return round((float) $amount, 2);
    }
}
