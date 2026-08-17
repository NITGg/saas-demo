<?php
/**
 * VdoCipher diagnostics — verifies the API secret works by listing videos.
 *
 * Site administration → Plugins → Local plugins → VdoCipher diagnostics.
 * Admin-only; no secret is ever printed.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_vdocipher_diagnose');

$PAGE->set_title(get_string('diagnose', 'local_vdocipher'));
$PAGE->set_heading(get_string('diagnose', 'local_vdocipher'));

echo $OUTPUT->header();
echo $OUTPUT->heading('VdoCipher connection check');

$configured = \local_vdocipher\api_client::is_configured();
$base = get_config('local_vdocipher', 'apibase') ?: 'https://dev.vdocipher.com/api';

echo html_writer::start_tag('dl');
echo html_writer::tag('dt', 'API secret configured');
echo html_writer::tag('dd', $configured
    ? html_writer::tag('span', 'yes', ['style' => 'color:green;font-weight:bold'])
    : html_writer::tag('span', 'NO — set it in the settings page', ['style' => 'color:#b00;font-weight:bold']));
echo html_writer::tag('dt', 'API base URL');
echo html_writer::tag('dd', s($base));
echo html_writer::end_tag('dl');

if ($configured) {
    echo $OUTPUT->heading('Live API test: list videos', 4);
    try {
        $client = new \local_vdocipher\api_client();
        $result = $client->list_videos(['page' => 1, 'limit' => 5]);

        $rows  = $result['rows'] ?? [];
        $count = $result['count'] ?? count($rows);

        echo $OUTPUT->notification(
            "Connection OK — account reports {$count} video(s). Showing up to 5:",
            \core\output\notification::NOTIFY_SUCCESS);

        $table = new html_table();
        $table->head = ['Video ID', 'Title', 'Status', 'Length (s)'];
        foreach ($rows as $row) {
            $table->data[] = [
                s($row['id'] ?? ''),
                s($row['title'] ?? ''),
                s($row['status'] ?? ''),
                s((string) ($row['length'] ?? '')),
            ];
        }
        if (empty($rows)) {
            $table->data[] = [get_string('nothingtodisplay'), '', '', ''];
        }
        echo html_writer::table($table);

    } catch (\local_vdocipher\api_exception $e) {
        echo $OUTPUT->notification(
            'API call failed: ' . s($e->getMessage()) .
            ($e->httpcode ? " (HTTP {$e->httpcode})" : ''),
            \core\output\notification::NOTIFY_ERROR);
        if ($e->response !== '') {
            echo html_writer::tag('pre', s(\core_text::substr($e->response, 0, 1000)));
        }
    }
}

echo $OUTPUT->footer();
