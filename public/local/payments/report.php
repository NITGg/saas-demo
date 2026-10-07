<?php
// The old revenue summary was replaced by the Sales & revenue report in local_nit_reports.
// Kept as a redirect so old links and bookmarks still land somewhere useful.
require_once(__DIR__ . '/../../config.php');

require_login();
redirect(new moodle_url('/local/nit_reports/index.php', ['report' => 'sales']));
