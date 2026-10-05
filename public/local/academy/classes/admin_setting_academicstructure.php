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

namespace local_academy;

use local_academy\local\academic_structure;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting: the Study systems with their Divisions.
 *
 * (The Years are the course categories — managed on Courses → Manage courses
 * and categories, not here.) Each study system is a row (its name + its
 * divisions, one per line) that can be added or removed in place. Saving stores the JSON
 * in local_academy/academic_structure and pushes the lists into the course and
 * user dropdown fields (academic_structure::sync()).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_academicstructure extends \admin_setting {

    /**
     * Get the stored JSON (null while never saved, so the defaults show).
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Validate, store and sync.
     *
     * @param mixed $data ['name' => string[], 'divisions' => string[]]
     * @return string '' on success, else an error message
     */
    public function write_setting($data) {
        if (is_string($data) && ($decoded = json_decode($data, true)) !== null) {
            // The default (JSON), e.g. when the setting is first applied on upgrade.
            $structure = academic_structure::normalise((array) $decoded);
        } else {
            $structure = self::from_post(is_array($data) ? $data : []);
        }
        if (empty($structure['systems'])) {
            return get_string('academic_nosystems', 'local_academy');
        }
        foreach ($structure['systems'] as $system) {
            if (empty($system['divisions'])) {
                return get_string('academic_nodivisions', 'local_academy', s($system['name']));
            }
        }
        $json = json_encode($structure, JSON_UNESCAPED_UNICODE);
        if (!$this->config_write($this->name, $json)) {
            return get_string('errorsetting', 'admin');
        }
        academic_structure::sync();
        return '';
    }

    /**
     * Turn the posted form into a structure.
     *
     * @param array $data
     * @return array
     */
    public static function from_post(array $data): array {
        $lines = static fn($text): array => preg_split('/\R/u', (string) $text);
        $systems = [];
        foreach ((array) ($data['name'] ?? []) as $i => $name) {
            $systems[] = [
                'name' => clean_param((string) $name, PARAM_TEXT),
                'divisions' => array_map(static fn($d) => clean_param($d, PARAM_TEXT), $lines($data['divisions'][$i] ?? '')),
            ];
        }
        return academic_structure::normalise(['systems' => $systems]);
    }

    /**
     * Render the systems table (the years are the course categories).
     *
     * @param mixed $data the stored JSON (or the posted array after a failed save)
     * @param string $query
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        if (is_array($data)) {
            $structure = self::from_post($data);
        } else {
            $decoded = is_string($data) ? json_decode($data, true) : null;
            $structure = is_array($decoded) ? academic_structure::normalise($decoded) : academic_structure::defaults();
        }
        $full = $this->get_full_name();
        $str = static fn(string $key) => s(get_string($key, 'local_academy'));

        $row = static function (string $name, array $divisions) use ($full, $str): string {
            return '<tr>'
                . '<td style="width:30%"><input type="text" class="form-control" name="' . $full . '[name][]" value="' . s($name) . '"'
                . ' placeholder="' . $str('academic_system') . '"></td>'
                . '<td><textarea class="form-control" rows="4" name="' . $full . '[divisions][]"'
                . ' placeholder="' . $str('academic_divisions_hint') . '">' . s(implode("\n", $divisions)) . '</textarea></td>'
                . '<td class="text-center" style="width:1%"><button type="button" class="btn btn-outline-danger btn-sm" data-la-remove>'
                . $str('academic_remove') . '</button></td>'
                . '</tr>';
        };
        $body = '';
        foreach ($structure['systems'] as $system) {
            $body .= $row($system['name'], $system['divisions']);
        }
        $id = 'la-academic-' . $this->name;

        $html = '<div id="' . $id . '" class="la-academic">'
            . '<div class="alert alert-info py-2">' . get_string('academic_years_categories', 'local_academy',
                (new \moodle_url('/course/management.php'))->out(false)) . '</div>'
            . '<table class="table table-sm align-middle mb-2"><thead><tr>'
            . '<th>' . $str('academic_system') . '</th><th>' . $str('academic_divisions') . '</th><th></th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table>'
            . '<template>' . $row('', []) . '</template>'
            . '<button type="button" class="btn btn-secondary btn-sm" data-la-add>+ ' . $str('academic_add') . '</button>'
            . '</div>'
            . '<script>(function(){var w=document.getElementById(' . json_encode($id) . ');if(!w){return;}'
            . 'var tb=w.querySelector("tbody"),tp=w.querySelector("template");'
            . 'w.addEventListener("click",function(e){'
            . 'if(e.target.closest("[data-la-add]")){tb.insertAdjacentHTML("beforeend",tp.innerHTML);'
            . 'var i=tb.lastElementChild&&tb.lastElementChild.querySelector("input");if(i){i.focus();}}'
            . 'var rm=e.target.closest("[data-la-remove]");if(rm){rm.closest("tr").remove();}});})();</script>';

        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}
