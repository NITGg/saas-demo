<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\local;

use local_nit_assistant\config;
use local_nit_assistant\llm\anthropic_client;
use local_nit_assistant\llm\client;

/**
 * Hook callbacks (db/hooks.php).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * The model client is the Anthropic SDK with the academy's own key.
     *
     * @param \core\hook\di_configuration $hook
     * @return void
     */
    public static function di_configuration(\core\hook\di_configuration $hook): void {
        $hook->add_definition(client::class, static function (): client {
            $key = config::apikey();
            if ($key === null) {
                throw new \moodle_exception('error_nokey', 'local_nit_assistant');
            }
            return new anthropic_client($key, (float) config::LIMITS['turntimeout']);
        });
        // WhatsApp Cloud API with the academy's own number (tests replace it too).
        $hook->add_definition(\local_nit_assistant\whatsapp\cloud::class, static function (): \local_nit_assistant\whatsapp\cloud {
            $cfg = \local_nit_assistant\whatsapp\cloud::config();
            if ($cfg === null) {
                throw new \moodle_exception('error_wanotset', 'local_nit_assistant');
            }
            return new \local_nit_assistant\whatsapp\cloud($cfg);
        });
    }
}
