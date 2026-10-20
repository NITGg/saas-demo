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

namespace local_nit_lessons\service;

use local_nit_core\api\config as core_config;
use local_nit_core\base\service;
use local_nit_lessons\exception\lesson_exception;

/**
 * Live-lesson settings (deadlines in minutes, and the course that hosts the meeting rooms), stored as
 * local_nit_lessons config (admin page "Live lesson settings") read through the SDK
 * config manager. Earning percentages live in local_nit_finance.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_service extends service {
    /** @var string This plugin's component. */
    const COMPONENT = 'local_nit_lessons';

    /** @var array<string,int> Setting key => default (minutes; a course id for lessons_courseid). */
    const DEFAULTS = [
        'min_booking_minutes'      => 60,
        'cancel_deadline_minutes'  => 720,     // 12 hours: free cancel / reschedule (academy spec 2.2).
        'update_deadline_minutes'  => 720,
        'start_allowed_minutes'    => 30,
        'complete_allowed_minutes' => 45,
        'absence_report_minutes'   => 15,
        'auto_complete_minutes'    => 4320,    // 72h (like Preply): a joined lesson completes itself this long after it ends; 0 = off.
        'instant_enabled'          => 1,       // "Get a tutor now" on (1) / off (0).
        'instant_wait_seconds'     => 120,      // How long a student waits for a teacher to accept.
        'instant_minutes'          => 30,      // Length of an instant lesson.
        'instant_wave_size'        => 3,       // Teachers asked at once.
        'instant_ring_seconds'     => 30,      // How long one wave rings before the next teachers are asked.
        'instant_noshow_minutes'   => 3,       // A teacher who accepted but did not open the room is replaced.
        'instant_extra_flex'       => 0,       // Extra Flex an instant lesson costs on top of the teacher's price.
        'instant_peak_extra_flex'  => 0,       // Extra Flex during peak hours (setting instant_peak_hours).
        'instant_quick_minutes'    => 15,      // "Quick question" length; 0 turns quick questions off.
        'instant_quick_flex'       => 1,       // Flat price of a quick question.
        'instant_trial_enabled'    => 0,       // A student's first lesson is a trial (100% to the academy).
        'instant_trial_flex'       => 1,       // Price of the trial lesson.
        'instant_ai_summary'       => 1,       // AI one-line summary of the student's note for the teacher.
        'instant_parent_alert'     => 1,       // WhatsApp the guardians when an instant lesson starts.
        'lessons_courseid'         => 0,
    ];

    /**
     * A single setting value (default applied).
     *
     * @param string $key
     * @return int
     */
    public function get(string $key): int {
        $default = self::DEFAULTS[$key] ?? 0;
        return (int) core_config::for_plugin(self::COMPONENT)->get_int($key, $default);
    }

    /**
     * All settings with defaults applied.
     *
     * @return array<string,int>
     */
    public function get_all(): array {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    /**
     * Update the provided deadline settings (US-AD-2-1). Only known keys are written; values must be
     * zero or greater.
     *
     * @param array<string,int> $data
     * @return void
     */
    public function update(array $data): void {
        $config = core_config::for_plugin(self::COMPONENT);
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            if ((int) $value < 0) {
                throw new lesson_exception('err_settingnegative');
            }
            $config->set($key, (int) $value);
        }
    }
}
