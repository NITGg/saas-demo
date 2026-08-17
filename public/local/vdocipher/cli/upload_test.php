<?php
/**
 * VdoCipher end-to-end upload test (CLI).
 *
 * Proves the full server-side pipeline: obtain upload credentials → push the
 * file to VdoCipher's S3 → poll until the video is "ready". Nothing here touches
 * the app; it's a way to validate Phase 2 with a real video.
 *
 * Usage (inside the container):
 *   php local/vdocipher/cli/upload_test.php --file=/path/to/video.mp4 --title="Smoke test"
 *   php local/vdocipher/cli/upload_test.php --videoid=<id>   # just poll an existing video
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['file' => '', 'title' => 'VdoCipher CLI test', 'videoid' => '', 'tries' => 40, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || (empty($options['file']) && empty($options['videoid']))) {
    cli_writeln("VdoCipher upload test\n");
    cli_writeln("  --file=PATH      local video file to upload");
    cli_writeln("  --title=TEXT     title in the VdoCipher dashboard");
    cli_writeln("  --videoid=ID     skip upload, just poll this video's status");
    cli_writeln("  --tries=N        status poll attempts (default 40, ~5s apart)");
    exit(0);
}

if (!\local_vdocipher\api_client::is_configured()) {
    cli_error('VdoCipher API secret is not configured (settings page).');
}
$client = new \local_vdocipher\api_client();

$videoid = trim($options['videoid']);

if ($videoid === '') {
    $file = $options['file'];
    if (!is_file($file)) {
        cli_error("File not found: {$file}");
    }

    cli_heading('1) Requesting upload credentials');
    $creds = $client->get_upload_credentials($options['title']);
    $videoid = (string) ($creds['videoId'] ?? '');
    $payload = $creds['clientPayload'] ?? [];
    if ($videoid === '' || empty($payload['uploadLink'])) {
        cli_error('Unexpected credentials response: ' . json_encode($creds));
    }
    cli_writeln("   videoId: {$videoid}");

    cli_heading('2) Uploading file to VdoCipher S3');
    $uploadlink = $payload['uploadLink'];

    // Forward EVERY signed field (dropping any means an S3 policy 403); file last.
    $fields = [];
    foreach ($payload as $k => $v) {
        if ($k === 'uploadLink' || $k === 'file' || !is_scalar($v)) {
            continue;
        }
        $fields[$k] = (string) $v;
    }
    if (!array_key_exists('success_action_redirect', $fields)) {
        $fields['success_action_redirect'] = '';
    }
    if (!array_key_exists('success_action_status', $fields)) {
        $fields['success_action_status'] = '201';
    }
    $fields['file'] = new CURLFile(realpath($file));

    $ch = curl_init($uploadlink);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        cli_error("Upload transport error: {$err}");
    }
    if ($code !== 201 && $code !== 200 && $code !== 204) {
        cli_error("Upload failed (HTTP {$code}): " . substr((string) $resp, 0, 500));
    }
    cli_writeln("   upload accepted (HTTP {$code})");
}

cli_heading('3) Polling status until ready');
$tries = (int) $options['tries'];
for ($i = 1; $i <= $tries; $i++) {
    try {
        $video = $client->get_video($videoid);
    } catch (\Throwable $e) {
        cli_writeln("   [{$i}] status check error: " . $e->getMessage());
        sleep(5);
        continue;
    }
    $status = $video['status'] ?? '(unknown)';
    $length = $video['length'] ?? '';
    cli_writeln("   [{$i}] status = {$status}" . ($length !== '' ? " (length {$length}s)" : ''));

    if (strcasecmp((string) $status, 'ready') === 0) {
        cli_writeln("\n✅ Video {$videoid} is READY.");
        exit(0);
    }
    if (in_array(strtolower((string) $status), ['error', 'failed'], true)) {
        cli_error("Video processing failed with status: {$status}");
    }
    sleep(5);
}

cli_writeln("\n⏳ Still processing after {$tries} checks. Re-run with --videoid={$videoid} to keep polling.");
exit(0);
