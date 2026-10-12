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

namespace local_nit_assistant\llm;

/**
 * Model profiles and prices. Settings store a profile NAME; the model id is mapped
 * here. Prices are USD per token from Anthropic's pricing page (2026-10-12) —
 * bump PRICE_TABLE_VERSION when they change.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profiles {
    /** @var string[] Profile names, cheapest first. */
    public const NAMES = ['fast', 'standard', 'complex'];

    /** @var array<string,string> Profile → model id. */
    public const MODEL = [
        'fast'     => 'claude-haiku-5-5',
        'standard' => 'claude-sonnet-5-5',
        'complex'  => 'claude-opus-5-5',
    ];

    /** Date the prices below were taken. */
    public const PRICE_TABLE_VERSION = '2026-10-12';

    /** @var array<string,array{in:float,out:float,cacheread:float,cachewrite:float}> Per million tokens; cachewrite = 5-minute TTL. */
    private const PRICES_PER_M = [
        'claude-haiku-5-5'  => ['in' => 0.10, 'out' => 0.50, 'cacheread' => 0.01, 'cachewrite' => 0.125],
        'claude-sonnet-5-5' => ['in' => 2.00, 'out' => 10.00, 'cacheread' => 0.20, 'cachewrite' => 2.50],
        'claude-opus-5-5'   => ['in' => 4.00, 'out' => 20.00, 'cacheread' => 0.20, 'cachewrite' => 5.00],
    ];

    /** The most expensive entry: unknown models are priced as this, so the budget errs safe. */
    private const FALLBACK = 'claude-opus-5-5';

    /**
     * Model id for a profile name (unknown name → standard).
     *
     * @param string $profile
     * @return string
     */
    public static function model(string $profile): string {
        return self::MODEL[$profile] ?? self::MODEL['standard'];
    }

    /**
     * The price-table key for a model id. The API may answer with a dated snapshot
     * id (e.g. "claude-haiku-4-5-20251001"), so such an id matches its model by
     * prefix — pricing it as the fallback was a real 4x overcharge in nit2.
     *
     * @param string $model
     * @return string|null null = not in the table
     */
    public static function price_model(string $model): ?string {
        if (isset(self::PRICES_PER_M[$model])) {
            return $model;
        }
        foreach (array_keys(self::PRICES_PER_M) as $key) {
            if (strpos($model, $key . '-') === 0) { // A dated snapshot of a known model.
                return $key;
            }
        }
        return null;
    }

    /**
     * Cost in USD of one call.
     *
     * @param string $model as returned by the API
     * @param usage $u
     * @return float
     */
    public static function cost(string $model, usage $u): float {
        $key = self::price_model($model);
        if ($key === null) {
            debugging("local_nit_assistant: no price for model {$model}; priced as " . self::FALLBACK, DEBUG_DEVELOPER);
        }
        $p = self::PRICES_PER_M[$key ?? self::FALLBACK];
        return ($u->input * $p['in'] + $u->output * $p['out'] + $u->cacheread * $p['cacheread']
            + $u->cachewrite * $p['cachewrite']) / 1000000;
    }

    /**
     * Upper bound reserved before a call: all input uncached + the full output cap.
     *
     * @param string $model
     * @param int $inputtokens estimate
     * @param int $maxoutput
     * @return float
     */
    public static function estimate(string $model, int $inputtokens, int $maxoutput): float {
        return self::cost($model, new usage($inputtokens, $maxoutput, 0, 0));
    }
}
