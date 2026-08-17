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

namespace local_nit_core\flag;

/**
 * A feature-flag definition (immutable data).
 *
 * The value (on/off) is stored as Moodle config; this is the declaration.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flag {
    /**
     * @param string $key globally-unique flag key
     * @param string $name human-readable name
     * @param string $description what the flag controls
     * @param bool $default default state when unset
     * @param string $domain grouping domain (e.g. ui, commerce)
     */
    public function __construct(
        /** @var string */
        private string $key,
        /** @var string */
        private string $name,
        /** @var string */
        private string $description,
        /** @var bool */
        private bool $default,
        /** @var string */
        private string $domain,
    ) {
    }

    /** @return string flag key */
    public function key(): string {
        return $this->key;
    }

    /** @return string human-readable name */
    public function name(): string {
        return $this->name;
    }

    /** @return string description */
    public function description(): string {
        return $this->description;
    }

    /** @return bool default state */
    public function default(): bool {
        return $this->default;
    }

    /** @return string grouping domain */
    public function domain(): string {
        return $this->domain;
    }
}
