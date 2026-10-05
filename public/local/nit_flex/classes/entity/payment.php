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
 * A payment transaction for a package purchase (money in). amount_minor is integer minor units;
 * a negative amount records a refund.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_payment';

    /** Money received. */
    const STATUS_SUCCESS = 'success';

    /** Attempt failed. */
    const STATUS_FAILED = 'failed';

    /** Awaiting confirmation. */
    const STATUS_PENDING = 'pending';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'userid'         => ['type' => PARAM_INT],
            'purchaseid'     => ['type' => PARAM_INT],
            'packageid'      => ['type' => PARAM_INT],
            'amount_minor'   => ['type' => PARAM_INT, 'default' => 0],
            'method'         => ['type' => PARAM_ALPHANUMEXT, 'default' => 'online'],
            'reference'      => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED, 'default' => null],
            'transaction_no' => ['type' => PARAM_ALPHANUMEXT],
            'status'         => ['type' => PARAM_ALPHA, 'default' => self::STATUS_SUCCESS],
        ];
    }
}
