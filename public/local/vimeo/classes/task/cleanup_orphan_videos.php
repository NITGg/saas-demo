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

namespace local_vimeo\task;

use local_vimeo\api_client;

defined('MOODLE_INTERNAL') || die();

/**
 * Delete videos uploaded to Vimeo but never attached to an activity (cmid = 0),
 * older than the TTL — from Vimeo and the mapping table — so they stop consuming
 * provider quota and counting against the per-academy video limit. A provider error
 * leaves the row for the next run.
 *
 * @package    local_vimeo
 */
class cleanup_orphan_videos extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_cleanup_orphans', 'local_vimeo');
    }

    public function execute(): void {
        global $DB;

        $hours = (int) get_config('local_vimeo', 'orphan_ttl_hours');
        if ($hours <= 0) {
            $hours = 24;
        }
        $cutoff = time() - $hours * 3600;

        $orphans = $DB->get_records_select(
            'local_vimeo_videos',
            'cmid = 0 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if (!$orphans) {
            return;
        }

        $client = new api_client();
        foreach ($orphans as $row) {
            try {
                if (!empty($row->videoid)) {
                    $client->delete_video($row->videoid);
                }
                $DB->delete_records('local_vimeo_videos', ['id' => $row->id]);
                mtrace("local_vimeo: cleaned orphan video {$row->videoid}");
            } catch (\Throwable $e) {
                mtrace("local_vimeo: orphan cleanup failed for {$row->videoid}: " . $e->getMessage());
            }
        }
    }
}
