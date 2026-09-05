<?php
/**
 * Vimeo diagnostics — verifies the access token works by listing videos.
 *
 * Site administration → Plugins → Local plugins → Vimeo diagnostics.
 * Admin-only; no token is ever printed.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_vimeo_diagnose');

$PAGE->set_title(get_string('diagnose', 'local_vimeo'));
$PAGE->set_heading(get_string('diagnose', 'local_vimeo'));

echo $OUTPUT->header();
echo $OUTPUT->heading('Vimeo connection check');

$configured = \local_vimeo\api_client::is_configured();
$base = get_config('local_vimeo', 'apibase') ?: \local_vimeo\api_client::DEFAULT_BASE;

echo html_writer::start_tag('dl');
echo html_writer::tag('dt', 'Access token configured');
echo html_writer::tag('dd', $configured
    ? html_writer::tag('span', 'yes', ['style' => 'color:green;font-weight:bold'])
    : html_writer::tag('span', 'NO — set it in the settings page', ['style' => 'color:#b00;font-weight:bold']));
echo html_writer::tag('dt', 'API base URL');
echo html_writer::tag('dd', s($base));
echo html_writer::end_tag('dl');

if ($configured) {
    echo $OUTPUT->heading('Live API test: list videos', 4);
    try {
        $client = new \local_vimeo\api_client();
        $result = $client->list_videos(['page' => 1, 'per_page' => 5]);

        $rows  = $result['data'] ?? [];
        $count = $result['total'] ?? count($rows);

        echo $OUTPUT->notification(
            "Connection OK — account reports {$count} video(s). Showing up to 5:",
            \core\output\notification::NOTIFY_SUCCESS);

        $table = new html_table();
        $table->head = ['Video ID', 'Title', 'Transcode status', 'Duration (s)'];
        foreach ($rows as $row) {
            $uri = (string) ($row['uri'] ?? '');
            $table->data[] = [
                s($uri !== '' ? basename($uri) : ''),
                s((string) ($row['name'] ?? '')),
                s((string) ($row['transcode']['status'] ?? '')),
                s((string) ($row['duration'] ?? '')),
            ];
        }
        if (empty($rows)) {
            $table->data[] = [get_string('nothingtodisplay'), '', '', ''];
        }
        echo html_writer::table($table);

    } catch (\local_vimeo\api_exception $e) {
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
