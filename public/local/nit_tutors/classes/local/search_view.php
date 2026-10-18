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

namespace local_nit_tutors\local;

use local_nit_lessons\service\teacher_service;

/**
 * The "Teachers" tab of the catalogue (/local/nit_category/index.php?tab=teachers): the filters
 * (template search_filters, in the catalogue's side column), the sort options (the catalogue's
 * sort box) and the teacher cards (template search_results, in its main column). The catalogue
 * draws the frame — title, search box, Courses | Teachers tabs, sort, side column — so both tabs
 * keep every element in the same place.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_view {

    /** Teachers per page. */
    const PERPAGE = 24;

    /** The id of the one GET form every control of the tab submits with. */
    const FORMID = 'nittu-filterform';

    /**
     * The filters as requested.
     *
     * @return array for search::normalise()
     */
    public static function raw_from_request(): array {
        return [
            'q' => optional_param('q', '', PARAM_TEXT),
            'subject' => optional_param('subject', '', PARAM_TEXT),
            'pricemin' => optional_param('pricemin', '', PARAM_RAW_TRIMMED),
            'pricemax' => optional_param('pricemax', '', PARAM_RAW_TRIMMED),
            'day' => optional_param('day', '', PARAM_RAW_TRIMMED),
            'time' => optional_param('time', '', PARAM_ALPHA),
            'rating' => optional_param('rating', '', PARAM_RAW_TRIMMED),
            'badge' => optional_param('badge', '', PARAM_RAW_TRIMMED),
            'sort' => optional_param('sort', 'recommended', PARAM_ALPHAEXT),
            'f' => optional_param_array('f', [], PARAM_TEXT),
        ];
    }

    /**
     * URL parameters of these filters (without the page): the tab's links and pager keep them.
     *
     * @param array $filters normalised
     * @return array
     */
    public static function params(array $filters): array {
        $params = array_filter([
            'q' => $filters['q'], 'subject' => $filters['subject'], 'pricemin' => $filters['pricemin'],
            'pricemax' => $filters['pricemax'], 'day' => $filters['day'], 'time' => $filters['time'],
            'rating' => $filters['rating'] ?: null, 'badge' => $filters['badge'] ?: null,
            'sort' => $filters['sort'] !== 'recommended' ? $filters['sort'] : null,
        ], fn($v) => $v !== null && $v !== '');
        foreach ($filters['f'] as $fid => $v) {
            $params["f[$fid]"] = $v;
        }
        return $params;
    }

    /**
     * Everything the tab shows.
     *
     * @param array $filters normalised (search::normalise())
     * @param int $page
     * @param \moodle_url $reseturl the tab with only the search words
     * @return array {total, filters (context of search_filters), results (context of search_results),
     *     sorts [{value, label, selected}]}
     */
    public static function context(array $filters, int $page, \moodle_url $reseturl): array {
        global $USER;
        $viewer = isloggedin() && !isguestuser() ? (int) $USER->id : 0;
        $result = search::run($filters, $viewer, $page, self::PERPAGE);
        $opt = fn($value, string $label, bool $selected) => ['value' => $value, 'label' => $label, 'selected' => $selected];

        $subjects = [];
        foreach (teacher_service::site_subjects() as $s) {
            $subjects[] = $opt($s, format_string($s), $s === $filters['subject']);
        }
        $days = [];
        foreach ([6, 0, 1, 2, 3, 4, 5] as $d) {
            $days[] = $opt($d, get_string('day' . $d, 'local_nit_lessons'), $filters['day'] === $d);
        }
        $times = [];
        foreach (array_keys(search::TIMES) as $t) {
            $times[] = $opt($t, get_string('time_' . $t, 'local_nit_tutors'), $filters['time'] === $t);
        }
        $ratings = [];
        foreach (['4.5', '4', '3'] as $r) {
            $ratings[] = $opt($r, get_string('ratingup', 'local_nit_tutors', $r), (string) $filters['rating'] === $r);
        }
        $sorts = [];
        foreach (search::SORTS as $s) {
            $sorts[] = $opt($s, get_string('sort_' . $s, 'local_nit_tutors'), $filters['sort'] === $s);
        }
        $fieldfilters = [];
        foreach (search::filter_fields() as $f) {
            $options = [];
            foreach (fields::options($f) as $o) {
                $options[] = $opt($o, format_string($o), ($filters['f'][$f->id] ?? '') === $o);
            }
            $fieldfilters[] = ['id' => $f->id, 'label' => format_string($f->name), 'options' => $options];
        }
        $badges = [];
        foreach (badge::all() as $b) {
            $badges[] = $opt($b->id, format_string($b->name), (int) $b->id === $filters['badge']);
        }
        $params = self::params($filters);
        $active = count($params) - (isset($params['q']) ? 1 : 0) - (isset($params['sort']) ? 1 : 0);
        $cards = array_map([search::class, 'card'], $result['teachers']);

        return [
            'total' => $result['total'],
            'sorts' => $sorts,
            'filters' => [
                'formid' => self::FORMID,
                'pricemin' => $filters['pricemin'],
                'pricemax' => $filters['pricemax'],
                'subjects' => $subjects,
                'days' => $days,
                'times' => $times,
                'ratings' => $ratings,
                'fieldfilters' => $fieldfilters,
                'hasbadgefilter' => !empty($badges),
                'badges' => $badges,
                'hasfilters' => $active > 0,
                'reseturl' => $reseturl->out(false),
            ],
            'results' => [
                'teachers' => $cards,
                'hasteachers' => !empty($cards),
                'applyurl' => (new \moodle_url('/local/nit_tutors/apply.php'))->out(false),
            ],
        ];
    }
}
