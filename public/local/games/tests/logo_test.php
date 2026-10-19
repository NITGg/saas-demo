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
 * Tests for the logo an admin uploads per game.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_games\logo
 * @covers     \local_games\external\get_games
 */
final class logo_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        logo::reset();
    }

    /**
     * A draft area holding one file, as the upload field would post it.
     *
     * @param string $filename
     * @param string $content
     * @return int draft item id
     */
    private function draft(string $filename, string $content): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid,
            'filepath' => '/', 'filename' => $filename,
        ], $content);
        return $draftid;
    }

    /** @return string a real 1x1 PNG */
    private function png(): string {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    public function test_no_upload_keeps_the_emoji(): void {
        $this->assertNull(logo::url('math-race'));
    }

    public function test_an_uploaded_logo_is_served_for_that_game_only(): void {
        logo::save_draft('math-race', $this->draft('race.png', $this->png()));

        $url = logo::url('math-race');
        $num = registry::get_defaults()['math-race']['num'];
        $this->assertStringContainsString('/pluginfile.php/' . \context_system::instance()->id
            . '/local_games/logo/' . $num . '/race.png', $url);
        $this->assertStringContainsString('rev=', $url);
        $this->assertNull(logo::url('math-shop'));

        // The upload field reopens with the logo in it...
        $draftid = logo::prepare_draft('math-race');
        $files = file_get_drafarea_files($draftid)->list;
        $this->assertSame(['race.png'], array_column($files, 'filename'));

        // ...and saving it emptied takes the emoji back.
        logo::save_draft('math-race', file_get_unused_draft_itemid());
        $this->assertNull(logo::url('math-race'));
    }

    public function test_a_file_that_is_not_an_image_is_not_shown(): void {
        logo::save_draft('math-race', $this->draft('race.png', 'not a picture'));
        $this->assertNull(logo::url('math-race'));
    }

    public function test_an_unknown_game_has_no_logo(): void {
        $this->assertSame(0, logo::itemid('no-such-game'));
        logo::save_draft('no-such-game', $this->draft('x.png', $this->png()));
        $this->assertNull(logo::url('no-such-game'));
    }

    public function test_the_app_receives_the_logo(): void {
        logo::save_draft('math-race', $this->draft('race.png', $this->png()));
        $this->setUser($this->getDataGenerator()->create_user());

        $result = \core_external\external_api::clean_returnvalue(
            external\get_games::execute_returns(), external\get_games::execute());
        $byid = [];
        foreach ($result['categories'] as $category) {
            foreach ($category['games'] as $game) {
                $byid[$game['id']] = $game['logourl'] ?? '';
            }
        }
        $this->assertStringContainsString('/race.png', $byid['math-race']);
        $this->assertSame('', $byid['math-shop']);
    }
}
