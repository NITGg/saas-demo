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
 * Create the "Level" course custom field (Beginner / Intermediate / Advanced) that
 * the T1 catalogue's Level filter + badge read. Idempotent: safe to run repeatedly
 * and safe to run on every academy during provisioning. Deliberately a CLI (not an
 * install/upgrade step) so it can never break a Moodle upgrade.
 *
 *   php local/nit_category/cli/setup_level_field.php
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$handler = \core_course\customfield\course_handler::create();

// Already there? Then we're done.
foreach ($handler->get_fields() as $field) {
    if ($field->get('shortname') === \local_nit_category\course_meta::LEVEL_SHORTNAME) {
        cli_writeln('Level field already exists — nothing to do.');
        exit(0);
    }
}

// Find (or create) a category to hold it.
$categoryid = 0;
foreach ($handler->get_categories_with_fields() as $cat) {
    $categoryid = $cat->get('id');
    break;
}
if (!$categoryid) {
    $categoryid = $handler->create_category(get_string('coursedetails'));
}

$category = \core_customfield\category_controller::create($categoryid);
$field = \core_customfield\field_controller::create(0, (object) ['type' => 'select'], $category);

$data = (object) [
    'name'              => get_string('level', 'local_nit_category'),
    'shortname'         => \local_nit_category\course_meta::LEVEL_SHORTNAME,
    'description'        => '',
    'descriptionformat' => FORMAT_HTML,
    'configdata'        => [
        'required'     => 0,
        'uniquevalues' => 0,
        'options'      => "Beginner\nIntermediate\nAdvanced",
        'defaultvalue' => '',
        'displaysize'  => 0,
        'locked'       => 0,
        'visibility'   => 2, // Visible to everyone (so learners see the level).
    ],
];

\core_customfield\api::save_field_configuration($field, $data);

cli_writeln('Created the "Level" course custom field (Beginner / Intermediate / Advanced).');
exit(0);
