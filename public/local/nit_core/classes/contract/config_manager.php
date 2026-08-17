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

namespace local_nit_core\contract;

/**
 * Typed configuration gateway.
 *
 * The single entry point for reading and writing plugin configuration, so that
 * callers never scatter raw get_config()/set_config() calls. Reads are already
 * cached by Moodle core, so this contract adds typing and scoping, not caching.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface config_manager {
    /**
     * Read a string setting.
     *
     * @param string $key setting name
     * @param string|null $default value returned when unset
     * @return string|null
     */
    public function get_string(string $key, ?string $default = null): ?string;

    /**
     * Read a boolean setting.
     *
     * @param string $key setting name
     * @param bool $default value returned when unset
     * @return bool
     */
    public function get_bool(string $key, bool $default = false): bool;

    /**
     * Read an integer setting.
     *
     * @param string $key setting name
     * @param int|null $default value returned when unset
     * @return int|null
     */
    public function get_int(string $key, ?int $default = null): ?int;

    /**
     * Write a setting.
     *
     * @param string $key setting name
     * @param string|int|bool|null $value value to store (null unsets)
     * @return void
     */
    public function set(string $key, string|int|bool|null $value): void;

    /**
     * Return a config reader scoped to another plugin component.
     *
     * @param string $component frankenstyle component name
     * @return config_manager
     */
    public function for_plugin(string $component): config_manager;
}
