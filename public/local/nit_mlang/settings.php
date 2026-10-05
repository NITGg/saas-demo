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
 * Settings for local_nit_mlang.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_nit_mlang\langs;
use local_nit_mlang\profilefields;
use local_nit_mlang\registry;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_nit_mlang_settings', get_string('pluginname', 'local_nit_mlang'));
    $ADMIN->add('localplugins', $settings);

    // Status panel: which languages the editor will show, and whether a multilang
    // filter is actually switched on to render the result. Skipped during
    // install/upgrade, when neither the filter tables nor the caches are ready.
    if (!during_initial_install()) {
        $names = array_map(function ($lang) {
            return $lang['name'] . ' (' . $lang['code'] . ')';
        }, langs::get());

        $status = html_writer::tag('p', get_string('statuslangs', 'local_nit_mlang', implode(', ', $names)));

        try {
            $active = filter_get_active_in_context(context_system::instance());
            if (!array_key_exists('multilang2', $active) && !array_key_exists('multilang', $active)) {
                $status .= $OUTPUT->notification(get_string('statusnofilter', 'local_nit_mlang'), 'warning', false);
            }
        } catch (\Throwable $e) {
            // Filters not queryable yet (mid-upgrade): the language list alone is
            // still worth showing.
            unset($e);
        }

        $settings->add(new admin_setting_heading(
            'local_nit_mlang/status',
            get_string('status', 'local_nit_mlang'),
            $status
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'local_nit_mlang/enabled',
        get_string('enabled', 'local_nit_mlang'),
        get_string('enabled_desc', 'local_nit_mlang'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_nit_mlang/editors',
        get_string('editors', 'local_nit_mlang'),
        get_string('editors_desc', 'local_nit_mlang'),
        1
    ));

    // Which custom profile field categories hold translatable *values*. Ticking a
    // category turns every text field and text area in it into one input per
    // language on the profile form, for the person filling it in - so an
    // instructor writes their specialisation and biography once in each language
    // and every reader sees their own. Categories holding identifiers (a passport
    // number, a national ID) are left alone.
    $settings->add(new admin_setting_configmultiselect(
        'local_nit_mlang/' . profilefields::SETTING,
        get_string('profilecategories', 'local_nit_mlang'),
        get_string('profilecategories_desc', 'local_nit_mlang'),
        [],
        profilefields::categories()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_nit_mlang/' . profilefields::SETTING_TEXTAREAS,
        get_string('profiletextareas', 'local_nit_mlang'),
        get_string('profiletextareas_desc', 'local_nit_mlang'),
        0
    ));

    // How to read a field name and a page type off a page — the two things the
    // settings below are written in terms of.
    $settings->add(new admin_setting_heading(
        'local_nit_mlang/howto',
        get_string('howto', 'local_nit_mlang'),
        get_string('howto_desc', 'local_nit_mlang')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_nit_mlang/extratextfields',
        get_string('extratextfields', 'local_nit_mlang'),
        get_string('extratextfields_desc', 'local_nit_mlang', s(implode("\n", registry::TEXT_FIELDS))),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_nit_mlang/extraexcludes',
        get_string('extraexcludes', 'local_nit_mlang'),
        get_string('extraexcludes_desc', 'local_nit_mlang',
            s(implode("\n", array_merge(registry::TEXT_EXCLUDES, registry::EDITOR_EXCLUDES)))),
        '',
        PARAM_RAW
    ));
}
