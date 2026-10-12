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

namespace message_fcm;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Masked setting holding the pasted Service Account JSON key; rejects anything that is not a key.
 *
 * @package    message_fcm
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_serviceaccountjson extends \admin_setting_configpasswordunmask {

    /**
     * Accepts an empty value (use the file instead) or a service account key.
     *
     * @param string $data
     * @return true|string
     */
    public function validate($data) {
        $data = trim((string)$data);
        if ($data === '') {
            return true;
        }
        $key = fcm_client::parse_credentials($data);
        if (!$key || empty($key['project_id'])) {
            return get_string('serviceaccountjson_invalid', 'message_fcm');
        }
        return true;
    }
}
