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

namespace local_academy\api;

defined('MOODLE_INTERNAL') || die();

use local_academy\local\home_data;
use local_academy\local\teacher_page;
use local_academy\local\user_fields;
use local_academy\player;

/**
 * The Bassthalk pages for the mobile app, as data: the home page sections
 * (selected courses, teachers, suggested lessons), the public teacher page and
 * the course details page. Same sources and rules as the web pages
 * (home_data, teacher_page, theme_nit format_topics_renderer::render_course_details).
 *
 * Image URLs are returned ready to load (webservice/pluginfile + token).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages {

    // ── Home page ─────────────────────────────────────────────────────────────

    /**
     * "كورسات مختارة": the admin-picked courses with the year filter.
     *
     * @param int $userid viewer (0 = visitor)
     * @return array
     */
    public static function home_selected(int $userid): array {
        $data = home_data::selected();
        $data['courses'] = array_map(static fn(array $c): array => self::course_card($c, $userid), $data['courses']);
        return $data;
    }

    /**
     * "المدرسين عندنا": the teachers with the year / study system / division filter.
     * `me` pre-selects the signed-in student's own year, system and division.
     *
     * @return array
     */
    public static function home_teachers(): array {
        $data = home_data::teachers();
        foreach ($data['teachers'] as &$teacher) {
            $teacher['photo'] = endpoint::file_url($teacher['photo']);
            unset($teacher['url']);
            $teacher['courses'] = array_map(static fn(array $c): array => [
                'years' => $c['years'],
                'system' => $c['system'],
                'division' => $c['division'],
            ], $teacher['courses']);
        }
        unset($teacher);
        return $data;
    }

    /**
     * "المحاضرات المقترحة": the latest courses.
     *
     * @param int $userid viewer (0 = visitor)
     * @return array
     */
    public static function home_lessons(int $userid): array {
        $data = home_data::lessons();
        return ['courses' => array_map(static fn(array $c): array => self::course_card($c, $userid), $data['courses'])];
    }

    /**
     * A home course card + the viewer's price / access state (numbers, not just the
     * display label).
     *
     * @param array $card a home_data course card
     * @param int $userid
     * @return array
     */
    protected static function course_card(array $card, int $userid): array {
        $card['image'] = self::image((string) ($card['image'] ?? ''));
        unset($card['url'], $card['enrolurl']);
        $card['state'] = self::price_state((int) $card['id'], $userid);
        return $card;
    }

    // ── Teacher page ──────────────────────────────────────────────────────────

    /**
     * The public teacher page: profile, years taught, counts and course cards.
     *
     * @param int $teacherid
     * @param int $userid viewer (0 = visitor)
     * @return array
     * @throws \moodle_exception teachernotfound
     */
    public static function teacher(int $teacherid, int $userid): array {
        $page = teacher_page::get($teacherid);
        if ($page === null) {
            throw new \moodle_exception('err_teachernotfound', 'local_academy');
        }
        $page['photo'] = endpoint::file_url($page['photo']);
        $page['courses'] = array_map(static fn(array $c): array => self::course_card($c, $userid), $page['courses']);
        $page['reviews'] = array_map(static fn(array $r): array => ['picture' => endpoint::file_url($r['picture'])] + $r,
            $page['reviews'] ?? []);
        return $page;
    }

    // ── Course details page ───────────────────────────────────────────────────

    /**
     * The course details page: about (summary, teachers, what you learn, skills,
     * requirements), the price card (price, offer, what the main button does) and
     * the lessons with each item's state for this viewer.
     *
     * @param int $courseid
     * @param int $userid viewer (0 = visitor)
     * @return array
     * @throws \moodle_exception coursenotfound
     */
    public static function course(int $courseid, int $userid): array {
        global $DB;
        $course = $courseid > SITEID ? $DB->get_record('course', ['id' => $courseid]) : null;
        if (!$course) {
            throw new \moodle_exception('err_coursenotfound', 'local_academy');
        }
        $context = \context_course::instance($course->id);
        if (!$course->visible && !($userid && has_capability('moodle/course:viewhiddencourses', $context, $userid))) {
            throw new \moodle_exception('err_coursenotfound', 'local_academy');
        }

        $modinfo = get_fast_modinfo($course, $userid ?: 0);
        $state = self::price_state((int) $course->id, $userid);
        $enrolled = $state['enrolled'];
        $cf = self::custom_fields($course, $context, $userid);
        $ml = static fn($raw): string => self::ml((string) $raw, $context);
        $chips = static function (string $shortname, bool $listsep = false) use ($cf, $ml): array {
            $text = isset($cf[$shortname]) ? $ml($cf[$shortname]) : '';
            if ($text === '') {
                return [];
            }
            $parts = preg_split($listsep ? '/[|\n•،,]+/u' : '/[|\n•]+/u', strip_tags($text));
            return array_values(array_filter(array_map('trim', $parts), 'strlen'));
        };

        // Sections and items.
        [$sections, $counts] = self::sections($course, $modinfo, $context, $userid, $enrolled, !$state['haspricing']);

        // Skills: the course_fields list, else the course tags.
        $skills = $chips('course_fields', true);
        if (!$skills) {
            foreach (\core_tag_tag::get_item_tags('core', 'course', $course->id) as $tag) {
                $skills[] = format_string($tag->get_display_name());
            }
        }
        $hours = $cf['total_number_of_hours'] ?? null;
        $hours = ($hours === null || $hours === '' || (float) $hours == 0.0) ? null : (float) $hours;

        // What the main button does for this viewer (same order as the web card).
        if (!$userid) {
            $action = 'login';
        } else if ($enrolled) {
            $action = 'open';
        } else if ($state['covered']) {
            $action = 'enrol_subscription';
        } else if ($state['haspricing']) {
            $action = 'buy';
        } else {
            $action = 'enrol_free';
        }
        $resume = ($userid && $enrolled) ? player::resume_cm($course) : null;

        $answers = home_data::course_answers([(int) $course->id])[(int) $course->id] ?? ['system' => '', 'division' => ''];
        $category = $DB->get_record('course_categories', ['id' => $course->category], 'id, name');

        return [
            'course' => [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'summary' => trim(html_to_text(format_text($course->summary, $course->summaryformat,
                    ['context' => $context]), 0, false)),
                'summaryhtml' => format_text($course->summary, $course->summaryformat, ['context' => $context]),
                'image' => self::image(self::course_image($context)),
                'category' => $category ? ['id' => (int) $category->id,
                    'name' => format_string($category->name, true, ['context' => \context_coursecat::instance($category->id)])] : null,
                'studysystem' => self::ml($answers['system'], $context),
                'division' => self::ml($answers['division'], $context),
                'subject' => isset($cf['course_fields']) ? strip_tags($ml($cf['course_fields'])) : '',
                'language' => isset($cf['language']) ? strip_tags($ml($cf['language'])) : '',
                'isfreeflag' => !empty($cf['free']),
                'certificate' => !empty($cf['certificate']),
                'hours' => $hours,
                'timemodified' => (int) $course->timemodified,
                'hasvideo' => self::has_video($modinfo),
            ],
            'counts' => $counts + ['students' => count_enrolled_users($context)],
            'teachers' => self::course_teachers($context),
            'learn' => array_merge($chips('ilos'), $chips('by_the_end_of_training')),
            'skills' => $skills,
            'audience' => $chips('target_audience'),
            'prerequisites' => $chips('prerequisites'),
            'price' => $state,
            'action' => $action,
            'resume_cmid' => $resume ? (int) $resume->id : 0,
            'sections' => $sections,
            'forums' => self::forums($modinfo),
        ];
    }

    /**
     * The visible sections with their items and the viewer's per-item state.
     *
     * @param \stdClass $course
     * @param \course_modinfo $modinfo
     * @param \context_course $context
     * @param int $userid
     * @param bool $enrolled
     * @param bool $isfree
     * @return array [sections, counts]
     */
    protected static function sections(\stdClass $course, \course_modinfo $modinfo, \context_course $context,
            int $userid, bool $enrolled, bool $isfree): array {
        $accessible = $userid && ($enrolled || $isfree);
        $completion = new \completion_info($course);
        $tracking = $userid && $enrolled && $completion->is_enabled();

        // Lesson states the web player uses (lock order, sold one by one, watched %).
        $walk = [];
        if ($userid) {
            foreach (player::walk($course)['lessons'] as $l) {
                $walk[$l['cmid']] = $l;
            }
        }
        $sale = ($userid >= 0 && class_exists('\local_nit_finance\local\access'))
            ? \local_nit_finance\local\access::course_state($userid, (int) $course->id) : null;

        $item = static function (\cm_info $cm) use ($accessible, $isfree, $walk, $sale, $completion, $tracking, $userid): array {
            $entry = [
                'cmid' => (int) $cm->id,
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'modname' => $cm->modname,
                'islabel' => $cm->modname === 'label',
                'marker' => '',          // free | locked | buy | owned | ''.
                'price' => null,
                'canopen' => false,
                'locked' => false,
                'forsale' => false,
                'completed' => false,
                'watched_percent' => null,
            ];
            if ($entry['islabel']) {
                return $entry;
            }
            $price = (int) ($sale['prices'][$cm->id] ?? 0);
            if ($sale && $price > 0 && !$sale['staff'] && !$sale['ownscourse']) {
                if (!empty($sale['owned'][$cm->id])) {
                    $entry['marker'] = 'owned';
                } else {
                    $entry['marker'] = 'buy';
                    $entry['forsale'] = true;
                    $entry['price'] = ['price_minor' => $price, 'price' => round($price / 100, 2), 'currency' => 'EGP'];
                }
            } else if ($isfree) {
                $entry['marker'] = 'free';
            } else if (!$accessible) {
                $entry['marker'] = 'locked';
            }
            $w = $walk[(int) $cm->id] ?? null;
            if ($w) {
                $entry['locked'] = (bool) $w['locked'];
                $entry['forsale'] = $entry['forsale'] || (bool) $w['forsale'];
                $entry['completed'] = (bool) $w['done'];
                $entry['watched_percent'] = $w['watched'] === null ? null : (int) $w['watched'];
            } else if ($tracking && $completion->is_enabled($cm)) {
                $state = (int) $completion->get_data($cm, true, $userid)->completionstate;
                $entry['completed'] = in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
            }
            $entry['canopen'] = $userid && ($accessible || $entry['marker'] === 'owned')
                && !$entry['locked'] && !$entry['forsale'] && $cm->url !== null;
            return $entry;
        };

        $numsections = course_get_format($course)->get_last_section_number();
        $sections = [];
        $counts = ['sections' => 0, 'lessons' => 0, 'assessments' => 0];
        foreach ($modinfo->get_section_info_all() as $snum => $sec) {
            if ((method_exists($sec, 'is_delegated') && $sec->is_delegated()) || $snum > $numsections) {
                continue;
            }
            if ($snum === 0 && (empty($modinfo->sections[0]) || !$sec->uservisible)) {
                continue;
            }
            $show = $sec->uservisible || ($sec->visible && !$sec->available && !empty($sec->availableinfo))
                || (!$sec->visible && !$course->hiddensections);
            if (!$show) {
                continue;
            }
            $counts['sections']++;
            $items = [];
            $done = 0;
            $total = 0;
            if ($sec->uservisible) {
                foreach (($modinfo->sections[$snum] ?? []) as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }
                    if ($cm->modname === 'subsection') {
                        // A part: its own title and items.
                        $sub = $cm->get_delegated_section_info();
                        $part = ['part' => format_string($cm->name, true, ['context' => $cm->context]), 'items' => []];
                        if ($sub && $sub->uservisible) {
                            foreach (($modinfo->sections[$sub->section] ?? []) as $subcmid) {
                                $subcm = $modinfo->cms[$subcmid];
                                if ($subcm->uservisible && $subcm->modname !== 'subsection') {
                                    $part['items'][] = $item($subcm);
                                }
                            }
                        }
                        $items[] = $part;
                        continue;
                    }
                    $items[] = $item($cm);
                }
                // Progress / counts over the real lessons (labels and parts excluded).
                $flat = [];
                foreach ($items as $entry) {
                    $flat = array_merge($flat, isset($entry['part']) ? $entry['items'] : [$entry]);
                }
                foreach ($flat as $entry) {
                    if ($entry['islabel']) {
                        continue;
                    }
                    $counts['lessons']++;
                    if (in_array($entry['modname'], ['assign', 'quiz', 'workshop', 'lesson'], true)) {
                        $counts['assessments']++;
                    }
                    if (!$tracking || $completion->is_enabled($modinfo->cms[$entry['cmid']])) {
                        $total++;
                        $done += $entry['completed'] ? 1 : 0;
                    }
                }
            }
            $summaryhtml = ($sec->uservisible && !empty($sec->summary))
                ? format_text($sec->summary, $sec->summaryformat, ['context' => $context]) : '';
            $sections[] = [
                'id' => (int) $sec->id,
                'number' => (int) $snum,
                'name' => format_string(get_section_name($course, $sec), true, ['context' => $context]),
                'summary' => trim(preg_replace('/\s+/u', ' ', html_to_text($summaryhtml, 0, false))),
                'available' => (bool) $sec->uservisible,
                'availableinfo' => (!$sec->uservisible && !empty($sec->availableinfo))
                    ? trim(html_to_text(\core_availability\info::format_info($sec->availableinfo, $course), 0, false)) : '',
                'progress' => ['tracked' => (bool) $tracking, 'done' => $done, 'total' => $total],
                'items' => $items,
            ];
        }
        return [$sections, $counts];
    }

    /**
     * Price / access state of a course for a viewer (local_nit_category's card logic).
     *
     * @param int $courseid
     * @param int $userid 0 = visitor
     * @return array
     */
    public static function price_state(int $courseid, int $userid): array {
        $state = class_exists('\local_nit_category\catalogue')
            ? \local_nit_category\catalogue::course_state($courseid, $userid)
            : ['enrolled' => false, 'covered' => false, 'free' => true, 'haspricing' => false, 'price' => 0.0,
               'offerlabel' => '', 'offerfinal' => 0.0, 'currency' => ''];
        $price = (float) $state['price'];
        $final = (float) $state['offerfinal'];
        $hasoffer = $state['offerlabel'] !== '' && $final > 0 && $final < $price;
        return [
            'enrolled' => (bool) $state['enrolled'],
            'covered' => (bool) $state['covered'],
            'free' => (bool) $state['free'],
            'haspricing' => (bool) $state['haspricing'],
            'price' => $price,
            'price_minor' => (int) round($price * 100),
            'currency' => (string) ($state['currency'] ?: ($state['haspricing'] ? 'EGP' : '')),
            'hasoffer' => $hasoffer,
            'offerlabel' => (string) $state['offerlabel'],
            'finalprice' => $hasoffer ? $final : $price,
            'finalprice_minor' => (int) round(($hasoffer ? $final : $price) * 100),
            'discountpercent' => ($hasoffer && $price > 0) ? (int) round(($price - $final) / $price * 100) : 0,
        ];
    }

    /**
     * The course's teachers (teacher / editing teacher, site admins excluded) with
     * their title line and photo.
     *
     * @param \context_course $context
     * @return array
     */
    protected static function course_teachers(\context_course $context): array {
        global $PAGE;
        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        if (!$roles) {
            return [];
        }
        $fields = 'ra.id AS raid, u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                   u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
        $out = [];
        $seen = [];
        foreach (get_role_users(array_keys($roles), $context, false, $fields) as $u) {
            if (isset($seen[$u->id]) || is_siteadmin($u->id)) {
                continue;
            }
            $seen[$u->id] = true;
            $picture = new \user_picture($u);
            $picture->size = 512;
            $title = user_fields::values((int) $u->id)['teachertitle'] ?? '';
            $out[] = [
                'id' => (int) $u->id,
                'fullname' => fullname($u),
                'title' => trim(format_string((string) $title, true, ['escape' => false])),
                'photo' => endpoint::file_url($picture->get_url($PAGE)->out(false)),
            ];
        }
        return $out;
    }

    /**
     * Visible course custom fields (shortname => raw value). Teacher-only / hidden
     * fields are left out for viewers who cannot edit the course.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @param int $userid
     * @return array
     */
    protected static function custom_fields(\stdClass $course, \context_course $context, int $userid): array {
        $out = [];
        $canviewhidden = $userid && has_capability('moodle/course:update', $context, $userid);
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($course->id, true) as $data) {
                $field = $data->get_field();
                if ((int) $field->get_configdata_property('visibility') < 2 && !$canviewhidden) {
                    continue;
                }
                $out[$field->get('shortname')] = $data->get_value();
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * The forums of the course ("المنتدى" tab).
     *
     * @param \course_modinfo $modinfo
     * @return array
     */
    protected static function forums(\course_modinfo $modinfo): array {
        $out = [];
        foreach ($modinfo->get_instances_of('forum') as $cm) {
            if ($cm->uservisible && $cm->url) {
                $out[] = ['cmid' => (int) $cm->id, 'instance' => (int) $cm->instance,
                    'name' => format_string($cm->name, true, ['context' => $cm->context])];
            }
        }
        return $out;
    }

    /**
     * Whether the course has a video lesson (the play glyph on the cover).
     *
     * @param \course_modinfo $modinfo
     * @return bool
     */
    protected static function has_video(\course_modinfo $modinfo): bool {
        foreach ($modinfo->get_cms() as $cm) {
            if (in_array($cm->modname, player::VIDEO_MODS, true) && $cm->visible && !$cm->deletioninprogress) {
                return true;
            }
        }
        return false;
    }

    /**
     * The course overview image URL, or ''.
     *
     * @param \context_course $context
     * @return string
     */
    protected static function course_image(\context_course $context): string {
        $fs = get_file_storage();
        foreach ($fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false) as $file) {
            if ($file->is_valid_image()) {
                return \moodle_url::make_pluginfile_url($file->get_contextid(), 'course', 'overviewfiles', null,
                    $file->get_filepath(), $file->get_filename())->out(false);
            }
        }
        return '';
    }

    /**
     * An image URL the app can load, '' for Moodle's generated pattern (served
     * behind the log-in) — the app draws its own placeholder.
     *
     * @param string $url
     * @return string
     */
    protected static function image(string $url): string {
        if ($url === '' || strpos($url, '/course/generated/') !== false) {
            return '';
        }
        return endpoint::file_url($url);
    }

    /**
     * A bilingual {mlang} value in the current language (format_string, with a
     * fallback when the multi-language filter is not active in this context).
     *
     * @param string $raw
     * @param \context $context
     * @return string
     */
    public static function ml(string $raw, \context $context): string {
        if (trim($raw) === '') {
            return '';
        }
        $out = trim(format_string($raw, true, ['context' => $context, 'escape' => false]));
        if (stripos($out, '{mlang') === false) {
            return $out;
        }
        $lang = current_language();
        if (!preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $out, $blocks, PREG_SET_ORDER)) {
            return $out;
        }
        $first = null;
        foreach ($blocks as $block) {
            $first = $first ?? $block[2];
            if (in_array($lang, array_map('trim', explode(',', strtolower($block[1]))), true)) {
                return trim($block[2]);
            }
        }
        return trim((string) $first);
    }
}
