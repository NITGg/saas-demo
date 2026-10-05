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

use local_nit_lessons\entity\lesson;

/**
 * The meeting-room seam. Lessons depend on this interface, never on Jitsi directly, so a later
 * slice can bind a real mod_jitsi implementation without touching lesson logic (Facade Principle).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface room_interface {
    /**
     * Create the meeting room for a lesson that is starting.
     *
     * @param lesson $lesson
     * @return object {sessionid:int, cmid:int, join_url:string}
     */
    public function create_for_lesson(lesson $lesson): object;

    /**
     * Close the room when the lesson ends (complete / absence). No-op if no room exists.
     *
     * @param lesson $lesson
     * @return void
     */
    public function end_for_lesson(lesson $lesson): void;

    /**
     * The join payload for a viewer, or null when there is no live room.
     *
     * @param lesson $lesson
     * @param int $viewerid
     * @return array|null
     */
    public function session_payload(lesson $lesson, int $viewerid): ?array;
}
