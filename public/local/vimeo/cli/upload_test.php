<?php
/**
 * Vimeo end-to-end upload test (CLI).
 *
 * Proves the full server-side pipeline: create the video → PATCH the bytes via
 * tus → poll until transcode.status is "complete". Nothing here touches the app;
 * it's a way to validate the plugin with a real video and a real token.
 *
 * Usage (inside the container):
 *   php local/vimeo/cli/upload_test.php --file=/path/to/video.mp4 --title="Smoke test"
 *   php local/vimeo/cli/upload_test.php --videoid=<id>   # just poll an existing video
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['file' => '', 'title' => 'Vimeo CLI test', 'videoid' => '', 'tries' => 40, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || (empty($options['file']) && empty($options['videoid']))) {
    cli_writeln("Vimeo upload test\n");
    cli_writeln("  --file=PATH      local video file to upload");
    cli_writeln("  --title=TEXT     title in the Vimeo dashboard");
    cli_writeln("  --videoid=ID     skip upload, just poll this video's status");
    cli_writeln("  --tries=N        status poll attempts (default 40, ~5s apart)");
    exit(0);
}

if (!\local_vimeo\api_client::is_configured()) {
    cli_error('Vimeo access token is not configured (settings page).');
}
$client = new \local_vimeo\api_client();

$videoid = trim($options['videoid']);

if ($videoid === '') {
    $file = $options['file'];
    if (!is_file($file)) {
        cli_error("File not found: {$file}");
    }

    cli_heading('1) Uploading file to Vimeo (create + tus PATCH)');
    try {
        $videoid = $client->upload(realpath($file), $options['title']);
    } catch (\Throwable $e) {
        cli_error('Upload failed: ' . $e->getMessage());
    }
    cli_writeln("   videoId: {$videoid}");
}

cli_heading('2) Polling transcode status until complete');
$tries = (int) $options['tries'];
for ($i = 1; $i <= $tries; $i++) {
    try {
        $video = $client->get_video($videoid);
    } catch (\Throwable $e) {
        cli_writeln("   [{$i}] status check error: " . $e->getMessage());
        sleep(5);
        continue;
    }
    $status = $video['transcode']['status'] ?? '(unknown)';
    $length = $video['duration'] ?? '';
    cli_writeln("   [{$i}] transcode.status = {$status}" . ($length !== '' ? " (duration {$length}s)" : ''));

    if (strcasecmp((string) $status, 'complete') === 0) {
        cli_writeln("\n✅ Video {$videoid} is COMPLETE.");
        cli_writeln("   embed: https://player.vimeo.com/video/{$videoid}");
        exit(0);
    }
    if (strcasecmp((string) $status, 'error') === 0) {
        cli_error("Video transcode failed with status: {$status}");
    }
    sleep(5);
}

cli_writeln("\n⏳ Still processing after {$tries} checks. Re-run with --videoid={$videoid} to keep polling.");
exit(0);
