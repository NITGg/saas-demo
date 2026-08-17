<?php
/**
 * GET /local/academy/certificate.php?cmid={cmid}&token={wstoken}
 *
 * Token-authenticated download of a mod_customcert certificate as a PDF, so the
 * mobile app can open it natively instead of a webview.
 *
 * Why this exists: customcert generates the certificate PDF on the fly (there is
 * no stored file), and its own view.php download requires a browser session. This
 * endpoint authenticates via a web-service token, verifies the user's access, then
 * streams the same PDF.
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

function certificate_fail(string $code, int $http = 400): void {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $code]);
    exit;
}

$cmid  = required_param('cmid', PARAM_INT);
$token = optional_param('token', '', PARAM_ALPHANUM);

// customcert must be installed (it is a separate plugin, not always present).
if (!class_exists('\mod_customcert\template')) {
    certificate_fail('customcert_not_installed', 501);
}

// Authenticate the token (same full validation as the other academy endpoints).
$user = \local_academy\token_auth::validate($token);
if (!$user) {
    certificate_fail('invalidtoken', 401);
}
\core\session\manager::set_user($user);

$cm = get_coursemodule_from_id('customcert', $cmid, 0, false, IGNORE_MISSING);
if (!$cm) {
    certificate_fail('invalidcmid', 404);
}
// Verify this cmid really is a customcert.
if ($cm->modname !== 'customcert') {
    certificate_fail('not_a_certificate', 404);
}

$context = context_module::instance($cm->id);

// Must be enrolled (or otherwise allowed to view the course).
if (!is_enrolled($context, $user, '', true) && !has_capability('moodle/course:view', $context)) {
    certificate_fail('notenrolled', 403);
}

// From here, surface any failure as clean JSON instead of a themed HTML error
// page, so the client (and we) can see the real cause instead of "could not
// open the PDF". This mirrors mod/customcert/view.php's downloadown path for
// this (service-based) customcert version.
try {
    $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', IGNORE_MISSING);
    if (!$customcert) {
        certificate_fail('customcert_record_missing', 404);
    }

    // Load the template via the repository + factory (this version has no
    // record-taking constructor).
    $templaterecord = (new \mod_customcert\service\template_repository())
        ->get_by_id_or_fail((int) $customcert->templateid);
    $template = \mod_customcert\template::from_record($templaterecord);

    // Issue the certificate if the user doesn't have one yet (as view.php does).
    if (!(new \mod_customcert\service\issue_repository())
            ->exists_for_user((int) $customcert->id, (int) $user->id)) {
        \mod_customcert\service\certificate_issue_service::create()
            ->issue_certificate((int) $customcert->id, (int) $user->id);
    }

    // Release the session lock before the (potentially slow) PDF render.
    \core\session\manager::write_close();

    // Discard any buffered output (stray notices/whitespace would corrupt the PDF
    // stream and make the client report "could not open the PDF").
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Stream the certificate PDF for this user (preview = false).
    \mod_customcert\service\pdf_generation_service::create()
        ->generate_pdf($template, false, (int) $user->id);
    exit;
} catch (\Throwable $e) {
    certificate_fail('generation_failed: ' . $e->getMessage(), 500);
}
