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
 * The picture an admin uploads to stand for a game, in place of its emoji.
 *
 * Stored in the system context, file area "logo", one image per game. The item
 * id is the game's registry number: it is unique and never changes, while the
 * slug is a string the file API cannot key on. No upload means the emoji the
 * registry ships keeps being the card face.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logo {

    /** File area name. */
    const FILEAREA = 'logo';

    /** @var array<int, \stored_file>|null item id => file, for this request */
    protected static $files = null;

    /**
     * Options for the upload field: one image.
     *
     * @return array
     */
    public static function options(): array {
        return [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'maxbytes'       => 2 * 1024 * 1024,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * The file item id of a game: its registry number.
     *
     * @param string $gameid registry slug
     * @return int 0 when the game is unknown
     */
    public static function itemid(string $gameid): int {
        $default = registry::get_defaults()[$gameid] ?? null;
        return $default ? (int) $default['num'] : 0;
    }

    /**
     * Every uploaded logo, read once per request (the hub shows all of them).
     *
     * @return array<int, \stored_file>
     */
    protected static function files(): array {
        if (self::$files === null) {
            self::$files = [];
            $fs = get_file_storage();
            $area = $fs->get_area_files(\context_system::instance()->id, 'local_games', self::FILEAREA,
                false, 'itemid, filepath, filename', false);
            foreach ($area as $file) {
                if (!isset(self::$files[$file->get_itemid()]) && $file->is_valid_image()) {
                    self::$files[$file->get_itemid()] = $file;
                }
            }
        }
        return self::$files;
    }

    /**
     * Forget what files() read, after a save.
     */
    public static function reset(): void {
        self::$files = null;
    }

    /**
     * Where a game's logo is served from.
     *
     * The upload time rides along as ?rev= so a replaced logo with the same file
     * name is not served from the browser's cache.
     *
     * @param string $gameid registry slug
     * @return string|null absolute URL, or null when none is uploaded
     */
    public static function url(string $gameid): ?string {
        $itemid = self::itemid($gameid);
        $file = $itemid ? (self::files()[$itemid] ?? null) : null;
        if (!$file) {
            return null;
        }
        $url = \moodle_url::make_pluginfile_url($file->get_contextid(), 'local_games', self::FILEAREA,
            $itemid, $file->get_filepath(), $file->get_filename());
        $url->param('rev', $file->get_timemodified());
        return $url->out(false);
    }

    /**
     * A draft area holding the game's current logo, for the upload field.
     *
     * @param string $gameid registry slug
     * @return int draft item id
     */
    public static function prepare_draft(string $gameid): int {
        $draftid = 0;
        file_prepare_draft_area($draftid, \context_system::instance()->id, 'local_games', self::FILEAREA,
            self::itemid($gameid) ?: null, self::options());
        return $draftid;
    }

    /**
     * Keep what the upload field holds as the game's logo (empty removes it).
     *
     * @param string $gameid registry slug
     * @param int $draftid draft item id the form posted
     */
    public static function save_draft(string $gameid, int $draftid): void {
        $itemid = self::itemid($gameid);
        if (!$itemid) {
            return;
        }
        file_save_draft_area_files($draftid, \context_system::instance()->id, 'local_games', self::FILEAREA,
            $itemid, self::options());
        self::reset();
    }
}
