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

namespace local_nit_core\base;

/**
 * Base repository for querying NIT entities.
 *
 * Intentionally thin: single-record CRUD lives on the persistent entity itself.
 * Subclass this only to add query methods that add real value (complex filters,
 * cached reads) — a repository that merely forwards to the entity is deleted.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class repository {
    /**
     * Fully-qualified class name of the entity this repository manages.
     *
     * @return class-string<entity>
     */
    abstract protected function entity_class(): string;

    /**
     * Find one entity by id.
     *
     * @param int $id entity id
     * @return entity|null
     */
    public function find(int $id): ?entity {
        $class = $this->entity_class();
        $record = $class::get_record(['id' => $id]);
        return $record ?: null;
    }

    /**
     * Find entities matching simple equality filters.
     *
     * @param array $filters column => value equality filters
     * @param int $limit maximum rows (0 = no limit)
     * @return entity[]
     */
    public function find_all(array $filters = [], int $limit = 0): array {
        $class = $this->entity_class();
        return $class::get_records($filters, '', 'ASC', 0, $limit);
    }

    /**
     * Whether any entity matches the filters.
     *
     * @param array $filters column => value equality filters
     * @return bool
     */
    public function exists(array $filters): bool {
        $class = $this->entity_class();
        return $class::count_records($filters) > 0;
    }
}
