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
 * Explain "Prevent messaging between students" for two users: is the rule on,
 * is the web-service callback registered, and is each user "staff" (and why).
 * Read only.
 *
 *   php local/academy/cli/messaging_check.php --from=student1 --to=student2
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_academy\local\student_messaging;

[$options] = cli_get_params(['from' => '', 'to' => '', 'help' => false], ['h' => 'help']);
if ($options['help'] || $options['from'] === '' || $options['to'] === '') {
    echo "php local/academy/cli/messaging_check.php --from=<username> --to=<username>\n";
    exit(0);
}

/** Find a user by username, email or id. */
function local_academy_messaging_check_user(string $key): stdClass {
    global $DB;
    $where = ctype_digit($key) ? ['id' => (int) $key] : ['username' => core_text::strtolower($key)];
    $user = $DB->get_record('user', $where + ['deleted' => 0]);
    if (!$user && !ctype_digit($key)) {
        $user = $DB->get_record('user', ['email' => $key, 'deleted' => 0], '*', IGNORE_MULTIPLE);
    }
    if (!$user) {
        cli_error("No user '$key'");
    }
    return $user;
}

$from = local_academy_messaging_check_user($options['from']);
$to = local_academy_messaging_check_user($options['to']);

echo "messaging (site): " . var_export($CFG->messaging ?? null, true) . "\n";
echo "messagingallusers: " . var_export($CFG->messagingallusers ?? null, true) . "\n";
echo "local_academy/" . student_messaging::CONFIG . ": "
    . var_export(get_config('local_academy', student_messaging::CONFIG), true) . "\n";
echo "rule enabled(): " . var_export(student_messaging::enabled(), true) . "\n";
$callbacks = get_plugins_with_function('override_webservice_execution');
$names = [];
foreach ($callbacks as $type => $plugins) {
    foreach ($plugins as $plugin => $fn) {
        $names[] = $type . '_' . $plugin;
    }
}
echo "override_webservice_execution callbacks: " . ($names ? implode(', ', $names) : 'NONE') . "\n\n";

foreach (['from' => $from, 'to' => $to] as $label => $user) {
    echo "[$label] id=$user->id username=$user->username name=" . fullname($user) . "\n";
    echo "  site admin: " . var_export(is_siteadmin($user), true) . "\n";
    echo "  " . student_messaging::STAFFCAP . " at site level: "
        . var_export(has_capability(student_messaging::STAFFCAP, context_system::instance(), $user), true) . "\n";
    $courses = get_user_capability_course(student_messaging::STAFFCAP, $user->id, false, 'shortname');
    echo "  courses where it has " . student_messaging::STAFFCAP . ": "
        . ($courses ? implode(', ', array_map(fn($c) => "$c->id ($c->shortname)", $courses)) : 'none') . "\n";
    $assigned = $GLOBALS['DB']->get_records_sql(
        "SELECT ra.id, r.shortname, ctx.contextlevel, ctx.instanceid
           FROM {role_assignments} ra
           JOIN {role} r ON r.id = ra.roleid
           JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE ra.userid = ?", [$user->id]);
    echo "  role assignments: " . ($assigned ? implode(', ', array_map(
        fn($a) => "$a->shortname@" . ($a->contextlevel == CONTEXT_COURSE ? "course$a->instanceid" :
            ($a->contextlevel == CONTEXT_COURSECAT ? "category$a->instanceid" :
            ($a->contextlevel == CONTEXT_SYSTEM ? 'site' : "ctx$a->contextlevel:$a->instanceid"))),
        $assigned)) : 'none') . "\n";
    student_messaging::reset_cache();
    echo "  is_staff(): " . var_export(student_messaging::is_staff((int) $user->id), true) . "\n\n";
}

echo "is_blocked(from -> to): " . var_export(student_messaging::is_blocked((int) $from->id, (int) $to->id), true) . "\n";
