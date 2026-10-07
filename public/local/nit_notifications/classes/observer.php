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

namespace local_nit_notifications;

/**
 * Event observers (see db/events.php).
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * A course module was created or changed: when it is a quiz, it may now be
     * visible to students — \local_nit_notifications\auto::quiz_changed() decides.
     *
     * @param \core\event\base $event course_module_created | course_module_updated
     */
    public static function course_module_changed(\core\event\base $event): void {
        if (($event->other['modulename'] ?? '') === 'quiz') {
            auto::quiz_changed((int) $event->objectid);
        }
    }
}
