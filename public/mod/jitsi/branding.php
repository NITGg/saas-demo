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
 * This academy's look inside its Jitsi rooms, through Jitsi's own "dynamic branding"
 * (config.dynamicBrandingUrl, set for the academy's rooms by moodle/server/jitsi/nit-rooms.js
 * on the Jitsi server): the site's colours and logo, and the toolbar icons in the site's
 * rounded outline style (Phosphor Icons, MIT, branding/icons/LICENSE).
 *
 *   branding.php            the branding JSON
 *   branding.php?icon=Name  one icon (Jitsi's icon name, e.g. IconMic)
 *
 * Public and cookie-less: the Jitsi page (another origin) fetches it, hence the CORS header.
 * If it cannot be reached Jitsi keeps its default look; the meeting is not affected.
 *
 * @package    mod_jitsi
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');

$icon = optional_param('icon', '', PARAM_ALPHANUM);
if ($icon !== '') {
    $file = jitsi_branding_icon_file($icon);
    if ($file === null) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/svg+xml');
    readfile($file);
    exit;
}

$PAGE->set_context(context_system::instance());
header('Content-Type: application/json');
echo json_encode(jitsi_branding_data($OUTPUT->get_compact_logo_url(300, 300) ?: null));
