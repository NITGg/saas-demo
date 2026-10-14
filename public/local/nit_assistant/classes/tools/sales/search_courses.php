<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\tools\sales;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * Course catalogue search, through the site's own catalogue
 * (local_nit_category\catalogue::search): the same courses a visitor sees,
 * with the price for their country and the current offer.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_courses extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'search_courses';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Search the course catalogue by words in the title or description (subject, grade, topic), in Arabic '
            . 'or English. Returns matching courses with category, teachers, rating, whether free, price for the '
            . 'visitor\'s country with currency, the current offer, and its page link. Leave query empty to list '
            . 'the newest courses. Use get_course for the details of one course.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales', 'student'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Words to look for; may be empty'],
                'price' => ['type' => 'string', 'enum' => ['all', 'free', 'paid'], 'description' => 'Default all'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8, 'description' => 'Default 5'],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        if (!class_exists('\local_nit_category\catalogue')) {
            return self::fail('service_unavailable', 'The course catalogue is not available.');
        }
        $limit = (int) ($input['limit'] ?? 5);
        $price = $input['price'] ?? 'all';
        $query = trim($input['query']);
        $result = self::search($query, $price, $limit, $ctx->userid, $query === '' ? 'newest' : 'recommended');
        if ($result['total'] === 0 && $query !== '') {
            // The catalogue matches the whole text; try the most telling single word.
            $words = preg_split('/\s+/u', $query);
            usort($words, static fn($a, $b) => \core_text::strlen($b) <=> \core_text::strlen($a));
            if (count($words) > 1 && \core_text::strlen($words[0]) >= 3) {
                $result = self::search($words[0], $price, $limit, $ctx->userid, 'recommended');
            }
        }
        $courses = array_map(static function (array $c) use ($ctx): array {
            $course = [
                'id' => (int) $c['id'],
                'name' => (string) $c['fullname'],
                'category' => (string) $c['categoryname'],
                'summary' => helper::plain($c['summary'], 200),
                'teachers' => array_map(static fn($t) => (string) ((array) $t)['fullname'], $c['teachers']),
                'rating' => $c['rating']['count'] ? $c['rating'] : null,
                'level' => $c['level'],
                'free' => (bool) $c['free'],
                'url' => helper::url('/local/academy/course.php', ['id' => $c['id']]),
            ];
            if (!$c['free'] && $c['haspricing']) {
                $course['price'] = helper::money($c['price']);
                $course['currency'] = (string) $c['currency'];
                if ($c['offerlabel'] !== '') {
                    $course['offer'] = ['label' => $c['offerlabel'], 'final_price' => helper::money($c['offerfinal'])];
                }
            }
            if ($ctx->userid > 0) {
                $course['enrolled'] = (bool) $c['enrolled'];
                $course['covered_by_your_subscription'] = (bool) $c['covered'];
            }
            return $course;
        }, $result['courses']);
        if (count($courses) === 1) {
            helper::button($ctx, 'action_course', $courses[0]['url'], $courses[0]['name']);
        } else if ($courses) {
            $params = $query !== '' ? ['q' => $query] : [];
            helper::button($ctx, 'action_catalogue', helper::url('/local/nit_category/index.php', $params));
        }
        return self::ok(['total' => $result['total'], 'courses' => $courses]);
    }

    /**
     * One catalogue search.
     *
     * @param string $q
     * @param string $price all | free | paid
     * @param int $limit
     * @param int $userid
     * @param string $sort
     * @return array
     */
    private static function search(string $q, string $price, int $limit, int $userid, string $sort): array {
        return \local_nit_category\catalogue::search(
            ['q' => $q, 'price' => $price, 'sort' => $sort, 'recursive' => true],
            0,
            $limit,
            $userid,
            $userid === 0
        );
    }
}
