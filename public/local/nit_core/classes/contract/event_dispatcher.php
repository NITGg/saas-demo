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

use local_nit_core\event\nit_event_base;

/**
 * Single choke point for dispatching NIT events.
 *
 * Decouples callers (e.g. the entity base) from the Moodle event trigger
 * mechanics, giving one place to evolve dispatch behaviour later.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface event_dispatcher {
    /**
     * Dispatch a NIT event through the Moodle Events API.
     *
     * @param nit_event_base $event the event to trigger
     * @return void
     */
    public function dispatch(nit_event_base $event): void;
}
