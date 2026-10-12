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

namespace local_academy;

use local_academy\local\file_lessons;

/**
 * Tests for the files a teacher may upload as a file lesson (BUG-039).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\file_lessons
 */
final class file_lessons_test extends \advanced_testcase {

    /** A 1×1 PNG. */
    const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** The start of a real PDF. */
    const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /**
     * Put files in the current user's draft area, as the file picker does.
     *
     * @param array $files filename => content
     * @return int draft item id
     */
    private function draft(array $files): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        $context = \context_user::instance($USER->id);
        foreach ($files as $name => $content) {
            get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'user',
                'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/', 'filename' => $name], $content);
        }
        return $draftid;
    }

    public function test_documents_and_images_are_accepted(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $draftid = $this->draft(['syllabus.pdf' => self::PDF, 'chart.PNG' => base64_decode(self::PNG)]);
        $this->assertSame([], file_lessons::validate('resource', ['files' => $draftid], (int) $teacher->id));
    }

    public function test_scripts_programs_and_fake_files_are_refused(): void {
        // The QA files: an HTML page with a script, an .exe, a text file renamed .pdf.
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        foreach ([
            'page.html' => ['<html><script>alert(1)</script></html>', 'err_filelessontype'],
            'setup.exe' => ["MZ\x90\x00" . str_repeat("\x00", 60), 'err_filelessontype'],
            'fake.pdf' => ['just some text, not a PDF', 'err_filelessonfake'],
        ] as $name => [$content, $error]) {
            $errors = file_lessons::validate('resource', ['files' => $this->draft([$name => $content])], (int) $teacher->id);
            $this->assertArrayHasKey('files', $errors, $name);
            $this->assertStringContainsString($name, $errors['files']);
            $this->assertSame($error === 'err_filelessonfake'
                ? get_string($error, 'local_academy', ['file' => $name, 'type' => 'pdf'])
                : get_string($error, 'local_academy', ['file' => $name,
                    'types' => implode(', ', array_keys(file_lessons::TYPES))]), $errors['files']);
        }
        // Folders too.
        $errors = file_lessons::validate('folder', ['files' => $this->draft(['x.html' => '<b>x</b>'])], (int) $teacher->id);
        $this->assertArrayHasKey('files', $errors);
    }

    public function test_size_limit_comes_from_the_admin_setting(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $this->assertSame(50 * 1024 * 1024, file_lessons::max_bytes()); // Default 50 MB.

        set_config('filelessonmaxmb', 1, 'local_academy');
        $big = self::PDF . str_repeat('0', 1024 * 1024);
        $errors = file_lessons::validate('resource', ['files' => $this->draft(['big.pdf' => $big])], (int) $teacher->id);
        $this->assertSame(get_string('err_filelessonsize', 'local_academy', ['file' => 'big.pdf', 'max' => display_size(1048576)]),
            $errors['files']);
    }

    public function test_site_admin_and_other_activities_are_not_limited(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $draftid = $this->draft(['page.html' => '<script>alert(1)</script>']);
        $this->assertSame([], file_lessons::validate('resource', ['files' => $draftid], (int) get_admin()->id));

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $draftid = $this->draft(['page.html' => '<script>alert(1)</script>']);
        $this->assertSame([], file_lessons::validate('assign', ['files' => $draftid], (int) $teacher->id));
    }
}
