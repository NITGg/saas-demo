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

namespace aiplacement_nit_videoassist;

use core_ai\aiactions\generate_text;

/**
 * Registers the video assistant as an AI placement.
 *
 * Deliberately thin. All of the assistant lives in local_nit_ai — the
 * transcripts, the checks, the chat, the player integration. This class exists
 * so administrators get one switch in the place they already look for AI
 * features, and so the assistant can be turned off per course or category the
 * same way Moodle's own AI features can.
 *
 * @package    aiplacement_nit_videoassist
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement extends \core_ai\placement {

    #[\Override]
    public static function get_action_list(): array {
        return [
            generate_text::class,
        ];
    }
}
