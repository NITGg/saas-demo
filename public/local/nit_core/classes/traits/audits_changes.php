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

namespace local_nit_core\traits;

use local_nit_core\api\events;
use local_nit_core\event\entity_created;
use local_nit_core\event\entity_deleted;
use local_nit_core\event\entity_updated;

/**
 * Emits standardized NIT audit events on persistent create/update/delete.
 *
 * Intended for use by classes extending \core\persistent (which already manages
 * the timecreated/timemodified/usermodified columns, so this trait does NOT
 * duplicate that). Applying it to any persistent gives uniform auditing.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
trait audits_changes {
    /** @var int|null Id captured before deletion, since it may be cleared afterwards. */
    private ?int $auditdeletedid = null;

    /**
     * Persistent hook: emit a created event.
     *
     * @return void
     */
    protected function after_create() {
        events::dispatch(entity_created::create_for_entity($this));
    }

    /**
     * Persistent hook: emit an updated event.
     *
     * @param bool $result outcome of the update
     * @return void
     */
    protected function after_update($result) {
        if ($result) {
            events::dispatch(entity_updated::create_for_entity($this));
        }
    }

    /**
     * Persistent hook: capture the id before the row is removed.
     *
     * @return void
     */
    protected function before_delete() {
        $this->auditdeletedid = $this->get_id();
    }

    /**
     * Persistent hook: emit a deleted event using the pre-delete id.
     *
     * @param bool $result outcome of the delete
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            events::dispatch(entity_deleted::create_for_entity($this, $this->auditdeletedid));
        }
    }
}
