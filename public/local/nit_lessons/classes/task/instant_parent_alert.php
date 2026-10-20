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

namespace local_nit_lessons\task;

use local_nit_lessons\service\instant_service;

/**
 * WhatsApp the student's guardians that an instant lesson has started. Parents have no accounts
 * (local_parent): the phones come from the student's guardian profile fields, and the message is
 * an approved WhatsApp template (setting instant_parent_template) sent from the academy number
 * (local_nit_assistant). Template parameters: {{1}} student first name, {{2}} subject,
 * {{3}} teacher name, {{4}} start time.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instant_parent_alert extends \core\task\adhoc_task {

    public function execute() {
        global $DB;
        $lesson = $DB->get_record('nit_lesson', ['id' => (int) ($this->get_custom_data()->lessonid ?? 0)]);
        $template = trim((string) get_config('local_nit_lessons', 'instant_parent_template'));
        if (!$lesson || $template === '' || !class_exists('\local_nit_assistant\whatsapp\cloud')) {
            return;
        }
        $phones = instant_service::guardian_phones((int) $lesson->studentid);
        $cloud = \local_nit_assistant\whatsapp\cloud::instance();
        if (!$phones || !$cloud) {
            return;
        }
        $student = \core_user::get_user((int) $lesson->studentid);
        $teacher = \core_user::get_user((int) $lesson->teacherid);
        $params = [
            $student ? $student->firstname : '',
            strip_tags(format_string($lesson->subject)),
            $teacher ? fullname($teacher) : '',
            userdate((int) $lesson->actual_start ?: time(), '%H:%M', \core_date::get_server_timezone()),
        ];
        $lang = trim((string) get_config('local_nit_lessons', 'instant_parent_template_lang')) ?: 'ar';
        foreach ($phones as $phone) {
            try {
                $cloud->send_template($phone, $template, $lang, $params);
            } catch (\Throwable $e) {
                mtrace('local_nit_lessons: parent alert to ' . substr($phone, 0, 4) . '… failed: ' . $e->getMessage());
            }
        }
    }
}
