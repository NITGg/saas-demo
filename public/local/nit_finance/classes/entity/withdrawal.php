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
 * A teacher's request to withdraw earned money. Amount is integer minor units.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class withdrawal extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_withdrawal';

    /** Requested, amount held. */
    const STATUS_PENDING = 'pending';

    /** Approved by an admin, still held. */
    const STATUS_APPROVED = 'approved';

    /** Rejected, hold released. */
    const STATUS_REJECTED = 'rejected';

    /** Paid out, money has left the platform. */
    const STATUS_PAID = 'paid';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'teacherid'     => ['type' => PARAM_INT],
            'amount_minor'  => ['type' => PARAM_INT, 'default' => 0],
            'method'        => ['type' => PARAM_ALPHANUMEXT, 'default' => 'bank'],
            'account'       => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'reference'     => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'status'        => ['type' => PARAM_ALPHA, 'default' => self::STATUS_PENDING],
            'reason'        => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'processedby'   => ['type' => PARAM_INT, 'default' => 0],
            'timeprocessed' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }
}
