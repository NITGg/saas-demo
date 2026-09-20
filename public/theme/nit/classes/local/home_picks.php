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

namespace theme_nit\local;

/**
 * Which live items each data-driven homepage section shows.
 *
 * The Courses / Categories / Subscriptions / Coupons sections render REAL
 * records (never typed content). The owner picks which of them appear on the
 * homepage; an empty pick means "all" (the default). Picks are stored as
 * theme_nit/home_pick_<section> (JSON list of ids / coupon codes) and applied
 * client-side by the front-page renderer (window.NIT_PICKS), so every template
 * honours them without touching its stored HTML.
 *
 * Sections whose feature is not in the academy's licence (local_license) are
 * reported unlicensed: the front page hides them and the editor cannot add them.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home_picks {
    /** Sections that render picked live items → the licence feature gating them ('' = always). */
    const SECTIONS = ['courses' => '', 'categories' => '', 'subscriptions' => 'subscriptions', 'coupons' => 'coupons',
        'testimonials' => ''];

    /** Whether the academy's licence includes a section's feature. */
    public static function licensed(string $section): bool {
        $feature = self::SECTIONS[$section] ?? '';
        if ($feature === '') {
            return true;
        }
        if (!class_exists('\local_license\license')) {
            return true;
        }
        try {
            return \local_license\license::has_feature($feature);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** All licence flags, for the front page / editor. */
    public static function licence_flags(): array {
        $out = [];
        foreach (array_keys(self::SECTIONS) as $s) {
            $out[$s] = self::licensed($s);
        }
        return $out;
    }

    /**
     * The saved pick for a section: a list of ids (string coupon codes for
     * coupons), or null = show all.
     */
    public static function get(string $section): ?array {
        if (!array_key_exists($section, self::SECTIONS)) {
            return null;
        }
        $raw = (string) get_config('theme_nit', 'home_pick_' . $section);
        if ($raw === '') {
            return null;
        }
        $v = json_decode($raw, true);
        return (is_array($v) && $v) ? array_values($v) : null;
    }

    /** Every section's pick (null = all). */
    public static function all(): array {
        $out = [];
        foreach (array_keys(self::SECTIONS) as $s) {
            $out[$s] = self::get($s);
        }
        return $out;
    }

    /**
     * Save a pick. An empty list clears it (= all).
     *
     * @param string $section
     * @param array $ids
     */
    public static function set(string $section, array $ids): void {
        if (!array_key_exists($section, self::SECTIONS)) {
            return;
        }
        $clean = [];
        foreach ($ids as $id) {
            $id = $section === 'coupons' ? clean_param((string) $id, PARAM_ALPHANUMEXT) : (int) $id;
            if ($id !== '' && $id !== 0) {
                $clean[] = $id;
            }
        }
        set_config('home_pick_' . $section, $clean ? json_encode(array_values(array_unique($clean))) : '', 'theme_nit');
        purge_all_caches();
    }

    /**
     * The items an owner can pick from, per section: [{id, label}].
     * Editor-only (runs a few queries), never on the public render path.
     */
    public static function options(): array {
        global $DB;
        $out = ['courses' => [], 'categories' => [], 'subscriptions' => [], 'coupons' => [], 'testimonials' => []];

        $courses = $DB->get_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID],
            'sortorder ASC', 'id, fullname, category');
        foreach ($courses as $c) {
            $out['courses'][] = ['id' => (int) $c->id, 'label' => format_string($c->fullname)];
        }
        foreach (\core_course_category::top()->get_children() as $cat) {
            $out['categories'][] = ['id' => (int) $cat->id, 'label' => $cat->get_formatted_name()];
        }
        if (self::licensed('subscriptions') && $DB->get_manager()->table_exists('nit_subscription')) {
            foreach ($DB->get_records('nit_subscription', ['status' => 'active'], 'price ASC', 'id, name, price') as $p) {
                $out['subscriptions'][] = ['id' => (int) $p->id, 'label' => format_string($p->name)];
            }
        }
        foreach (self::reviews(50) as $r) {
            $out['testimonials'][] = ['id' => (int) $r['id'],
                'label' => $r['name'] . ' · ' . $r['course'] . ' · ' . str_repeat('★', (int) $r['rating']) . ' — '
                    . \core_text::substr($r['text'], 0, 80)];
        }
        if (self::licensed('coupons') && $DB->get_manager()->table_exists('nit_coupon')) {
            foreach ($DB->get_records('nit_coupon', ['status' => 'active'], 'timecreated DESC', 'id, code') as $cp) {
                $out['coupons'][] = ['id' => (string) $cp->code, 'label' => (string) $cp->code];
            }
        }
        return $out;
    }

    /**
     * Real course reviews with text (local_nit_reviews), newest first — the
     * testimonials feed. [{id, text, rating, name, course, avatar}].
     *
     * @param int $limit
     * @param int[]|null $onlyids restrict to these review ids (owner's pick)
     * @return array
     */
    public static function reviews(int $limit = 3, ?array $onlyids = null): array {
        global $DB, $PAGE;
        if (!$DB->get_manager()->table_exists('local_nit_reviews')) {
            return [];
        }
        $where = "r.review IS NOT NULL AND " . $DB->sql_isnotempty('local_nit_reviews', 'r.review', false, true);
        $params = [];
        if ($onlyids) {
            [$in, $params] = $DB->get_in_or_equal(array_map('intval', $onlyids), SQL_PARAMS_NAMED);
            $where .= " AND r.id $in";
        }
        // The homepage must never fail on the testimonials feed.
        try {
            $ufields = \core_user\fields::for_userpic()->with_name()->get_sql('u', false, '', '', false)->selects;
            $rows = $DB->get_records_sql("SELECT r.id, r.rating, r.review, r.timecreated, c.fullname AS coursename, $ufields
                                        FROM {local_nit_reviews} r
                                        JOIN {user} u ON u.id = r.userid
                                        JOIN {course} c ON c.id = r.courseid
                                       WHERE $where AND u.deleted = 0 AND c.visible = 1
                                    ORDER BY r.timecreated DESC", $params, 0, $limit);
        } catch (\Throwable $e) {
            debugging('theme_nit testimonials feed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $avatar = '';
            try {
                $up = new \user_picture($r);
                $up->size = 64;
                $avatar = $up->get_url($PAGE)->out(false);
            } catch (\Throwable $e) {
                $avatar = '';
            }
            $out[] = [
                'id'     => (int) $r->id,
                'text'   => trim((string) $r->review),
                'rating' => (int) $r->rating,
                'name'   => fullname($r),
                'course' => format_string($r->coursename),
                'avatar' => $avatar,
            ];
        }
        return $out;
    }
}
