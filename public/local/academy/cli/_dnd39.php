<?php
// Throw-away: log in as teacher, drag-and-drop upload a file onto a course page.
[$_, $user, $pass, $course, $fname, $fpath] = $argv;
$jar = tempnam('/tmp', 'jar');
function req($url, $post = null, $multipart = false) {
    global $jar;
    $ch = curl_init('http://localhost' . $url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Host: localhost:8082']]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
    }
    return curl_exec($ch);
}
$html = req('/login/index.php');
preg_match('/name="logintoken" value="([^"]+)"/', $html, $m);
req('/login/index.php', ['username' => $user, 'password' => $pass, 'logintoken' => $m[1]]);
$html = req("/course/view.php?id=$course");
preg_match('/"sesskey":"([^"]+)"/', $html, $s);
echo "dnd " . req('/course/dndupload.php', ['repo_upload_file' => new CURLFile($fpath, 'application/octet-stream', $fname),
    'course' => $course, 'section' => 0, 'type' => 'Files', 'module' => 'resource', 'sesskey' => $s[1]], true) . "\n";
