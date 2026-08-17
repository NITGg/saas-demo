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

namespace local_nit_core\event;

use local_nit_core\contract\identifiable;

/**
 * Base class for generic NIT entity audit events.
 *
 * These events carry no object table (they are generic across all NIT
 * entities); the concrete entity class and id travel in the "other" payload.
 * Concrete subclasses set crud/edulevel in init().
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class nit_event_base extends \core\event\base {
    /**
     * Build an audit event for the given entity.
     *
     * @param identifiable $entity the entity being audited
     * @param int|null $entityid explicit id (used post-delete when the entity id may be cleared)
     * @return static
     */
    public static function create_for_entity(identifiable $entity, ?int $entityid = null): static {
        $event = static::create([
            'context' => \context_system::instance(),
            'other'   => [
                'entityclass' => get_class($entity),
                'entityid'    => (int) ($entityid ?? $entity->get_id()),
            ],
        ]);
        return $event;
    }

    /**
     * Human-readable description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        $class = $this->other['entityclass'] ?? 'unknown';
        $id    = $this->other['entityid'] ?? 0;
        $crud  = $this->data['crud'] ?? '?';
        return "The user with id '{$this->userid}' performed action '{$crud}' " .
            "on NIT entity '{$class}' with id '{$id}'.";
    }
}
