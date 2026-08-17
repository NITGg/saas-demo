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

use core_cache\cache;
use local_nit_core\contract\cache_manager as cache_manager_contract;

/**
 * Default cache manager: namespaced facade over a single MUC application cache.
 *
 * Areas are folded into the key so all NIT caches share one definition while
 * remaining logically separated. An area index is kept so purge_area() can
 * clear a whole area without a full cache purge.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @internal
 */
class cache_manager implements cache_manager_contract {
    /** @var string MUC definition name declared in db/caches.php. */
    private const DEFINITION = 'registry';

    /** @var \core_cache\application_cache|null Lazily built cache instance. */
    private $cache = null;

    /**
     * Return the underlying MUC cache, building it on first use.
     *
     * @return \core_cache\application_cache
     */
    private function store() {
        if ($this->cache === null) {
            $this->cache = cache::make('local_nit_core', self::DEFINITION);
        }
        return $this->cache;
    }

    /**
     * Build the physical cache key for an area + key pair.
     *
     * @param string $area logical area
     * @param string $key item key
     * @return string
     */
    private function compose(string $area, string $key): string {
        return $area . ':' . $key;
    }

    /**
     * Return the index key that tracks all keys stored in an area.
     *
     * @param string $area logical area
     * @return string
     */
    private function indexkey(string $area): string {
        return '__index:' . $area;
    }

    /**
     * Record a key in its area index (for purge_area()).
     *
     * @param string $area logical area
     * @param string $key item key
     * @return void
     */
    private function track(string $area, string $key): void {
        $index = $this->store()->get($this->indexkey($area));
        $index = is_array($index) ? $index : [];
        $index[$key] = true;
        $this->store()->set($this->indexkey($area), $index);
    }

    /**
     * Fetch a cached value.
     *
     * @param string $area logical area
     * @param string $key item key
     * @return mixed the value, or null on a miss
     */
    public function get(string $area, string $key): mixed {
        $value = $this->store()->get($this->compose($area, $key));
        return $value === false ? null : $value;
    }

    /**
     * Store a value.
     *
     * @param string $area logical area
     * @param string $key item key
     * @param mixed $value value to store
     * @return bool true on success
     */
    public function set(string $area, string $key, mixed $value): bool {
        $this->track($area, $key);
        return $this->store()->set($this->compose($area, $key), $value);
    }

    /**
     * Delete a value.
     *
     * @param string $area logical area
     * @param string $key item key
     * @return bool true on success
     */
    public function delete(string $area, string $key): bool {
        return $this->store()->delete($this->compose($area, $key));
    }

    /**
     * Purge every key belonging to an area.
     *
     * @param string $area logical area
     * @return bool true on success
     */
    public function purge_area(string $area): bool {
        $index = $this->store()->get($this->indexkey($area));
        if (is_array($index)) {
            foreach (array_keys($index) as $key) {
                $this->store()->delete($this->compose($area, $key));
            }
        }
        return $this->store()->delete($this->indexkey($area));
    }
}
