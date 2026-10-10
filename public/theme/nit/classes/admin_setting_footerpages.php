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
 * Admin setting: the site footer "pages" column as an editable list of rows
 * (subclassed for the navbar menus: {@see admin_setting_navlinks}).
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

    /**
     * Who-sees-it choices, in the order the select lists them (the first is the default).
     *
     * @return array<string, string> value => label
     */
    protected function audiences(): array {
        return [
            'all' => get_string('footerpages_show_all', 'theme_nit'),
            'guest' => get_string('footerpages_show_guest', 'theme_nit'),
            'user' => get_string('footerpages_show_user', 'theme_nit'),
        ];
    }

    /**
     * The rows shown before an admin saves the list.
     *
     * @return array<int, array{name:string, url:string, show:string}>
     */
    protected function default_rows(): array {
        return \theme_nit_footer_pages_default();
    }

    /**
     * Whether a saved empty list opens with the default rows (an emptied footer
     * column stays empty; an emptied navbar menu falls back to Moodle's links).
     *
     * @return bool
     */
    protected function empty_shows_defaults(): bool {
        return false;
    }

    /**
     * Get the stored JSON.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * The stored "show" of one posted row.
     *
     * @param mixed $raw what the row's "Show to" control posted
     * @return mixed the value to store, or null when the row is invalid
     */
    protected function parse_show($raw) {
        $audiences = array_keys($this->audiences());
        $show = is_string($raw) ? $raw : $audiences[0];
        return in_array($show, $audiences, true) ? $show : $audiences[0];
    }

    /**
     * The error shown when parse_show() rejects a row.
     *
     * @return string
     */
    protected function show_error(): string {
        return get_string('errorsetting', 'admin');
    }

    /**
     * The "Show to" control of one row.
     *
     * @param string $field the input name prefix of the row's show value
     * @param array $row
     * @return string HTML
     */
    protected function show_cell(string $field, array $row): string {
        $labels = $this->audiences();
        $first = array_key_first($labels);
        $opts = '';
        foreach ($labels as $value => $label) {
            $opts .= \html_writer::tag('option', s($label),
                ['value' => $value] + ((($row['show'] ?? $first) === $value) ? ['selected' => 'selected'] : []));
        }
        return '<select class="form-select" name="' . $field . '">' . $opts . '</select>';
    }

    /**
     * Validate and store the posted rows, in the order they were posted (the order
     * an admin arranged them in with the ↑ / ↓ buttons).
     *
     * @param mixed $data ['name' => [key => string], 'url' => [key => string], 'show' => [key => mixed]]
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
                continue; // An empty row (and the always-posted marker) is simply dropped.
            }
            if ($name === '' || $url === '') {
                return get_string('footerpages_incomplete', 'theme_nit');
            }
            if ($url[0] !== '/' && !preg_match('~^https?://~i', $url)) {
                return get_string('footerpages_invalidurl', 'theme_nit', s($url));
            }
            $show = $this->parse_show($shows[$i] ?? null);
            if ($show === null) {
                return $this->show_error();
            }
            $rows[] = [
                'name' => $name,
                'url' => clean_param($url, PARAM_URL) ?: $url,
                'show' => $show,
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
                if (trim((string) $n) === '' && trim((string) ($data['url'][$i] ?? '')) === '') {
                    continue; // The hidden marker / an empty row.
                }
                $rows[] = ['name' => $n, 'url' => $data['url'][$i] ?? '', 'show' => $data['show'][$i] ?? []];
            }
        } else {
            $decoded = ($data === null || $data === false) ? null : json_decode((string) $data, true);
            $rows = is_array($decoded) && ($decoded || !$this->empty_shows_defaults()) ? $decoded : $this->default_rows();
        }

        // Each row posts under its own key ([name][k], [url][k], [show][k]…), so a row's
        // fields stay together whatever its "Show to" control posts; PHP keeps the keys
        // in page order, which is the order an admin set with ↑ / ↓.
        $full = $this->get_full_name();
        $first = array_key_first($this->audiences());
        $rowhtml = function(array $row, string $key) use ($full): string {
            $up = s(get_string('moveup'));
            $down = s(get_string('movedown'));
            return '<tr class="nit-fp-row">'
                . '<td class="text-nowrap">'
                . '<button type="button" class="btn btn-outline-secondary btn-sm" data-nit-fp-up title="' . $up . '" aria-label="' . $up . '">↑</button> '
                . '<button type="button" class="btn btn-outline-secondary btn-sm" data-nit-fp-down title="' . $down . '" aria-label="' . $down . '">↓</button>'
                . '</td>'
                . '<td><input type="text" class="form-control" name="' . $full . '[name][' . $key . ']" value="' . s($row['name'] ?? '') . '"'
                . ' placeholder="' . s(get_string('footerpages_name', 'theme_nit')) . '"></td>'
                . '<td><input type="text" class="form-control" dir="ltr" name="' . $full . '[url][' . $key . ']" value="' . s($row['url'] ?? '') . '"'
                . ' placeholder="/my/  ·  https://…"></td>'
                . '<td>' . $this->show_cell($full . '[show][' . $key . ']', $row) . '</td>'
                . '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" data-nit-fp-remove>'
                . s(get_string('footerpages_remove', 'theme_nit')) . '</button></td>'
                . '</tr>';
        };

        $body = '';
        foreach (array_values($rows) as $i => $row) {
            $body .= $rowhtml($row, 'r' . $i);
        }
        $template = $rowhtml(['name' => '', 'url' => '', 'show' => $first, 'isnew' => true], '__KEY__');
        $id = 'nit-fp-' . $this->name;

        $html = '<div class="nit-footerpages" id="' . $id . '">'
            // An always-present hidden marker so an emptied list still posts.
            . '<input type="hidden" name="' . $full . '[name][_]" value="">'
            . '<input type="hidden" name="' . $full . '[url][_]" value="">'
            . '<table class="table table-sm align-middle mb-2"><thead><tr>'
            . '<th><span class="visually-hidden">' . s(get_string('order')) . '</span></th>'
            . '<th>' . s(get_string('footerpages_name', 'theme_nit')) . '</th>'
            . '<th>' . s(get_string('footerpages_url', 'theme_nit')) . '</th>'
            . '<th>' . s(get_string('footerpages_show', 'theme_nit')) . '</th>'
            . '<th></th></tr></thead><tbody>' . $body . '</tbody></table>'
            . '<template>' . $template . '</template>'
            . '<button type="button" class="btn btn-secondary btn-sm" data-nit-fp-add>+ '
            . s(get_string('footerpages_add', 'theme_nit')) . '</button>'
            . '</div>'
            . '<script>(function(){var w=document.getElementById(' . json_encode($id) . ');if(!w){return;}'
            . 'var tb=w.querySelector("tbody"),tp=w.querySelector("template"),n=0;'
            . 'w.addEventListener("click",function(e){'
            . 'if(e.target.closest("[data-nit-fp-add]")){'
            . 'tb.insertAdjacentHTML("beforeend",tp.innerHTML.split("__KEY__").join("n"+Date.now()+"_"+(n++)));'
            . 'var r=tb.lastElementChild;if(r){var i=r.querySelector("input[type=text]");if(i){i.focus();}}}'
            . 'var tr=e.target.closest("tr");'
            . 'if(e.target.closest("[data-nit-fp-up]")&&tr.previousElementSibling){tb.insertBefore(tr,tr.previousElementSibling);e.target.closest("button").focus();}'
            . 'if(e.target.closest("[data-nit-fp-down]")&&tr.nextElementSibling){tb.insertBefore(tr.nextElementSibling,tr);e.target.closest("button").focus();}'
            . 'if(e.target.closest("[data-nit-fp-remove]")){tr.remove();}});})();</script>';

        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}
