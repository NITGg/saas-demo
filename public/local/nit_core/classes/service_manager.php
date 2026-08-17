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

namespace local_nit_core;

use local_nit_core\contract\cache_manager;
use local_nit_core\contract\config_manager;
use local_nit_core\contract\event_dispatcher;
use local_nit_core\exception\nit_exception;

/**
 * The NIT dependency-injection seam (ADR-0001, Option B).
 *
 * A thin service locator that lazily builds SDK services and lets tests swap
 * any of them for a double. It intentionally delegates only NIT services;
 * core services are resolved through \core\di where needed. This insulates
 * plugins from churn in core's plugin-DI registration ergonomics.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @internal
 */
final class service_manager {
    /** @var array<string,object> Test overrides keyed by service id. */
    private static array $overrides = [];

    /** @var array<string,object> Built singletons keyed by service id. */
    private static array $instances = [];

    /**
     * Factory map: service id (an interface name) => builder closure.
     *
     * @return array<string,callable():object>
     */
    private static function factories(): array {
        return [
            config_manager::class   => static fn(): object => new manager\config_manager(),
            cache_manager::class    => static fn(): object => new manager\cache_manager(),
            event_dispatcher::class => static fn(): object => new manager\event_dispatcher(),
        ];
    }

    /**
     * Resolve a service by its id (its interface name).
     *
     * @param string $id service interface name
     * @return object
     * @throws nit_exception when the id is unknown
     */
    public static function get(string $id): object {
        if (isset(self::$overrides[$id])) {
            return self::$overrides[$id];
        }
        if (isset(self::$instances[$id])) {
            return self::$instances[$id];
        }
        $factories = self::factories();
        if (!isset($factories[$id])) {
            throw new nit_exception('error_unknownservice', $id);
        }
        return self::$instances[$id] = ($factories[$id])();
    }

    /**
     * Whether a service id is resolvable.
     *
     * @param string $id service interface name
     * @return bool
     */
    public static function has(string $id): bool {
        return isset(self::$overrides[$id]) || isset(self::factories()[$id]);
    }

    /**
     * Override a service with a test double (unit-test seam).
     *
     * @param string $id service interface name
     * @param object $service replacement instance
     * @return void
     */
    public static function override(string $id, object $service): void {
        self::$overrides[$id] = $service;
    }

    /**
     * Clear overrides and built singletons. Call from test tearDown.
     *
     * @return void
     */
    public static function reset(): void {
        self::$overrides = [];
        self::$instances = [];
    }
}
