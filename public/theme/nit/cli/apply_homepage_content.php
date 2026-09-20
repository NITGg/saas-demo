<?php
// This file is part of the NIT academy SaaS provisioning pipeline.
//
// It is NOT meant to be edited by the client. NIT / the owner fill the homepage
// content in the build flow (or in Moodle), and this script writes it into the
// homepage template blocks.

/**
 * Fill the editable homepage content hooks from a JSON manifest.
 *
 * Run inside the client's Moodle container by create.sh, after the homepage
 * template has been applied:
 *
 *   php public/theme/nit/cli/apply_homepage_content.php --manifest=/tmp/nit-content/content.json
 *
 * Manifest (every field optional):
 *   {
 *     "text":   { "hero_title": {"en":"...","ar":"..."}, "contact_email":"x@y.com", ... },
 *     "href":   { "app_ios":"https://...", "app_android":"https://..." },
 *     "images": { "hero":"hero.jpg", "about":"about.jpg", "logo":"logo.png" }
 *   }
 * Image values are paths (absolute, or relative to the manifest) — read here and
 * inlined as data: URIs. Absent fields keep the template's designed defaults.
 *
 * @package   theme_nit
 * @copyright NIT
 */

$configpath = getenv('NIT_MOODLE_CONFIG');
if ($configpath === false || $configpath === '') {
    $configpath = dirname(__DIR__, 4) . '/config.php';
}

define('CLI_SCRIPT', true);
require($configpath);
require_once($CFG->libdir . '/clilib.php');

use theme_nit\local\homepage_content;

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'manifest' => ''],
    ['h' => 'help', 'm' => 'manifest']
);

if ($options['help'] || $options['manifest'] === '') {
    echo "Fill the NIT homepage content hooks from a JSON manifest.\n\n";
    echo "  --manifest=PATH   JSON file with text/href/images (see file header).\n";
    exit($options['help'] ? 0 : 1);
}

if (!is_file($options['manifest'])) {
    cli_error('Manifest not found: ' . $options['manifest']);
}
$manifest = json_decode(file_get_contents($options['manifest']), true);
if (!is_array($manifest)) {
    cli_error('Manifest is not valid JSON: ' . $options['manifest']);
}
$basedir = dirname($options['manifest']);

// Resolve image paths -> data URIs (absent/unreadable are dropped).
$images = isset($manifest['images']) && is_array($manifest['images']) ? $manifest['images'] : [];
$manifest['images'] = [];
foreach ($images as $key => $path) {
    if (!is_string($path) || $path === '') {
        continue;
    }
    if (!preg_match('#^(/|[A-Za-z]:\\\\)#', $path)) {
        $path = rtrim($basedir, '/\\') . '/' . $path;
    }
    if (!is_file($path)) {
        cli_problem("  ! image '$key': file not found ($path) — skipped");
        continue;
    }
    $mime = mime_content_type($path) ?: 'image/png';
    $data = @file_get_contents($path);
    if ($data === false) {
        cli_problem("  ! image '$key': unreadable — skipped");
        continue;
    }
    $manifest['images'][$key] = 'data:' . $mime . ';base64,' . base64_encode($data);
}

cli_heading('Applying NIT homepage content');
$log = [];
homepage_content::apply($manifest, $log);
foreach ($log as $line) {
    cli_writeln('  ' . $line);
}
cli_writeln('Homepage content applied.');
exit(0);
