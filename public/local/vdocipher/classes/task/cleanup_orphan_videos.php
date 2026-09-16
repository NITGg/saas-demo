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

namespace local_vdocipher\task;

use local_vdocipher\api_client;

defined('MOODLE_INTERNAL') || die();

/**
 * Delete videos uploaded to VdoCipher but never attached to an activity.
 *
 * A video uploaded via the AJAX endpoint gets a mapping row with cmid = 0. If the
 * teacher never saves the activity, that row (and the remote video) would otherwise
 * leak forever — consuming provider quota AND counting against the per-academy video
 * limit. This task removes such rows (older than the TTL) from the provider and the
 * mapping table. A row is only ever removed once its remote video is gone, so a
 * provider error just leaves it for the next run.
 *
 * @package    local_vdocipher
 */
class cleanup_orphan_videos extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_cleanup_orphans', 'local_vdocipher');
    }

    public function execute(): void {
        global $DB;

        $hours = (int) get_config('local_vdocipher', 'orphan_ttl_hours');
        if ($hours <= 0) {
            $hours = 24;
        }
        $cutoff = time() - $hours * 3600;

        $orphans = $DB->get_records_select(
            'local_vdocipher_videos',
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
                    $client->delete_videos([$row->videoid]);
                }
                $DB->delete_records('local_vdocipher_videos', ['id' => $row->id]);
                mtrace("local_vdocipher: cleaned orphan video {$row->videoid}");
            } catch (\Throwable $e) {
                // Keep the row so we retry next run rather than orphan it locally.
                mtrace("local_vdocipher: orphan cleanup failed for {$row->videoid}: " . $e->getMessage());
            }
        }
    }
}
