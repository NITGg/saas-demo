<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. See <http://www.gnu.org/licenses/>.

/**
 * JSON for the home page HTML blocks (theme/nit/blocks/templates/bassthalk).
 *
 *   ?section=subjects  → {"years": [...], "subjects": [...]}           (كورسات مختارة — subject cards)
 *   ?section=selected  → {"years": [...], "courses": [...]}            (the same section's former course cards)
 *   ?section=teachers  → {"years", "systems", "me", "teachers": [...]} (المدرسين عندنا)
 *   ?section=lessons   → {"all", "courses": [...]}                     (المحاضرات المقترحة)
 *
 * Public like the home page itself (visible courses and their teachers' card data only);
 * behind the log-in when the site forces log-in.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$section = required_param('section', PARAM_ALPHA);
$PAGE->set_context(context_system::instance());
if (!empty($CFG->forcelogin)) {
    require_login();
}

$data = null;
switch ($section) {
    case 'selected':
        $data = \local_academy\local\home_data::selected();
        break;
    case 'subjects':
        $data = \local_academy\local\home_data::subjects();
        break;
    case 'teachers':
        $data = \local_academy\local\home_data::teachers();
        break;
    case 'lessons':
        $data = \local_academy\local\home_data::lessons();
        break;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($data === null) {
    http_response_code(404);
    echo json_encode(['error' => 'unknown section']);
    die;
}
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
