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

require_once($CFG->dirroot . '/theme/nit/lib.php');

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
        foreach (\theme_nit_navmenu_audiences($this->menu) as $value) {
            $labels[$value] = get_string('navmenu_show_' . $value, 'theme_nit');
        }
        return $labels;
    }

    /**
     * "Show to" is a set of roles (any number of them); a new row starts with all ticked.
     *
     * @param string $field
     * @param array $row
     * @return string HTML
     */
    protected function show_cell(string $field, array $row): string {
        $ticked = !empty($row['isnew']) ? array_keys($this->audiences())
            : \theme_nit_navmenu_roles($row['show'] ?? 'all', $this->menu);
        $html = '<div class="d-flex flex-wrap gap-3">';
        foreach ($this->audiences() as $value => $label) {
            $html .= '<label class="form-check mb-0 text-nowrap">'
                . '<input type="checkbox" class="form-check-input" name="' . $field . '[]" value="' . s($value) . '"'
                . (in_array($value, $ticked, true) ? ' checked' : '') . '> '
                . '<span class="form-check-label">' . s($label) . '</span></label>';
        }
        return $html . '</div>';
    }

    /**
     * The ticked roles; a row with none ticked is refused (nobody would see it).
     *
     * @param mixed $raw
     * @return string[]|null
     */
    protected function parse_show($raw) {
        $roles = \theme_nit_navmenu_roles(is_array($raw) ? $raw : [], $this->menu);
        return $roles ?: null;
    }

    protected function show_error(): string {
        return get_string('navmenu_show_none', 'theme_nit');
    }

    protected function empty_shows_defaults(): bool {
        return $this->menu !== 'bar';
    }

    /**
     * The editor starts from the links the menu shows today: the known pages
     * (with their roles) plus anything else in the gear menu right now.
     *
     * @return array
     */
    protected function default_rows(): array {
        global $PAGE, $OUTPUT;
        $rows = \theme_nit_navmenu_default($this->menu);
        if ($this->menu !== 'gear') {
            return $rows;
        }
        try {
            $nav = (new \core\navigation\output\primary($PAGE))->export_for_template($OUTPUT)['mobileprimarynav'] ?? [];
        } catch (\Throwable $e) {
            return $rows;
        }
        return \theme_nit_navmenu_merge_current($rows, $nav);
    }
}
