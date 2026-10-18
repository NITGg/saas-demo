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
 * JSON for the navbar search box (theme/nit/templates/theme_boost/navbar.mustache):
 *
 *   ?q=<text> → {"q", "total", "courses": [{id, name, url, image, category}], "moreurl",
 *                "teachers": {total, teachers: [{id, name, image, url, headline, badge}], moreurl, label, all}}
 *
 * The courses the viewer may see in the catalogue (index.php), so it is public like
 * the catalogue and behind the log-in when the site forces log-in. "teachers" (bookable
 * teachers matching the text, local_nit_tutors) is there only when that plugin is.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$q = optional_param('q', '', PARAM_TEXT);
$PAGE->set_context(context_system::instance());
if (!empty($CFG->forcelogin)) {
    require_login();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$result = \local_nit_category\catalogue::suggest($q);
if (class_exists('\local_nit_tutors\local\search')) {
    $result['teachers'] = \local_nit_tutors\local\search::suggest($q, 3) + [
        'label' => get_string('tab_teachers', 'local_nit_tutors'),
        'all' => get_string('viewallteachers', 'local_nit_tutors'),
    ];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
