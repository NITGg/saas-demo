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

namespace mod_games;

use core_external\external_api;
use local_games\mlang;

/**
 * A Game activity's own content and which content a round is played from.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_games\content
 * @covers     \mod_games\external\get_content
 */
final class content_test extends \advanced_testcase {

    /**
     * A Game activity playing Quiz, and its record.
     *
     * @param int $mode content mode
     * @return array [course, cm, games record]
     */
    private function make_activity(int $mode): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('games',
            ['course' => $course->id, 'gameid' => 'quiz', 'contentmode' => $mode]);
        $game = $DB->get_record('games', ['id' => $cm->id]);

        return [$course, $cm, $game];
    }

    /**
     * One quiz row, optionally in a single language.
     *
     * @param string $question
     * @param string|null $lang null = every language
     * @return array
     */
    private function quiz_row(string $question, ?string $lang = null): array {
        $text = function (string $value) use ($lang): string {
            return $lang === null ? $value : mlang::build([$lang => $value]);
        };
        return [
            'question' => $text($question),
            'answer'   => $text('A'),
            'wrong1'   => $text('B'),
            'wrong2'   => '',
            'wrong3'   => '',
        ];
    }

    /**
     * The questions a resolved list holds.
     *
     * @param array[] $resolved
     * @return string[]
     */
    private function questions(array $resolved): array {
        return array_column($resolved, 'question');
    }

    public function test_default_mode_plays_corner_content(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_DEFAULT);
        content::save_row(0, $game, $this->quiz_row('Mine?'));

        $playing = $this->questions(content::resolved($game, 'en'));

        $this->assertNotContains('Mine?', $playing);
        $this->assertCount(count(\local_games\content::for_game('quiz', 'en')), $playing);
    }

    public function test_own_mode_falls_back_until_content_exists(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_OWN);
        $default = count(\local_games\content::for_game('quiz', 'en'));

        $this->assertCount($default, content::resolved($game, 'en'));
        $this->assertFalse(content::uses_own($game, 'en'));

        content::save_row(0, $game, $this->quiz_row('Mine 1'));
        content::save_row(0, $game, $this->quiz_row('Mine 2'));

        $this->assertSame(['Mine 1', 'Mine 2'], $this->questions(content::resolved($game, 'en')));
        $this->assertTrue(content::uses_own($game, 'en'));
    }

    public function test_own_mode_falls_back_per_language(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_OWN);
        content::save_row(0, $game, $this->quiz_row('عربي فقط', 'ar'));

        $this->assertSame(['عربي فقط'], $this->questions(content::resolved($game, 'ar')));
        // Nothing written in English: English students get the default instead of an empty game.
        $this->assertNotContains('عربي فقط', $this->questions(content::resolved($game, 'en')));
        $this->assertNotEmpty(content::resolved($game, 'en'));
    }

    public function test_both_mode_puts_own_first_then_default(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_BOTH);
        content::save_row(0, $game, $this->quiz_row('Mine'));

        $playing = $this->questions(content::resolved($game, 'en'));

        $this->assertSame('Mine', $playing[0]);
        $this->assertCount(1 + count(\local_games\content::for_game('quiz', 'en')), $playing);
    }

    public function test_save_keeps_only_shape_fields_and_rows_stay_in_their_activity(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_OWN);
        [, , $other] = $this->make_activity(content::MODE_OWN);

        $id = content::save_row(0, $game, $this->quiz_row('Q') + ['bogus' => 'x']);

        $row = content::get_row($id, (int) $game->id);
        $this->assertArrayNotHasKey('bogus', $row->data);
        $this->assertNull(content::get_row($id, (int) $other->id));

        // Deleting through another activity must not touch it.
        content::delete_row($id, (int) $other->id);
        $this->assertSame(1, content::count_rows((int) $game->id));
    }

    public function test_copy_defaults_and_move(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, , $game] = $this->make_activity(content::MODE_OWN);

        $copied = content::copy_defaults($game);

        $this->assertSame(\local_games\content::count_rows('quiz'), $copied);
        $this->assertSame($copied, content::count_rows((int) $game->id));

        $rows = content::rows((int) $game->id);
        content::move_row((int) $rows[1]->id, (int) $game->id, -1);
        $this->assertSame((int) $rows[1]->id, (int) content::rows((int) $game->id)[0]->id);
    }

    public function test_changing_game_keeps_rows_of_the_same_shape_only(): void {
        $this->resetAfterTest();
        [, , $game] = $this->make_activity(content::MODE_OWN);
        content::save_row(0, $game, $this->quiz_row('Q'));

        // quiz -> xo-quiz: both ask questions.
        $this->assertFalse(content::game_changed((int) $game->id, 'quiz', 'xo-quiz'));
        $this->assertSame(1, content::count_rows((int) $game->id));

        // quiz -> color-challenge: a question means nothing to the colour game.
        $this->assertTrue(content::game_changed((int) $game->id, 'quiz', 'color-challenge'));
        $this->assertSame(0, content::count_rows((int) $game->id));
    }

    public function test_deleting_the_activity_deletes_its_content(): void {
        $this->resetAfterTest();
        [, $cm, $game] = $this->make_activity(content::MODE_OWN);
        content::save_row(0, $game, $this->quiz_row('Q'));

        course_delete_module($cm->cmid);

        $this->assertSame(0, content::count_rows((int) $game->id));
    }

    public function test_duplicate_copies_content_and_mode(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cm, $game] = $this->make_activity(content::MODE_BOTH);
        content::save_row(0, $game, $this->quiz_row('Q1'));
        content::save_row(0, $game, $this->quiz_row('Q2'));

        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($cm->cmid));
        $copy = $DB->get_record('games', ['id' => $newcm->instance]);

        $this->assertSame(content::MODE_BOTH, (int) $copy->contentmode);
        $this->assertSame(['Q1', 'Q2'], array_map(static function ($row) {
            return $row->data['question'];
        }, content::rows((int) $copy->id)));
    }

    public function test_external_get_content_returns_activity_content(): void {
        $this->resetAfterTest();
        [$course, $cm, $game] = $this->make_activity(content::MODE_OWN);
        content::save_row(0, $game, $this->quiz_row('Only this'));

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = external_api::clean_returnvalue(external\get_content::execute_returns(),
            external\get_content::execute($cm->cmid));

        $this->assertSame('questions', $result['shape']);
        $this->assertSame(['Only this'], array_column($result['quiz'], 'question'));
        $this->assertNotEmpty($result['settings']);
    }

    public function test_external_get_content_requires_access(): void {
        $this->resetAfterTest();
        [, $cm] = $this->make_activity(content::MODE_OWN);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        external\get_content::execute($cm->cmid);
    }
}
