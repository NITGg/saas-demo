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

declare(strict_types = 1);

namespace mod_games\completion;

use core_completion\activity_custom_completion;
use mod_games\play_manager;

/**
 * The two ways a Game activity can complete itself: enough rounds played, or a
 * good enough round.
 *
 * Both are deliberately generous. The corner's design rule is that a wrong
 * answer is never a punishment, and a completion rule that can be failed would
 * be exactly that - so "play it three times" is a rule a child cannot lose, and
 * the score rule is measured against their best round rather than their last.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * The completion state of one rule for this user.
     *
     * @param string $rule the completion rule
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $game = $DB->get_record('games', ['id' => $this->cm->instance],
            'id, completionplays, completionscore', MUST_EXIST);
        $played = play_manager::get((int) $this->cm->instance, $this->userid);

        if ($rule === 'completionplays') {
            $needed = (int) $game->completionplays;
            $done = $played ? (int) $played->plays : 0;
            return ($needed > 0 && $done >= $needed) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        $needed = (int) $game->completionscore;
        $best = $played ? (int) $played->bestscore : 0;

        return ($needed > 0 && $best >= $needed) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules this module defines.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionplays',
            'completionscore',
        ];
    }

    /**
     * How each rule reads on screen.
     *
     * @return array<string, string>
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $game = $DB->get_record('games', ['id' => $this->cm->instance],
            'id, completionplays, completionscore', MUST_EXIST);

        return [
            'completionplays' => get_string('completiondetail:plays', 'mod_games', $game->completionplays),
            'completionscore' => get_string('completiondetail:score', 'mod_games', $game->completionscore),
        ];
    }

    /**
     * The order the rules are listed in.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionplays',
            'completionscore',
        ];
    }
}
