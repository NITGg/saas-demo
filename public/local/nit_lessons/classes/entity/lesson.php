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
 * A lesson between a student and a teacher, and its lifecycle state.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_lesson';

    /** Requested, awaiting teacher. */
    const STATUS_PENDING = 'pending';

    /** Awaiting the student's response to a suggested time. */
    const STATUS_WAITING_STUDENT = 'waiting_student';

    /** Awaiting the teacher's response to a suggested time. */
    const STATUS_WAITING_TEACHER = 'waiting_teacher';

    /** Confirmed; one Flex reserved. */
    const STATUS_CONFIRMED = 'confirmed';

    /** Room open. */
    const STATUS_IN_PROGRESS = 'in_progress';

    /** Completed; Flex consumed + revenue distributed. */
    const STATUS_COMPLETED = 'completed';

    /** Student absent; Flex consumed. */
    const STATUS_STUDENT_ABSENT = 'student_absent';

    /** Teacher absent; Flex returned. */
    const STATUS_TEACHER_ABSENT = 'teacher_absent';

    /** Cancelled by the student. */
    const STATUS_CANCELLED = 'cancelled';

    /** Cancelled by the teacher. */
    const STATUS_CANCELLED_TEACHER = 'cancelled_teacher';

    /** Rejected by the teacher. */
    const STATUS_REJECTED = 'rejected';

    /** Default lesson length in minutes. */
    const DEFAULT_DURATION = 60;

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'studentid'      => ['type' => PARAM_INT],
            'teacherid'      => ['type' => PARAM_INT],
            'subject'        => ['type' => PARAM_TEXT],
            'status'         => ['type' => PARAM_ALPHAEXT, 'default' => self::STATUS_PENDING],
            'requested_time' => ['type' => PARAM_INT, 'default' => 0],
            'confirmed_time' => ['type' => PARAM_INT, 'default' => 0],
            'duration'       => ['type' => PARAM_INT, 'default' => self::DEFAULT_DURATION],
            'note'           => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'reject_reason'  => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'cancel_reason'  => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'purchaseid'     => ['type' => PARAM_INT, 'default' => 0],
            'flex_state'     => ['type' => PARAM_ALPHA, 'default' => 'none'],
            'actual_start'   => ['type' => PARAM_INT, 'default' => 0],
            'actual_end'     => ['type' => PARAM_INT, 'default' => 0],
            'sessionid'      => ['type' => PARAM_INT, 'default' => 0],
            'cmid'           => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
