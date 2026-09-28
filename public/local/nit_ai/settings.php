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
 * Site settings for local_nit_ai.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_nit_ai', get_string('pluginname', 'local_nit_ai'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_nit_ai/quizgenheading',
        get_string('setting_quizgenheading', 'local_nit_ai'),
        get_string('setting_quizgenheading_desc', 'local_nit_ai')
    ));

    // How many questions a video produces is meant to follow how much it teaches,
    // so this is a ceiling on spending rather than a target to aim at.
    $settings->add(new admin_setting_configtext(
        'local_nit_ai/quizgen_maxquestions',
        get_string('setting_maxquestions', 'local_nit_ai'),
        get_string('setting_maxquestions_desc', 'local_nit_ai'),
        80,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_nit_ai/quizgen_maxgappasses',
        get_string('setting_maxgappasses', 'local_nit_ai'),
        get_string('setting_maxgappasses_desc', 'local_nit_ai'),
        3,
        PARAM_INT
    ));
}
