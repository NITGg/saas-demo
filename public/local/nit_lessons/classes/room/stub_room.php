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
 * No-op meeting room used in A1: the lesson lifecycle and the money engine work end to end without
 * a real video room. A later slice swaps this for a Jitsi-backed implementation.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_room implements room_interface {
    /**
     * No real room is created; returns empty identifiers.
     *
     * @param lesson $lesson
     * @return object
     */
    public function create_for_lesson(lesson $lesson): object {
        return (object) ['sessionid' => 0, 'cmid' => 0, 'join_url' => ''];
    }

    /**
     * Nothing to close.
     *
     * @param lesson $lesson
     * @return void
     */
    public function end_for_lesson(lesson $lesson): void {
    }

    /**
     * No join payload under the stub.
     *
     * @param lesson $lesson
     * @param int $viewerid
     * @return array|null
     */
    public function session_payload(lesson $lesson, int $viewerid): ?array {
        return null;
    }
}
