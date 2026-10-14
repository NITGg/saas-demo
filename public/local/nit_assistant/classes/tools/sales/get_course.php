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
 * One course's details, from the same data as its public page
 * (local_academy\api\pages::course): what it teaches, who teaches it, its
 * outline, and the price and next step for this visitor.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_course extends tool {
    /** Items per list (outline, outcomes…). */
    private const MAXITEMS = 12;

    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'get_course';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Details of one course by id (from search_courses): description, teachers, what students learn, '
            . 'who it is for, prerequisites, number of lessons, outline, hours, certificate, price for the visitor\'s '
            . 'country with the current offer, whether an active subscription covers it, and the next step '
            . '(sign in, buy, enrol free, or open).';
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
            'properties' => ['courseid' => ['type' => 'integer', 'minimum' => 2]],
            'required' => ['courseid'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        if (!class_exists('\local_academy\api\pages')) {
            return self::fail('service_unavailable', 'Course details are not available.');
        }
        try {
            $d = \local_academy\api\pages::course((int) $input['courseid'], $ctx->userid);
        } catch (\moodle_exception $e) {
            return self::fail('not_found', 'No such course. Use search_courses to find its id.');
        }
        $c = $d['course'];
        $p = $d['price'];
        $list = static fn(array $items): array => array_slice(array_map('strval', $items), 0, self::MAXITEMS);
        $url = helper::url('/local/academy/course.php', ['id' => $c['id']]);
        $out = [
            'id' => (int) $c['id'],
            'name' => (string) $c['fullname'],
            'category' => $c['category']['name'] ?? '',
            'study_system' => (string) $c['studysystem'],
            'division' => (string) $c['division'],
            'subject' => (string) $c['subject'],
            'language' => (string) $c['language'],
            'description' => helper::plain($c['summary'], 1200),
            'teachers' => array_map(static fn($t) => (string) $t['fullname'], $d['teachers']),
            'learn' => $list($d['learn']),
            'skills' => $list($d['skills']),
            'audience' => $list($d['audience']),
            'prerequisites' => $list($d['prerequisites']),
            'lessons' => (int) ($d['counts']['lessons'] ?? 0),
            'assessments' => (int) ($d['counts']['assessments'] ?? 0),
            'hours' => $c['hours'],
            'certificate' => (bool) $c['certificate'],
            'outline' => $list(array_map(static fn($s) => $s['name'], $d['sections'])),
            'free' => (bool) $p['free'],
            'next_step' => (string) $d['action'],
            'url' => $url,
        ];
        if (!$p['free']) {
            $out['price'] = helper::money($p['price']);
            $out['currency'] = (string) $p['currency'];
            if ($p['hasoffer']) {
                $out['offer'] = ['label' => $p['offerlabel'], 'final_price' => helper::money($p['finalprice'])];
            }
        }
        if ($ctx->userid > 0) {
            $out['enrolled'] = (bool) $p['enrolled'];
            $out['covered_by_your_subscription'] = (bool) $p['covered'];
        }
        helper::button($ctx, 'action_course', $url, $out['name']);
        return self::ok($out);
    }
}
