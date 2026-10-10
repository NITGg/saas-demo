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

namespace local_nit_category;

/**
 * The category image & icon (image.php, lib.php).
 *
 * Each test makes its own categories: the lib memoises files per category id.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_nit_category_get_image_url
 * @covers     ::local_nit_category_render_icon
 * @covers     ::local_nit_category_set_icon_emoji
 */
final class category_media_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/nit_category/lib.php');
    }

    /** Store a (tiny, valid) PNG in a category filearea. */
    private function store(int $categoryid, string $filearea, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_coursecat::instance($categoryid)->id, 'component' => 'local_nit_category',
            'filearea' => $filearea, 'itemid' => 0, 'filepath' => '/', 'filename' => $filename,
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    public function test_image_falls_back_from_upload_to_description_to_parent(): void {
        $gen = $this->getDataGenerator();
        $parent = $gen->create_category();
        $withupload = $gen->create_category(['parent' => $parent->id,
            'description' => '<p><img src="https://example.com/desc.png"></p>']);
        $withdesc = $gen->create_category(['parent' => $parent->id,
            'description' => '<p><img src="https://example.com/desc.png"></p>']);
        $bare = $gen->create_category(['parent' => $parent->id]);
        $this->store($parent->id, LOCAL_NIT_CATEGORY_IMAGE_FILEAREA, 'parent.png');
        $this->store($withupload->id, LOCAL_NIT_CATEGORY_IMAGE_FILEAREA, 'own.png');

        $this->assertStringEndsWith('/local_nit_category/categoryimage/own.png',
            local_nit_category_get_image_url($withupload->id));
        $this->assertSame('https://example.com/desc.png', local_nit_category_get_image_url($withdesc->id));
        $this->assertStringEndsWith('/categoryimage/parent.png', local_nit_category_get_image_url($bare->id));
        // Without inheriting (section titles), a bare category has no image.
        $this->assertSame('', local_nit_category_get_image_url($bare->id, false));
    }

    public function test_icon_file_wins_over_emoji_and_emoji_is_seen_at_once(): void {
        $gen = $this->getDataGenerator();
        $a = $gen->create_category();
        $b = $gen->create_category();

        $this->assertSame('', local_nit_category_render_icon($a->id));
        local_nit_category_set_icon_emoji($a->id, ' 📐 ');
        // Read in the same request right after saving (image.php shows the preview).
        $this->assertSame('📐', local_nit_category_get_icon_emoji($a->id));
        $this->assertSame('<span class="i" aria-hidden="true">📐</span>', local_nit_category_render_icon($a->id, 'i'));

        local_nit_category_set_icon_emoji($b->id, '🎨');
        $this->store($b->id, LOCAL_NIT_CATEGORY_ICON_FILEAREA, 'icon.png');
        $this->assertStringContainsString('/categoryicon/icon.png', local_nit_category_render_icon($b->id, 'i', 'B'));

        // Clearing one keeps the other.
        local_nit_category_set_icon_emoji($a->id, '');
        $this->assertSame('', local_nit_category_get_icon_emoji($a->id));
        $this->assertSame('🎨', local_nit_category_get_icon_emoji($b->id));
    }
}
