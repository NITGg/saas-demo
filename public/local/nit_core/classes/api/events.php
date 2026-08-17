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

namespace local_nit_core\api;

use local_nit_core\contract\event_dispatcher;
use local_nit_core\event\nit_event_base;

/**
 * Ergonomic public facade for dispatching NIT events.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class events {
    /**
     * Dispatch a NIT event.
     *
     * @param nit_event_base $event the event to trigger
     * @return void
     */
    public static function dispatch(nit_event_base $event): void {
        /** @var event_dispatcher $dispatcher */
        $dispatcher = services::get(event_dispatcher::class);
        $dispatcher->dispatch($event);
    }
}
