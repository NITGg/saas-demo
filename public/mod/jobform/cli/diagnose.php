<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Diagnose the Job Form web-service API on this server.
 *
 * Prints the plugin versions, schema state, web-service registration, and then
 * calls each student API function with FULL developer debug output (message,
 * errorcode, debuginfo, backtrace) — even when site debugging is off. Use this
 * to see exactly which table/line a "Can't find data record" error comes from.
 *
 * Usage (run inside the Moodle container / server):
 *   php mod/jobform/cli/diagnose.php --cmid=39 --courseid=9 [--userid=123]
 *
 *   --cmid      course-module id to test get_form / view_jobform / submit_form
 *   --courseid  course id to test get_jobforms_by_courses
 *   --userid    run as this user (default: a site admin). Pass the token's user
 *               to reproduce exactly what the mobile app hits.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['cmid' => 0, 'courseid' => 0, 'userid' => 0, 'help' => false],
    ['h' => 'help']
);

if ($options['help']) {
    cli_writeln("Diagnose the Job Form web-service API.\n"
        . "  --cmid=<id>      test get_form / view_jobform / submit_form\n"
        . "  --courseid=<id>  test get_jobforms_by_courses\n"
        . "  --userid=<id>    run as this user (default: a site admin)\n");
    exit(0);
}

// Force full developer debugging for this run, regardless of site settings.
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

$sep = str_repeat('-', 70);

// ---------------------------------------------------------------------------
// 1. Plugin versions.
// ---------------------------------------------------------------------------
cli_heading('Plugin versions (installed in DB)');
foreach (['mod_jobform', 'local_jobform'] as $comp) {
    $dbver = get_config($comp, 'version');
    cli_writeln(sprintf('  %-16s installed=%s', $comp, $dbver ?: 'NOT INSTALLED'));
}
cli_writeln('  (compare mod_jobform to the version.php in the deployed code)');

// ---------------------------------------------------------------------------
// 2. Schema.
// ---------------------------------------------------------------------------
cli_heading('Schema');
$dbman = $DB->get_manager();
foreach (['jobform', 'jobform_field', 'jobform_group', 'jobform_submission',
          'jobform_submission_data', 'local_jobform_field', 'local_jobform_group'] as $t) {
    cli_writeln(sprintf('  table %-24s %s', $t,
        $dbman->table_exists(new xmldb_table($t)) ? 'OK' : '*** MISSING ***'));
}
foreach (['jobform_field' => 'groupid', 'local_jobform_field' => 'groupid'] as $t => $c) {
    $exists = $dbman->table_exists(new xmldb_table($t))
        && $dbman->field_exists(new xmldb_table($t), new xmldb_field($c));
    cli_writeln(sprintf('  column %-16s.%-10s %s', $t, $c, $exists ? 'OK' : '*** MISSING ***'));
}

// ---------------------------------------------------------------------------
// 3. Web-service registration.
// ---------------------------------------------------------------------------
cli_heading('Web-service functions (external_functions table)');
$fns = $DB->get_records_select('external_functions', $DB->sql_like('name', '?'),
    ['mod_jobform%'], 'name', 'name, classname, methodname');
if (!$fns) {
    cli_writeln('  *** NONE registered — run admin/cli/upgrade.php ***');
}
foreach ($fns as $f) {
    cli_writeln(sprintf('  %-38s -> %s::%s', $f->name, $f->classname, $f->methodname));
}

// ---------------------------------------------------------------------------
// 4. Act as a user and call each function.
// ---------------------------------------------------------------------------
if ($options['userid']) {
    $user = $DB->get_record('user', ['id' => $options['userid']], '*', MUST_EXIST);
} else {
    $user = get_admin();
}
\core\session\manager::set_user($user);
cli_heading('Calling functions as user id=' . $user->id . ' (' . fullname($user) . ')');

/**
 * Run one call and print the result or the full exception.
 *
 * @param string $label
 * @param callable $fn
 * @return void
 */
function jobform_diag_run(string $label, callable $fn): void {
    global $sep;
    cli_writeln($sep);
    cli_writeln('CALL: ' . $label);
    try {
        $res = $fn();
        cli_writeln('  OK: ' . substr(json_encode($res), 0, 300));
    } catch (\Throwable $e) {
        cli_writeln('  EXCEPTION: ' . get_class($e));
        cli_writeln('  errorcode: ' . ($e->errorcode ?? '(none)'));
        cli_writeln('  message:   ' . $e->getMessage());
        cli_writeln('  debuginfo: ' . ($e->debuginfo ?? '(null)'));
        cli_writeln('  thrown at: ' . $e->getFile() . ':' . $e->getLine());
        cli_writeln('  backtrace:');
        foreach (array_slice($e->getTrace(), 0, 10) as $i => $t) {
            cli_writeln(sprintf('    #%d %s%s%s  (%s:%s)', $i,
                $t['class'] ?? '', $t['type'] ?? '', $t['function'] ?? '',
                isset($t['file']) ? basename($t['file']) : '?', $t['line'] ?? '?'));
        }
    }
}

if ($options['courseid']) {
    jobform_diag_run('get_jobforms_by_courses([' . $options['courseid'] . '])',
        fn() => mod_jobform_external::get_jobforms_by_courses([(int) $options['courseid']]));
}
if ($options['cmid']) {
    // Read-only checks only — no submission is written.
    jobform_diag_run('get_form(' . $options['cmid'] . ')',
        fn() => mod_jobform_external::get_form((int) $options['cmid']));
    jobform_diag_run('view_jobform(' . $options['cmid'] . ')',
        fn() => mod_jobform_external::view_jobform((int) $options['cmid']));
}
if (!$options['courseid'] && !$options['cmid']) {
    cli_writeln("\nPass --cmid=<id> and/or --courseid=<id> to test the calls.");
}

cli_writeln($sep);
cli_writeln('Done.');
