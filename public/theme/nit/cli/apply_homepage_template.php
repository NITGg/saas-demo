<?php
// This file is part of the NIT academy SaaS provisioning pipeline.
//
// It is NOT meant to be edited by the client. NIT selects a homepage template in
// the "build your product" flow; the provisioning service passes the chosen id
// here so a freshly-provisioned academy comes up on that template — the owner
// then applies their own images and brand colour on top.

/**
 * Apply one of the ten NIT homepage templates (t1..t10) to this academy.
 *
 * Run inside the client's Moodle container by create.sh, once the site + its
 * seed section blocks exist:
 *
 *   php public/theme/nit/cli/apply_homepage_template.php --template=t4
 *
 * Rewrites the Site-home nit_section blocks from theme/nit/blocks/templates/<id>/
 * and records the choice in theme_nit config (homepage_template).
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

use theme_nit\local\homepage_templates;
use theme_nit\local\template_applier;

list($options, $unrecognised) = cli_get_params(
    ['help' => false, 'template' => '', 'list' => false],
    ['h' => 'help', 't' => 'template', 'l' => 'list']
);

if ($options['help']) {
    echo "Apply a NIT homepage template (t1..t10) to this academy.\n\n";
    echo "  --template=ID   the template id to apply, e.g. --template=t4\n";
    echo "  --list          print the available template ids and names\n";
    exit(0);
}

if ($options['list']) {
    cli_heading('Available homepage templates');
    foreach (homepage_templates::all() as $id => $t) {
        cli_writeln('  ' . str_pad($id, 5) . ' ' . $t['name']['en'] . '  (' . $t['font'] . ')');
    }
    cli_writeln('Current: ' . homepage_templates::current());
    exit(0);
}

$id = trim((string) $options['template']);
if ($id === '') {
    cli_error('Missing --template=ID (use --list to see the options).');
}
if (!homepage_templates::exists($id)) {
    cli_error("Unknown template '$id' (use --list to see the options).");
}

cli_heading('Applying NIT homepage template: ' . $id . ' — ' . homepage_templates::all()[$id]['name']['en']);

$log = [];
template_applier::apply($id, $log);
foreach ($log as $line) {
    cli_writeln('  ' . $line);
}

cli_writeln('Homepage template applied.');
exit(0);
