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
 * A Flex (lesson) package in the admin catalog. price_minor is integer minor units.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package extends entity {
    /** @var string Backing table. */
    const TABLE = 'nit_package';

    /** Purchasable. */
    const STATUS_ACTIVE = 'active';

    /** Hidden from students, retained for history. */
    const STATUS_INACTIVE = 'inactive';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name'            => ['type' => PARAM_TEXT],
            'description'     => ['type' => PARAM_RAW, 'null' => NULL_ALLOWED, 'default' => null],
            'flex_count'      => ['type' => PARAM_INT, 'default' => 0],
            'price_minor'     => ['type' => PARAM_INT, 'default' => 0],
            'expiration_days' => ['type' => PARAM_INT, 'default' => 0],
            'status'          => ['type' => PARAM_ALPHA, 'default' => self::STATUS_ACTIVE],
        ];
    }
}
