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
 * NIT topics-format renderer — Bassthalk course-detail page.
 *
 * On the multi-section course landing page (not editing) this replaces Moodle's
 * stock course body with the Bassthalk "course details" layout (Figma: "Course
 * details page", html.to.design capture of bassthalk.com/course/…):
 *
 *   - a price card (course image, price + old price + discount badge, the list
 *     of lessons with ticks, the subscribe / enrol / continue button), and
 *   - an "عن الكورس / المنتدى" tabbed card (title, summary, teacher photo, name
 *     and title), and
 *   - a "الدروس" card: every section is a lesson; the open one has a blue
 *     header, its sub-sections (mod_subsection) become the green "parts"
 *     ("كبسولة الشرح", "كتاب التفوق") and its activities the grey item rows.
 *
 * Styles live in scss/components/_bthcourse.scss; every colour reads the
 * Brand Colors "Bassthalk" group (g18, `.nit-brand-18`).
 *
 * Everything on the page is DRIVEN BY REAL COURSE DATA — there are no static
 * placeholder values. Options our platform has that the Bassthalk design does
 * not (learners / updated / language facts, free & certificate chips, "What
 * you'll learn", skills, requirements, the facts list, Free / locked markers,
 * subscription coverage) are kept and drawn in the same visual language. They
 * read the course custom fields under "Other fields", mapped by shortname:
 *        course_fields ............. subject line + "skills" chips
 *        total_number_of_hours ..... duration fact
 *        language .................. language of instruction
 *        certificate (checkbox) .... "Shareable certificate"
 *        free (checkbox) ........... Free chip
 *        target_audience ........... "Who this is for"
 *        prerequisites ............. "Requirements"
 *        ilos ...................... "What you'll learn"
 *        by_the_end_of_training .... "What you'll learn"
 *
 * A field (or whole block) that has no value is simply NOT rendered. Short-text
 * fields authored in the bilingual "{mlang en}…{mlang}{mlang ar}…{mlang}"
 * convention are resolved to the current language via {@see acad_ml()}. A field
 * may hold several items separated by a pipe "|" (or a newline / bullet), which
 * become individual chips — see {@see acad_chips()}.
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
                // The course DETAIL page is what learners see — prospects AND
                // enrolled students (lessons link to their activities, and the card
                // shows Continue / Enrol / Subscribe per state). Only staff who can
                // EDIT the course (and anyone in edit mode — already excluded
                // above) get the real Moodle course page, for management.
                // "Switch role to… Student" previews the learner page: the switched
                // role drives has_capability(), and a site admin who switched is not
                // treated as staff either.
                $courseid = (int) $this->page->course->id;
                $context  = \context_course::instance($courseid);
                $isstaff  = has_capability('moodle/course:update', $context)
                    || (is_siteadmin() && !is_role_switched($courseid));
                if (!$isstaff) {
                    return $this->acad_render_page($format);
                }
            }
        }
        return parent::render($widget);
    }

    /**
     * The course detail page on its own — for /local/academy/course.php, which
     * shows it to every learner and to visitors (course/view.php needs a log-in).
     *
     * @param stdClass $course
     * @return string HTML
     */
    public function render_course_details(stdClass $course): string {
        return $this->acad_render_page(course_get_format($course));
    }

    /**
     * Whether the viewer is a visitor (not logged in, or the guest user): they
     * see the lessons but cannot open them, and the button takes them to log in.
     *
     * @return bool
     */
    protected function acad_is_visitor(): bool {
        return !isloggedin() || isguestuser();
    }

    /**
     * Build the full branded page for the given course format.
     *
     * @param \core_courseformat\base $format the course format
     * @return string HTML
     */
    protected function acad_render_page($format): string {
        $course  = $format->get_course();
        $modinfo = get_fast_modinfo($course);
        $context = context_course::instance($course->id);

        $data = $this->acad_gather($course, $modinfo, $context);

        // Main column: the "about / forum" card, then the lessons card.
        $main  = $this->acad_about_card($course, $context, $data);
        $main .= $this->acad_modules($course, $modinfo, $context, $data);

        // Side column: the price card. It comes FIRST in the DOM so it leads on
        // phones and tablets (as on bassthalk.com); on desktop the grid puts it on
        // the inline-end side.
        $aside = $this->acad_purchase_card($course, $context, $data);

        // The navbar loads Tajawal 400/500/700 and Almarai; the price is set in
        // Tajawal Black (900), which only this page uses.
        $o  = html_writer::empty_tag('link', [
            'rel' => 'stylesheet', 'href' => 'https://fonts.googleapis.com/css2?family=Tajawal:wght@900&display=swap',
        ]);
        $o .= html_writer::start_div('bthc nit-brand-18');
        $o .= html_writer::start_div('bthc__wrap');
        $o .= html_writer::tag('aside', $aside, ['class' => 'bthc__aside']);
        $o .= html_writer::div($main, 'bthc__main');
        $o .= html_writer::end_div(); // wrap.
        $o .= html_writer::end_div(); // bthc.

        // Accordion / tabs helper. A format renderer runs after <head> is flushed,
        // so $PAGE->requires->js() would be dropped; emit inline <script>.
        $o .= html_writer::script($this->acad_inline_js());
        // Subscribe buttons -> shared checkout modal (Kashier), mirroring nit_category.
        $o .= $this->acad_checkout_js();

        return $o;
    }

    // =========================================================================
    // Data gathering — one pass, so each block just reads $data.
    // =========================================================================

    /**
     * Collect everything the blocks need in a single pass.
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @return stdClass
     */
    protected function acad_gather($course, $modinfo, $context) {
        $d = new stdClass();
        $d->course   = $course;
        $d->context  = $context;
        $d->enrolled = count_enrolled_users($context);

        // Visible top-level sections (each becomes one lesson). Sub-sections
        // (mod_subsection's delegated sections) are drawn inside their parent.
        $numsections   = course_get_format($course)->get_last_section_number();
        $d->modulerows = [];
        $d->modcount   = 0;
        $d->assesscount = 0;
        $d->itemcount   = 0;
        foreach ($modinfo->get_section_info_all() as $snum => $sec) {
            if (method_exists($sec, 'is_delegated') && $sec->is_delegated()) {
                continue;
            }
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

        // Count real lessons (not sub-section containers / labels) and graded
        // assessment activities.
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || in_array($cm->modname, ['subsection', 'label'], true)) {
                continue;
            }
            $d->itemcount++;
            if (in_array($cm->modname, ['assign', 'quiz', 'workshop', 'lesson'], true)) {
                $d->assesscount++;
            }
        }

        // Teachers.
        $d->teachers = $this->acad_teachers($context);

        // Skills fall back to course tags when the course_fields custom field is empty.
        $d->tags = core_tag_tag::get_item_tags('core', 'course', $course->id);

        // Course image.
        $d->image = $this->acad_course_image_url($context);

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
     * Enrolled teachers, each with its "teachertitle" profile field (the line
     * under the name, e.g. "أستاذ الأحياء و العلوم المتكاملة") when filled.
     *
     * @param \context_course $context
     * @return array
     */
    protected function acad_teachers($context) {
        global $DB;

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
            // Site admins are enrolled as teacher in every course they create
            // (\local_academy\observer::course_created) — they are not teachers.
            if (isset($seen[$u->id]) || is_siteadmin($u->id)) {
                continue;
            }
            $seen[$u->id] = true;
            $out[] = $u;
        }

        if ($out) {
            [$insql, $params] = $DB->get_in_or_equal(array_column($out, 'id'), SQL_PARAMS_NAMED);
            $titles = $DB->get_records_sql_menu(
                "SELECT d.userid, d.data
                   FROM {user_info_data} d
                   JOIN {user_info_field} f ON f.id = d.fieldid
                  WHERE f.shortname = 'teachertitle' AND d.userid $insql", $params);
            foreach ($out as $u) {
                $u->teachertitle = trim((string) ($titles[$u->id] ?? ''));
            }
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
    // "عن الكورس / المنتدى" card
    // =========================================================================

    /**
     * The tabbed about card: "عن الكورس" (title, summary, facts, teachers and the
     * learning-outcome blocks) and, when the course has a forum the viewer can
     * see, "المنتدى" (links to the course forums).
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_about_card($course, $context, $data) {
        $forums = $this->acad_forums($course);

        // Tabs.
        $tabs = $this->acad_tab('bthc-about', get_string('acad_tababout', 'theme_nit'), true);
        if ($forums !== '') {
            $tabs .= $this->acad_tab('bthc-forum', get_string('acad_tabforum', 'theme_nit'), false);
        }
        $reviews = $this->acad_reviews($course, $data);
        if ($reviews !== '') {
            $tabs .= $this->acad_tab('bthc-reviews', get_string('acad_tabreviews', 'theme_nit'), false);
        }
        $o = html_writer::div($tabs, 'bthc__tabs', ['role' => 'tablist']);

        // About panel.
        $about  = $this->acad_hero($course, $context, $data);
        $about .= $this->acad_rail($course, $context, $data);
        $extras = $this->acad_learn($data) . $this->acad_skills($data) . $this->acad_requirements($data);
        if ($extras !== '') {
            $about .= html_writer::div($extras, 'bthc__extras');
        }
        $o .= html_writer::div($about, 'bthc__panel', [
            'id' => 'bthc-about', 'role' => 'tabpanel', 'aria-labelledby' => 'bthc-about-tab',
        ]);

        // Forum panel.
        if ($forums !== '') {
            $o .= html_writer::div($forums, 'bthc__panel', [
                'id' => 'bthc-forum', 'role' => 'tabpanel', 'aria-labelledby' => 'bthc-forum-tab', 'hidden' => 'hidden',
            ]);
        }

        // Reviews panel.
        if ($reviews !== '') {
            $o .= html_writer::div($reviews, 'bthc__panel', [
                'id' => 'bthc-reviews', 'role' => 'tabpanel', 'aria-labelledby' => 'bthc-reviews-tab', 'hidden' => 'hidden',
            ]);
        }

        return html_writer::div($o, 'bthc__card bthc__about');
    }

    /**
     * Whether learner reviews are available (local_nit_reviews installed).
     *
     * @return bool
     */
    protected function acad_has_reviews(): bool {
        return class_exists('\local_nit_reviews\api');
    }

    /**
     * "★ 4.6 (23 reviews)" — '' when nothing approved yet, so no made-up figure shows.
     *
     * @param object $agg {avg, count}
     * @param string $class extra class
     * @return string
     */
    protected function acad_rating($agg, string $class = ''): string {
        if (empty($agg->count)) {
            return '';
        }
        return html_writer::span(
            $this->acad_icon('star') .
            html_writer::span(format_float($agg->avg, 1), 'bthc__rating-avg') .
            html_writer::span('(' . get_string('acad_nreviews', 'theme_nit', $agg->count) . ')', 'bthc__rating-count'),
            trim('bthc__rating ' . $class),
            ['title' => get_string('acad_ratingof', 'theme_nit', format_float($agg->avg, 1))]);
    }

    /**
     * The "التقييمات" tab: the course's average and star spread, a button to rate
     * the course and its teachers (for whoever may), and the latest approved
     * comments. '' when reviews are unavailable.
     *
     * @param stdClass $course
     * @param stdClass $data
     * @return string
     */
    protected function acad_reviews($course, $data): string {
        if (!$this->acad_has_reviews()) {
            return '';
        }
        $api = '\local_nit_reviews\api';
        $agg = $api::get_aggregate((int) $course->id);

        // Summary: the big average with stars, then one bar per star value.
        $summary = '';
        if ($agg->count > 0) {
            $stars = $api::get_distribution((int) $course->id);
            $bars = '';
            for ($n = 5; $n >= 1; $n--) {
                $pct = (int) round(100 * $stars[$n] / $agg->count);
                $bars .= html_writer::div(
                    html_writer::span($n, 'bthc__rv-n') . $this->acad_icon('star') .
                    html_writer::div(html_writer::div('', 'bthc__rv-fill', ['style' => "width:{$pct}%"]), 'bthc__rv-track') .
                    html_writer::span($stars[$n], 'bthc__rv-c'),
                    'bthc__rv-bar');
            }
            $summary = html_writer::div(
                html_writer::div(
                    html_writer::div(format_float($agg->avg, 1), 'bthc__rv-avg') .
                    html_writer::div($this->acad_stars((int) round($agg->avg)), 'bthc__rv-stars') .
                    html_writer::div(get_string('acad_nreviews', 'theme_nit', $agg->count), 'bthc__rv-total'),
                    'bthc__rv-score') .
                html_writer::div($bars, 'bthc__rv-bars'),
                'bthc__rv-summary');
        }

        // Rate button: the course, or any of its teachers.
        $canrate = $api::can_rate((int) $course->id);
        foreach ($data->teachers as $t) {
            $canrate = $canrate || $api::can_rate_teacher((int) $course->id, (int) $t->id);
        }
        $button = '';
        if ($canrate) {
            $button = html_writer::link(new moodle_url('/local/nit_reviews/rate.php', ['courseid' => $course->id]),
                $this->acad_icon('star') . html_writer::span(get_string('acad_ratebtn', 'theme_nit')),
                ['class' => 'bthc__rv-btn']);
        }

        // Latest approved comments.
        $items = '';
        foreach ($api::get_reviews((int) $course->id, 0, 10)['reviews'] as $r) {
            $items .= html_writer::div(
                html_writer::div(
                    html_writer::img($r['pictureurl'], '', ['class' => 'bthc__rv-pic', 'loading' => 'lazy']) .
                    html_writer::div(
                        html_writer::div(s($r['fullname']), 'bthc__rv-name') .
                        html_writer::div($this->acad_stars($r['rating']) .
                            html_writer::span(userdate($r['timemodified'], get_string('strftimedatefullshort')),
                                'bthc__rv-date'), 'bthc__rv-meta'),
                        'bthc__rv-who'),
                    'bthc__rv-head') .
                ($r['review'] !== '' ? html_writer::div(nl2br(s($r['review'])), 'bthc__rv-text') : ''),
                'bthc__rv-item');
        }

        if ($summary === '' && $items === '') {
            $summary = html_writer::div(get_string('acad_noreviews', 'theme_nit'), 'bthc__rv-empty');
        }
        $head = html_writer::div($summary . $button, 'bthc__rv-top');
        return html_writer::div($head . ($items !== '' ? html_writer::div($items, 'bthc__rv-list') : ''), 'bthc__rv');
    }

    /**
     * Five stars, the first $n filled.
     *
     * @param int $n 0..5
     * @return string
     */
    protected function acad_stars(int $n): string {
        $n = max(0, min(5, $n));
        return html_writer::span(
            html_writer::span(str_repeat('★', $n), 'bthc__stars-on') . html_writer::span(str_repeat('★', 5 - $n), 'bthc__stars-off'),
            'bthc__stars', ['aria-label' => get_string('acad_ratingof', 'theme_nit', $n)]);
    }

    /**
     * One tab button.
     *
     * @param string $panelid
     * @param string $label already HTML-safe (a lang string)
     * @param bool $active
     * @return string
     */
    protected function acad_tab($panelid, $label, $active): string {
        return html_writer::tag('button', $label, [
            'type'          => 'button',
            'role'          => 'tab',
            'id'            => $panelid . '-tab',
            'class'         => 'bthc__tab' . ($active ? ' is-active' : ''),
            'aria-selected' => $active ? 'true' : 'false',
            'aria-controls' => $panelid,
            'onclick'       => 'AcademyUI.crTab(this)',
        ]);
    }

    /**
     * The forum list for the "المنتدى" tab ('' when the course has no forum the
     * viewer can see — the tab is then not shown).
     *
     * @param stdClass $course
     * @return string
     */
    protected function acad_forums($course): string {
        $rows = '';
        foreach (get_fast_modinfo($course)->get_instances_of('forum') as $cm) {
            if (!$cm->uservisible || !$cm->url) {
                continue;
            }
            $rows .= $this->acad_item_row($cm, false, false, true);
        }
        return $rows === '' ? '' : html_writer::div($rows, 'bthc__items bthc__forums');
    }

    /**
     * Title, summary, chips and facts at the top of the about panel.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_hero($course, $context, $data) {
        $o = html_writer::start_div('bthc__head');

        // Title.
        $o .= html_writer::tag('h1', format_string($course->fullname), ['class' => 'bthc__title']);

        // Summary (the grey line under the title on bassthalk.com).
        $o .= $this->acad_expertise($course, $context, $data);

        // Subject line from course_fields (real). Already HTML-safe.
        $subject = $this->acad_cf_text('course_fields', $data);
        if ($subject !== '') {
            $o .= html_writer::tag('p', $subject, ['class' => 'bthc__subject']);
        }

        // Status chips — only real facts (free course, shareable certificate) —
        // and the facts row (learners, updated date, language).
        $meta = '';
        if ($this->acad_cf_bool('free', $data)) {
            $meta .= html_writer::tag('span', get_string('acad_free', 'theme_nit'),
                ['class' => 'bthc__chip bthc__chip--free']);
        }
        if ($this->acad_cf_bool('certificate', $data)) {
            $meta .= html_writer::tag('span', get_string('acad_certificate', 'theme_nit'), ['class' => 'bthc__chip']);
        }
        if ($this->acad_has_reviews()) {
            $meta .= $this->acad_rating(\local_nit_reviews\api::get_aggregate((int) $course->id), 'bthc__fact');
        }
        if ($data->enrolled > 0) {
            $meta .= html_writer::span(
                $this->acad_icon('people') .
                html_writer::span($this->acad_count($data->enrolled, 'acad_1learner', 'acad_nlearners')),
                'bthc__fact');
        }
        $updated = $course->timemodified ? userdate($course->timemodified, get_string('strftimedatefullshort')) : '';
        if ($updated !== '') {
            $meta .= html_writer::span(
                $this->acad_icon('clock') .
                html_writer::span(get_string('acad_updated', 'theme_nit', s($updated))),
                'bthc__fact');
        }
        $lang = $this->acad_cf_text('language', $data);
        if ($lang !== '') {
            // $lang is already HTML-safe (resolved via format_string/{mlang}).
            $meta .= html_writer::span($this->acad_icon('lang') . html_writer::span($lang), 'bthc__fact');
        }
        if ($meta !== '') {
            $o .= html_writer::div($meta, 'bthc__facts');
        }

        $o .= html_writer::end_div();
        return $o;
    }

    /**
     * The course summary. Empty ⇒ ''.
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
        return html_writer::div($summary, 'bthc__summary');
    }

    /**
     * Teachers: photo, name and title, as under the summary on bassthalk.com.
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

        $ratings = $this->acad_has_reviews()
            ? \local_nit_reviews\api::get_teacher_aggregates(array_map(fn($t) => (int) $t->id, $data->teachers)) : [];
        $tutors = '';
        foreach ($data->teachers as $t) {
            $userpic = new user_picture($t);
            $userpic->size = 512;
            $photo = html_writer::empty_tag('img', [
                'src'     => $userpic->get_url($this->page)->out(false),
                'alt'     => s(fullname($t)),
                'class'   => 'bthc__teacher-pic',
                'loading' => 'lazy',
            ]);
            // The public teacher page (as on bassthalk.com); the title may carry {mlang}.
            $profileurl = new moodle_url('/local/academy/teacher.php', ['id' => $t->id]);
            $title = $this->acad_ml($t->teachertitle, $data);
            if ($title === '') {
                $title = get_string('acad_instructorrole', 'theme_nit');
            }
            $tutors .= html_writer::div(
                html_writer::link($profileurl, $photo, ['class' => 'bthc__teacher-photo', 'tabindex' => '-1']) .
                html_writer::div(
                    html_writer::link($profileurl, s(fullname($t)), ['class' => 'bthc__teacher-name']) .
                    html_writer::div($title, 'bthc__teacher-title') .
                    (isset($ratings[$t->id]) ? $this->acad_rating($ratings[$t->id], 'bthc__rating--teacher') : ''),
                    'bthc__teacher-txt'
                ),
                'bthc__teacher'
            );
        }

        return html_writer::div($tutors, 'bthc__teachers');
    }

    /**
     * A titled block inside the about panel (learn / skills / requirements).
     *
     * @param string $title heading text (plain, HTML-escaped here)
     * @param string $body inner HTML
     * @param string $id optional anchor id
     * @return string
     */
    protected function acad_section($title, $body, $id = ''): string {
        $attrs = ['class' => 'bthc__h3'];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        return html_writer::tag('section',
            html_writer::tag('h2', s($title), $attrs) . $body,
            ['class' => 'bthc__block']);
    }

    /**
     * "What you'll learn" — Intended Learning Outcomes + end-of-training
     * statements. Empty ⇒ ''.
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

        $list = '';
        foreach ($items as $item) {
            // $item is already HTML-safe (resolved via format_string/{mlang}).
            $list .= html_writer::tag('li', $this->acad_mask('check') . html_writer::span($item));
        }

        return $this->acad_section(get_string('acad_learn', 'theme_nit'),
            html_writer::tag('ul', $list, ['class' => 'bthc__ticks bthc__ticks--learn']), 'about');
    }

    /**
     * "Skills" — course_fields chips, else course tags. Empty ⇒ ''.
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
                $pills .= html_writer::tag('span', $item, ['class' => 'bthc__pill']);
            }
        } else if (!empty($data->tags)) {
            foreach ($data->tags as $tag) {
                $pills .= html_writer::tag('span', format_string($tag->get_display_name()), ['class' => 'bthc__pill']);
            }
        } else {
            return '';
        }

        return $this->acad_section(get_string('acad_skills', 'theme_nit'),
            html_writer::div($pills, 'bthc__pills'), 'skills');
    }

    /**
     * "Requirements & audience" — prerequisites + target audience. Empty ⇒ ''.
     *
     * @param stdClass $data
     * @return string
     */
    protected function acad_requirements($data) {
        $cards = '';

        $audience = $this->acad_chips('target_audience', $data);
        if (!empty($audience)) {
            $cards .= $this->acad_req_card(get_string('acad_audience', 'theme_nit'), $audience);
        }

        $prereq = $this->acad_chips('prerequisites', $data);
        if (!empty($prereq)) {
            $cards .= $this->acad_req_card(get_string('acad_prerequisites', 'theme_nit'), $prereq);
        }

        if ($cards === '') {
            return '';
        }

        return $this->acad_section(get_string('acad_requirements', 'theme_nit'),
            html_writer::div($cards, 'bthc__req'), 'requirements');
    }

    /**
     * One requirements box (heading + list of points), drawn like a lesson part.
     *
     * @param string $heading
     * @param string[] $points
     * @return string
     */
    protected function acad_req_card($heading, array $points) {
        $list = '';
        foreach ($points as $p) {
            // $p is already HTML-safe (resolved via format_string/{mlang}).
            $list .= html_writer::tag('li', $p);
        }
        return html_writer::div(
            html_writer::div(s($heading), 'bthc__req-h') .
            html_writer::tag('ul', $list, ['class' => 'bthc__req-list']),
            'bthc__req-card'
        );
    }

    // =========================================================================
    // "الدروس" card (sections accordion)
    // =========================================================================

    /**
     * The lessons card: one accordion row per section; the first starts open.
     *
     * @param stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_modules($course, $modinfo, $context, $data) {
        global $USER;

        if (empty($data->modulerows)) {
            return '';
        }

        // Access state drives the per-lesson Free pill / lock (real, not fabricated):
        // a free course -> Free; a paid course the viewer hasn't bought -> locked;
        // enrolled/covered -> no marker (they already have access).
        $isenrolled = is_enrolled($context, $USER->id, '', true);
        $isfree     = !$this->acad_has_pricing($course->id);
        $accessible = !$this->acad_is_visitor() && ($isenrolled || $isfree);

        // Progress "(done/total)" on each lesson needs completion tracking and an
        // enrolled learner; otherwise the header shows the number of items.
        $completion = new \completion_info($course);
        $tracking   = $isenrolled && $completion->is_enabled();

        $acc = '';
        $idx = 0;
        foreach ($data->modulerows as $snum => $section) {
            $idx++;
            $acc .= $this->acad_module_row($course, $section, $modinfo, $snum, ($idx === 1), $context,
                $accessible, $isfree, $tracking ? $completion : null);
        }

        // "N modules · M lessons" summary line under the heading.
        $meta = $this->acad_count($data->modcount, 'acad_nmodule', 'acad_nmodules');
        if ($data->itemcount > 0) {
            $meta .= ' · ' . $this->acad_count($data->itemcount, 'acad_1lesson', 'acad_nlessons');
        }

        $body = html_writer::tag('h2', get_string('acad_lessons', 'theme_nit'), ['class' => 'bthc__h2', 'id' => 'curriculum'])
              . html_writer::div($meta, 'bthc__curr-meta')
              . html_writer::div($acc, 'bthc__secs');

        return html_writer::div($body, 'bthc__card bthc__lessons');
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
     * One lesson (section): header button + collapsible body.
     *
     * @param stdClass $course
     * @param \section_info $section
     * @param \course_modinfo $modinfo
     * @param int $snum
     * @param bool $open
     * @param \context_course $context
     * @param bool $accessible the viewer can open the lessons
     * @param bool $isfree the course is free
     * @param \completion_info|null $completion set when progress is tracked for the viewer
     * @return string
     */
    protected function acad_module_row($course, $section, $modinfo, $snum, $open, $context, $accessible, $isfree,
            $completion) {
        $title  = get_section_name($course, $section);
        $bodyid = 'bthc-sec-' . $snum;
        $cmlist = !empty($modinfo->sections[$snum]) ? $modinfo->sections[$snum] : [];

        // The description is the second line of the header (plain text). When it
        // carries more than text (images, links, media) it is also shown in full
        // at the top of the body so nothing is lost.
        $summaryhtml = '';
        $summarytext = '';
        if ($section->uservisible && !empty($section->summary)) {
            $summaryhtml = format_text($section->summary, $section->summaryformat, ['context' => $context]);
            $summarytext = trim(preg_replace('/\s+/u', ' ', html_to_text($summaryhtml, 0, false)));
        }

        // Header badge: "(done/total)" when progress is tracked, else "(N lessons)".
        [$done, $total] = $this->acad_section_progress($modinfo, $cmlist, $completion);
        $badge = '';
        if ($total > 0) {
            $badge = $completion
                ? '(' . $done . '/' . $total . ')'
                : '(' . $this->acad_count($total, 'acad_1lesson', 'acad_nlessons') . ')';
        }

        $o  = html_writer::start_div('bthc__sec' . ($open ? ' is-open' : ''));

        $o .= html_writer::start_tag('button', [
            'class'         => 'bthc__sec-head',
            'type'          => 'button',
            'onclick'       => 'AcademyUI.crModule(this)',
            'aria-expanded' => $open ? 'true' : 'false',
            'aria-controls' => $bodyid,
        ]);
        $o .= html_writer::span(
            html_writer::span(format_string($title), 'bthc__sec-title') .
            ($summarytext !== '' ? html_writer::span(s($summarytext), 'bthc__sec-sub') : ''),
            'bthc__sec-txt');
        if ($badge !== '') {
            $o .= html_writer::span($badge, 'bthc__sec-count');
        }
        $o .= html_writer::span($this->acad_mask('chevbubble'), 'bthc__chev', ['aria-hidden' => 'true']);
        $o .= html_writer::end_tag('button');

        // Body.
        $o .= html_writer::start_div('bthc__sec-body', ['id' => $bodyid, 'role' => 'region']);

        if ($summaryhtml !== '' && preg_match('/<(img|a|iframe|video|audio|table|ul|ol)\b/i', $summaryhtml)) {
            $o .= html_writer::div($summaryhtml, 'bthc__sec-desc');
        }

        if (!$section->uservisible) {
            if (!empty($section->availableinfo)) {
                $locked = \core_availability\info::format_info($section->availableinfo, $course);
                $o .= html_writer::div(
                    $this->acad_icon('lock') . html_writer::span($locked), 'bthc__locked');
            }
        } else {
            $items = $this->acad_activities($modinfo, $cmlist, $accessible, $isfree);
            $o .= $items !== ''
                ? $items
                : html_writer::div(get_string('acad_noitems', 'theme_nit'), 'bthc__empty');
        }

        $o .= html_writer::end_div(); // body.
        $o .= html_writer::end_div(); // sec.
        return $o;
    }

    /**
     * Completed / total lessons of one section (sub-sections included).
     *
     * @param \course_modinfo $modinfo
     * @param array $cmlist
     * @param \completion_info|null $completion
     * @return int[] [done, total]
     */
    protected function acad_section_progress($modinfo, $cmlist, $completion): array {
        $done  = 0;
        $total = 0;
        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible || $cm->modname === 'label') {
                continue;
            }
            if ($cm->modname === 'subsection') {
                $sub = $cm->get_delegated_section_info();
                if ($sub && !empty($modinfo->sections[$sub->section])) {
                    [$d, $t] = $this->acad_section_progress($modinfo, $modinfo->sections[$sub->section], $completion);
                    $done += $d;
                    $total += $t;
                }
                continue;
            }
            if ($completion) {
                if (!$completion->is_enabled($cm)) {
                    continue;
                }
                $state = $completion->get_data($cm, true)->completionstate;
                if (in_array((int) $state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                    $done++;
                }
            }
            $total++;
        }
        return [$done, $total];
    }

    /**
     * The contents of one lesson: activity rows, and each sub-section as a green
     * "part" (title + إخفاء) followed by its own rows.
     *
     * @param \course_modinfo $modinfo
     * @param array $cmlist
     * @param bool $accessible
     * @param bool $isfree
     * @return string
     */
    protected function acad_activities($modinfo, $cmlist, $accessible, $isfree) {
        $o    = '';
        $rows = '';
        $flush = function () use (&$o, &$rows) {
            if ($rows !== '') {
                $o .= html_writer::div($rows, 'bthc__items');
                $rows = '';
            }
        };

        foreach ($cmlist as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }
            if ($cm->modname === 'subsection') {
                $flush();
                $o .= $this->acad_part($modinfo, $cm, $accessible, $isfree);
                continue;
            }
            $rows .= $this->acad_item_row($cm, $accessible, $isfree);
        }
        $flush();
        return $o;
    }

    /**
     * One sub-section ("كبسولة الشرح"): green header with a hide/show toggle, then
     * its items. Open by default, as on bassthalk.com.
     *
     * @param \course_modinfo $modinfo
     * @param \cm_info $cm the mod_subsection activity
     * @param bool $accessible
     * @param bool $isfree
     * @return string
     */
    protected function acad_part($modinfo, $cm, $accessible, $isfree) {
        $sub   = $cm->get_delegated_section_info();
        $items = '';
        if ($sub && $sub->uservisible && !empty($modinfo->sections[$sub->section])) {
            foreach ($modinfo->sections[$sub->section] as $subcmid) {
                $subcm = $modinfo->cms[$subcmid];
                if ($subcm->uservisible && $subcm->modname !== 'subsection') {
                    $items .= $this->acad_item_row($subcm, $accessible, $isfree);
                }
            }
        }
        $bodyid = 'bthc-part-' . $cm->id;

        $head = html_writer::tag('button',
            html_writer::span(format_string($cm->name), 'bthc__part-title') .
            html_writer::span(
                html_writer::span(get_string('acad_parthide', 'theme_nit'), 'bthc__part-hide') .
                html_writer::span(get_string('acad_partshow', 'theme_nit'), 'bthc__part-show') .
                $this->acad_mask('chevgroup'),
                'bthc__part-tog'),
            [
                'type'          => 'button',
                'class'         => 'bthc__part-head',
                'onclick'       => 'AcademyUI.crPart(this)',
                'aria-expanded' => 'true',
                'aria-controls' => $bodyid,
            ]);

        $body = $items !== ''
            ? html_writer::div($items, 'bthc__items', ['id' => $bodyid])
            : html_writer::div(get_string('acad_noitems', 'theme_nit'), 'bthc__empty', ['id' => $bodyid]);

        return html_writer::div($head . $body, 'bthc__part is-open');
    }

    /**
     * One grey item row: round icon, linked name, and the Free / lock marker.
     *
     * @param \cm_info $cm
     * @param bool $accessible
     * @param bool $isfree
     * @param bool $nomarker never show a Free / lock marker (forum tab)
     * @return string
     */
    protected function acad_item_row($cm, $accessible, $isfree, $nomarker = false) {
        // Labels are text on the course page — show their text, not a link.
        if ($cm->modname === 'label') {
            return html_writer::div(format_string($cm->name), 'bthc__item bthc__item--label');
        }

        // Lessons sold one by one (local_nit_finance) and watched % (local_nit_videoprogress).
        [$salemarker, $saleurl] = $nomarker ? ['', null] : $this->acad_sale_marker($cm);
        // A visitor sees what the course holds but cannot open a lesson.
        // Lessons open inside the course player frame (video lessons are their own player).
        $lessonurl = ($cm->url && class_exists('\local_academy\player'))
            ? new moodle_url(\local_academy\player::url_for($cm)) : $cm->url;
        $url = $this->acad_is_visitor() ? null : ($saleurl ?? $lessonurl);
        $name = $url
            ? html_writer::link($url, format_string($cm->name), ['class' => 'bthc__item-link'])
            : format_string($cm->name);

        // Real access marker: Free on a free course, a lock on a paid course the
        // viewer can't yet access, nothing once they're enrolled/covered.
        $marker = '';
        if ($salemarker !== '') {
            $marker = $salemarker;
        } else if (!$nomarker) {
            if ($isfree) {
                $marker = html_writer::span(get_string('acad_free', 'theme_nit'), 'bthc__free');
            } else if (!$accessible) {
                $marker = html_writer::span($this->acad_icon('lock'),
                    'bthc__lock', ['title' => get_string('acad_lockedlesson', 'theme_nit')]);
            }
        }

        return html_writer::div(
            html_writer::span($this->acad_cm_icon($cm), 'bthc__item-ico', ['aria-hidden' => 'true']) .
            html_writer::div($name, 'bthc__item-name') . $this->acad_watched_chip($cm) . $marker,
            'bthc__item'
        );
    }

    /** @var array|null per-course lesson-sale state for the viewer (local_nit_finance) */
    protected $acadsalestate = null;

    /**
     * Marker for a lesson sold on its own: "Buy · price" (linking to the buy
     * page) while the viewer has not bought it, "Unlocked" once they have.
     *
     * @param \cm_info $cm
     * @return array [marker HTML ('' = none), URL to use for the lesson name or null]
     */
    protected function acad_sale_marker($cm): array {
        global $USER;
        if (!class_exists('\local_nit_finance\local\access')) {
            return ['', null];
        }
        if ($this->acadsalestate === null || $this->acadsalestate['courseid'] !== (int) $cm->course) {
            $userid = isloggedin() && !isguestuser() ? (int) $USER->id : 0;
            $this->acadsalestate = ['courseid' => (int) $cm->course]
                + \local_nit_finance\local\access::course_state($userid, (int) $cm->course);
        }
        $state = $this->acadsalestate;
        $price = (int) ($state['prices'][$cm->id] ?? 0);
        if ($price <= 0 || $state['staff'] || $state['ownscourse']) {
            return ['', null];
        }
        if (!empty($state['owned'][$cm->id])) {
            return [html_writer::span(get_string('owned', 'local_nit_finance'), 'nitfin-chip nitfin-chip--owned nit-brand-18'), null];
        }
        $buyurl = new \moodle_url('/local/nit_finance/buy.php', ['cmid' => $cm->id]);
        $chip = html_writer::link($buyurl,
            get_string('buy', 'local_nit_finance') . ' · ' . \local_nit_finance\local\money::format($price),
            ['class' => 'nitfin-chip nit-brand-18']);
        // A video lesson with a free preview: the lesson name opens the preview.
        if (class_exists('\local_nit_finance\local\preview') && isloggedin() && !isguestuser()
                && \local_nit_finance\local\preview::for_user((int) $USER->id, $cm, $state) > 0) {
            $previewurl = \local_nit_finance\local\preview::url((int) $cm->id);
            $chip = html_writer::link($previewurl, get_string('previewchip', 'local_nit_finance'),
                ['class' => 'nitfin-chip nit-brand-18']) . ' ' . $chip;
            return [$chip, $previewurl];
        }
        return [$chip, $buyurl];
    }

    /** @var array|null watched % per video lesson for the viewer: [courseid, cmid => percent] */
    protected $acadwatched = null;

    /**
     * Watched-% ring for a video lesson the viewer has started.
     *
     * @param \cm_info $cm
     * @return string HTML ('' when not a started video)
     */
    protected function acad_watched_chip($cm): string {
        global $USER;
        if (!class_exists('\local_nit_videoprogress\progress')
                || !in_array($cm->modname, \local_nit_videoprogress\progress::PROVIDERS, true)
                || !isloggedin() || isguestuser()) {
            return '';
        }
        if ($this->acadwatched === null || $this->acadwatched['courseid'] !== (int) $cm->course) {
            $this->acadwatched = ['courseid' => (int) $cm->course,
                'percents' => \local_nit_videoprogress\progress::course_percents((int) $USER->id, (int) $cm->course)];
        }
        if (!isset($this->acadwatched['percents'][$cm->id])) {
            return '';
        }
        return \local_nit_videoprogress\ui::chip((int) $this->acadwatched['percents'][$cm->id]);
    }

    /**
     * The icon of an item row: the Bassthalk glyph for the kinds the design
     * draws (file / page → book, video → play, homework → edit), otherwise the
     * activity's own Moodle icon — every one painted in the brand icon colour.
     *
     * @param \cm_info $cm
     * @return string
     */
    protected function acad_cm_icon($cm): string {
        $map = [
            'vimeo' => 'play', 'vdocipher' => 'play',
            'assign' => 'edit',
            'resource' => 'book', 'book' => 'book', 'page' => 'book', 'folder' => 'book',
            'url' => 'book', 'imscp' => 'book',
        ];
        if (isset($map[$cm->modname])) {
            return $this->acad_mask($map[$cm->modname]);
        }
        return html_writer::span('', 'bthc__mask bthc__mask--mod',
            ['style' => "--bthc-ico:url('" . $cm->get_icon_url()->out(false) . "')"]);
    }

    /**
     * A Bassthalk icon (theme_nit/pix/bthcourse/<name>.svg) drawn as a CSS mask,
     * so its colour comes from the brand role on the element.
     *
     * @param string $name check | chevbubble | chevgroup | book | play | edit
     * @return string
     */
    protected function acad_mask($name): string {
        return html_writer::span('', 'bthc__mask bthc__mask--' . $name, ['aria-hidden' => 'true']);
    }

    // =========================================================================
    // Price card — mirrors the proven pricing / offer / enrol / buy behaviour of
    // local/nit_category/index.php.
    // =========================================================================

    /**
     * The price card: course image, price (+ old price and discount badge), the
     * list of lessons, the Subscribe / Enrol / Continue button and the facts list.
     * The pricing / enrolment logic is identical to the catalogue card so
     * Subscribe -> the same Kashier checkout and Enrol -> the same flow.
     *
     * @param stdClass $course
     * @param \context_course $context
     * @param stdClass $data
     * @return string
     */
    protected function acad_purchase_card($course, $context, $data) {
        $info       = $this->acad_courseinfo($course->id);
        // Enrolled learners also see this detail page, so "continue" must go to
        // the first LESSON (course/view.php is this page — linking there would loop).
        $lessonurl  = $this->acad_first_lesson_url($course);
        $detailsurl = $lessonurl ?: (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
            ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);

        // Course image, with a play glyph ONLY when the course actually has a
        // video lesson — otherwise a play button would promise something that
        // isn't there.
        $media = '';
        if ($data->image) {
            $media .= html_writer::empty_tag('img', [
                'src' => $data->image->out(false), 'alt' => '', 'class' => 'bthc__cover-img',
            ]);
        }
        if ($this->acad_has_video_lesson($course)) {
            $media .= html_writer::span($this->acad_icon('play'), 'bthc__play', ['aria-hidden' => 'true']);
        }
        $cover = html_writer::div($media, 'bthc__cover' . ($data->image ? '' : ' is-empty'));

        // Price row: price, then the old price and the discount badge when an
        // offer applies (bassthalk.com shows "80.00  110.00  خصم 27%").
        if (!$info['haspricing']) {
            $price = html_writer::span(get_string('acad_free', 'theme_nit'), 'bthc__price bthc__price--free');
        } else if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0 && $info['offerfinal'] < $info['price']) {
            $pct   = (int) round(($info['price'] - $info['offerfinal']) / $info['price'] * 100);
            $price = html_writer::span(s(number_format($info['offerfinal'], 2)), 'bthc__price')
                   . html_writer::tag('s', s(number_format($info['price'], 2)), ['class' => 'bthc__oldprice'])
                   . html_writer::span(get_string('acad_discount', 'theme_nit', $pct), 'bthc__discount',
                       ['title' => s($info['offerlabel'])]);
        } else {
            $price = html_writer::span(s(number_format($info['price'], 2)), 'bthc__price');
        }
        $pricerow = html_writer::div($price, 'bthc__price-row');

        // "الدروس:" — the lessons of the course, ticked.
        $lessons = '';
        if (!empty($data->modulerows)) {
            $list = '';
            foreach ($data->modulerows as $sec) {
                $list .= html_writer::tag('li',
                    $this->acad_mask('check') . html_writer::span(format_string(get_section_name($course, $sec))));
            }
            $lessons = html_writer::div(
                html_writer::tag('h3', get_string('acad_lessonslabel', 'theme_nit'), ['class' => 'bthc__list-h']) .
                html_writer::tag('ul', $list, ['class' => 'bthc__ticks bthc__ticks--card']),
                'bthc__list');
        }

        // CTA — enrolled -> continue; covered -> enrol + continue; paid ->
        // Subscribe (checkout modal); free -> enrol. Mirrors nit_category exactly.
        if ($this->acad_is_visitor()) {
            // Log in first; the course page sets itself as the page to come back to.
            $cta = html_writer::link(get_login_url(),
                get_string($info['haspricing'] ? 'acad_subscribe' : 'acad_enrol', 'theme_nit'), ['class' => 'bthc__btn']);
        } else if ($info['enrolled']) {
            $cta = html_writer::link($detailsurl, get_string('acad_gotocourse', 'theme_nit'),
                ['class' => 'bthc__btn']);
        } else if ($info['covered']) {
            $cta = html_writer::div(get_string('acad_insubscription', 'theme_nit'), 'bthc__cover-note')
                . html_writer::link($enrolurl, get_string('acad_enrol', 'theme_nit'), ['class' => 'bthc__btn'])
                . html_writer::link($detailsurl, get_string('acad_gotocourse', 'theme_nit'),
                    ['class' => 'bthc__btn bthc__btn--ghost']);
        } else if ($info['haspricing']) {
            $cta = html_writer::tag('button', get_string('acad_subscribe', 'theme_nit'), [
                'type'                => 'button',
                'class'               => 'bthc__btn',
                'data-nit-buy-course' => '',
                'data-courseid'       => (int) $course->id,
                'data-name'           => s(format_string($course->fullname)),
                'data-price'          => s((string) $info['price']),
            ]);
        } else {
            $cta = html_writer::link($enrolurl, get_string('acad_enrol', 'theme_nit'), ['class' => 'bthc__btn']);
        }

        return html_writer::div(
            $cover . $pricerow . $lessons . html_writer::div($cta, 'bthc__cta') . $this->acad_glance($data),
            'bthc__card bthc__buy'
        );
    }

    /**
     * Facts list under the button: only rows that have real data are shown.
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

        $body = '';
        foreach ($rows as $r) {
            $body .= html_writer::div($r[0] . html_writer::span($r[1]), 'bthc__feat');
        }
        return html_writer::div($body, 'bthc__features');
    }

    /**
     * URL of the lesson an enrolled learner resumes at (else the FIRST navigable
     * activity), so "continue" goes into the content instead of looping back to
     * this detail page. Returns '' when the course has no viewable activity yet.
     *
     * @param stdClass $course
     * @return string
     */
    protected function acad_first_lesson_url($course): string {
        // The learner's resume point (first lesson not yet completed) when the player knows it.
        if (class_exists('\local_academy\player') && ($cm = \local_academy\player::resume_cm($course))) {
            return \local_academy\player::url_for($cm);
        }
        try {
            $modinfo = get_fast_modinfo($course);
            foreach ($modinfo->get_section_info_all() as $secinfo) {
                foreach (($modinfo->sections[$secinfo->section] ?? []) as $cmid) {
                    $cm = $modinfo->cms[$cmid] ?? null;
                    if ($cm && $cm->uservisible && $cm->url && !in_array($cm->modname, ['label', 'subsection'], true)) {
                        // Open in the course player frame (video lessons are their own player).
                        return class_exists('\local_academy\player')
                            ? \local_academy\player::url_for($cm) : $cm->url->out(false);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through to ''.
        }
        return '';
    }

    /**
     * Does the course contain at least one video lesson (mod_vimeo / mod_vdocipher)?
     * Checks visibility to anyone (not uservisible) so a prospect who cannot open
     * the lesson still sees that an intro video exists.
     *
     * @param \stdClass $course
     * @return bool
     */
    protected function acad_has_video_lesson($course): bool {
        try {
            foreach (get_fast_modinfo($course)->get_cms() as $cm) {
                if (in_array($cm->modname, ['vimeo', 'vdocipher'], true) && $cm->visible && !$cm->deletioninprogress) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to false.
        }
        return false;
    }

    /**
     * Per-course state for the price card: enrolment, subscription coverage,
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
     * Wire the Subscribe buttons to the shared checkout modal (coupon + auto
     * offer -> Kashier). Mirrors the footer script of local/nit_category/index.php;
     * guarded so it is a no-op when the commerce plugins are absent. The format
     * renderer runs after <head> is flushed, so the external module is loaded via
     * a plain <script src> (not $PAGE->requires->js, which would be dropped).
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
            'co_title', 'co_intro', 'co_total', 'co_total_sub', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
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
     * Small inline SVG icon (stroke = currentColor) by key — used by the facts
     * the Bassthalk design has no glyph for.
     *
     * @param string $key
     * @return string
     */
    protected function acad_icon($key): string {
        $paths = [
            'modules' => '<rect x="3" y="4" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="3" y="10" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/><rect x="3" y="16" width="18" height="4" rx="1" stroke="currentColor" stroke-width="1.7"/>',
            'clock'   => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
            'assess'  => '<rect x="5" y="3" width="14" height="18" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M9 8h6M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'lang'    => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" stroke="currentColor" stroke-width="1.7"/>',
            'cert'    => '<circle cx="12" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M8 14l-1 7 5-3 5 3-1-7" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'people'  => '<circle cx="9" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16 5.5a3.2 3.2 0 0 1 0 5M17.5 20a5.5 5.5 0 0 0-2.5-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
            'play'    => '<path d="M9 7.5v9l7-4.5-7-4.5z" fill="currentColor"/>',
            'star'    => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5z" fill="currentColor"/>',
            'lock'    => '<rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.7"/>',
            'book'    => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H12v16H5.5A1.5 1.5 0 0 1 4 18.5v-13z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H12v16h6.5a1.5 1.5 0 0 0 1.5-1.5v-13z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
            'infinity' => '<path d="M7 12c0-2 1.5-3.2 3-3.2 2 0 2.8 2 4 3.2 1.2 1.2 2 3.2 4 3.2 1.5 0 3-1.2 3-3.2s-1.5-3.2-3-3.2c-2 0-2.8 2-4 3.2-1.2 1.2-2 3.2-4 3.2-1.5 0-3-1.2-3-3.2z" stroke="currentColor" stroke-width="1.7"/>',
        ];
        $p = $paths[$key] ?? '';
        return '<svg class="bthc__ico bthc__ico--' . $key . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
            . $p . '</svg>';
    }

    /**
     * Inline accordion / part / tab helpers. Emitted in the body because a format
     * renderer runs after <head> is flushed.
     *
     * @return string JavaScript
     */
    protected function acad_inline_js() {
        return <<<'JS'
(function (w) {
    'use strict';
    w.AcademyUI = w.AcademyUI || {};

    function toggle(btn, rowsel) {
        var row = btn.closest(rowsel);
        if (!row) { return; }
        var open = row.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Lesson header: toggles .is-open on its .bthc__sec wrapper.
    w.AcademyUI.crModule = function (btn) { toggle(btn, '.bthc__sec'); };
    // Lesson part ("كبسولة الشرح"): toggles .is-open on its .bthc__part wrapper.
    w.AcademyUI.crPart = function (btn) { toggle(btn, '.bthc__part'); };

    // "عن الكورس / المنتدى" tabs.
    w.AcademyUI.crTab = function (btn) {
        var bar = btn.closest('[role="tablist"]');
        if (!bar) { return; }
        bar.querySelectorAll('[role="tab"]').forEach(function (tab) {
            var on = tab === btn;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            var panel = document.getElementById(tab.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
    };
})(window);
JS;
    }
}
