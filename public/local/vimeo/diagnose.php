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

// ── One activity: why does its video not play here? ─────────────────────────
// ?cmid=N (the id in mod/vimeo/view.php?id=N). Shows the embed the page uses and
// what Vimeo says about the video: processing status, privacy, the domains it
// may be embedded on. "Whitelist" adds this site's domain.
$cmid = optional_param('cmid', 0, PARAM_INT);
echo $OUTPUT->heading('Check one Vimeo activity', 4);
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex gap-2 align-items-center mb-3']);
echo html_writer::tag('label', 'Activity id (mod/vimeo/view.php?id=…)', ['for' => 'vimeo-cmid']);
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'cmid', 'id' => 'vimeo-cmid',
    'value' => $cmid ?: '', 'class' => 'form-control', 'style' => 'width:8rem']);
echo html_writer::tag('button', 'Check', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if ($cmid) {
    $cm = get_coursemodule_from_id('vimeo', $cmid, 0, false, IGNORE_MISSING);
    $instance = $cm ? $DB->get_record('vimeo', ['id' => $cm->instance]) : null;
    if (!$instance) {
        echo $OUTPUT->notification("No Vimeo activity with id {$cmid}.", \core\output\notification::NOTIFY_ERROR);
    } else if (trim((string) $instance->videoid) === '') {
        echo $OUTPUT->notification('This activity has no video id.', \core\output\notification::NOTIFY_ERROR);
    } else {
        $videoid = (string) $instance->videoid;
        $domain = \local_vimeo\video_service::site_domain();
        $embed = 'https://player.vimeo.com/video/' . rawurlencode($videoid)
            . ($instance->videohash !== '' ? '?h=' . rawurlencode($instance->videohash) : '');

        if ($configured && optional_param('whitelist', 0, PARAM_BOOL) && confirm_sesskey()) {
            try {
                (new \local_vimeo\api_client())->whitelist_domain($videoid, $domain);
                echo $OUTPUT->notification("Added {$domain} to the video's embed domains.",
                    \core\output\notification::NOTIFY_SUCCESS);
            } catch (\local_vimeo\api_exception $e) {
                echo $OUTPUT->notification('Whitelist failed: ' . s($e->getMessage()),
                    \core\output\notification::NOTIFY_ERROR);
            }
        }

        $rows = [
            ['Activity', s(format_string($instance->name))],
            ['Video id', s($videoid)],
            ['Privacy hash (?h=)', $instance->videohash !== '' ? s($instance->videohash)
                : 'none — an UNLISTED video needs it: paste the full link (vimeo.com/ID/HASH) in the activity settings'],
            ['Embed URL', html_writer::link($embed, s($embed), ['target' => '_blank'])],
            ['This site\'s domain', s($domain)],
        ];
        if ($configured) {
            try {
                $client = new \local_vimeo\api_client();
                $video = $client->get_video($videoid);
                $privacy = $video['privacy'] ?? [];
                $rows[] = ['Transcode status', s((string) ($video['transcode']['status'] ?? '?'))
                    . (($video['transcode']['status'] ?? '') !== 'complete' ? ' — the video plays only after Vimeo finishes processing it' : '')];
                $rows[] = ['Privacy view / embed', s(($privacy['view'] ?? '?') . ' / ' . ($privacy['embed'] ?? '?'))];
                if (($privacy['embed'] ?? '') === 'whitelist') {
                    $domains = $client->list_domains($videoid);
                    $ok = in_array(strtolower($domain), array_map('strtolower', $domains), true);
                    $rows[] = ['Allowed embed domains', s($domains ? implode(', ', $domains) : '(none)') . ' — '
                        . ($ok ? html_writer::tag('span', 'this site is allowed', ['style' => 'color:green;font-weight:bold'])
                            : html_writer::tag('span', 'THIS SITE IS NOT ALLOWED — Vimeo refuses to play here',
                                ['style' => 'color:#b00;font-weight:bold']) . ' '
                            . $OUTPUT->single_button(new moodle_url('/local/vimeo/diagnose.php',
                                ['cmid' => $cmid, 'whitelist' => 1, 'sesskey' => sesskey()]), "Whitelist {$domain}", 'get'))];
                }
            } catch (\local_vimeo\api_exception $e) {
                $rows[] = ['Vimeo API', html_writer::tag('span', s($e->getMessage())
                    . ($e->httpcode ? " (HTTP {$e->httpcode})" : '')
                    . ($e->httpcode == 404 ? ' — the video does not exist in the account of this access token' : ''),
                    ['style' => 'color:#b00;font-weight:bold'])];
            }
        }
        $table = new html_table();
        $table->data = $rows;
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
