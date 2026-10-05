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
 * A student's package purchase: snapshot of the package terms plus the live Flex balance
 * (remaining / reserved / consumed). price_paid_minor is integer minor units.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_purchase extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_package_purchase';

    /** Paid and usable. */
    const STATUS_ACTIVE = 'active';

    /** Awaiting payment. */
    const STATUS_PENDING = 'pending';

    /** All Flex spent. */
    const STATUS_FULLY_USED = 'fully_used';

    /** Expired by time. */
    const STATUS_EXPIRED = 'expired';

    /** Cancelled/unassigned. */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'packageid'        => ['type' => PARAM_INT],
            'userid'           => ['type' => PARAM_INT],
            'price_paid_minor' => ['type' => PARAM_INT, 'default' => 0],
            'flex_count'       => ['type' => PARAM_INT, 'default' => 0],
            'expiration_days'  => ['type' => PARAM_INT, 'default' => 0],
            'status'           => ['type' => PARAM_ALPHAEXT, 'default' => self::STATUS_ACTIVE],
            'source'           => ['type' => PARAM_ALPHAEXT, 'default' => 'online'],
            'remaining_flex'   => ['type' => PARAM_INT, 'default' => 0],
            'reserved_flex'    => ['type' => PARAM_INT, 'default' => 0],
            'consumed_flex'    => ['type' => PARAM_INT, 'default' => 0],
            'expires_at'       => ['type' => PARAM_INT, 'default' => 0],
            'timeactivated'    => ['type' => PARAM_INT, 'default' => 0],
            'expiry_notified'  => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
