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
 * NIT topics-format renderer — branded course-detail landing page.
 *
 * On the multi-section course landing page (not editing) this replaces Moodle's
 * stock course body with a bespoke, on-brand "course detail" layout inspired by
 * the Coursera information architecture but styled entirely from the theme_nit
 * Brand Colors palette (see scss/components/_coursepage.scss).
 *
 * Everything on the page is DRIVEN BY REAL COURSE DATA — there are no static
 * placeholder values. The bands read:
 *   - the course record (name, summary, start date, image),
 *   - the section / activity tree (modules accordion, counts, assessments),
 *   - enrolled teachers (instructors), enrolment count, category chain, tags, and
 *   - the course custom fields under the "Other fields" category, mapped by
 *     shortname:
 *        course_fields ............. subject line + "skills" chips
 *        total_number_of_hours ..... duration stat
 *        language .................. language of instruction
 *        certificate (checkbox) .... "Shareable certificate"
 *        free (checkbox) ........... Free / Paid badge
 *        target_audience ........... "Who this is for"
 *        prerequisites ............. "Requirements"
 *        ilos ...................... "What you'll learn"
 *        by_the_end_of_training .... "What you'll learn"
 *
 * A field (or whole band) that has no value is simply NOT rendered — nothing is
 * ever shown as an em-dash. Short-text fields authored in the bilingual
 * "{mlang en}…{mlang}{mlang ar}…{mlang}" convention are resolved to the current
 * language via {@see acad_ml()} (the site multilang filter runs first; we fall
 * back to resolving the tags ourselves so the page is correct even where the
 * {mlang} filter is not installed). A field may also hold several items in one
 * value separated by a pipe "|" (or a newline / bullet), which become individual
 * chips — see {@see acad_chips()}.
 *
 * In editing mode (and on single-section views) we defer to the parent renderer
 * so teachers keep the normal drag/drop management UI.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_nit\output;

use renderable;
use stdClass;
use context_course;
use moodle_url;
use html_writer;
use user_picture;
use core_course_category;
use core_tag_tag;
use core_courseformat\output\local\content;

defined('MOODLE_INTERNAL') || die();

/**
 * Branded course-detail renderer for the topics format.
 */
class format_topics_renderer extends \format_topics\output\renderer {

    /**
     * Canonical delimiter for multi-value short-text custom fields.
     *
     * Single source of truth for the "chips" contract: the course-edit chips
     * editor (see {@see \local_nit_core\hook\output_callbacks::add_course_edit_chips()})
     * joins entries with this token, and {@see acad_chips()} splits on it (a bare
     * pipe is one of the separators the split regex recognises), so a field edited
     * as chips reads back as a chip list here.
     */
    const CHIP_SEP = '|';

    /**
     * Intercept the course content renderable.
     *
     * On the multi-section landing page (not editing, not a single-section view)
     * we replace Moodle's course body with the branded detail layout. Everything
     * else falls through to the stock format renderer.
     *
     * @param renderable $widget instance with renderable interface
     * @return string the widget HTML
     */
    public function render(renderable $widget) {
        if ($widget instanceof content && !$this->page->user_is_editing()) {
            $format = course_get_format($this->page->course);
            // get_sectionid() is null on the "all sections" landing page.
            if (!$format->get_sectionid()) {
                return $this->acad_render_page($format);
            }
        }
        return parent::render($widget);
    }

    /**
     * Build the full branded page for the given course format.
     *
     * Content bands are built first (each returns '' when it has no data) so the
     * sticky tab bar can advertise only the sections that actually appear.
     *
     * @param \core_courseformat\base $format the course format
     * @return string HTML
     */
    protected function acad_render_page($format): string {
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = context_course::instance($course->id);

        $data = $this->acad_gather($course, $modinfo, $context);

        // Content bands + the tabs they map to (id => label), in display order.
        $tabs  = [];
        $body  = '';

        $learn = $this->acad_learn($data);
        if ($learn !== '') {
            $tabs['about'] = get_string('acad_about', 'theme_nit');
            $body .= $learn;
        }

        $skills = $this->acad_skills($data);
        if ($skills !== '') {
            $tabs['skills'] = get_string('acad_skills_tab', 'theme_nit');
            $body .= $skills;
        }

        $requirements = $this->acad_requirements($data);
        if ($requirements !== '') {
            $tabs['requirements'] = get_string('acad_requirements', 'theme_nit');
            $body .= $requirements;
        }

        $expertise = $this->acad_expertise($course, $context, $data);
        if ($expertise !== '') {
            $body .= $expertise;
        }

        // Modules band always renders (the section tree is the core of the page).
        $tabs['modules'] = get_string('acad_modules', 'theme_nit');
        $body .= $this->acad_modules($course, $modinfo, $context, $data);

        // Assemble in visual order. The brand group class lets a course adopt its
        // top-level category's palette (Group 1/2/3), same as category pages.
        $groupclass = '';
        if (function_exists('theme_nit_category_brand_group') && $course->category) {
            $groupclass = theme_nit_brand_group_class(theme_nit_category_brand_group($course->category));
        }

        $o  = html_writer::start_div('acad-cr' . ($groupclass ? ' ' . $groupclass : ''));
        $o .= $this->acad_breadcrumb($data);
        $o .= $this->acad_hero($course, $context, $data);
        $o .= $this->acad_tabs($tabs);
        $o .= $body;
        $o .= html_writer::end_div();

        // Accordion / tab helpers. A format renderer runs after <head> is flushed,
        // so $PAGE->requires->js() would be dropped; emit a plain inline <script>.
        $o .= html_writer::script($this->acad_inline_js());

        return $o;
    }

    // =========================================================================
    // Data gathering — one pass, so each band just reads $data.
    // =========================================================================

    /**
     * Collect everything the bands need in a single pass.
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @return stdClass
     */
    protected function acad_gather($course, $modinfo, $context) {
        global $DB;

        $d = new stdClass();
        $d->course   = $course;
        $d->context  = $context;
        $d->enrolled = count_enrolled_users($context);

        // Category chain for the breadcrumb.
        $d->catnames = [];
        if ($course->category) {
            $cat = core_course_category::get($course->category, IGNORE_MISSING);
            if ($cat) {
                foreach (array_reverse($cat->get_parents()) as $pid) {
                    $p = $DB->get_record('course_categories', ['id' => $pid], 'name');
                    if ($p) {
                        $d->catnames[] = format_string($p->name);
                    }
                }
                $d->catnames[] = format_string($cat->get_formatted_name());
            }
        }

        // Visible sections (become the module accordion rows).
        $numsections   = course_get_format($course)->get_last_section_number();
        $d->modulerows = [];
        $d->modcount   = 0;
        $d->assesscount = 0;
        foreach ($modinfo->get_section_info_all() as $snum => $sec) {
            if ($snum === 0) {
                if (empty($modinfo->sections[0]) || !$sec->uservisible) {
                    continue;
                }
            }
            if ($snum > $numsections) {
                continue;
            }
            $show = $sec->uservisible ||
                ($sec->visible && !$sec->available && !empty($sec->availableinfo)) ||
                (!$sec->visible && !$course->hiddensections);
            if (!$show) {
                continue;
            }
            $d->modulerows[$snum] = $sec;
            $d->modcount++;
        }

        // Count graded assessment activities (real "Assessments" figure).
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (in_array($cm->modname, ['assign', 'quiz', 'workshop', 'lesson'], true)) {
                $d->assesscount++;
            }
        }

        // Teachers (Instructors).
        $d->teachers = $this->acad_teachers($context);

        // Skills fall back to course tags when the course_fields custom field is empty.
        $d->tags = core_tag_tag::get_item_tags('core', 'course', $course->id);

        // Course image.
        $d->image = $this->acad_course_image_url($context);

        // Start date.
        $d->startlabel = $course->startdate ? userdate($course->startdate, get_string('strftimedatefullshort')) : '';

        // Course custom fields under "Other fields", keyed by shortname, respecting
        // visibility. Hidden / teacher-only fields are dropped for public viewers.
        $d->cf = [];
        $canviewhidden = has_capability('moodle/course:update', $context);
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($course->id, true) as $fd) {
                $field = $fd->get_field();
                $vis   = (int) $field->get_configdata_property('visibility');
                // 0 = nobody, 1 = teachers, 2 = everyone.
                if ($vis < 2 && !$canviewhidden) {
                    continue;
                }
                $d->cf[$field->get('shortname')] = (object) [
                    'type' => $field->get('type'),
                    'raw'  => $fd->get_value(),
                ];
            }
        } catch (\Throwable $e) {
            $d->cf = [];
        }

        return $d;
    }

    /**
     * A count rendered with the correct singular / plural language string.
     *
     * @param int $n
     * @param string $onekey singular string key
     * @param string $manykey plural string key
     * @return string
     */
    protected function acad_count($n, $onekey, $manykey): string {
        return get_string(((int) $n === 1) ? $onekey : $manykey, 'theme_nit', $n);
    }

    /**
     * Enrolled teachers for the "Instructors" slots.
     *
     * @param \context_course $context
     * @return array
     */
    protected function acad_teachers($context) {
        $out   = [];
        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        if (empty($roles)) {
            return $out;
        }
        // With multiple role ids, get_role_users() requires the first field to be
        // the unique role-assignment id (ra.id) — otherwise it emits a developer
        // debugging warning. The duplicate users this can yield (a teacher holding
        // both roles) are collapsed by the $seen check below.
        $fields = 'ra.id AS raid, u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
        $users = get_role_users(array_keys($roles), $context, false, $fields);
        $seen  = [];
        foreach ($users as $u) {
            if (isset($seen[$u->id])) {
                continue;
            }
            $seen[$u->id] = true;
            $out[] = $u;
        }
        return $out;
    }

    // =========================================================================
    // Custom-field helpers
    // =========================================================================

    /**
     * Resolve a bilingual "{mlang}" value to the current language as plain text.
     *
     * The site's multilang filter (if installed) runs first via format_string();
     * if {mlang} tags survive we resolve them ourselves so the page is correct
     * even where the {mlang} filter is not present. Returns '' for empty input.
     *
     * @param string|null $raw
     * @param stdClass $data
     * @return string
     */
    protected function acad_ml($raw, $data): string {
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }
        $out = format_string($raw, true, ['context' => $data->context]);
        if (stripos($out, '{mlang') === false) {
            return trim($out);
        }
        return trim($this->acad_resolve_mlang($out));
    }

    /**
     * Resolve {mlang XX}…{mlang} blocks for the current language.
     *
     * Blocks whose language list contains the current language win; otherwise
     * "other" blocks are used; failing both, the first block is shown so a value
     * is never lost. Mirrors filter_multilang2 selection behaviour.
     *
     * @param string $text
     * @return string
     */
    protected function acad_resolve_mlang($text): string {
        $lang = current_language();
        $pattern = '/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is';
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return $text;
        }
        $matched = '';
        $other   = '';
        $first   = null;
        foreach ($matches as $block) {
            $langs   = array_map('trim', explode(',', strtolower($block[1])));
            $content = $block[2];
            if ($first === null) {
                $first = $content;
            }
            if (in_array($lang, $langs, true)) {
                $matched .= $content;
            }
            if (in_array('other', $langs, true)) {
                $other .= $content;
            }
        }
        if ($matched !== '') {
            return $matched;
        }
        if ($other !== '') {
            return $other;
        }
        return $first ?? '';
    }

    /**
     * A single short-text custom field, resolved to plain text ('' when absent).
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_text($shortname, $data): string {
        if (!isset($data->cf[$shortname])) {
            return '';
        }
        return $this->acad_ml($data->cf[$shortname]->raw, $data);
    }

    /**
     * A checkbox custom field as bool (false when absent).
     *
     * @param string $shortname
     * @param stdClass $data
     * @return bool
     */
    protected function acad_cf_bool($shortname, $data): bool {
        if (!isset($data->cf[$shortname])) {
            return false;
        }
        return (bool) $data->cf[$shortname]->raw;
    }

    /**
     * A number custom field as a trimmed display string ('' when absent/zero-empty).
     *
     * @param string $shortname
     * @param stdClass $data
     * @return string
     */
    protected function acad_cf_number($shortname, $data): string {
        if (!isset($data->cf[$shortname])) {
            return '';
        }
        $val = $data->cf[$shortname]->raw;
        if ($val === null || $val === '' || (is_numeric($val) && (float) $val == 0.0)) {
            return '';
        }
        // Whole numbers show without the ".0" the number field stores; keep any
        // genuine decimal part.
        $f = (float) $val;
        return ($f == (int) $f) ? (string) (int) $f : rtrim(rtrim((string) $f, '0'), '.');
    }

    /**
     * Split one short-text value into a clean list of chips.
     *
     * Resolves {mlang} first, then splits on explicit list separators — pipe "|",
     * newline, and the bullet "•". When $listsep is true the value is also split
     * on commas (Arabic "،" included), which suits keyword/tag style fields such
     * as course_fields. Returns [] for empty input.
     *
     * @param string $shortname
     * @param stdClass $data
     * @param bool $listsep also split on commas
     * @return string[]
     */
    protected function acad_chips($shortname, $data, bool $listsep = false): array {
        $text = $this->acad_cf_text($shortname, $data);
        if ($text === '') {
            return [];
        }
        $sep = $listsep ? '/[|\n•،,]+/u' : '/[|\n•]+/u';
        $parts = preg_split($sep, $text);
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, function ($p) {
            return $p !== '';
        }));
    }

    // =========================================================================
    // Bands
    // =========================================================================

    /**
     * Breadcrumb band (Browse › category chain).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_breadcrumb($data) {
        $parts = array_merge([get_string('acad_browse', 'theme_nit')], $data->catnames);
        $html  = '';
        $last  = count($parts) - 1;
        foreach ($parts as $i => $p) {
            // $p is already HTML-safe (a lang string or format_string() output).
            $html .= html_writer::tag('span', $p, ['class' => 'acad-cr__crumb']);
            if ($i < $last) {
                $html .= html_writer::tag('span', '›', ['class' => 'acad-cr__crumb-sep', 'aria-hidden' => 'true']);
            }
        }
        return html_writer::div(html_writer::div($html, 'acad-cr__crumbs'), 'acad-cr__wrap');
    }

    /**
     * Hero band: provider, title, subject, instructor, CTA + badges, and the
     * "at a glance" facts card (the page's signature element).
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_hero($course, $context, $data) {
        global $USER;

        $isenrolled = is_enrolled($context, $USER->id, '', true);
        if ($isenrolled) {
            $url   = new moodle_url('/course/view.php', ['id' => $course->id]);
            $label = get_string('acad_gotocourse', 'theme_nit');
        } else {
            $url   = new moodle_url('/enrol/index.php', ['id' => $course->id]);
            $label = get_string('acad_enrol', 'theme_nit');
        }

        // Provider = top-level category.
        $provider = !empty($data->catnames) ? $data->catnames[0] : format_string($course->shortname);

        $o  = html_writer::start_div('acad-cr__hero');

        // ---- Left main ----
        $o .= html_writer::start_div('acad-cr__hero-main');
        $o .= html_writer::div($provider, 'acad-cr__provider');
        $o .= html_writer::tag('h1', format_string($course->fullname), ['class' => 'acad-cr__title']);

        // Subject line from course_fields (real), else nothing. Already HTML-safe.
        $subject = $this->acad_cf_text('course_fields', $data);
        if ($subject !== '') {
            $o .= html_writer::tag('p', $subject, ['class' => 'acad-cr__partof']);
        }

        // Instructor line from enrolled teachers only (real).
        if (!empty($data->teachers)) {
            $first = fullname($data->teachers[0]);
            $more  = count($data->teachers) - 1;
            $instr = html_writer::tag('strong', get_string('acad_instructor', 'theme_nit') . ' ')
                   . html_writer::tag('span', s($first), ['class' => 'acad-cr__instr-name'])
                   . ($more > 0 ? ' ' . get_string('acad_plusmore', 'theme_nit', $more) : '');
            $o .= html_writer::div($instr, 'acad-cr__instructor');
        }

        // CTA row: enrol/go button + free-or-paid badge + start date.
        $o .= html_writer::start_div('acad-cr__cta-row');
        $o .= html_writer::link($url, s($label), ['class' => 'btn btn-primary acad-cr-btn']);
        if ($this->acad_cf_bool('free', $data)) {
            $o .= html_writer::tag('span', get_string('acad_free', 'theme_nit'),
                ['class' => 'acad-cr__badge acad-cr__badge--free']);
        }
        if ($data->startlabel !== '') {
            $o .= html_writer::tag('span',
                get_string('acad_starts', 'theme_nit', s($data->startlabel)),
                ['class' => 'acad-cr__starts']);
        }
        $o .= html_writer::end_div();

        if ($data->enrolled > 0) {
            $o .= html_writer::div(
                get_string('acad_enrolledcount', 'theme_nit', html_writer::tag('b', number_format($data->enrolled))),
                'acad-cr__enrolled');
        }
        $o .= html_writer::end_div(); // hero-main.

        // ---- Right aside: "at a glance" facts (signature) ----
        $o .= $this->acad_glance($data);

        $o .= html_writer::end_div(); // hero.
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /**
     * "At a glance" facts card: only rows that have real data are shown.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_glance($data) {
        $rows = [];

        // Modules.
        if ($data->modcount > 0) {
            $rows[] = [$this->acad_icon('modules'),
                get_string('acad_modules', 'theme_nit'),
                $this->acad_count($data->modcount, 'acad_nmodule', 'acad_nmodules')];
        }
        // Duration (hours).
        $hours = $this->acad_cf_number('total_number_of_hours', $data);
        if ($hours !== '') {
            $rows[] = [$this->acad_icon('clock'),
                get_string('acad_duration', 'theme_nit'),
                get_string(($hours === '1') ? 'acad_nhour' : 'acad_nhours', 'theme_nit', s($hours))];
        }
        // Assessments (computed from the activity tree).
        if ($data->assesscount > 0) {
            $rows[] = [$this->acad_icon('assess'),
                get_string('acad_assessments', 'theme_nit'),
                $this->acad_count($data->assesscount, 'acad_nassessment', 'acad_nassessments')];
        }
        // Language of instruction.
        $lang = $this->acad_cf_text('language', $data);
        if ($lang !== '') {
            // $lang is already HTML-safe (resolved via format_string/{mlang}).
            $rows[] = [$this->acad_icon('lang'), get_string('acad_language', 'theme_nit'), $lang];
        }
        // Certificate.
        if ($this->acad_cf_bool('certificate', $data)) {
            $rows[] = [$this->acad_icon('cert'),
                get_string('acad_certificate', 'theme_nit'),
                get_string('acad_certificate_sub', 'theme_nit')];
        }

        if (empty($rows)) {
            return '';
        }

        $body = '';
        foreach ($rows as $r) {
            $body .= html_writer::div(
                html_writer::div($r[0], 'acad-cr__glance-ico') .
                html_writer::div(
                    html_writer::div($r[1], 'acad-cr__glance-k') .
                    html_writer::div($r[2], 'acad-cr__glance-v'),
                    'acad-cr__glance-txt'
                ),
                'acad-cr__glance-row'
            );
        }

        return html_writer::div(
            html_writer::tag('h2', get_string('acad_ataglance', 'theme_nit'), ['class' => 'acad-cr__glance-h']) . $body,
            'acad-cr__glance');
    }

    /**
     * Sticky tab bar built from the bands that actually rendered.
     *
     * @param array $tabs id => label
     * @return string
     */
    protected function acad_tabs($tabs) {
        if (count($tabs) < 2) {
            return '';
        }
        $o = html_writer::start_div('acad-cr__tabs');
        $first = true;
        foreach ($tabs as $id => $label) {
            $cls = 'acad-cr__tab' . ($first ? ' is-active' : '');
            $o .= html_writer::tag('button', s($label), [
                'class'      => $cls,
                'type'       => 'button',
                'data-crtab' => $id,
                'onclick'    => 'AcademyUI.crTab(this)',
            ]);
            $first = false;
        }
        $o .= html_writer::end_div();
        return html_writer::div($o, 'acad-cr__wrap');
    }

    /**
     * "What you'll learn" band — Intended Learning Outcomes + end-of-training
     * statements. Empty ⇒ '' (band omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_learn($data) {
        $items = array_merge(
            $this->acad_chips('ilos', $data),
            $this->acad_chips('by_the_end_of_training', $data)
        );
        if (empty($items)) {
            return '';
        }

        $check = $this->acad_icon('check');
        $grid  = '';
        foreach ($items as $item) {
            // $item is already HTML-safe (resolved via format_string/{mlang}).
            $grid .= html_writer::div($check . html_writer::tag('span', $item), 'acad-cr__learn-item');
        }

        $inner = html_writer::tag('h2', get_string('acad_learn', 'theme_nit'),
                    ['class' => 'acad-cr__h2', 'id' => 'about'])
               . html_writer::div(html_writer::div($grid, 'acad-cr__learn-grid'), 'acad-cr__learn-card');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * "Course fields / Skills" band — course_fields chips, else course tags.
     * Empty ⇒ '' (band omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_skills($data) {
        $items = $this->acad_chips('course_fields', $data, true);
        $pills = '';
        if (!empty($items)) {
            foreach ($items as $item) {
                // $item is already HTML-safe (resolved via format_string/{mlang}).
                $pills .= html_writer::tag('span', $item, ['class' => 'acad-cr__pill']);
            }
        } else if (!empty($data->tags)) {
            foreach ($data->tags as $tag) {
                $pills .= html_writer::tag('span', format_string($tag->get_display_name()), ['class' => 'acad-cr__pill']);
            }
        } else {
            return '';
        }

        $inner = html_writer::tag('h2', get_string('acad_skills', 'theme_nit'),
                    ['class' => 'acad-cr__h2', 'id' => 'skills'])
               . html_writer::div($pills, 'acad-cr__skills');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * "Requirements & audience" band — prerequisites + target audience.
     * Empty ⇒ '' (band omitted).
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_requirements($data) {
        $cards = '';

        $audience = $this->acad_chips('target_audience', $data);
        if (!empty($audience)) {
            $cards .= $this->acad_req_card(
                $this->acad_icon('people'),
                get_string('acad_audience', 'theme_nit'),
                $audience);
        }

        $prereq = $this->acad_chips('prerequisites', $data);
        if (!empty($prereq)) {
            $cards .= $this->acad_req_card(
                $this->acad_icon('list'),
                get_string('acad_prerequisites', 'theme_nit'),
                $prereq);
        }

        if ($cards === '') {
            return '';
        }

        $inner = html_writer::tag('h2', get_string('acad_requirements', 'theme_nit'),
                    ['class' => 'acad-cr__h2', 'id' => 'requirements'])
               . html_writer::div($cards, 'acad-cr__req-grid');

        return html_writer::div(html_writer::div($inner, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * One requirements card (icon + heading + list of points).
     *
     * @param string $icon
     * @param string $heading
     * @param string[] $points
     * @return string
     */
    protected function acad_req_card($icon, $heading, array $points) {
        $list = '';
        foreach ($points as $p) {
            // $p is already HTML-safe (resolved via format_string/{mlang}).
            $list .= html_writer::tag('li', $p);
        }
        return html_writer::div(
            html_writer::div($icon . html_writer::tag('span', $heading), 'acad-cr__req-h') .
            html_writer::tag('ul', $list, ['class' => 'acad-cr__req-list']),
            'acad-cr__req-card'
        );
    }

    /**
     * "About this course" band — the course summary. Empty ⇒ '' (band omitted).
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_expertise($course, $context, $data) {
        $summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);
        if (trim(strip_tags($summary)) === '') {
            return '';
        }

        $left = html_writer::tag('h2', get_string('acad_about_h', 'theme_nit'), ['class' => 'acad-cr__h2'])
              . html_writer::div($summary, 'acad-cr__summary');

        return html_writer::div(
            html_writer::div(html_writer::div($left, 'acad-cr__wrap'), 'acad-cr__band'),
            'acad-cr__soft'
        );
    }

    // =========================================================================
    // Modules band (accordion + instructor rail)
    // =========================================================================

    /**
     * Modules band: accordion (left) + instructor rail (right).
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_modules($course, $modinfo, $context, $data) {
        $acc = '';
        $idx = 0;
        foreach ($data->modulerows as $snum => $section) {
            $idx++;
            $acc .= $this->acad_module_row($course, $section, $modinfo, $snum, $idx, ($idx === 1), $context);
        }

        $left = html_writer::tag('h2',
                    $this->acad_count($data->modcount, 'acad_1modulein', 'acad_nmodulesin'),
                    ['class' => 'acad-cr__h2', 'id' => 'modules'])
              . html_writer::div($acc, 'acad-cr__acc');
        $rail = $this->acad_rail($course, $context, $data);

        $grid = html_writer::div(
            html_writer::div($left, 'acad-cr__modules-main') .
            html_writer::div($rail, 'acad-cr__modules-rail'),
            'acad-cr__modules-grid'
        );

        return html_writer::div(html_writer::div($grid, 'acad-cr__wrap'), 'acad-cr__band');
    }

    /**
     * One accordion row (section header + collapsible body).
     *
     * @param stdClass $course
     * @param \section_info $section
     * @param \course_modinfo $modinfo
     * @param int $snum
     * @param int $idx
     * @param bool $open
     * @param \context_course $context
     * @return string
     */
    protected function acad_module_row($course, $section, $modinfo, $snum, $idx, $open, $context) {
        $title  = get_section_name($course, $section);
        $bodyid = 'acad-cr-mod-' . $snum;
        $cmlist = !empty($modinfo->sections[$snum]) ? $modinfo->sections[$snum] : [];

        // Count visible activities + a "What's included" tally by module type.
        $typecounts = [];
        $visitems   = 0;
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }
            $visitems++;
            $plural = (string) $cm->modplural;
            $typecounts[$plural] = ($typecounts[$plural] ?? 0) + 1;
        }
        $included = [];
        foreach ($typecounts as $plural => $count) {
            $included[] = html_writer::tag('b', $count) . ' ' . s($plural);
        }

        $o  = html_writer::start_div('acad-cr__mod' . ($open ? ' is-open' : ''), ['id' => 'acad-cr-modwrap-' . $snum]);

        // Header.
        $o .= html_writer::start_tag('button', [
            'class'         => 'acad-cr__mod-head',
            'type'          => 'button',
            'onclick'       => 'AcademyUI.crModule(this)',
            'aria-expanded' => $open ? 'true' : 'false',
            'aria-controls' => $bodyid,
        ]);
        $meta = get_string('acad_modulen', 'theme_nit', $idx);
        if ($visitems > 0) {
            $meta .= ' · ' . $this->acad_count($visitems, 'acad_nitem', 'acad_nitems');
        }
        $o .= html_writer::div(
            html_writer::tag('div', format_string($title), ['class' => 'acad-cr__mod-title']) .
            html_writer::tag('div', $meta, ['class' => 'acad-cr__mod-meta'])
        );
        $o .= html_writer::tag('span',
            get_string('acad_moduledetails', 'theme_nit') . $this->acad_icon('chevron'),
            ['class' => 'acad-cr__mod-toggle']);
        $o .= html_writer::end_tag('button');

        // Body.
        $o .= html_writer::start_div('acad-cr__mod-body', ['id' => $bodyid, 'role' => 'region']);

        if ($section->uservisible && !empty($section->summary)) {
            $desc = format_text($section->summary, $section->summaryformat, ['context' => $context]);
            if (trim(strip_tags($desc)) !== '') {
                $o .= html_writer::div($desc, 'acad-cr__mod-desc');
            }
        }

        if (!empty($included)) {
            $o .= html_writer::tag('div', get_string('acad_included', 'theme_nit'), ['class' => 'acad-cr__included-h']);
            $o .= html_writer::div(implode(' · ', $included), 'acad-cr__included-sum');
        }

        if (!$section->uservisible) {
            if (!empty($section->availableinfo)) {
                $locked = \core_availability\info::format_info($section->availableinfo, $course);
                $o .= html_writer::div($locked, 'acad-cr__act-locked');
            }
        } else {
            $o .= $this->acad_activities($modinfo, $cmlist);
        }

        $o .= html_writer::end_div(); // body.
        $o .= html_writer::end_div(); // mod.
        return $o;
    }

    /**
     * Activity rows inside one module body.
     *
     * @param \course_modinfo $modinfo
     * @param array $cmlist
     * @return string
     */
    protected function acad_activities($modinfo, $cmlist) {
        $o = '';
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            $ico = html_writer::empty_tag('img', [
                'src' => $cm->get_icon_url(), 'alt' => '', 'class' => 'acad-cr__act-ico', 'aria-hidden' => 'true',
            ]);
            $name = $cm->url
                ? html_writer::link($cm->url, format_string($cm->name))
                : format_string($cm->name);

            $o .= html_writer::div(
                $ico .
                html_writer::div($name, 'acad-cr__act-name') .
                html_writer::tag('span', s((string) $cm->modfullname), ['class' => 'acad-cr__act-type']),
                'acad-cr__act'
            );
        }
        return $o;
    }

    /**
     * Right-hand instructor + "offered by" rail.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_rail($course, $context, $data) {
        $out = '';

        // Instructors card (only when there are teachers).
        if (!empty($data->teachers)) {
            $tutors = '';
            foreach ($data->teachers as $t) {
                $userpic = new user_picture($t);
                $userpic->size = 100;
                $avatar = $this->output->render($userpic);
                $profileurl = new moodle_url('/user/view.php', ['id' => $t->id, 'course' => $course->id]);
                $tutors .= html_writer::div(
                    $avatar .
                    html_writer::div(
                        html_writer::link($profileurl, s(fullname($t)), ['class' => 'acad-cr__tutor-name']) .
                        html_writer::div(get_string('acad_instructorrole', 'theme_nit'), 'acad-cr__tutor-org')
                    ),
                    'acad-cr__tutor'
                );
            }
            $out .= html_writer::div(
                html_writer::tag('div', get_string('acad_instructors', 'theme_nit'), ['class' => 'acad-cr__rail-h']) .
                $tutors,
                'acad-cr__rail-card'
            );
        }

        // Offered by = top-level category.
        if (!empty($data->catnames)) {
            $out .= html_writer::div(
                html_writer::tag('div', get_string('acad_offeredby', 'theme_nit'), ['class' => 'acad-cr__offered-h']) .
                html_writer::div($data->catnames[0], 'acad-cr__offered-logo'),
                'acad-cr__rail-card'
            );
        }

        return $out;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * URL of the course overview image, or null when none is set.
     *
     * @param \context_course $context
     * @return moodle_url|null
     */
    protected function acad_course_image_url($context) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id DESC', false);
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $context->id, 'course', 'overviewfiles', null, $file->get_filepath(), $file->get_filename());
        }
        return null;
    }

    /**
     * Small inline SVG icon (stroke = currentColor) by key.
     *
     * @param string $key
     * @return string
     */
    protected function acad_icon($key): string {
        $paths = [
            'check'   => '<path d="M4 10l4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'chevron' => '<polyline points="5,8 10,13 15,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'modules' => '<rect x="3" y="4" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="3" y="10" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="3" y="16" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/>',
            'clock'   => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
            'assess'  => '<rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'lang'    => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" stroke="currentColor" stroke-width="1.7"/>',
            'cert'    => '<circle cx="12" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M8 14l-1 7 5-3 5 3-1-7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'people'  => '<circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16 5.5a3.2 3.2 0 0 1 0 5M17.5 20a5.5 5.5 0 0 0-2.5-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'list'    => '<path d="M8 6h12M8 12h12M8 18h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="4" cy="6" r="1.3" fill="currentColor"/><circle cx="4" cy="12" r="1.3" fill="currentColor"/><circle cx="4" cy="18" r="1.3" fill="currentColor"/>',
        ];
        $p = $paths[$key] ?? '';
        return '<svg class="acad-cr__ico acad-cr__ico--' . $key . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
            . $p . '</svg>';
    }

    /**
     * Inline accordion / tab helpers. Emitted in the body because a format
     * renderer runs after <head> is flushed.
     *
     * @return string JavaScript
     */
    protected function acad_inline_js() {
        return <<<'JS'
(function (w) {
    'use strict';
    w.AcademyUI = w.AcademyUI || {};

    // Accordion row: header button toggles .is-open on its .acad-cr__mod wrapper.
    w.AcademyUI.crModule = function (btn) {
        var row = btn.closest('.acad-cr__mod');
        if (!row) { return; }
        var open = row.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    // Sticky tab bar: activate the clicked tab and smooth-scroll to its anchor.
    w.AcademyUI.crTab = function (btn) {
        var bar = btn.closest('.acad-cr__tabs');
        if (bar) {
            bar.querySelectorAll('.acad-cr__tab').forEach(function (t) {
                t.classList.remove('is-active');
            });
        }
        btn.classList.add('is-active');
        var id = btn.getAttribute('data-crtab');
        var target = id && document.getElementById(id);
        if (target) {
            var top = target.getBoundingClientRect().top + window.pageYOffset - 70;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    };
})(window);
JS;
    }
}
