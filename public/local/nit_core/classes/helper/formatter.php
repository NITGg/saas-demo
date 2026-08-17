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

namespace local_nit_core\helper;

/**
 * Shared formatting helpers (currency, dates) used across NIT plugins.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class formatter {
    /**
     * Format a decimal amount as localized currency.
     *
     * @param float $amount amount in major units
     * @param string $currency ISO 4217 currency code (e.g. EGP, SAR, USD)
     * @param string|null $locale locale (defaults to the current language)
     * @return string
     */
    public static function currency(float $amount, string $currency, ?string $locale = null): string {
        $locale = $locale ?? current_language();
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Format an amount given in minor units (e.g. piastres, cents) as currency.
     *
     * @param int $minorunits amount in the currency's smallest unit
     * @param string $currency ISO 4217 currency code
     * @param string|null $locale locale (defaults to the current language)
     * @return string
     */
    public static function money_minor(int $minorunits, string $currency, ?string $locale = null): string {
        return self::currency($minorunits / 100, $currency, $locale);
    }

    /**
     * Format a timestamp using Moodle's locale-aware date formatting.
     *
     * @param int $timestamp unix timestamp
     * @param string $format strftime-style format (empty = user default)
     * @return string
     */
    public static function datetime(int $timestamp, string $format = ''): string {
        return userdate($timestamp, $format);
    }
}
