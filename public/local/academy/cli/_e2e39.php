<?php
// Throw-away end-to-end client: log in as teacher, upload a file, save a File activity.
[$_, $user, $pass, $course, $fname, $fpath] = $argv;
$base = 'http://localhost';
$jar = tempnam('/tmp', 'jar');
function req($url, $post = null, $multipart = false) {
    global $base, $jar;
    $ch = curl_init($base . $url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Host: localhost:8082'], CURLOPT_FOLLOWLOCATION => 0, CURLOPT_HEADER => 1]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
    }
    $r = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $h = substr($r, 0, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $h, $m);
    return [$code, substr($r, $hs), $m[1] ?? ''];
}
[, $html] = req('/login/index.php');
preg_match('/name="logintoken" value="([^"]+)"/', $html, $m);
[$c, , $loc] = req('/login/index.php', ['username' => $user, 'password' => $pass, 'logintoken' => $m[1]]);
echo "login $c -> $loc\n";
[$c, $html] = req("/course/modedit.php?add=resource&type=&course=$course&section=0&return=0");
echo "form $c\n";
// All inputs of the mform.
preg_match('/<form[^>]*class="mform[^"]*"[^>]*>(.*?)<\/form>/s', $html, $fm);
$fields = [];
preg_match_all('/<input[^>]*>/', $fm[1], $ins);
foreach ($ins[0] as $in) {
    if (!preg_match('/name="([^"]+)"/', $in, $n)) continue;
    preg_match('/value="([^"]*)"/', $in, $v);
    preg_match('/type="([^"]+)"/', $in, $t);
    $type = $t[1] ?? 'text';
    if (in_array($type, ['checkbox', 'radio', 'submit']) && strpos($in, 'checked') === false) continue;
    $fields[$n[1]] = html_entity_decode($v[1] ?? '');
}
preg_match_all('/<select[^>]*name="([^"]+)"[^>]*>(.*?)<\/select>/s', $fm[1], $sels, PREG_SET_ORDER);
foreach ($sels as $s) {
    if (preg_match('/<option[^>]*value="([^"]*)"[^>]*selected/', $s[2], $o) || preg_match('/<option[^>]*value="([^"]*)"/', $s[2], $o)) {
        $fields[$s[1]] = html_entity_decode($o[1]);
    }
}
preg_match_all('/<textarea[^>]*name="([^"]+)"[^>]*>(.*?)<\/textarea>/s', $fm[1], $tas, PREG_SET_ORDER);
foreach ($tas as $t) { $fields[$t[1]] = html_entity_decode($t[2]); }
$sesskey = $fields['sesskey'];
$itemid = $fields['files'];
preg_match('/"upload":\{"id":"?(\d+)/', $html, $rm) || preg_match('/"id":"?(\d+)"?,"name":"Upload a file","type":"upload"/', $html, $rm);
$repo = $rm[1] ?? getenv('REPO');
preg_match('/"contextid":(\d+)/', $html, $cm);
echo "sesskey=$sesskey files=$itemid repo=$repo\n";
[$c, $up] = req('/repository/repository_ajax.php?action=upload', [
    'repo_upload_file' => new CURLFile($fpath, 'application/octet-stream', $fname), 'title' => $fname,
    'sesskey' => $sesskey, 'repo_id' => $repo, 'itemid' => $itemid, 'savepath' => '/', 'author' => 'T',
    'license' => 'unknown', 'ctx_id' => $cm[1] ?? 1, 'maxbytes' => -1, 'areamaxbytes' => -1, 'client_id' => 'x', 'env' => 'filemanager',
], true);
echo "upload $c " . substr($up, 0, 200) . "\n";
$fields['name'] = 'E2E ' . $fname;
$fields['submitbutton2'] = 'Save and return to course';
unset($fields['cancel'], $fields['submitbutton']);
[$c, $out, $loc] = req('/course/modedit.php', $fields);
echo "save $c -> $loc\n";
if (preg_match_all('/id="id_error_[^"]*"[^>]*>(.*?)<\/div>/s', $out, $er)) {
    foreach ($er[1] as $e) { if (trim(strip_tags($e)) !== '') echo "ERROR: " . trim(strip_tags($e)) . "\n"; }
}
