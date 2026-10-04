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

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting: the site footer "pages" column as an editable list of rows.
 *
 * Each row is a page name, its link (starting with "/" for a page on this site,
 * or a full http(s) address) and who sees it (everyone / visitors / signed-in
 * users). Rows are added and removed in place; the list is stored as JSON in
 * theme_nit/footer_pages and read by theme_nit_footer_pages().
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_footerpages extends \admin_setting {

    /** @var string[] who-sees-it values, in the order the select lists them */
    private const AUDIENCES = ['all', 'guest', 'user'];

    /**
     * Get the stored JSON.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Validate and store the posted rows.
     *
     * @param mixed $data ['name' => string[], 'url' => string[], 'show' => string[]]
     * @return string '' on success, else an error message
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            $data = [];
        }
        $names = (array) ($data['name'] ?? []);
        $urls = (array) ($data['url'] ?? []);
        $shows = (array) ($data['show'] ?? []);

        $rows = [];
        foreach ($names as $i => $name) {
            $name = trim(clean_param((string) $name, PARAM_TEXT));
            $url = trim(clean_param((string) ($urls[$i] ?? ''), PARAM_RAW_TRIMMED));
            if ($name === '' && $url === '') {
                continue; // An empty row is simply dropped.
            }
            if ($name === '' || $url === '') {
                return get_string('footerpages_incomplete', 'theme_nit');
            }
            if ($url[0] !== '/' && !preg_match('~^https?://~i', $url)) {
                return get_string('footerpages_invalidurl', 'theme_nit', s($url));
            }
            $show = (string) ($shows[$i] ?? 'all');
            $rows[] = [
                'name' => $name,
                'url' => clean_param($url, PARAM_URL) ?: $url,
                'show' => in_array($show, self::AUDIENCES, true) ? $show : 'all',
            ];
        }
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->config_write($this->name, $json) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Render the editable rows.
     *
     * @param mixed $data the stored JSON (or the posted array after a failed save)
     * @param string $query
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        if (is_array($data)) {
            $rows = [];
            foreach ((array) ($data['name'] ?? []) as $i => $n) {
                $rows[] = ['name' => $n, 'url' => $data['url'][$i] ?? '', 'show' => $data['show'][$i] ?? 'all'];
            }
        } else {
            $decoded = ($data === null || $data === false) ? null : json_decode((string) $data, true);
            $rows = is_array($decoded) ? $decoded : \theme_nit_footer_pages_default();
        }

        $full = $this->get_full_name();
        $labels = [
            'all' => get_string('footerpages_show_all', 'theme_nit'),
            'guest' => get_string('footerpages_show_guest', 'theme_nit'),
            'user' => get_string('footerpages_show_user', 'theme_nit'),
        ];
        $rowhtml = function(array $row) use ($full, $labels): string {
            $opts = '';
            foreach ($labels as $value => $label) {
                $opts .= \html_writer::tag('option', s($label),
                    ['value' => $value] + ((($row['show'] ?? 'all') === $value) ? ['selected' => 'selected'] : []));
            }
            return '<tr class="nit-fp-row">'
                . '<td><input type="text" class="form-control" name="' . $full . '[name][]" value="' . s($row['name'] ?? '') . '"'
                . ' placeholder="' . s(get_string('footerpages_name', 'theme_nit')) . '"></td>'
                . '<td><input type="text" class="form-control" dir="ltr" name="' . $full . '[url][]" value="' . s($row['url'] ?? '') . '"'
                . ' placeholder="/my/  ·  https://…"></td>'
                . '<td><select class="form-select" name="' . $full . '[show][]">' . $opts . '</select></td>'
                . '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" data-nit-fp-remove>'
                . s(get_string('footerpages_remove', 'theme_nit')) . '</button></td>'
                . '</tr>';
        };

        $body = '';
        foreach ($rows as $row) {
            $body .= $rowhtml($row);
        }
        $template = $rowhtml(['name' => '', 'url' => '', 'show' => 'all']);
        $id = 'nit-fp-' . $this->name;

        $html = '<div class="nit-footerpages" id="' . $id . '">'
            // An always-present hidden marker so an emptied list still posts.
            . '<input type="hidden" name="' . $full . '[name][]" value="">'
            . '<input type="hidden" name="' . $full . '[url][]" value="">'
            . '<input type="hidden" name="' . $full . '[show][]" value="all">'
            . '<table class="table table-sm align-middle mb-2"><thead><tr>'
            . '<th>' . s(get_string('footerpages_name', 'theme_nit')) . '</th>'
            . '<th>' . s(get_string('footerpages_url', 'theme_nit')) . '</th>'
            . '<th>' . s(get_string('footerpages_show', 'theme_nit')) . '</th>'
            . '<th></th></tr></thead><tbody>' . $body . '</tbody></table>'
            . '<template>' . $template . '</template>'
            . '<button type="button" class="btn btn-secondary btn-sm" data-nit-fp-add>+ '
            . s(get_string('footerpages_add', 'theme_nit')) . '</button>'
            . '</div>'
            . '<script>(function(){var w=document.getElementById(' . json_encode($id) . ');if(!w){return;}'
            . 'var tb=w.querySelector("tbody"),tp=w.querySelector("template");'
            . 'w.addEventListener("click",function(e){'
            . 'if(e.target.closest("[data-nit-fp-add]")){tb.insertAdjacentHTML("beforeend",tp.innerHTML);'
            . 'var r=tb.lastElementChild;if(r){var i=r.querySelector("input");if(i){i.focus();}}}'
            . 'var rm=e.target.closest("[data-nit-fp-remove]");if(rm){rm.closest("tr").remove();}});})();</script>';

        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}
