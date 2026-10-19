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

namespace mod_games;

use local_games\progress;

/**
 * What this activity remembers about the rounds played inside it.
 *
 * The corner already records every round: points, badges and personal bests,
 * for life, across the whole site. None of that answers the question a teacher
 * has, which is "did the children in my class play the game I set, in this
 * course". So the activity keeps its own tally, scoped to the course module, and
 * leaves the corner's numbers alone.
 *
 * Both are written from the same round - {@see \local_games\progress::submit()}
 * by the corner's own web service, and this class by ours - which is why the
 * numbers here are a subset rather than a second opinion.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class play_manager {

    /**
     * A round is a round however well it went; anything past this is a broken
     * client. Matches the corner's own ceiling.
     */
    const MAX_PER_ROUND = progress::MAX_PER_ROUND;

    /**
     * Record one finished round against this activity.
     *
     * @param int $gamesid games.id
     * @param int $userid
     * @param int $correct correct answers this round
     * @param int $streak longest run of correct answers this round
     * @param int $score the round score the game itself reports
     * @return \stdClass the user's row after the round
     */
    public static function record(int $gamesid, int $userid, int $correct, int $streak, int $score): \stdClass {
        global $DB;

        $correct = min(max($correct, 0), self::MAX_PER_ROUND);
        $streak  = min(max($streak, 0), $correct);
        $score   = min(max($score, 0), self::MAX_PER_ROUND);

        $now = time();
        $record = $DB->get_record('games_play', ['gamesid' => $gamesid, 'userid' => $userid]);

        if ($record) {
            $record->plays += 1;
            $record->points += $correct;
            $record->bestscore = max((int) $record->bestscore, $score);
            $record->beststreak = max((int) $record->beststreak, $streak);
            $record->timemodified = $now;
            $DB->update_record('games_play', $record);

            return $record;
        }

        $record = (object) [
            'gamesid'      => $gamesid,
            'userid'       => $userid,
            'plays'        => 1,
            'points'       => $correct,
            'bestscore'    => $score,
            'beststreak'   => $streak,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('games_play', $record);

        return $record;
    }

    /**
     * One user's standing in one activity, or null if they have not played.
     *
     * @param int $gamesid games.id
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $gamesid, int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('games_play', ['gamesid' => $gamesid, 'userid' => $userid]) ?: null;
    }

    /**
     * Everyone who has played this activity, keyed by user id.
     *
     * @param int $gamesid games.id
     * @return array<int, \stdClass>
     */
    public static function get_all(int $gamesid): array {
        global $DB;
        return $DB->get_records('games_play', ['gamesid' => $gamesid], '', 'userid, plays, points, bestscore, beststreak, timemodified');
    }
}
