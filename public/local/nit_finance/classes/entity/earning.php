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

namespace local_nit_finance\entity;

use local_nit_core\base\entity;

/**
 * A recorded teacher/platform revenue split for one completed lesson.
 *
 * All money fields are integer minor units (piastres). By construction
 * teacher_amount_minor + platform_amount_minor == flex_value_minor.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class earning extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_earning';

    /** Active earning: counts toward balances. */
    const STATUS_ACTIVE = 'active';

    /** Reversed earning: no longer counts. */
    const STATUS_REVERSED = 'reversed';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'lessonid'              => ['type' => PARAM_INT],
            'teacherid'             => ['type' => PARAM_INT],
            'studentid'             => ['type' => PARAM_INT, 'default' => 0],
            'purchaseid'            => ['type' => PARAM_INT, 'default' => 0],
            'flex_value_minor'      => ['type' => PARAM_INT, 'default' => 0],
            'teacher_amount_minor'  => ['type' => PARAM_INT, 'default' => 0],
            'platform_amount_minor' => ['type' => PARAM_INT, 'default' => 0],
            'teacher_percent'       => ['type' => PARAM_INT, 'default' => 0],
            'platform_percent'      => ['type' => PARAM_INT, 'default' => 0],
            'status'                => ['type' => PARAM_ALPHA, 'default' => self::STATUS_ACTIVE],
            'reverse_reason'        => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'reversedby'            => ['type' => PARAM_INT, 'default' => 0],
            'timereversed'          => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
