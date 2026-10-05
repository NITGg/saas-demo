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

namespace theme_nit\local;

use admin_setting_configtext;
use admin_setting_configtextarea;
use admin_setting_heading;
use admin_settingpage;

/**
 * "Site pages manager": the content of the Bassthalk-style pages that are not
 * HTML blocks, one tab each (the site footer, the navbar menus). The home page sections
 * are nit_section blocks — see theme/nit/blocks/templates/bassthalk.
 *
 * Registered under Site administration → Plugins → Local plugins by
 * local/academy/settings.php (admin/settings.php?section=theme_nit_sitepages).
 * The settings keep their theme_nit names (footer_*), so moving the
 * page here changed no stored value.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sitepages_settings {

    /** Admin section name of the page. */
    public const SECTION = 'theme_nit_sitepages';

    /**
     * Build the tabbed page.
     *
     * @param bool $fulltree whether the settings themselves are needed
     * @return admin_settingpage
     */
    public static function page(bool $fulltree): admin_settingpage {
        global $CFG;
        require_once($CFG->dirroot . '/theme/nit/lib.php');

        $page = new \theme_boost_admin_settingspage_tabs(self::SECTION,
            get_string('sitepages', 'theme_nit'), 'moodle/site:config');
        if ($fulltree) {
            $page->add_tab(self::footer_tab());
            $page->add_tab(self::navmenus_tab());
        }
        return $page;
    }

    /**
     * Footer tab: the site footer (every page).
     *
     * @return admin_settingpage
     */
    private static function footer_tab(): admin_settingpage {
        $tab = new admin_settingpage('theme_nit_sitepages_footer', get_string('sitepages_footer', 'theme_nit'));
        $tab->add(new admin_setting_configtextarea(
            'theme_nit/footer_description',
            get_string('footerdescription', 'theme_nit'),
            get_string('footerdescription_desc', 'theme_nit'),
            theme_nit_ml('This platform was made to prepare students for every side of secondary school and beyond',
                'تم صنع هذه المنصة بهدف تهيئة الطالب لـ كامل جوانب الثانوية العامة و ما بعدها'),
            PARAM_TEXT, 60, 3
        ));
        $tab->add(new admin_setting_configtext(
            'theme_nit/footer_copyright',
            get_string('footercopyright', 'theme_nit'),
            get_string('footercopyright_desc', 'theme_nit'),
            theme_nit_ml('All rights reserved © {year}', 'جميع الحقوق محفوظة © {year}'),
            PARAM_TEXT
        ));
        $tab->add(new \theme_nit\admin_setting_footerpages(
            'theme_nit/footer_pages',
            get_string('footerpages', 'theme_nit'),
            get_string('footerpages_desc', 'theme_nit'),
            json_encode(theme_nit_footer_pages_default(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
        $tab->add(new admin_setting_heading(
            'theme_nit/footer_socialheading',
            get_string('footersocial', 'theme_nit'),
            get_string('footersocial_desc', 'theme_nit')
        ));
        foreach (theme_nit_footer_social_networks() as $key => $net) {
            $tab->add(new admin_setting_configtext(
                'theme_nit/footer_' . $key,
                get_string('footersocial_' . $key, 'theme_nit'),
                '',
                '',
                PARAM_URL
            ));
        }
        return $tab;
    }

    /**
     * Navbar menus tab: the links of the gear menu and of the avatar (user) menu.
     *
     * @return admin_settingpage
     */
    private static function navmenus_tab(): admin_settingpage {
        $tab = new admin_settingpage('theme_nit_sitepages_navmenus', get_string('sitepages_navmenus', 'theme_nit'));
        $tab->add(new \theme_nit\admin_setting_navlinks('gear',
            get_string('navmenu_gear', 'theme_nit'), get_string('navmenu_gear_desc', 'theme_nit')));
        $tab->add(new \theme_nit\admin_setting_navlinks('user',
            get_string('navmenu_user', 'theme_nit'), get_string('navmenu_user_desc', 'theme_nit')));
        return $tab;
    }

}
