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
 * A link to the site's own checkout page for an item. The assistant never
 * takes a payment: the visitor pays on the existing pages, which sign them in
 * first when needed.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class start_checkout extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'start_checkout';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'Get the link where the visitor buys a course, a subscription plan or a lesson package, shown to them '
            . 'as a button. Use it when they want to buy or subscribe. Payment happens on that page, not in the chat; '
            . 'visitors who are not signed in are asked to sign in or create an account there first.';
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
                'item_type' => ['type' => 'string', 'enum' => ['course', 'subscription', 'package']],
                'item_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Required for a course'],
            ],
            'required' => ['item_type'],
            'additionalProperties' => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $CFG, $DB;
        $type = $input['item_type'];
        if ($type === 'course') {
            $id = (int) ($input['item_id'] ?? 0);
            $course = $id > (int) SITEID ? $DB->get_record('course', ['id' => $id], 'id, fullname, visible') : null;
            if (!$course || !$course->visible) {
                return self::fail('not_found', 'No such course. Use search_courses to find its id.');
            }
            $free = !class_exists('\local_payments\price_resolver') || !\local_payments\price_resolver::has_pricing($id);
            $url = $free ? helper::url('/local/academy/course.php', ['id' => $id])
                : helper::url('/local/payments/buy.php', ['courseid' => $id]);
            helper::button($ctx, $free ? 'action_enrolfree' : 'action_buycourse', $url);
        } else if ($type === 'subscription') {
            $url = helper::url('/local/nit_lessons/student.php', ['tab' => 'subavailable']);
            helper::button($ctx, 'action_subscribe', $url);
        } else {
            $url = helper::url('/local/nit_lessons/student.php', ['tab' => 'flexavailable']);
            helper::button($ctx, 'action_buypackage', $url);
        }
        $out = ['url' => $url, 'signed_in' => $ctx->userid > 0];
        if ($ctx->userid === 0) {
            $out['note'] = 'They will be asked to sign in first; new students can create an account.';
            if (!empty($CFG->registerauth)) {
                helper::button($ctx, 'action_signup', helper::url('/login/signup.php'));
            }
        }
        return self::ok($out);
    }
}
