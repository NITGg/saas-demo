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

use local_nit_core\contract\auditable;
use local_nit_core\contract\identifiable;
use local_nit_core\traits\audits_changes;

/**
 * Base class for all NIT persistent entities.
 *
 * Extends \core\persistent (which already defines and populates id,
 * timecreated, timemodified and usermodified) and layers uniform audit-event
 * emission via the audits_changes trait. Subclasses only declare the table
 * name and their own properties.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
abstract class entity extends \core\persistent implements identifiable, auditable {
    use audits_changes;

    /**
     * Return the record id, or null when not yet persisted.
     *
     * @return int|null
     */
    public function get_id(): ?int {
        // Use raw_get, not get('id'): \core\persistent::get() auto-dispatches to a get_<property>()
        // method when one exists, so calling $this->get('id') here would re-enter get_id() forever.
        $id = (int) $this->raw_get('id');
        return $id > 0 ? $id : null;
    }

    /**
     * Return the record as a plain array for the audit trail.
     *
     * @return array
     */
    public function audit_snapshot(): array {
        return (array) $this->to_record();
    }
}
