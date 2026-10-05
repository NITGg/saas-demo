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

namespace local_academy\local;

/**
 * Study system → Division: once a system is picked, the division dropdown only
 * offers that system's divisions (academic_structure::map()).
 *
 * Added to Moodle's own forms through a hook (no core edit): the course settings
 * form and the user profile forms. Our own pages (profile, registration) call
 * script() directly.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linked_selects {

    /** Page type => [system select id, division select id]. */
    private const FORMS = [
        'course-edit' => ['id_customfield_' . academic_structure::COURSE_SYSTEM, 'id_customfield_' . academic_structure::COURSE_DIVISION],
        'user-edit' => ['id_profile_field_' . academic_structure::USER_SYSTEM, 'id_profile_field_' . academic_structure::USER_DIVISION],
        'user-editadvanced' => ['id_profile_field_' . academic_structure::USER_SYSTEM, 'id_profile_field_' . academic_structure::USER_DIVISION],
    ];

    /**
     * Hook: add the linking script to the forms above.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;
        $ids = self::FORMS[$PAGE->pagetype] ?? null;
        if ($ids) {
            $hook->add_html(self::script($ids[0], $ids[1]));
        }
    }

    /**
     * The linking script for two selects (matched by option TEXT, so it works
     * whether the option values are positions or the texts themselves).
     *
     * @param string $systemid id of the study-system select
     * @param string $divisionid id of the division select
     * @return string a <script> element
     */
    public static function script(string $systemid, string $divisionid): string {
        // The options show the names filtered ({mlang} → the page language), so match on that.
        $shown = static fn(string $name): string => format_string($name, true,
            ['context' => \context_system::instance(), 'escape' => false]);
        $map = [];
        foreach (academic_structure::map() as $system => $divisions) {
            $map[$shown($system)] = array_map($shown, $divisions);
        }
        $map = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script>(function(){'
            . 'var map=' . $map . ',sys=document.getElementById(' . json_encode($systemid) . '),'
            . 'div=document.getElementById(' . json_encode($divisionid) . ');if(!sys||!div){return;}'
            . 'function txt(o){return (o.textContent||"").trim();}'
            . 'function apply(){var o=sys.options[sys.selectedIndex];var allowed=o?map[txt(o)]:null;'
            . 'Array.prototype.forEach.call(div.options,function(d){'
            . 'var empty=(d.value===""||d.value==="0");var ok=empty||!allowed||allowed.indexOf(txt(d))!==-1;'
            . 'd.hidden=!ok;d.disabled=!ok;});'
            . 'var cur=div.options[div.selectedIndex];if(cur&&cur.disabled){div.selectedIndex=0;}}'
            . 'sys.addEventListener("change",apply);apply();})();</script>';
    }
}
