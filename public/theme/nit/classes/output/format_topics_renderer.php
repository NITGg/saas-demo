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
                // The T1 course LANDING (with the Buy/Enrol card) is for prospective
                // learners only. Enrolled learners and staff/managers see the normal
                // Moodle course content (untouched) — never the buy landing on their
                // own course.
                $context = \context_course::instance($this->page->course->id);
                $canaccesscontent = is_enrolled($context, null, '', true)
                    || has_capability('moodle/course:update', $context)
                    || is_siteadmin();
                if (!$canaccesscontent) {
                    return $this->acad_render_page($format);
                }
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

        // Brand group class lets a course adopt its top-level category's palette
        // (Group 1/2/3), same as category pages.
        $groupclass = '';
        if (function_exists('theme_nit_category_brand_group') && $course->category) {
            $groupclass = theme_nit_brand_group_class(theme_nit_category_brand_group($course->category));
        }

        // ---- Left column (content) — each band returns '' when it has no data. ----
        $main  = $this->acad_hero($course, $context, $data);   // header: title / meta / instructor.
        $main .= $this->acad_learn($data);                     // "What you'll learn".
        $main .= $this->acad_skills($data);                    // "Skills you'll gain".
        $main .= $this->acad_requirements($data);              // "Requirements".
        $main .= $this->acad_modules($course, $modinfo, $context, $data); // "Curriculum".
        $main .= $this->acad_rail($course, $context, $data);   // "Instructor".

        // ---- Right column (sticky purchase card). ----
        $aside = $this->acad_purchase_card($course, $context, $data);

        $layout = html_writer::div(
            html_writer::div($main, 'acadt1__main') .
            html_writer::tag('aside', $aside, ['class' => 'acadt1__aside']),
            'acadt1__layout'
        );

        $o  = html_writer::start_div('acadt1' . ($groupclass ? ' ' . $groupclass : ''), ['dir' => 'auto']);
        $o .= $this->acad_styles();
        $o .= html_writer::start_div('acadt1__wrap');
        $o .= $this->acad_breadcrumb($data);
        $o .= $layout;
        $o .= html_writer::end_div(); // wrap.
        $o .= html_writer::end_div(); // acadt1.

        // Accordion helper + font loader. A format renderer runs after <head> is
        // flushed, so $PAGE->requires->js() would be dropped; emit inline <script>.
        $o .= html_writer::script($this->acad_inline_js());
        // Buy buttons -> shared checkout modal (Kashier), mirroring nit_category.
        $o .= $this->acad_checkout_js();

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
        $d->itemcount   = 0;
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
            $d->itemcount++;
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
        // Category chain + the course itself as the trailing (current) crumb.
        $parts = array_merge($data->catnames, [format_string($data->course->fullname)]);
        $html  = '';
        $last  = count($parts) - 1;
        foreach ($parts as $i => $p) {
            // $p is already HTML-safe (a lang string or format_string() output).
            $cls = 'acadt1__crumb' . ($i === $last ? ' is-current' : '');
            $html .= html_writer::tag('span', $p, ['class' => $cls]);
            if ($i < $last) {
                $html .= html_writer::tag('span', '/', ['class' => 'acadt1__crumb-sep', 'aria-hidden' => 'true']);
            }
        }
        return html_writer::tag('nav', $html, ['class' => 'acadt1__crumbs']);
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
        $o = html_writer::start_div('acadt1__header');

        // Eyebrow = top-level category (provider).
        $provider = !empty($data->catnames) ? $data->catnames[0] : format_string($course->shortname);
        $o .= html_writer::div($provider, 'acadt1__eyebrow');

        // Status chips — only real facts (free course, shareable certificate).
        $chips = '';
        if ($this->acad_cf_bool('free', $data)) {
            $chips .= html_writer::tag('span', get_string('acad_free', 'theme_nit'),
                ['class' => 'acadt1__chip acadt1__chip--free']);
        }
        if ($this->acad_cf_bool('certificate', $data)) {
            $chips .= html_writer::tag('span', get_string('acad_certificate', 'theme_nit'), ['class' => 'acadt1__chip']);
        }
        if ($chips !== '') {
            $o .= html_writer::div($chips, 'acadt1__chips');
        }

        // Title.
        $o .= html_writer::tag('h1', format_string($course->fullname), ['class' => 'acadt1__title']);

        // Subject line from course_fields (real). Already HTML-safe.
        $subject = $this->acad_cf_text('course_fields', $data);
        if ($subject !== '') {
            $o .= html_writer::tag('p', $subject, ['class' => 'acadt1__subject']);
        }

        // Short summary ("About this course").
        $o .= $this->acad_expertise($course, $context, $data);

        // Meta row — real facts only (learners, updated date, language).
        $meta = '';
        if ($data->enrolled > 0) {
            $meta .= html_writer::div(
                $this->acad_icon('people') .
                html_writer::tag('span', $this->acad_count($data->enrolled, 'acad_1learner', 'acad_nlearners')),
                'acadt1__meta-item');
        }
        $updated = $course->timemodified ? userdate($course->timemodified, get_string('strftimedatefullshort')) : '';
        if ($updated !== '') {
            $meta .= html_writer::div(
                $this->acad_icon('clock') .
                html_writer::tag('span', get_string('acad_updated', 'theme_nit', s($updated))),
                'acadt1__meta-item');
        }
        $lang = $this->acad_cf_text('language', $data);
        if ($lang !== '') {
            // $lang is already HTML-safe (resolved via format_string/{mlang}).
            $meta .= html_writer::div($this->acad_icon('lang') . html_writer::tag('span', $lang), 'acadt1__meta-item');
        }
        if ($meta !== '') {
            $o .= html_writer::div($meta, 'acadt1__meta');
        }

        // Instructor line from enrolled teachers only (real).
        if (!empty($data->teachers)) {
            $first = fullname($data->teachers[0]);
            $more  = count($data->teachers) - 1;
            $instr = get_string('acad_instructor', 'theme_nit') . ' '
                   . html_writer::tag('b', s($first))
                   . ($more > 0 ? ' ' . get_string('acad_plusmore', 'theme_nit', $more) : '');
            $o .= html_writer::div($instr, 'acadt1__instructor');
        }

        $o .= html_writer::end_div(); // header.
        return $o;
    }

    /**
     * "At a glance" facts card: only rows that have real data are shown.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_glance($data) {
        $rows = [];

        // Lessons (visible activities) + modules — the course's real shape.
        if ($data->itemcount > 0) {
            $rows[] = [$this->acad_icon('book'),
                $this->acad_count($data->itemcount, 'acad_1lesson', 'acad_nlessons')];
        }
        if ($data->modcount > 0) {
            $rows[] = [$this->acad_icon('modules'),
                $this->acad_count($data->modcount, 'acad_nmodule', 'acad_nmodules')];
        }
        // Duration (hours) — real custom field.
        $hours = $this->acad_cf_number('total_number_of_hours', $data);
        if ($hours !== '') {
            $rows[] = [$this->acad_icon('clock'),
                get_string(($hours === '1') ? 'acad_nhour' : 'acad_nhours', 'theme_nit', s($hours))];
        }
        // Assessments (computed from the activity tree).
        if ($data->assesscount > 0) {
            $rows[] = [$this->acad_icon('assess'),
                $this->acad_count($data->assesscount, 'acad_nassessment', 'acad_nassessments')];
        }
        // Language of instruction.
        $lang = $this->acad_cf_text('language', $data);
        if ($lang !== '') {
            // $lang is already HTML-safe (resolved via format_string/{mlang}).
            $rows[] = [$this->acad_icon('lang'), $lang];
        }
        // Shareable certificate.
        if ($this->acad_cf_bool('certificate', $data)) {
            $rows[] = [$this->acad_icon('cert'), get_string('acad_certificate_sub', 'theme_nit')];
        }
        // Lifetime access is a real fact of enrolment on this platform.
        $rows[] = [$this->acad_icon('infinity'), get_string('acad_lifetime', 'theme_nit')];

        if (empty($rows)) {
            return '';
        }

        $body = '';
        foreach ($rows as $r) {
            $body .= html_writer::div($r[0] . html_writer::tag('span', $r[1]), 'acadt1__feat');
        }
        return html_writer::div($body, 'acadt1__features');
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
            $grid .= html_writer::div($check . html_writer::tag('span', $item), 'acadt1__learn-item');
        }

        return $this->acad_section(get_string('acad_learn', 'theme_nit'),
            html_writer::div($grid, 'acadt1__learn'), 'about');
    }

    /**
     * A titled content section (shared shell for the left-column bands).
     *
     * @param string $title heading text (plain, HTML-escaped here)
     * @param string $body inner HTML
     * @param string $id optional anchor id
     * @return string
     */
    protected function acad_section($title, $body, $id = ''): string {
        $attrs = ['class' => 'acadt1__h2'];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        return html_writer::tag('section',
            html_writer::tag('h2', s($title), $attrs) . $body,
            ['class' => 'acadt1__section']);
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
                $pills .= html_writer::tag('span', $item, ['class' => 'acadt1__pill']);
            }
        } else if (!empty($data->tags)) {
            foreach ($data->tags as $tag) {
                $pills .= html_writer::tag('span', format_string($tag->get_display_name()), ['class' => 'acadt1__pill']);
            }
        } else {
            return '';
        }

        return $this->acad_section(get_string('acad_skills', 'theme_nit'),
            html_writer::div($pills, 'acadt1__pills'), 'skills');
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

        return $this->acad_section(get_string('acad_requirements', 'theme_nit'),
            html_writer::div($cards, 'acadt1__req'), 'requirements');
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
            html_writer::div($icon . html_writer::tag('span', s($heading)), 'acadt1__req-h') .
            html_writer::tag('ul', $list, ['class' => 'acadt1__req-list']),
            'acadt1__req-card'
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
        return html_writer::div($summary, 'acadt1__summary');
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
        global $USER;

        // Access state drives the per-lesson Free pill / lock (real, not fabricated):
        // a free course -> Free; a paid course the viewer hasn't bought -> locked;
        // enrolled/covered -> no marker (they already have access).
        $isenrolled = is_enrolled($context, $USER->id, '', true);
        $isfree     = !$this->acad_has_pricing($course->id);
        $accessible = $isenrolled || $isfree;

        $acc = '';
        $idx = 0;
        foreach ($data->modulerows as $snum => $section) {
            $idx++;
            $acc .= $this->acad_module_row(
                $course, $section, $modinfo, $snum, $idx, ($idx === 1), $context, $accessible, $isfree);
        }

        // "N modules · M lessons" summary line.
        $meta = $this->acad_count($data->modcount, 'acad_nmodule', 'acad_nmodules');
        if ($data->itemcount > 0) {
            $meta .= ' · ' . $this->acad_count($data->itemcount, 'acad_1lesson', 'acad_nlessons');
        }

        $body = html_writer::div($meta, 'acadt1__curr-meta')
              . html_writer::div($acc, 'acadt1__acc');

        return $this->acad_section(get_string('acad_curriculum', 'theme_nit'), $body, 'curriculum');
    }

    /**
     * Whether a course has active paid pricing (guarded; false when the payments
     * plugin is absent).
     *
     * @param int $courseid
     * @return bool
     */
    protected function acad_has_pricing($courseid): bool {
        return class_exists('\local_payments\price_resolver')
            && (bool) \local_payments\price_resolver::has_pricing($courseid);
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
    protected function acad_module_row($course, $section, $modinfo, $snum, $idx, $open, $context, $accessible, $isfree) {
        $title  = get_section_name($course, $section);
        $bodyid = 'acadt1-mod-' . $snum;
        $cmlist = !empty($modinfo->sections[$snum]) ? $modinfo->sections[$snum] : [];

        // Count visible activities for the header meta.
        $visitems = 0;
        foreach ($cmlist as $cmid) {
            if ($modinfo->cms[$cmid]->uservisible) {
                $visitems++;
            }
        }

        $o  = html_writer::start_div('acadt1__mod' . ($open ? ' is-open' : ''));

        // Header (toggle button).
        $o .= html_writer::start_tag('button', [
            'class'         => 'acadt1__mod-head',
            'type'          => 'button',
            'onclick'       => 'AcademyUI.crModule(this)',
            'aria-expanded' => $open ? 'true' : 'false',
            'aria-controls' => $bodyid,
        ]);
        $meta = get_string('acad_modulen', 'theme_nit', $idx);
        if ($visitems > 0) {
            $meta .= ' · ' . $this->acad_count($visitems, 'acad_1lesson', 'acad_nlessons');
        }
        $o .= html_writer::div(
            html_writer::tag('div', format_string($title), ['class' => 'acadt1__mod-title']) .
            html_writer::tag('div', $meta, ['class' => 'acadt1__mod-meta'])
        );
        $o .= html_writer::tag('span', $this->acad_icon('chevron'), ['class' => 'acadt1__mod-chev']);
        $o .= html_writer::end_tag('button');

        // Body.
        $o .= html_writer::start_div('acadt1__mod-body', ['id' => $bodyid, 'role' => 'region']);

        if ($section->uservisible && !empty($section->summary)) {
            $desc = format_text($section->summary, $section->summaryformat, ['context' => $context]);
            if (trim(strip_tags($desc)) !== '') {
                $o .= html_writer::div($desc, 'acadt1__mod-desc');
            }
        }

        if (!$section->uservisible) {
            if (!empty($section->availableinfo)) {
                $locked = \core_availability\info::format_info($section->availableinfo, $course);
                $o .= html_writer::div(
                    $this->acad_icon('lock') . html_writer::tag('span', $locked), 'acadt1__act-locked');
            }
        } else {
            $o .= $this->acad_activities($modinfo, $cmlist, $accessible, $isfree);
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
    protected function acad_activities($modinfo, $cmlist, $accessible, $isfree) {
        $o = '';
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            $ico = html_writer::empty_tag('img', [
                'src' => $cm->get_icon_url(), 'alt' => '', 'class' => 'acadt1__act-ico', 'aria-hidden' => 'true',
            ]);
            $name = $cm->url
                ? html_writer::link($cm->url, format_string($cm->name))
                : format_string($cm->name);

            // Real access marker: Free on a free course, a lock on a paid course the
            // viewer can't yet access, nothing once they're enrolled/covered.
            if ($isfree) {
                $marker = html_writer::tag('span', get_string('acad_free', 'theme_nit'), ['class' => 'acadt1__free']);
            } else if (!$accessible) {
                $marker = html_writer::tag('span', $this->acad_icon('lock'),
                    ['class' => 'acadt1__lock', 'title' => get_string('acad_lockedlesson', 'theme_nit')]);
            } else {
                $marker = '';
            }

            $o .= html_writer::div(
                $ico . html_writer::div($name, 'acadt1__act-name') . $marker,
                'acadt1__act'
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
        if (empty($data->teachers)) {
            return '';
        }

        $tutors = '';
        foreach ($data->teachers as $t) {
            $userpic = new user_picture($t);
            $userpic->size = 100;
            $avatar = $this->output->render($userpic);
            $profileurl = new moodle_url('/user/view.php', ['id' => $t->id, 'course' => $course->id]);
            $tutors .= html_writer::div(
                html_writer::div($avatar, 'acadt1__tutor-pic') .
                html_writer::div(
                    html_writer::link($profileurl, s(fullname($t)), ['class' => 'acadt1__tutor-name']) .
                    html_writer::div(get_string('acad_instructorrole', 'theme_nit'), 'acadt1__tutor-role'),
                    'acadt1__tutor-txt'
                ),
                'acadt1__tutor'
            );
        }

        return $this->acad_section(get_string('acad_instructors', 'theme_nit'),
            html_writer::div($tutors, 'acadt1__tutors'), 'instructor');
    }

    // =========================================================================
    // Purchase card (sticky, right column) — mirrors the proven pricing / offer /
    // enrol / buy behaviour of local/nit_category/index.php.
    // =========================================================================

    /**
     * Sticky purchase card: preview thumbnail, price (+ offer), Enrol/Buy CTA and
     * a real-facts feature list. The pricing / enrolment logic is identical to the
     * catalogue card so Buy -> the same Kashier checkout and Enrol -> the same flow.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_purchase_card($course, $context, $data) {
        $info       = $this->acad_courseinfo($course->id);
        $detailsurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
            ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);

        // Preview thumbnail (course image) with a play glyph.
        $thumbattrs = ['class' => 'acadt1__preview'];
        if ($data->image) {
            $thumbattrs['style'] = "background-image:url('" . s($data->image->out(false)) . "');";
        }
        $preview = html_writer::tag('div',
            html_writer::tag('span', $this->acad_icon('play'), ['class' => 'acadt1__play', 'aria-hidden' => 'true']),
            $thumbattrs
        );

        // Price row — identical states to the catalogue card.
        $pricestr   = function_exists('theme_nit_course_price') ? theme_nit_course_price((int) $course->id) : '';
        $pricelabel = $pricestr !== '' ? $pricestr : get_string('acad_free', 'theme_nit');
        if (!$info['haspricing']) {
            $price = html_writer::tag('span', get_string('acad_free', 'theme_nit'),
                ['class' => 'acadt1__price acadt1__price--free']);
        } else if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0) {
            $price = html_writer::tag('span', s($pricelabel), ['class' => 'acadt1__strike'])
                . html_writer::tag('span',
                    s(number_format($info['offerfinal'], 0)) . ' ' . get_string('acad_currency', 'theme_nit'),
                    ['class' => 'acadt1__price'])
                . html_writer::tag('span', s($info['offerlabel']), ['class' => 'acadt1__offer']);
        } else {
            $price = html_writer::tag('span', s($pricelabel), ['class' => 'acadt1__price']);
        }
        $pricerow = html_writer::div($price, 'acadt1__price-row');

        // CTA — enrolled -> continue; covered -> enrol + details; paid -> Buy (modal);
        // free -> enrol. Mirrors nit_category exactly (same enrol URL + buy hook).
        if ($info['enrolled']) {
            $cta = html_writer::link($detailsurl, get_string('acad_gotocourse', 'theme_nit'),
                ['class' => 'acadt1__btn']);
        } else if ($info['covered']) {
            $cta = html_writer::div(get_string('acad_insubscription', 'theme_nit'), 'acadt1__cover')
                . html_writer::link($enrolurl, get_string('acad_enrol', 'theme_nit'), ['class' => 'acadt1__btn'])
                . html_writer::link($detailsurl, get_string('acad_gotocourse', 'theme_nit'),
                    ['class' => 'acadt1__btn acadt1__btn--ghost']);
        } else if ($info['haspricing']) {
            $cta = html_writer::tag('button', get_string('acad_buynow', 'theme_nit'), [
                'type'               => 'button',
                'class'              => 'acadt1__btn',
                'data-nit-buy-course' => '',
                'data-courseid'      => (int) $course->id,
                'data-name'          => s(format_string($course->fullname)),
                'data-price'         => s((string) $info['price']),
            ]);
        } else {
            $cta = html_writer::link($enrolurl, get_string('acad_enrol', 'theme_nit'), ['class' => 'acadt1__btn']);
        }

        // Real-facts feature list (built by acad_glance).
        $features = $this->acad_glance($data);

        return html_writer::div(
            $preview . html_writer::div($pricerow . $cta . $features, 'acadt1__buy-body'),
            'acadt1__buy'
        );
    }

    /**
     * Per-course state for the purchase card: enrolment, subscription coverage,
     * pricing, and any active offer. A verbatim mirror of the $nitcourseinfo
     * closure in local/nit_category/index.php (single source of truth for the
     * buy/enrol behaviour), guarded so it degrades when the plugins are absent.
     *
     * @param int $courseid
     * @return array
     */
    protected function acad_courseinfo($courseid) {
        global $USER, $CFG;

        $out = ['enrolled' => false, 'covered' => false, 'free' => true, 'haspricing' => false,
            'price' => 0.0, 'offerlabel' => '', 'offerfinal' => 0.0];
        $uid = (int) ($USER->id ?? 0);
        $ctx = context_course::instance($courseid);
        $out['enrolled'] = $uid > 0 && is_enrolled($ctx, $uid, '', true);

        $nitcheckout = class_exists('\local_payments\price_resolver')
            && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
            && class_exists('\local_nit_commerce\discount_manager');
        if (!$nitcheckout) {
            return $out;
        }

        $out['haspricing'] = (bool) \local_payments\price_resolver::has_pricing($courseid);
        $out['free'] = !$out['haspricing'];

        if (!$out['enrolled'] && $out['haspricing']
                && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            $out['covered'] = (bool) \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $uid);
        }

        if ($out['haspricing']) {
            try {
                $pricing = \local_payments\price_resolver::resolve($courseid, $uid);
                $base = (float) $pricing->price;
                $out['price'] = $base;
                $summary = \local_nit_commerce\discount_manager::offer_summary('course', (int) $courseid, $base);
                if ($summary) {
                    $out['offerlabel'] = $summary['label'];
                    $out['offerfinal'] = (float) $summary['final'];
                }
            } catch (\Throwable $e) {
                // Leave defaults on any pricing error.
            }
        }
        return $out;
    }

    /**
     * Wire the Buy buttons to the shared checkout modal (coupon + auto offer ->
     * Kashier). Mirrors the footer script of local/nit_category/index.php; guarded
     * so it is a no-op when the commerce plugins are absent. The format renderer
     * runs after <head> is flushed, so the external module is loaded via a plain
     * <script src> (not $PAGE->requires->js, which would be dropped).
     *
     * @return string
     */
    protected function acad_checkout_js() {
        global $CFG;

        $nitcheckout = class_exists('\local_payments\price_resolver')
            && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
            && class_exists('\local_nit_commerce\discount_manager');
        if (!$nitcheckout) {
            return '';
        }
        require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

        $costr = local_nit_commerce_string_map([
            'co_title', 'co_intro', 'co_total', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
            'co_secure', 'co_proceed', 'co_cancel', 'co_loading', 'co_coupon_failed', 'co_currency',
        ]);

        $o  = html_writer::tag('script', '',
            ['src' => (new moodle_url('/local/nit_commerce/checkout_modal.js'))->out(false)]);
        $o .= html_writer::script('window.NIT_CO = ' . json_encode([
            'wwwroot'  => $CFG->wwwroot,
            'sesskey'  => sesskey(),
            'commerce' => '/local/nit_commerce/api.php',
            'str'      => $costr,
            'loggedin' => isloggedin() && !isguestuser(),
        ]) . ';');
        $o .= html_writer::script(<<<'JS'
(function () {
    function init() {
        if (!window.NitCheckout || !window.NIT_CO) { return; }
        NitCheckout.init(window.NIT_CO);
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-nit-buy-course]');
            if (!btn) { return; }
            ev.preventDefault();
            if (!window.NIT_CO.loggedin) { window.location.href = window.NIT_CO.wwwroot + '/login/index.php'; return; }
            var id = btn.getAttribute('data-courseid');
            NitCheckout.open({
                itemType: 'course',
                itemId: parseInt(id, 10),
                name: btn.getAttribute('data-name'),
                price: parseFloat(btn.getAttribute('data-price')) || 0,
                proceed: function (code) {
                    window.location.href = window.NIT_CO.wwwroot + '/local/payments/checkout.php?courseid=' + id +
                        '&sesskey=' + encodeURIComponent(window.NIT_CO.sesskey) + '&coupon_code=' + encodeURIComponent(code);
                }
            });
        });
    }
    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
JS
        );
        return $o;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * The inline <style> block for the T1 "Modern Minimal" course landing.
     *
     * Every colour resolves from the theme_nit Brand Colors palette (--nit-brand-*)
     * so the page re-skins with the rest of the site and honours RTL/LTR and
     * dark/light automatically. The local --t-* tokens map brand roles by job,
     * exactly as local/nit_category/index.php does.
     *
     * @return string
     */
    protected function acad_styles(): string {
        $css = <<<'CSS'
.acadt1{
  --t-accent: var(--nit-brand-primary);
  --t-accent-2: var(--nit-brand-accent);
  --t-on: var(--nit-brand-on-primary, #fff);
  --t-accent-soft: color-mix(in srgb, var(--nit-brand-primary) 9%, transparent);
  /* T1 keeps its LIGHT structure (like the homepage templates); only the accent
     comes from the academy brand, so the T1 look stays consistent on any brand. */
  --t-ink: #16191D;
  --t-bg: #FFFFFF;
  --t-surface: #FAFAF8;
  --t-muted: #6E7781;
  --t-border: #EDEDE9;
  --t-success: var(--nit-brand-success);
  font-family: 'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif;
  background: var(--t-bg); color: var(--t-ink);
  width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); min-height: 100vh;
}
.acadt1 a{ text-decoration: none; color: inherit; }
.acadt1__wrap{ max-width: 1200px; margin: 0 auto; padding: clamp(20px,3vw,40px) 20px 72px; }
.acadt1__ico{ width: 20px; height: 20px; flex: 0 0 auto; }

/* Breadcrumb */
.acadt1__crumbs{ display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 13px; color: var(--t-muted); margin-bottom: 24px; }
.acadt1__crumb-sep{ color: var(--t-muted); opacity: .6; }
.acadt1__crumb.is-current{ color: var(--t-ink); font-weight: 600; }

/* Two-column layout — sticky purchase card on the right; below content on mobile */
.acadt1__layout{ display: grid; grid-template-columns: minmax(0,1fr) 360px; gap: clamp(24px,4vw,56px); align-items: start; }
@media (max-width: 960px){ .acadt1__layout{ grid-template-columns: 1fr; } .acadt1__aside{ position: static !important; } }

/* Header */
.acadt1__eyebrow{ font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: var(--t-accent); font-weight: 700; }
.acadt1__chips{ display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.acadt1__chip{ font-size: 12px; font-weight: 700; letter-spacing: .04em; padding: 5px 12px; border-radius: 40px; border: 1px solid var(--t-border); color: var(--t-ink); background: var(--t-surface); }
.acadt1__chip--free{ background: color-mix(in srgb, var(--t-success) 14%, transparent); color: var(--t-success); border-color: transparent; }
.acadt1__title{ margin: 16px 0 0; font-size: clamp(30px,4vw,46px); font-weight: 250; letter-spacing: -0.03em; line-height: 1.08; }
.acadt1__subject{ margin: 14px 0 0; font-size: 16px; color: var(--t-accent); font-weight: 600; }
.acadt1__summary{ margin: 18px 0 0; font-size: 16px; line-height: 1.75; color: var(--t-muted); }
.acadt1__summary *{ color: var(--t-muted); }
.acadt1__meta{ display: flex; flex-wrap: wrap; gap: 10px 22px; margin-top: 22px; }
.acadt1__meta-item{ display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--t-muted); }
.acadt1__meta-item .acadt1__ico{ width: 17px; height: 17px; color: var(--t-accent); }
.acadt1__instructor{ margin-top: 16px; font-size: 14px; color: var(--t-muted); }
.acadt1__instructor b{ color: var(--t-ink); font-weight: 600; }

/* Sections */
.acadt1__section{ margin-top: clamp(36px,5vw,52px); }
.acadt1__h2{ font-size: clamp(22px,2.6vw,30px); font-weight: 250; letter-spacing: -0.02em; margin: 0 0 20px; }

/* What you'll learn */
.acadt1__learn{ display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 14px 28px; }
.acadt1__learn-item{ display: flex; gap: 11px; font-size: 15px; line-height: 1.5; color: var(--t-ink); }
.acadt1__learn-item .acadt1__ico{ width: 20px; height: 20px; color: var(--t-accent); margin-top: 1px; }

/* Skills */
.acadt1__pills{ display: flex; flex-wrap: wrap; gap: 10px; }
.acadt1__pill{ font-size: 13px; font-weight: 600; padding: 8px 16px; border-radius: 40px; border: 1px solid var(--t-border); color: var(--t-ink); background: var(--t-surface); }

/* Requirements */
.acadt1__req{ display: grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap: 18px; }
.acadt1__req-card{ border: 1px solid var(--t-border); border-radius: 16px; padding: 22px; background: var(--t-surface); }
.acadt1__req-h{ display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 600; margin-bottom: 12px; }
.acadt1__req-h .acadt1__ico{ color: var(--t-accent); }
.acadt1__req-list{ margin: 0; padding-inline-start: 18px; color: var(--t-muted); font-size: 14px; line-height: 1.75; }

/* Curriculum */
.acadt1__curr-meta{ margin: -8px 0 20px; font-size: 14px; color: var(--t-muted); }
.acadt1__acc{ display: flex; flex-direction: column; gap: 12px; }
.acadt1__mod{ border: 1px solid var(--t-border); border-radius: 14px; overflow: hidden; background: var(--t-surface); }
.acadt1__mod-head{ width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 16px 20px; background: none; border: 0; cursor: pointer; text-align: start; font-family: inherit; color: var(--t-ink); }
.acadt1__mod-title{ font-size: 16px; font-weight: 600; }
.acadt1__mod-meta{ margin-top: 3px; font-size: 13px; color: var(--t-muted); }
.acadt1__mod-chev{ display: inline-flex; color: var(--t-muted); transition: transform .2s ease; }
.acadt1__mod.is-open .acadt1__mod-chev{ transform: rotate(180deg); }
.acadt1__mod-body{ display: none; padding: 0 20px 14px; }
.acadt1__mod.is-open .acadt1__mod-body{ display: block; }
.acadt1__mod-desc{ padding: 4px 0 12px; font-size: 14px; line-height: 1.6; color: var(--t-muted); }
.acadt1__act{ display: flex; align-items: center; gap: 12px; padding: 11px 0; border-top: 1px solid color-mix(in srgb, var(--t-border) 70%, transparent); }
.acadt1__act-ico{ width: 18px; height: 18px; object-fit: contain; opacity: .85; flex: 0 0 auto; }
.acadt1__act-name{ flex: 1; min-width: 0; font-size: 14px; color: var(--t-ink); }
.acadt1__act-name a:hover{ color: var(--t-accent); }
.acadt1__act-type{ display: none; }
.acadt1__free{ font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--t-success); background: color-mix(in srgb, var(--t-success) 14%, transparent); padding: 3px 9px; border-radius: 40px; flex: 0 0 auto; }
.acadt1__lock{ display: inline-flex; color: var(--t-muted); flex: 0 0 auto; }
.acadt1__lock .acadt1__ico{ width: 16px; height: 16px; }
.acadt1__act-locked{ display: flex; align-items: center; gap: 8px; padding: 12px 0 4px; font-size: 13px; color: var(--t-muted); }
.acadt1__act-locked .acadt1__ico{ width: 16px; height: 16px; }

/* Instructor */
.acadt1__tutors{ display: flex; flex-direction: column; gap: 18px; }
.acadt1__tutor{ display: flex; align-items: center; gap: 14px; }
.acadt1__tutor-pic img{ width: 56px; height: 56px; border-radius: 50%; object-fit: cover; }
.acadt1__tutor-name{ font-size: 16px; font-weight: 600; color: var(--t-ink); }
.acadt1__tutor-name:hover{ color: var(--t-accent); }
.acadt1__tutor-role{ margin-top: 2px; font-size: 13px; color: var(--t-muted); }

/* Purchase card */
.acadt1__aside{ position: sticky; top: 20px; }
.acadt1__buy{ border: 1px solid var(--t-border); border-radius: 18px; overflow: hidden; background: var(--t-bg); box-shadow: 0 18px 44px rgba(20,24,28,0.10); }
.acadt1__preview{ position: relative; aspect-ratio: 16/9; display: grid; place-items: center; background: repeating-linear-gradient(135deg,#EFEFEC 0 11px,#F7F7F5 11px 22px) center/cover no-repeat; }
.acadt1__play{ width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.92); color: var(--t-accent); box-shadow: 0 10px 26px rgba(20,24,28,.24); }
.acadt1__play .acadt1__ico{ width: 26px; height: 26px; }
.acadt1__buy-body{ padding: 22px; }
.acadt1__price-row{ display: flex; align-items: baseline; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
.acadt1__price{ font-size: 30px; font-weight: 300; letter-spacing: -0.02em; color: var(--t-ink); }
.acadt1__price--free{ color: var(--t-success); font-weight: 600; }
.acadt1__strike{ font-size: 16px; color: var(--t-muted); text-decoration: line-through; opacity: .7; }
.acadt1__offer{ background: var(--t-accent); color: var(--t-on); font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 40px; }
.acadt1__btn{ display: block; width: 100%; text-align: center; box-sizing: border-box; background: var(--t-accent); color: var(--t-on); font-size: 15px; font-weight: 600; font-family: inherit; padding: 15px 20px; border: 0; border-radius: 12px; cursor: pointer; box-shadow: 0 14px 30px color-mix(in srgb, var(--t-accent) 30%, transparent); }
.acadt1__btn:hover{ filter: brightness(1.06); }
.acadt1__btn--ghost{ margin-top: 10px; background: var(--t-bg); color: var(--t-ink); border: 1px solid var(--t-border); box-shadow: none; }
.acadt1__cover{ margin-bottom: 12px; text-align: center; font-size: 13px; font-weight: 700; color: var(--t-accent); }
.acadt1__features{ margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--t-border); display: flex; flex-direction: column; gap: 13px; }
.acadt1__feat{ display: flex; align-items: center; gap: 11px; font-size: 14px; color: var(--t-ink); }
.acadt1__feat .acadt1__ico{ width: 18px; height: 18px; color: var(--t-accent); }
CSS;
        return html_writer::tag('style', $css);
    }

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
            'play'    => '<path d="M9 7.5v9l7-4.5-7-4.5z" fill="currentColor"/>',
            'lock'    => '<rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.7"/>',
            'book'    => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H12v16H5.5A1.5 1.5 0 0 1 4 18.5v-13z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H12v16h6.5a1.5 1.5 0 0 0 1.5-1.5v-13z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'infinity' => '<path d="M7 12c0-2 1.5-3.2 3-3.2 2 0 2.8 2 4 3.2 1.2 1.2 2 3.2 4 3.2 1.5 0 3-1.2 3-3.2s-1.5-3.2-3-3.2c-2 0-2.8 2-4 3.2-1.2 1.2-2 3.2-4 3.2-1.5 0-3-1.2-3-3.2z" stroke="currentColor" stroke-width="1.7"/>',
        ];
        $p = $paths[$key] ?? '';
        return '<svg class="acadt1__ico acadt1__ico--' . $key . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
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

    // Accordion row: header button toggles .is-open on its .acadt1__mod wrapper.
    w.AcademyUI.crModule = function (btn) {
        var row = btn.closest('.acadt1__mod');
        if (!row) { return; }
        var open = row.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    // Load the T1 signature font once (Manrope), matching the homepage templates.
    if (!document.getElementById('nit-tpl-font-t1')) {
        var l = document.createElement('link');
        l.id = 'nit-tpl-font-t1';
        l.rel = 'stylesheet';
        l.href = 'https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap';
        (document.head || document.documentElement).appendChild(l);
    }
})(window);
JS;
    }
}
