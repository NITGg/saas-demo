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
 * Settings for local_nit_core.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_nit_core\branding\preset_repository;
use local_nit_core\flag\registry;

if ($hassiteconfig) {
    // ---- SDK / developer settings (under Site administration → Plugins → Local plugins) ----
    $settings = new admin_settingpage('local_nit_core_settings', get_string('pluginname', 'local_nit_core'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_nit_core/showwelcomepanel',
        get_string('showwelcomepanel', 'local_nit_core'),
        get_string('showwelcomepanel_desc', 'local_nit_core'),
        0
    ));

    // ---- Branding (under Site administration → Appearance) ----
    $branding = new admin_settingpage('local_nit_core_branding', get_string('branding', 'local_nit_core'));
    $ADMIN->add('appearance', $branding);

    // Preset select.
    $presets = [];
    foreach (preset_repository::keys() as $key) {
        $presets[$key] = get_string('preset_' . $key, 'local_nit_core');
    }
    $preset = new admin_setting_configselect(
        'local_nit_core/brand_preset',
        get_string('brand_preset', 'local_nit_core'),
        get_string('brand_preset_desc', 'local_nit_core'),
        preset_repository::DEFAULT_PRESET,
        $presets
    );
    $preset->set_updatedcallback('theme_reset_all_caches');
    $branding->add($preset);

    // Primary colour override.
    $primary = new admin_setting_configcolourpicker(
        'local_nit_core/brand_primary',
        get_string('brand_primary', 'local_nit_core'),
        get_string('brand_primary_desc', 'local_nit_core'),
        ''
    );
    $primary->set_updatedcallback('theme_reset_all_caches');
    $branding->add($primary);

    // Accent colour override.
    $accent = new admin_setting_configcolourpicker(
        'local_nit_core/brand_accent',
        get_string('brand_accent', 'local_nit_core'),
        get_string('brand_accent_desc', 'local_nit_core'),
        ''
    );
    $accent->set_updatedcallback('theme_reset_all_caches');
    $branding->add($accent);

    // Font override (allowlist).
    $fonts = [
        ''        => get_string('brand_font_default', 'local_nit_core'),
        'sans'    => get_string('brand_font_sans', 'local_nit_core'),
        'rounded' => get_string('brand_font_rounded', 'local_nit_core'),
        'serif'   => get_string('brand_font_serif', 'local_nit_core'),
    ];
    $font = new admin_setting_configselect(
        'local_nit_core/brand_font',
        get_string('brand_font', 'local_nit_core'),
        get_string('brand_font_desc', 'local_nit_core'),
        '',
        $fonts
    );
    $font->set_updatedcallback('theme_reset_all_caches');
    $branding->add($font);

    // ---- Feature flags (under Local plugins → NIT Features) ----
    $features = new admin_settingpage('local_nit_core_features', get_string('features', 'local_nit_core'));
    $ADMIN->add('localplugins', $features);

    foreach (registry::by_domain() as $domain => $domainflags) {
        $features->add(new admin_setting_heading(
            'local_nit_core/flagdomain_' . $domain,
            get_string('flagdomain_' . $domain, 'local_nit_core'),
            ''
        ));
        foreach ($domainflags as $flag) {
            $features->add(new admin_setting_configcheckbox(
                'local_nit_core/flag_' . $flag->key(),
                $flag->name(),
                $flag->description(),
                $flag->default() ? 1 : 0
            ));
        }
    }
}
