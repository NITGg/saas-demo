<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
$u = $DB->get_record('user', ['username' => 'nit_dev_teacher']);
echo "teacher id {$u->id} siteadmin=" . (is_siteadmin($u) ? 1 : 0) . "\n";
foreach (enrol_get_all_users_courses($u->id) as $c) {
    $ctx = context_course::instance($c->id);
    echo "course {$c->id} {$c->shortname} addinstance=" . (has_capability('mod/resource:addinstance', $ctx, $u) ? 1 : 0) . "\n";
}
echo "maxmb=" . var_export(get_config('local_academy', 'filelessonmaxmb'), true) . " ver=" . get_config('local_academy', 'version') . "\n";
print_r(array_keys(get_plugins_with_function('coursemodule_validation', 'lib.php')['local'] ?? []));
echo "wwwroot={$CFG->wwwroot}\n";
