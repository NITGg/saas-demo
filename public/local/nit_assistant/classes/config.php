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

namespace local_nit_assistant;

use local_nit_assistant\llm\profiles;
use local_nit_core\helper\crypto;

/**
 * The academy's assistant settings, with safe defaults.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** Per-turn and per-conversation limits (settings in a later phase). */
    public const LIMITS = [
        'maxtoolcalls' => 6,
        'maxiterations' => 8,
        'maxturninput' => 30000,
        'maxturnoutput' => 1500,
        'maxconvmessages' => 60,
        'turntimeout' => 30,
    ];

    /** Messages of history sent to the model. */
    public const HISTORY = 30;

    /**
     * Is the assistant on and able to answer (switch on, key set, budget above 0)?
     *
     * @return bool
     */
    public static function usable(): bool {
        return (bool) get_config('local_nit_assistant', 'enabled') && self::apikey() !== null && self::daily_budget() > 0;
    }

    /**
     * The academy's Anthropic key (decrypted), or null.
     *
     * @return string|null
     */
    public static function apikey(): ?string {
        $key = crypto::config_secret('local_nit_assistant', 'apikey');
        return $key === null || trim($key) === '' ? null : trim($key);
    }

    /**
     * Daily budget in USD (0 = off).
     *
     * @return float
     */
    public static function daily_budget(): float {
        return max(0.0, (float) (get_config('local_nit_assistant', 'dailybudget') ?: 0));
    }

    /**
     * May visitors (not signed in) chat?
     *
     * @return bool
     */
    public static function visitors(): bool {
        $value = get_config('local_nit_assistant', 'visitors');
        return $value === false || (bool) $value;
    }

    /**
     * Messages one sender may send per hour (four times this per day).
     *
     * @return int
     */
    public static function hourly_limit(): int {
        return max(1, (int) (get_config('local_nit_assistant', 'hourlylimit') ?: 30));
    }

    /**
     * The team's working hours as the academy wrote them (may hold {mlang} parts).
     *
     * @return string
     */
    public static function working_hours(): string {
        return trim((string) get_config('local_nit_assistant', 'workinghours'));
    }

    /**
     * The first message shown when the chat opens, in a language.
     *
     * @param string $lang ar | en
     * @return string
     */
    public static function greeting(string $lang): string {
        $text = \local_nit_assistant\tools\sales\helper::ml((string) get_config('local_nit_assistant', 'greeting'), $lang);
        return $text !== '' ? $text : get_string_manager()->get_string('greeting_default', 'local_nit_assistant', null, $lang);
    }

    /**
     * Model for chat replies.
     *
     * @return string
     */
    public static function chat_model(): string {
        return profiles::model((string) (get_config('local_nit_assistant', 'chatprofile') ?: 'standard'));
    }

    /**
     * Model for summaries and tags.
     *
     * @return string
     */
    public static function summary_model(): string {
        return profiles::model((string) (get_config('local_nit_assistant', 'summaryprofile') ?: 'fast'));
    }
}
