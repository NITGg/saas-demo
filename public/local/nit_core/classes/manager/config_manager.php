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

namespace local_nit_core\manager;

use local_nit_core\contract\config_manager as config_manager_contract;

/**
 * Default config manager: typed, component-scoped wrapper over Moodle config.
 *
 * Deliberately does NOT add its own cache: get_config() is already cached by
 * core per request, so an extra MUC layer here would be forwarding-only.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @internal
 */
class config_manager implements config_manager_contract {
    /** @var string Component whose settings this instance reads/writes. */
    private string $component;

    /**
     * Build a config manager bound to a component.
     *
     * @param string $component frankenstyle component (defaults to the SDK itself)
     */
    public function __construct(string $component = 'local_nit_core') {
        $this->component = $component;
    }

    /**
     * Raw fetch returning null when the setting is unset.
     *
     * @param string $key setting name
     * @return string|null
     */
    private function raw(string $key): ?string {
        $value = get_config($this->component, $key);
        // get_config() returns false when the setting does not exist.
        return $value === false ? null : (string) $value;
    }

    /**
     * Read a string setting.
     *
     * @param string $key setting name
     * @param string|null $default value returned when unset
     * @return string|null
     */
    public function get_string(string $key, ?string $default = null): ?string {
        return $this->raw($key) ?? $default;
    }

    /**
     * Read a boolean setting.
     *
     * @param string $key setting name
     * @param bool $default value returned when unset
     * @return bool
     */
    public function get_bool(string $key, bool $default = false): bool {
        $value = $this->raw($key);
        return $value === null ? $default : (bool) (int) $value;
    }

    /**
     * Read an integer setting.
     *
     * @param string $key setting name
     * @param int|null $default value returned when unset
     * @return int|null
     */
    public function get_int(string $key, ?int $default = null): ?int {
        $value = $this->raw($key);
        return $value === null ? $default : (int) $value;
    }

    /**
     * Write (or unset, when null) a setting.
     *
     * @param string $key setting name
     * @param string|int|bool|null $value value to store; null unsets
     * @return void
     */
    public function set(string $key, string|int|bool|null $value): void {
        if ($value === null) {
            unset_config($key, $this->component);
            return;
        }
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }
        set_config($key, $value, $this->component);
    }

    /**
     * Return a config reader scoped to another component.
     *
     * @param string $component frankenstyle component name
     * @return config_manager_contract
     */
    public function for_plugin(string $component): config_manager_contract {
        return new self($component);
    }
}
