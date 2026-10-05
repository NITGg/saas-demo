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

namespace theme_nit;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin setting: the links of a navbar menu (the gear menu or the avatar menu),
 * edited like the footer "pages" column. Both menus are for signed-in users, so
 * "who sees it" is a role: every signed-in user, students, teachers or admins.
 *
 * Until it is saved the menu keeps Moodle's own links; see theme_nit_navmenu_links().
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_navlinks extends admin_setting_footerpages {

    /** @var string 'gear' or 'user' */
    private $menu;

    /**
     * @param string $menu 'gear' or 'user'
     * @param string $visiblename
     * @param string $description
     */
    public function __construct(string $menu, string $visiblename, string $description) {
        $this->menu = $menu;
        // Default '' = Moodle's own links (theme_nit_navmenu_links() returns null); the
        // editor still starts from default_rows(), so saving it unchanged keeps the menu.
        parent::__construct('theme_nit/navmenu_' . $menu, $visiblename, $description, '');
    }

    protected function audiences(): array {
        $labels = [];
        foreach (\theme_nit_navmenu_audiences() as $value) {
            $labels[$value] = get_string('navmenu_show_' . $value, 'theme_nit');
        }
        return $labels;
    }

    protected function default_rows(): array {
        return \theme_nit_navmenu_default($this->menu);
    }
}
