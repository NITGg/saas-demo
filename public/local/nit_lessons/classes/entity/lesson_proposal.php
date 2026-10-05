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

namespace local_nit_lessons\entity;

use local_nit_core\base\entity;

/**
 * A suggested or reschedule time proposed during lesson negotiation.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_proposal extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_lesson_proposal';

    /** A suggested time during the pre-confirm negotiation. */
    const TYPE_SUGGEST = 'suggest';

    /** A reschedule request on an already-confirmed lesson. */
    const TYPE_RESCHEDULE = 'reschedule';

    /** Awaiting a response. */
    const STATUS_PENDING = 'pending';

    /** Accepted. */
    const STATUS_ACCEPTED = 'accepted';

    /** Rejected. */
    const STATUS_REJECTED = 'rejected';

    /** Superseded by a newer proposal. */
    const STATUS_SUPERSEDED = 'superseded';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'lessonid'      => ['type' => PARAM_INT],
            'proposedby'    => ['type' => PARAM_INT],
            'role'          => ['type' => PARAM_ALPHA],
            'proposed_time' => ['type' => PARAM_INT],
            'type'          => ['type' => PARAM_ALPHA, 'default' => self::TYPE_SUGGEST],
            'status'        => ['type' => PARAM_ALPHA, 'default' => self::STATUS_PENDING],
        ];
    }
}
