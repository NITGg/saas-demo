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

namespace local_games;

/**
 * Tests for the Games Corner points and badges.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_games\progress
 */
final class progress_test extends \advanced_testcase {

    public function test_submit_accumulates_points_and_plays(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        progress::submit($user->id, 'math-race', 4, 1, 3, 40);
        $result = progress::submit($user->id, 'math-race', 6, 0, 2, 25);

        $this->assertSame(10, $result['points']);
        $this->assertSame(10, $result['gamepoints']);
        $this->assertSame(40, $result['bestscore']);
        $this->assertSame(2, progress::get_totals($user->id)['plays']);
    }

    public function test_submit_clamps_out_of_range_values(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $result = progress::submit($user->id, 'math-race', 100000, -5, 100000, -1);

        $this->assertSame(progress::MAX_PER_ROUND, $result['points']);
        $this->assertSame(0, $result['bestscore']);
    }

    public function test_badge_awarded_once_when_rule_met(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // math-race: 'fast-calculator' needs a streak of 10.
        $result = progress::submit($user->id, 'math-race', 9, 0, 9, 9);
        $this->assertSame([], $result['newbadges']);

        $result = progress::submit($user->id, 'math-race', 10, 0, 10, 10);
        $this->assertSame(['fast-calculator'], $result['newbadges']);
        $this->assertSame(1, $result['badges']);

        $result = progress::submit($user->id, 'math-race', 12, 0, 12, 12);
        $this->assertSame([], $result['newbadges']);
        $this->assertSame(1, $result['badges']);
    }

    public function test_maxwrong_rule_blocks_badge(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // math-catcher: 'sharp-hunter' needs 20 correct and no mistakes.
        $result = progress::submit($user->id, 'math-catcher', 25, 1, 25, 25);
        $this->assertSame([], $result['newbadges']);

        $result = progress::submit($user->id, 'math-catcher', 20, 0, 5, 20);
        $this->assertSame(['sharp-hunter'], $result['newbadges']);
    }

    public function test_progress_is_per_user(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();

        progress::submit($a->id, 'math-race', 10, 0, 10, 10);

        $this->assertSame(['points' => 0, 'badges' => 0, 'plays' => 0], progress::get_totals($b->id));
    }
}
