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

namespace local_games;

/**
 * Points and badges: everything the corner remembers about a learner.
 *
 * The design doc gives one scoring rule for every game - "a point for each
 * correct answer" - so points are derived here from the round stats rather than
 * taken from the browser. Badges are declared per game in {@see registry} and
 * awarded once, for life.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {

    /**
     * A sane ceiling for one round. Anything above this is a broken client (or
     * someone poking the endpoint), so we clamp rather than reject: a child
     * mid-round should never see an error.
     */
    const MAX_PER_ROUND = 500;

    /**
     * Lifetime points and badge count for one user.
     *
     * @param int $userid
     * @return array{points: int, badges: int, plays: int}
     */
    public static function get_totals(int $userid): array {
        global $DB;

        $points = (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(points), 0) FROM {local_games_progress} WHERE userid = :userid',
            ['userid' => $userid]
        );
        $plays = (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(plays), 0) FROM {local_games_progress} WHERE userid = :userid',
            ['userid' => $userid]
        );
        $badges = $DB->count_records('local_games_badge', ['userid' => $userid]);

        return ['points' => $points, 'badges' => $badges, 'plays' => $plays];
    }

    /**
     * Per-game progress rows for one user, keyed by game slug.
     *
     * @param int $userid
     * @return array<string, \stdClass>
     */
    public static function get_user_progress(int $userid): array {
        global $DB;
        return $DB->get_records('local_games_progress', ['userid' => $userid], '', 'gameid, points, plays, bestscore, beststreak');
    }

    /**
     * Badge keys this user has already earned.
     *
     * @param int $userid
     * @return string[]
     */
    public static function get_user_badges(int $userid): array {
        global $DB;
        return $DB->get_fieldset_select('local_games_badge', 'badge', 'userid = :userid', ['userid' => $userid]);
    }

    /**
     * Record one finished round and return the updated totals.
     *
     * @param int    $userid
     * @param string $gameid game slug, already known to be live
     * @param int    $correct correct answers this round
     * @param int    $wrong wrong answers this round
     * @param int    $streak longest run of correct answers this round
     * @param int    $score the round score the game itself reports
     * @param int    $goal how many times the game's own goal was met this round
     * @return array{points: int, badges: int, gamepoints: int, bestscore: int, newbadges: string[]}
     */
    public static function submit(int $userid, string $gameid, int $correct, int $wrong, int $streak,
            int $score, int $goal = 0): array {
        global $DB;

        $correct = min(max($correct, 0), self::MAX_PER_ROUND);
        $wrong   = min(max($wrong, 0), self::MAX_PER_ROUND);
        $streak  = min(max($streak, 0), $correct);
        $score   = min(max($score, 0), self::MAX_PER_ROUND);
        $goal    = min(max($goal, 0), self::MAX_PER_ROUND);

        $now = time();
        $record = $DB->get_record('local_games_progress', ['userid' => $userid, 'gameid' => $gameid]);

        if ($record) {
            $record->points     = $record->points + $correct;
            $record->plays      = $record->plays + 1;
            $record->bestscore  = max($record->bestscore, $score);
            $record->beststreak = max($record->beststreak, $streak);
            $record->timemodified = $now;
            $DB->update_record('local_games_progress', $record);
        } else {
            $record = (object) [
                'userid'       => $userid,
                'gameid'       => $gameid,
                'points'       => $correct,
                'plays'        => 1,
                'bestscore'    => $score,
                'beststreak'   => $streak,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_games_progress', $record);
        }

        $newbadges = self::award_badges($userid, $gameid, $correct, $wrong, $streak, $goal);

        $totals = self::get_totals($userid);

        return [
            'points'     => $totals['points'],
            'badges'     => $totals['badges'],
            'gamepoints' => (int) $record->points,
            'bestscore'  => (int) $record->bestscore,
            'newbadges'  => $newbadges,
        ];
    }

    /**
     * Award any badge of this game whose rule the round satisfied.
     *
     * Badges are once-only: the unique (userid, badge) index is the guard, and a
     * duplicate insert is swallowed so a concurrent second tab cannot turn a
     * won badge into an error page.
     *
     * @param int    $userid
     * @param string $gameid
     * @param int    $correct
     * @param int    $wrong
     * @param int    $streak
     * @param int    $goal
     * @return string[] badge keys awarded by this round
     */
    private static function award_badges(int $userid, string $gameid, int $correct, int $wrong, int $streak,
            int $goal): array {
        global $DB;

        $game = registry::get_game($gameid);
        if (empty($game['badges'])) {
            return [];
        }

        $already = self::get_user_badges($userid);
        $awarded = [];

        foreach ($game['badges'] as $badge => $rule) {
            if (in_array($badge, $already, true)) {
                continue;
            }
            if (!self::rule_met($rule, $correct, $wrong, $streak, $goal)) {
                continue;
            }
            try {
                $DB->insert_record('local_games_badge', (object) [
                    'userid'      => $userid,
                    'gameid'      => $gameid,
                    'badge'       => $badge,
                    'timeawarded' => time(),
                ]);
                $awarded[] = $badge;
            } catch (\dml_exception $e) {
                // Lost a race with another tab; the badge is already theirs.
                continue;
            }
        }

        return $awarded;
    }

    /**
     * Whether a round satisfies every threshold in a badge rule.
     *
     * @param array $rule as declared in the registry
     * @param int   $correct
     * @param int   $wrong
     * @param int   $streak
     * @param int   $goal
     * @return bool
     */
    private static function rule_met(array $rule, int $correct, int $wrong, int $streak, int $goal): bool {
        if (isset($rule['streak']) && $streak < $rule['streak']) {
            return false;
        }
        if (isset($rule['correct']) && $correct < $rule['correct']) {
            return false;
        }
        if (isset($rule['maxwrong']) && $wrong > $rule['maxwrong']) {
            return false;
        }
        // Whatever the game itself calls winning: matches won in XO, planets
        // reached in Space Trip, a board cleared inside a flip budget. Counting
        // right answers cannot express any of those, and inventing a separate
        // rule per game would put game logic in the server.
        if (isset($rule['goal']) && $goal < $rule['goal']) {
            return false;
        }
        return true;
    }
}
