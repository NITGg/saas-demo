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

namespace aiplacement_nit_quizgen;

use core_ai\aiactions\generate_text;

/**
 * Registers quiz generation from a video transcript as an AI placement.
 *
 * Separate from aiplacement_nit_videoassist on purpose. The two features share
 * a transcript but not an audience or a cost profile: the assistant answers one
 * student's question, this writes a whole question bank. An administrator who
 * wants students chatting but nobody spending tokens on generation — or the
 * reverse — needs two switches, not one.
 *
 * @package    aiplacement_nit_quizgen
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
