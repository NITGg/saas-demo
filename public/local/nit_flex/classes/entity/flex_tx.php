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

namespace local_nit_flex\entity;

use local_nit_core\base\entity;

/**
 * One row of the Flex ledger: a signed change to a student's remaining Flex balance,
 * with the before/after snapshot for auditability (US-AD-3-4).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flex_tx extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_flex_tx';

    /** Opening balance from an online purchase. */
    const TYPE_PURCHASE = 'purchase';

    /** Opening balance from an admin assignment. */
    const TYPE_ASSIGN = 'assign';

    /** One Flex held for a confirmed lesson. */
    const TYPE_RESERVE = 'reserve';

    /** One reserved Flex permanently spent. */
    const TYPE_CONSUME = 'consume';

    /** One reserved (or consumed) Flex returned to balance. */
    const TYPE_RETURN = 'return';

    /** Flex lost to package expiry. */
    const TYPE_EXPIRE = 'expire';

    /** Manual admin adjustment. */
    const TYPE_ADJUST = 'adjust';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'userid'         => ['type' => PARAM_INT],
            'purchaseid'     => ['type' => PARAM_INT],
            'lessonid'       => ['type' => PARAM_INT, 'default' => 0],
            'type'           => ['type' => PARAM_ALPHA],
            'amount'         => ['type' => PARAM_INT, 'default' => 0],
            'balance_before' => ['type' => PARAM_INT, 'default' => 0],
            'balance_after'  => ['type' => PARAM_INT, 'default' => 0],
            'performedby'    => ['type' => PARAM_INT, 'default' => 0],
            'reason'         => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
        ];
    }
}
