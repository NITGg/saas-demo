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

    /**
     * The File activity form of a new activity, built as course/modedit.php builds it.
     *
     * @param \stdClass $course
     * @return array [form, the form's hidden data]
     */
    private function resource_form(\stdClass $course): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/resource/mod_form.php');
        [, $context, $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'resource', 0);
        $PAGE->set_context($context);
        $PAGE->set_url('/course/modedit.php', ['add' => 'resource', 'course' => $course->id]);
        return [new \mod_resource_mod_form($data, $cw->section, $cm, $course), (array) $data];
    }

    public function test_the_real_file_activity_form_refuses_the_file(): void {
        // What a teacher does on course/modedit.php: Moodle's form must call our check
        // (production saved an .exe because Moodle never called it).
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $submit = function(array $files) use ($course): \mod_resource_mod_form {
            [, $hidden] = $this->resource_form($course);
            \mod_resource_mod_form::mock_submit($hidden + ['name' => 'Lesson', 'files' => $this->draft($files),
                'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0], 'submitbutton2' => 'Save']);
            [$form] = $this->resource_form($course);
            return $form;
        };

        $form = $submit(['setup.exe' => "MZ\x90\x00" . str_repeat("\x00", 60)]);
        $this->assertNull($form->get_data());
        $this->assertStringContainsString(s(get_string('err_filelessontype', 'local_academy',
            ['file' => 'setup.exe', 'types' => implode(', ', array_keys(file_lessons::TYPES))])), $form->render());

        $this->assertNotNull($submit(['syllabus.pdf' => self::PDF])->get_data());
    }

    public function test_the_file_picker_shows_the_lesson_limit(): void {
        $this->resetAfterTest();
        set_config('filelessonmaxmb', 1, 'local_academy');
        $course = $this->getDataGenerator()->create_course();
        $limit = get_string('maxfilesize', 'moodle', display_size(1024 * 1024, 0));

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        [$form] = $this->resource_form($course);
        $this->assertStringContainsString($limit, $form->render());

        $this->setAdminUser();
        [$form] = $this->resource_form($course);
        $this->assertStringNotContainsString($limit, $form->render());
    }

    public function test_drag_and_drop_on_the_course_page_refuses_the_file(): void {
        // course/dndupload.php makes a File activity without the form.
        global $SCRIPT;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);
        $upload = function(string $name, string $content, string $module = 'resource'): string {
            global $SCRIPT;
            $SCRIPT = '/course/dndupload.php';
            $tmp = make_request_directory() . '/upload';
            file_put_contents($tmp, $content);
            $_POST = ['type' => 'Files', 'module' => $module];
            $_FILES = ['repo_upload_file' => ['name' => $name, 'type' => 'application/octet-stream',
                'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)]];
            return file_lessons::dnd_upload_problem();
        };

        $this->assertSame(get_string('err_filelessontype', 'local_academy',
            ['file' => 'setup.exe', 'types' => implode(', ', array_keys(file_lessons::TYPES))]),
            $upload('setup.exe', "MZ\x90\x00" . str_repeat("\x00", 60)));
        $this->assertSame(get_string('err_filelessonfake', 'local_academy', ['file' => 'fake.pdf', 'type' => 'pdf']),
            $upload('fake.pdf', 'just some text, not a PDF'));
        $this->assertSame('', $upload('syllabus.pdf', self::PDF));
        $this->assertSame('', $upload('page.html', '<script>alert(1)</script>', 'page')); // Not a file lesson.

        $this->setAdminUser();
        $this->assertSame('', $upload('setup.exe', "MZ\x90\x00"));

        // Moodle runs it on every request.
        $callbacks = array_column(\core\di::get(\core\hook\manager::class)
            ->get_callbacks_for_hook(\core\hook\after_config::class), 'callback');
        $this->assertContains(file_lessons::class . '::after_config', $callbacks);
        $SCRIPT = null;
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
