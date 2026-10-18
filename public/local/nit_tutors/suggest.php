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

/**
 * JSON: teachers matching ?q= for the header search's live results (next to the courses of
 * /local/nit_category/suggest.php).
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$PAGE->set_context(context_system::instance());
if (!empty($CFG->forcelogin)) {
    require_login();
}
$q = optional_param('q', '', PARAM_TEXT);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(\local_nit_tutors\local\search::suggest($q, 4), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
