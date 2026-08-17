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

namespace local_nit_core\flag;

/**
 * Feature flags declared by local_nit_core itself.
 *
 * Two demo flags that prove the engine end to end. Real feature flags are
 * declared by their own plugins as they are built.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_flags implements provider {
    /**
     * The SDK's demo flags.
     *
     * @return flag[]
     */
    public function get_flags(): array {
        return [
            new flag(
                'ui_compact_mode',
                get_string('flag_ui_compact_mode', 'local_nit_core'),
                get_string('flag_ui_compact_mode_desc', 'local_nit_core'),
                false,
                'ui'
            ),
            new flag(
                'experimental_preview',
                get_string('flag_experimental_preview', 'local_nit_core'),
                get_string('flag_experimental_preview_desc', 'local_nit_core'),
                false,
                'experimental'
            ),
        ];
    }
}
