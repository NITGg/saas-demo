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
 * Admin tree settings for local_nit_pages.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_nit_pages_cat', get_string('pluginname', 'local_nit_pages')));

    $ADMIN->add('local_nit_pages_cat', new admin_externalpage(
        'local_nit_pages_manage',
        get_string('manage_pages', 'local_nit_pages'),
        new moodle_url('/local/nit_pages/index.php')
    ));

    $ADMIN->add('local_nit_pages_cat', new admin_externalpage(
        'local_nit_pages_articles',
        get_string('manage_articles', 'local_nit_pages'),
        new moodle_url('/local/nit_pages/manage_articles.php')
    ));
}
