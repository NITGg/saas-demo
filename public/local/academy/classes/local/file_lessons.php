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

namespace local_academy\local;

/**
 * Which files a teacher may upload as a file lesson (BUG-039).
 *
 * Moodle's File and Folder activities accept any file of any size, so an HTML
 * page with a script, an .exe or a text file renamed .pdf could be given to
 * students. Here a file lesson accepts documents and images only — checked by the
 * file's content, not just its name — up to a size an admin sets (Site
 * administration → Plugins → Local plugins → Academy, default 50 MB). Site admins
 * are not limited, as elsewhere in Moodle.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_lessons {

    /** The activities whose uploaded files are checked: their file field. */
    const MODULES = ['resource' => 'files', 'folder' => 'files'];

    /** Default largest file, in MB (setting local_academy/filelessonmaxmb). */
    const DEFAULT_MAX_MB = 50;

    /**
     * Allowed extension => the content types its file may really have.
     * Old Office files are "OLE" containers, new ones are zip packages.
     */
    const TYPES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /**
     * The largest file a teacher may upload, in bytes.
     *
     * @return int
     */
    public static function max_bytes(): int {
        $mb = (int) get_config('local_academy', 'filelessonmaxmb');
        return ($mb > 0 ? $mb : self::DEFAULT_MAX_MB) * 1024 * 1024;
    }

    /**
     * Errors for the files in a file lesson's settings form (empty = fine).
     *
     * @param string $modname the activity type, e.g. "resource"
     * @param array $data the submitted form data
     * @param int $userid who saves the form
     * @return array<string, string> form field => error
     */
    public static function validate(string $modname, array $data, int $userid): array {
        global $USER;
        $field = self::MODULES[$modname] ?? null;
        if ($field === null || empty($data[$field]) || is_siteadmin($userid)) {
            return [];
        }
        $draftcontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($draftcontext->id, 'user', 'draft', (int) $data[$field],
            'filepath, filename', false);
        $problems = [];
        foreach ($files as $file) {
            $problem = self::problem($file->get_filename(), (int) $file->get_filesize(), self::sniff($file));
            if ($problem !== '') {
                $problems[] = $problem;
            }
        }
        return $problems ? [$field => implode(' ', $problems)] : [];
    }

    /**
     * What is wrong with one file ('' when it may be uploaded).
     *
     * @param string $filename
     * @param int $size bytes
     * @param string $contenttype the type found in the file's content
     * @return string
     */
    public static function problem(string $filename, int $size, string $contenttype): string {
        $extension = \core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$extension])) {
            return get_string('err_filelessontype', 'local_academy',
                ['file' => $filename, 'types' => implode(', ', array_keys(self::TYPES))]);
        }
        if (!in_array($contenttype, self::TYPES[$extension], true)) {
            return get_string('err_filelessonfake', 'local_academy', ['file' => $filename, 'type' => $extension]);
        }
        if ($size > self::max_bytes()) {
            return get_string('err_filelessonsize', 'local_academy',
                ['file' => $filename, 'max' => display_size(self::max_bytes())]);
        }
        return '';
    }

    /**
     * The content type of a stored file, read from its first bytes (not its name).
     *
     * @param \stored_file $file
     * @return string
     */
    private static function sniff(\stored_file $file): string {
        $handle = $file->get_content_file_handle();
        $head = $handle ? (string) fread($handle, 8192) : '';
        if ($handle) {
            fclose($handle);
        }
        return $head === '' ? '' : (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
    }
}
