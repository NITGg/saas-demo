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

use local_games\admin\manager;

/**
 * Game control: deleting several content rows at once.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_games\admin\manager::delete_rows
 */
final class manager_test extends \advanced_testcase {

    public function test_delete_rows_deletes_only_the_given_games_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $quiz = [
            manager::save_row(0, 'quiz', ['question' => 'Q1', 'answer' => 'A', 'wrong1' => 'B']),
            manager::save_row(0, 'quiz', ['question' => 'Q2', 'answer' => 'A', 'wrong1' => 'B']),
            manager::save_row(0, 'quiz', ['question' => 'Q3', 'answer' => 'A', 'wrong1' => 'B']),
        ];
        $other = manager::save_row(0, 'xo-quiz', ['question' => 'X', 'answer' => 'A', 'wrong1' => 'B']);
        $before = content::count_rows('quiz');

        // Two of this game's rows, plus one belonging to another game.
        $deleted = manager::delete_rows([$quiz[0], $quiz[2], $other], 'quiz');

        $this->assertSame(2, $deleted);
        $this->assertSame($before - 2, content::count_rows('quiz'));
        $this->assertNotNull(manager::get_row($quiz[1]));
        $this->assertNotNull(manager::get_row($other));
    }

    public function test_delete_rows_with_nothing_selected(): void {
        $this->resetAfterTest();
        $this->assertSame(0, manager::delete_rows([], 'quiz'));
    }
}
