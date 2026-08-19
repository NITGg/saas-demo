<?php
// This file is part of the NIT academy SaaS provisioning pipeline.
//
// It is NOT meant to be edited by the client. The NIT "build your product"
// form collects the branding, the provisioning service stages it, and this
// script applies it to a freshly-provisioned academy — so the client never
// has to touch Moodle's admin settings.

/**
 * Apply per-client branding (site name + logo + favicon) to this academy.
 *
 * Run inside the client's Moodle container by create.sh, once the site is up:
 *
 *   php public/theme/nit/cli/apply_brand.php --manifest=/var/www/html/nit-brand/brand.json
 *
 * The manifest is JSON. Every field is optional; only the ones present are
 * applied, so a partial brand (e.g. name only, no logo) is fine:
 *
 *   {
 *     "fullname_ar": "أكاديمية المستقبل",
 *     "fullname_en": "Future Academy",
 *     "shortname_ar": "المستقبل",
 *     "shortname_en": "Future",
 *     "logo": "logo.png",          // path, absolute or relative to the manifest
 *     "favicon": "favicon.ico"
 *   }
 *
 * Names with both an AR and EN value are stored as a {mlang} multi-language
 * string (the multilang2 filter — enabled by create.sh — renders the right one
 * per visitor language). A single-language name is stored verbatim.
 *
 * @package   theme_nit
 * @copyright NIT
 */

// The generated config.php lives at the code root (four levels up:
// cli -> nit -> theme -> public -> <root>/config.php). Allow an override for
// non-standard layouts / local testing.
$configpath = getenv('NIT_MOODLE_CONFIG');
if ($configpath === false || $configpath === '') {
    $configpath = dirname(__DIR__, 4) . '/config.php';
}

define('CLI_SCRIPT', true);
require($configpath);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

list($options, $unrecognised) = cli_get_params(
    [
        'help'        => false,
        'manifest'    => '',
        'fullname-ar' => null,
        'fullname-en' => null,
        'shortname-ar' => null,
        'shortname-en' => null,
        'logo'        => null,
        'favicon'     => null,
    ],
    ['h' => 'help']
);

if ($options['help']) {
    echo "Apply NIT per-client branding (site name + logo + favicon).\n\n";
    echo "  --manifest=PATH   JSON file with the branding fields (see file header).\n";
    echo "  Individual flags (--fullname-ar, --fullname-en, --shortname-ar,\n";
    echo "  --shortname-en, --logo, --favicon) override the manifest.\n";
    exit(0);
}

/**
 * Combine an Arabic and English value into a {mlang} multi-language string.
 * If only one side is present, return it verbatim (no tags).
 */
function nit_brand_mlang($ar, $en): string {
    $ar = trim((string)$ar);
    $en = trim((string)$en);
    if ($ar !== '' && $en !== '') {
        return '{mlang ar}' . $ar . '{mlang}{mlang en}' . $en . '{mlang}';
    }
    return $ar !== '' ? $ar : $en;
}

/**
 * Store one uploaded image as a core_admin site file (logo / favicon) exactly
 * the way the admin settings form does: system context, itemid 0, root path,
 * and the config value is the leading-slash filename.
 */
function nit_brand_set_site_file(string $filearea, string $configname, string $sourcepath): void {
    if (!is_file($sourcepath)) {
        cli_problem("  ! $filearea: file not found ($sourcepath) — skipped");
        return;
    }
    $fs = get_file_storage();
    $ctx = context_system::instance();

    // A single file per area — clear whatever the template shipped first.
    $fs->delete_area_files($ctx->id, 'core_admin', $filearea, 0);

    $filename = clean_param(basename($sourcepath), PARAM_FILE);
    if ($filename === '') {
        cli_problem("  ! $filearea: unsafe filename — skipped");
        return;
    }

    $fs->create_file_from_pathname([
        'contextid' => $ctx->id,
        'component' => 'core_admin',
        'filearea'  => $filearea,
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $filename,
    ], $sourcepath);

    // Same value shape the admin form writes: '/<filename>'.
    set_config($configname, '/' . $filename, 'core_admin');
    cli_writeln("  + $filearea set to $filename");
}

// ── Load the manifest, let individual flags override ────────────────────────
$data = [];
if ($options['manifest'] !== '') {
    if (!is_file($options['manifest'])) {
        cli_error('Manifest not found: ' . $options['manifest']);
    }
    $decoded = json_decode(file_get_contents($options['manifest']), true);
    if (!is_array($decoded)) {
        cli_error('Manifest is not valid JSON: ' . $options['manifest']);
    }
    $data = $decoded;
    $manifestdir = dirname($options['manifest']);
} else {
    $manifestdir = getcwd();
}

foreach (['fullname-ar', 'fullname-en', 'shortname-ar', 'shortname-en', 'logo', 'favicon'] as $flag) {
    if ($options[$flag] !== null) {
        $data[str_replace('-', '_', $flag)] = $options[$flag];
    }
}

/** Resolve an image path: absolute as-is, otherwise relative to the manifest. */
$resolvepath = function ($p) use ($manifestdir) {
    $p = (string)$p;
    if ($p === '') {
        return '';
    }
    if (preg_match('#^(/|[A-Za-z]:\\\\)#', $p)) {
        return $p;
    }
    return rtrim($manifestdir, '/\\') . '/' . $p;
};

cli_heading('Applying NIT branding');

// ── 1. Site name (full + short), multi-language ─────────────────────────────
global $DB;
$fullname  = nit_brand_mlang($data['fullname_ar'] ?? '', $data['fullname_en'] ?? '');
$shortname = nit_brand_mlang($data['shortname_ar'] ?? '', $data['shortname_en'] ?? '');

if ($fullname !== '') {
    $DB->set_field('course', 'fullname', $fullname, ['id' => SITEID]);
    cli_writeln('  + site full name updated');
}
if ($shortname !== '') {
    // shortname is a unique key on the site course, but SITEID is the only row
    // that ever holds it, so a plain update is safe.
    $DB->set_field('course', 'shortname', $shortname, ['id' => SITEID]);
    cli_writeln('  + site short name updated');
}

// ── 2. Logo + favicon ───────────────────────────────────────────────────────
if (!empty($data['logo'])) {
    nit_brand_set_site_file('logo', 'logo', $resolvepath($data['logo']));
}
if (!empty($data['favicon'])) {
    nit_brand_set_site_file('favicon', 'favicon', $resolvepath($data['favicon']));
}

// ── 3. Bust caches so the new name/logo show immediately ────────────────────
// theme_reset_all_caches() bumps the theme revision (new logo URL), and a full
// purge clears the string/format caches that hold the old site name.
theme_reset_all_caches();
purge_all_caches();

cli_writeln('Branding applied.');
exit(0);
