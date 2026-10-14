<?php
// Throw-away: drop the half-installed PHPUnit tables (prefix phpu_ only).
define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
require(__DIR__ . '/../../../config.php');
$db = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);
$res = $db->query("SHOW TABLES LIKE 'phpu\_%'");
$n = 0;
while ($row = $res->fetch_row()) {
    if (strpos($row[0], 'phpu_') !== 0) { continue; }
    $db->query("DROP TABLE `{$row[0]}`"); $n++;
}
echo "dropped $n phpu_ tables\n";
