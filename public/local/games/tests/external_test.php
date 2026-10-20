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

use core_external\external_api;
use local_games\external\get_content;
use local_games\external\get_game;
use local_games\external\get_games;

/**
 * What the mobile app reads to run a game natively: shape, settings, speech locale.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_games\external\get_games
 * @covers     \local_games\external\get_game
 * @covers     \local_games\external\get_content
 * @covers     \local_games\registry::settings_for
 */
final class external_test extends \advanced_testcase {

    public function test_every_game_has_settings(): void {
        foreach (array_keys(registry::get_defaults()) as $id) {
            $this->assertNotEmpty(registry::settings_for($id), "No settings for {$id}");
        }
    }

    public function test_get_games_and_get_game_return_shape(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $hub = external_api::clean_returnvalue(get_games::execute_returns(), get_games::execute());
        foreach ($hub['categories'] as $category) {
            foreach ($category['games'] as $card) {
                $this->assertSame(registry::shape_for($card['id']), $card['shape']);
            }
        }

        $game = external_api::clean_returnvalue(get_game::execute_returns(), get_game::execute('word-builder'));
        $this->assertSame('vocabulary', $game['shape']);
    }

    public function test_get_content_returns_settings_and_rulesversion(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $content = external_api::clean_returnvalue(get_content::execute_returns(), get_content::execute('runner'));

        $settings = array_column($content['settings'], 'value', 'key');
        $this->assertEquals(3, $settings['hearts']);
        $this->assertEquals(4.2, $settings['travelslow']);
        $this->assertSame(registry::RULES_VERSION, $content['rulesversion']);
        $this->assertSame('questions', $content['shape']);
        $this->assertNotEmpty($content['quiz']);
    }

    public function test_speechlang_follows_language(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $content = external_api::clean_returnvalue(get_content::execute_returns(), get_content::execute('speak-words'));
        $this->assertSame('en', $content['speechlang']);

        force_current_language('ar');
        $content = external_api::clean_returnvalue(get_content::execute_returns(), get_content::execute('speak-words'));
        $this->assertSame('ar-EG', $content['speechlang']);
    }

    public function test_web_payload_carries_same_settings(): void {
        $this->resetAfterTest();
        $payload = content::payload('math-race');
        $this->assertEquals(registry::settings_for('math-race'), (array) $payload['settings']);
    }
}
