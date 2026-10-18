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

namespace local_nit_lessons\task;

use local_nit_lessons\api\lessons;

/**
 * Completes started lessons the teacher forgot to close (setting auto_complete_minutes).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_complete_lessons extends \core\task\scheduled_task {

    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_autocomplete', 'local_nit_lessons');
    }

    /**
     * Complete what is due.
     */
    public function execute() {
        $count = lessons::auto_complete_due(time());
        mtrace("local_nit_lessons: $count lesson(s) auto-completed");
    }
}
