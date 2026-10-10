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

/**
 * Admin settings of local_nit_lessons (added to the "Lesson packages & live lessons" category).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$parent = $ADMIN->locate('local_nit_flex_cat') ? 'local_nit_flex_cat' : 'localplugins';

if ($hassiteconfig || has_capability('local/nit_lessons:managesettings', context_system::instance())) {
    $ADMIN->add($parent, new admin_externalpage(
        'local_nit_lessons_manage',
        get_string('managelessons', 'local_nit_lessons'),
        new moodle_url('/local/nit_lessons/manage_lessons.php'),
        'local/nit_lessons:managesettings'
    ));
}

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_lessons_settings', get_string('lessonsettings', 'local_nit_lessons'));
    if ($ADMIN->fulltree) {
        $courses = [0 => get_string('choosedots')];
        foreach (get_courses('all', 'c.fullname ASC', 'c.id, c.fullname') as $course) {
            if ((int) $course->id !== SITEID) {
                $courses[$course->id] = format_string($course->fullname);
            }
        }
        $settings->add(new admin_setting_configselect('local_nit_lessons/lessons_courseid',
            get_string('lessonscourse', 'local_nit_lessons'), get_string('lessonscourse_desc', 'local_nit_lessons'),
            0, $courses));
        // The subjects teachers pick from on their profile (/local/academy/profile.php).
        $settings->add(new admin_setting_configtextarea('local_nit_lessons/subjects',
            get_string('set_subjects', 'local_nit_lessons'), get_string('set_subjects_desc', 'local_nit_lessons'),
            \local_nit_lessons\service\teacher_service::default_subjects(), PARAM_TEXT, 60, 10));
        foreach (\local_nit_lessons\service\settings_service::DEFAULTS as $key => $default) {
            if ($key === 'lessons_courseid') {
                continue;
            }
            $settings->add(new admin_setting_configtext('local_nit_lessons/' . $key,
                get_string('set_' . $key, 'local_nit_lessons'), get_string('set_' . $key . '_desc', 'local_nit_lessons'),
                $default, PARAM_INT, 6));
        }
    }
    $ADMIN->add($parent, $settings);
}

// Site administration → Local plugins: keep our pages in the agreed order.
if (class_exists('\local_academy\local\admin_order')) {
    \local_academy\local\admin_order::apply($ADMIN);
}
