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

namespace local_nit_category;

use core_course_category;
use core_course_list_element;

/**
 * The course catalogue: filters, the category → courses tree and the per-course
 * card state (enrolment, subscription cover, price, offer).
 *
 * One implementation shared by the web catalogue (index.php) and the mobile
 * JSON API (api.php), so both always agree on what a filter means and what a
 * card says.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {

    /** @var array<string,array> Sort key => core_course_category::get_courses() sort spec. */
    const SORTS = [
        'recommended' => ['sortorder' => 1],       // the site's curated order
        'newest'      => ['timecreated' => -1],    // most recently created first
        'az'          => ['fullname' => 1],        // alphabetical
    ];

    /** @var string[] Accepted price filters. */
    const PRICES = ['all', 'free', 'paid'];

    /**
     * Whether the reviews plugin (rating filter / stars) is present.
     *
     * @return bool
     */
    public static function has_reviews(): bool {
        return class_exists('\local_nit_reviews\api');
    }

    /**
     * Whether course pricing + the offer engine are present (price filter, prices, offers).
     *
     * @return bool
     */
    public static function checkout_available(): bool {
        global $CFG;
        return class_exists('\local_payments\price_resolver')
            && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
            && class_exists('\local_nit_commerce\discount_manager');
    }

    /**
     * Validate raw filter values, falling back to the defaults on anything unknown
     * (the same rules the web page has always applied).
     *
     * @param string $sort recommended|newest|az
     * @param string $price all|free|paid
     * @param string $level a level option ('' = all)
     * @param int $rating minimum stars 1..5 (0 = all)
     * @return array{sort:string, price:string, level:string, rating:int}
     */
    public static function normalise_filters(string $sort, string $price, string $level, int $rating): array {
        if (!isset(self::SORTS[$sort])) {
            $sort = 'recommended';
        }
        if (!in_array($price, self::PRICES, true)) {
            $price = 'all';
        }
        if ($level !== '' && !in_array($level, course_meta::level_options(), true)) {
            $level = '';
        }
        if ($rating < 1 || $rating > 5 || !self::has_reviews()) {
            $rating = 0;
        }
        return ['sort' => $sort, 'price' => $price, 'level' => $level, 'rating' => $rating];
    }

    /**
     * Courses of one category, in the chosen order (only those the user may see).
     *
     * @param core_course_category $cat
     * @param bool $recursive include every descendant category
     * @param string $sort a SORTS key
     * @return core_course_list_element[]
     */
    public static function fetch_courses(core_course_category $cat, bool $recursive, string $sort): array {
        return $cat->get_courses([
            'recursive'      => $recursive,
            'sort'           => self::SORTS[$sort] ?? self::SORTS['recommended'],
            'summary'        => true,
            'coursecontacts' => true,
        ]);
    }

    /**
     * A category "node": its own (direct) courses plus a node for every child
     * category, recursively.
     *
     * @param core_course_category $cat
     * @param string $sort
     * @return array{cat: core_course_category, courses: array, children: array}
     */
    public static function build_node(core_course_category $cat, string $sort): array {
        $children = [];
        foreach ($cat->get_children() as $child) {
            $children[] = self::build_node($child, $sort);
        }
        return [
            'cat'      => $cat,
            'courses'  => self::fetch_courses($cat, false, $sort), // Direct only; descendants are child nodes.
            'children' => $children,
        ];
    }

    /**
     * Total courses in a node's whole subtree.
     *
     * @param array $node
     * @return int
     */
    public static function count_tree(array $node): int {
        $n = count($node['courses']);
        foreach ($node['children'] as $child) {
            $n += self::count_tree($child);
        }
        return $n;
    }

    /**
     * Does a course pass the price / level / rating filters?
     *
     * @param int $courseid
     * @param array $filters from normalise_filters()
     * @return bool
     */
    public static function course_matches(int $courseid, array $filters): bool {
        if (($filters['price'] ?? 'all') !== 'all' && class_exists('\local_payments\price_resolver')) {
            $paid = (bool) \local_payments\price_resolver::has_pricing($courseid);
            if ($filters['price'] === 'paid' ? !$paid : $paid) {
                return false;
            }
        }
        if (($filters['level'] ?? '') !== '' && course_meta::get_level($courseid) !== $filters['level']) {
            return false;
        }
        if (($filters['rating'] ?? 0) > 0) {
            $agg = \local_nit_reviews\api::get_aggregate($courseid);
            if ($agg->count < 1 || $agg->avg < $filters['rating']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether any filter is active (the tree only needs walking when one is).
     *
     * @param array $filters
     * @return bool
     */
    public static function has_active_filter(array $filters): bool {
        $price = ($filters['price'] ?? 'all') !== 'all' && class_exists('\local_payments\price_resolver');
        return $price || ($filters['level'] ?? '') !== '' || ($filters['rating'] ?? 0) > 0;
    }

    /**
     * Recursively drop the courses of a node that fail the filters.
     *
     * @param array $node
     * @param array $filters
     * @return array
     */
    public static function filter_node(array $node, array $filters): array {
        $node['courses'] = array_values(array_filter($node['courses'],
            static fn($c) => self::course_matches((int) $c->id, $filters)));
        $node['children'] = array_map(static fn($child) => self::filter_node($child, $filters), $node['children']);
        return $node;
    }

    /**
     * The sections the web catalogue renders for a parent category:
     *   - no subcategories → one node for the (leaf) parent itself;
     *   - a chosen subcategory → that subtree;
     *   - "All" → a node for courses sitting directly under the parent (if any),
     *     then one subtree per subcategory.
     * Filters are applied and empty subtrees dropped.
     *
     * @param core_course_category $category the parent (top() for "all")
     * @param core_course_category[] $subcategories its direct children
     * @param core_course_category|null $target the chosen subcategory, null = all
     * @param array $filters from normalise_filters()
     * @return array[] root nodes
     */
    public static function root_nodes(core_course_category $category, array $subcategories,
            ?core_course_category $target, array $filters): array {
        $sort = $filters['sort'];
        $rootnodes = [];
        if (empty($subcategories)) {
            $rootnodes[] = self::build_node($category, $sort);
        } else if ($target) {
            $rootnodes[] = self::build_node($target, $sort);
        } else {
            $directcourses = self::fetch_courses($category, false, $sort);
            if (!empty($directcourses)) {
                $rootnodes[] = ['cat' => $category, 'courses' => $directcourses, 'children' => []];
            }
            foreach ($subcategories as $sc) {
                $rootnodes[] = self::build_node($sc, $sort);
            }
        }
        if (self::has_active_filter($filters)) {
            $rootnodes = array_map(static fn($n) => self::filter_node($n, $filters), $rootnodes);
        }
        return array_values(array_filter($rootnodes, static fn($n) => self::count_tree($n) > 0));
    }

    /**
     * Per-course card state: enrolment, subscription coverage, pricing and offer.
     *
     * @param int $courseid
     * @param int $userid 0 = anonymous
     * @return array{enrolled:bool, covered:bool, free:bool, haspricing:bool, price:float,
     *               offerlabel:string, offerfinal:float, currency:string}
     */
    public static function course_state(int $courseid, int $userid): array {
        $out = ['enrolled' => false, 'covered' => false, 'free' => true, 'haspricing' => false,
            'price' => 0.0, 'offerlabel' => '', 'offerfinal' => 0.0, 'currency' => ''];
        $ctx = \context_course::instance($courseid);
        $out['enrolled'] = $userid > 0 && is_enrolled($ctx, $userid, '', true);

        if (!self::checkout_available()) {
            return $out;
        }
        $out['haspricing'] = (bool) \local_payments\price_resolver::has_pricing($courseid);
        $out['free'] = !$out['haspricing'];

        // Covered by an active subscription (grants access without buying). Only relevant
        // when not already enrolled and the course is paid (a free course is just "enrol").
        if (!$out['enrolled'] && $out['haspricing']
                && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            $out['covered'] = (bool) \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $userid);
        }

        if ($out['haspricing']) {
            try {
                $pricing = \local_payments\price_resolver::resolve($courseid, $userid);
                $base = (float) $pricing->price;
                $out['price'] = $base;
                $out['currency'] = (string) $pricing->currency;
                $summary = \local_nit_commerce\discount_manager::offer_summary('course', $courseid, $base);
                if ($summary) {
                    $out['offerlabel'] = $summary['label'];   // E.g. "-40%".
                    $out['offerfinal'] = (float) $summary['final'];
                }
            } catch (\Throwable $e) {
                // Leave defaults on any pricing error.
                unset($e);
            }
        }
        return $out;
    }

    // ── Mobile API ────────────────────────────────────────────────────────────

    /**
     * The category tree with recursive course counts (only categories the
     * user may see; hidden ones are skipped for public callers).
     *
     * @param bool $publiconly true for anonymous (pre-login) callers: hide hidden categories
     * @return array[] [{id, name, description, parent, depth, coursecount, children[]}]
     */
    public static function category_tree(bool $publiconly = false): array {
        return self::category_children(core_course_category::top(), $publiconly);
    }

    /**
     * Recursive helper for category_tree().
     *
     * @param core_course_category $cat
     * @param bool $publiconly
     * @return array[]
     */
    protected static function category_children(core_course_category $cat, bool $publiconly): array {
        $out = [];
        foreach ($cat->get_children() as $child) {
            if ($publiconly && !$child->visible) {
                continue;
            }
            $children = self::category_children($child, $publiconly);
            $out[] = [
                'id'          => (int) $child->id,
                'name'        => $child->get_formatted_name(),
                'description' => self::plain(format_text((string) $child->description,
                    $child->descriptionformat, ['context' => $child->get_context()])),
                'parent'      => (int) $child->parent,
                'depth'       => (int) $child->depth,
                'coursecount' => $publiconly
                    ? count(self::public_courses(self::fetch_courses($child, true, 'recommended')))
                    : (int) $child->get_courses_count(['recursive' => true]),
                'children'    => $children,
            ];
        }
        return $out;
    }

    /**
     * Drop hidden courses (and courses inside hidden categories) for public callers.
     *
     * @param core_course_list_element[] $courses
     * @return core_course_list_element[]
     */
    protected static function public_courses(array $courses): array {
        return array_values(array_filter($courses, static function ($c) {
            if (empty($c->visible)) {
                return false;
            }
            $cat = core_course_category::get((int) $c->category, IGNORE_MISSING, true);
            return $cat && $cat->is_uservisible() && self::category_path_visible($cat);
        }));
    }

    /**
     * Whether a category and all its parents are visible.
     *
     * @param core_course_category $cat
     * @return bool
     */
    protected static function category_path_visible(core_course_category $cat): bool {
        foreach ($cat->get_parents() as $pid) {
            $p = core_course_category::get($pid, IGNORE_MISSING, true);
            if (!$p || !$p->visible) {
                return false;
            }
        }
        return (bool) $cat->visible;
    }

    /**
     * Search the catalogue the way the web page filters it, flat and paged.
     *
     * @param array $params categoryid (0 = all), recursive (bool), q (search text),
     *                      sort, price, level, rating
     * @param int $page 0-based
     * @param int $perpage
     * @param int $userid card-state user (0 = anonymous)
     * @param bool $publiconly hide hidden courses / categories (anonymous callers)
     * @param callable|null $fileurl maps a pluginfile URL to one the client can load
     * @return array{total:int, page:int, perpage:int, filters:array, courses:array[]}
     */
    public static function search(array $params, int $page, int $perpage, int $userid, bool $publiconly = false,
            ?callable $fileurl = null): array {
        $filters = self::normalise_filters((string) ($params['sort'] ?? 'recommended'),
            (string) ($params['price'] ?? 'all'), (string) ($params['level'] ?? ''), (int) ($params['rating'] ?? 0));

        $categoryid = (int) ($params['categoryid'] ?? 0);
        $category = $categoryid ? core_course_category::get($categoryid, IGNORE_MISSING) : core_course_category::top();
        if (!$category || ($publiconly && $categoryid && !self::category_path_visible($category))) {
            throw new \moodle_exception('err_categorynotfound', 'local_nit_category');
        }
        $recursive = !array_key_exists('recursive', $params) || !empty($params['recursive']);
        $courses = self::fetch_courses($category, $recursive || !$categoryid, $filters['sort']);
        if ($publiconly) {
            $courses = self::public_courses($courses);
        }

        $q = \core_text::strtolower(trim((string) ($params['q'] ?? '')));
        $matched = [];
        foreach ($courses as $course) {
            if ($q !== '' && !self::matches_text($course, $q)) {
                continue;
            }
            if (self::has_active_filter($filters) && !self::course_matches((int) $course->id, $filters)) {
                continue;
            }
            $matched[] = $course;
        }

        $total = count($matched);
        $slice = array_slice($matched, $page * $perpage, $perpage);
        $ids = array_map(static fn($c) => (int) $c->id, $slice);
        $teachers = self::teachers($ids);
        $ratings = self::has_reviews() ? \local_nit_reviews\api::get_aggregates($ids) : [];

        $out = [];
        foreach ($slice as $course) {
            $out[] = self::export_course($course, $userid, $teachers[(int) $course->id] ?? [],
                $ratings[(int) $course->id] ?? null, $fileurl);
        }

        return [
            'total'   => $total,
            'page'    => $page,
            'perpage' => $perpage,
            'filters' => $filters + [
                'levels'         => array_map(static fn($l) => ['value' => $l, 'label' => format_string($l)],
                    course_meta::level_options()),
                'pricingenabled' => self::checkout_available(),
                'ratingenabled'  => self::has_reviews(),
            ],
            'courses' => $out,
        ];
    }

    /**
     * Case-insensitive text match on the course names and summary.
     *
     * @param core_course_list_element $course
     * @param string $q lower-cased needle
     * @return bool
     */
    protected static function matches_text(core_course_list_element $course, string $q): bool {
        $hay = \core_text::strtolower(implode(' ', [
            $course->get_formatted_name(),
            (string) $course->fullname,
            (string) $course->shortname,
            strip_tags((string) ($course->summary ?? '')),
        ]));
        return strpos($hay, $q) !== false;
    }

    /**
     * One course as the app's catalogue card.
     *
     * @param core_course_list_element $course
     * @param int $userid
     * @param array $teachers [{id, fullname}]
     * @param object|null $rating {avg, count}
     * @param callable|null $fileurl
     * @return array
     */
    public static function export_course(core_course_list_element $course, int $userid, array $teachers,
            ?object $rating, ?callable $fileurl = null): array {
        $courseid = (int) $course->id;
        $context = \context_course::instance($courseid);

        $image = '';
        foreach ($course->get_course_overviewfiles() as $f) {
            if ($f->is_valid_image()) {
                $image = \moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(),
                    $f->get_filearea(), $f->get_itemid() ?: null, $f->get_filepath(), $f->get_filename())->out(false);
                break;
            }
        }
        if ($image !== '' && $fileurl) {
            $image = $fileurl($image);
        }

        $category = core_course_category::get((int) $course->category, IGNORE_MISSING, true);
        $level = course_meta::get_level($courseid);
        $state = self::course_state($courseid, $userid);

        return [
            'id'           => $courseid,
            'fullname'     => $course->get_formatted_name(),
            'shortname'    => format_string($course->shortname, true, ['context' => $context]),
            'summary'      => self::plain(format_text((string) ($course->summary ?? ''),
                (int) ($course->summaryformat ?? FORMAT_HTML), ['context' => $context, 'noclean' => false])),
            'categoryid'   => (int) $course->category,
            'categoryname' => $category ? $category->get_formatted_name() : '',
            'image'        => $image,
            'teachers'     => $teachers,
            'rating'       => [
                'avg'   => $rating ? round((float) $rating->avg, 1) : 0.0,
                'count' => $rating ? (int) $rating->count : 0,
            ],
            'level'        => $level !== null ? format_string($level) : null,
            'timecreated'  => (int) $course->timecreated,
            'enrolled'     => $state['enrolled'],
            'covered'      => $state['covered'],
            'free'         => $state['free'],
            'haspricing'   => $state['haspricing'],
            'price'        => $state['price'],
            'price_minor'  => (int) round($state['price'] * 100),
            'currency'     => $state['currency'],
            'offerlabel'   => $state['offerlabel'],
            'offerfinal'   => $state['offerfinal'],
            'offerfinal_minor' => (int) round($state['offerfinal'] * 100),
        ];
    }

    /**
     * Teachers (editingteacher / teacher archetypes) of many courses in one query.
     *
     * @param int[] $courseids
     * @return array<int, array[]> courseid => [{id, fullname}]
     */
    public static function teachers(array $courseids): array {
        global $DB;
        $courseids = array_values(array_filter(array_map('intval', $courseids)));
        if (!$courseids) {
            return [];
        }
        $roleids = $DB->get_fieldset_select('role', 'id', "archetype IN ('editingteacher', 'teacher')");
        if (!$roleids) {
            return [];
        }
        [$rsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT ra.id AS raid, ctx.instanceid AS courseid, u.id, $namefields
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :lvl
                  JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                 WHERE ra.roleid $rsql AND ctx.instanceid $csql
              ORDER BY ra.timemodified ASC, ra.id ASC";
        $rows = $DB->get_records_sql($sql, $rparams + $cparams + ['lvl' => CONTEXT_COURSE]);
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $cid = (int) $r->courseid;
            if (isset($seen[$cid][(int) $r->id])) {
                continue; // Same person holding two teacher roles.
            }
            $seen[$cid][(int) $r->id] = true;
            $out[$cid][] = ['id' => (int) $r->id, 'fullname' => fullname($r)];
        }
        return $out;
    }

    /**
     * Formatted HTML → trimmed plain text.
     *
     * @param string $html
     * @return string
     */
    public static function plain(string $html): string {
        return trim(html_to_text($html, 0, false));
    }
}
