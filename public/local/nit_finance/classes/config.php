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

namespace local_nit_finance;

use local_nit_core\api\config as core_config;

/**
 * Typed access to local_nit_finance settings (read through the SDK config manager).
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** @var string This plugin's component name. */
    const COMPONENT = 'local_nit_finance';

    /** Default teacher share of a consumed Flex (percent). */
    const DEFAULT_TEACHER_PERCENT = 40;

    /** Default platform share of a consumed Flex (percent). */
    const DEFAULT_PLATFORM_PERCENT = 60;

    /**
     * The teacher's share of a Flex value, in percent.
     *
     * @return int
     */
    public static function teacher_percent(): int {
        return (int) core_config::for_plugin(self::COMPONENT)
            ->get_int('teacher_percent', self::DEFAULT_TEACHER_PERCENT);
    }

    /**
     * The platform's share of a Flex value, in percent.
     *
     * @return int
     */
    public static function platform_percent(): int {
        return (int) core_config::for_plugin(self::COMPONENT)
            ->get_int('platform_percent', self::DEFAULT_PLATFORM_PERCENT);
    }
}
