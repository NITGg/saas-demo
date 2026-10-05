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

namespace local_nit_lessons\room;

/**
 * Resolves the meeting-room implementation: the Jitsi room (tests can swap in the stub through
 * {@see self::set_override()}).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class room_factory {
    /** @var room_interface|null Test override. */
    private static ?room_interface $override = null;

    /**
     * The active room implementation.
     *
     * @return room_interface
     */
    public static function instance(): room_interface {
        return self::$override ?? new jitsi_room();
    }

    /**
     * Override the room implementation (unit-test seam).
     *
     * @param room_interface|null $room
     * @return void
     */
    public static function set_override(?room_interface $room): void {
        self::$override = $room;
    }
}
