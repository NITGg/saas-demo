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

namespace local_nit_lessons\local;

use local_nit_lessons\service\teacher_service;

/**
 * Hook callbacks of local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Gear-menu pages: "My lessons & Flex" for everyone, the teacher pages for teachers,
     * "Live lessons" for managers.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $CFG, $USER;
        if (!isloggedin() || isguestuser()) {
            return;
        }
        require_once($CFG->dirroot . '/local/nit_flex/lib.php');
        if (!local_nit_flex_enabled()) {
            return;
        }
        $primary = $hook->get_primaryview();
        $primary->add(get_string('studenthub', 'local_nit_lessons'),
            new \moodle_url('/local/nit_lessons/student.php'), \navigation_node::TYPE_CUSTOM, null,
            'local_nit_lessons_hub');
        if ((new teacher_service())->is_teacher((int) $USER->id)) {
            $primary->add(get_string('mylessons', 'local_nit_lessons'),
                new \moodle_url('/local/nit_lessons/my_lessons.php'), \navigation_node::TYPE_CUSTOM, null,
                'local_nit_lessons_mylessons');
            $primary->add(get_string('teacherprofile', 'local_nit_lessons'),
                new \moodle_url('/local/nit_lessons/teacher_profile.php'), \navigation_node::TYPE_CUSTOM, null,
                'local_nit_lessons_teacherprofile');
        }
        if (has_capability('local/nit_lessons:managesettings', \context_system::instance())) {
            $primary->add(get_string('managelessons', 'local_nit_lessons'),
                new \moodle_url('/local/nit_lessons/manage_lessons.php'), \navigation_node::TYPE_CUSTOM, null,
                'local_nit_lessons_manage');
        }
    }
}
