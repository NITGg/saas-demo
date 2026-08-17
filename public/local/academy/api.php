<?php
/**
 * Academy token-authenticated JSON API (quiz slice).
 *
 * Protocol (unchanged from the old academy):
 *   GET|POST /local/academy/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...} | {"status":"fail","error":"..."}
 *
 * The token is validated against Moodle's external_tokens table and $USER is set
 * to the token's owner; state-changing calls require POST.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

/**
 * Emit a JSON envelope and stop, dropping any stray output first.
 */
function academy_respond($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

/**
 * Reject non-POST requests for state-changing calls.
 */
function academy_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
    }
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_ALPHANUM);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
}
// Full web-service token validation (expiry, IP restriction, service enabled,
// account state) — not just a raw token→user lookup.
$USER = \local_academy\token_auth::validate($token);
if (!$USER) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_invalidtoken', 'local_academy')]);
}
\core\session\manager::set_user($USER);
$userid = (int) $USER->id;

// The quiz + teacher managers append this token to returned file/image URLs so
// clients can load them directly (webservice/pluginfile.php + token).
\local_academy\quiz_manager::set_token($token);
\local_academy\teacher_manager::set_token($token);

try {
    switch ($function) {
        // ── Quiz API ──────────────────────────────────────────────────────────────

        // List quizzes. Students only see their enrolled courses; admins see all.
        case 'get_quizzes':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_quizzes($userid, $is_admin, $courseid)]);
            break;

        // Get a quiz with structured questions. Correct answers shown to admin only.
        case 'get_quiz':
            $cmid     = required_param('cmid', PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_quiz($cmid, $userid, $is_admin, $is_admin)]);
            break;

        // Start a new attempt (any authenticated user, acts as themselves).
        case 'start_quiz_attempt':
            academy_require_post();
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::start_attempt($quizid, $userid)]);
            break;

        // Submit answers and finish an attempt (one-shot: grade all + close).
        case 'submit_quiz_attempt':
            academy_require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            $raw       = required_param('answers', PARAM_RAW);
            $answers   = json_decode($raw, true);
            if (!is_array($answers)) {
                academy_respond(['status' => 'fail', 'error' => 'answers must be a JSON array']);
            }
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::submit_attempt($attemptid, $userid, $answers)]);
            break;

        // Save the answer to ONE question without finishing the attempt.
        case 'save_quiz_answer':
            academy_require_post();
            $attemptid  = required_param('attemptid', PARAM_INT);
            $questionid = required_param('questionid', PARAM_INT);
            $raw        = required_param('answer', PARAM_RAW);
            // Accept either a JSON value ("3" or "[3,5]") or a bare scalar (3).
            $answer = json_decode($raw, true);
            if ($answer === null && trim($raw) !== 'null') {
                $answer = is_numeric($raw) ? (int)$raw : $raw;
            }
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::save_answer($attemptid, $userid, $questionid, $answer)]);
            break;

        // Submit all saved answers and finish the attempt.
        case 'finish_quiz_attempt':
            academy_require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::finish_attempt($attemptid, $userid)]);
            break;

        // Review a finished attempt. Correct answers shown to admin only.
        case 'get_quiz_attempt':
            $attemptid = required_param('attemptid', PARAM_INT);
            $is_admin  = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_attempt($attemptid, $userid, $is_admin)]);
            break;

        // List the current user's attempts on a quiz.
        case 'get_my_quiz_attempts':
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_my_attempts($quizid, $userid)]);
            break;

        // ── Password reset (OTP) + change password ──────────────────────────────────
        // Forgot-password endpoints are pre-login: call them with the shared
        // Registration API token. change_password is post-login: call it with the
        // user's own token.

        // Step 1: email a 6-digit OTP. Always returns generic success.
        case 'request_password_otp':
            academy_require_post();
            $email = required_param('email', PARAM_RAW_TRIMMED);
            try {
                $data = \local_academy\password_reset_manager::request_otp($email);
            } catch (\moodle_exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
            academy_respond(['status' => 'success', 'data' => $data]);
            break;

        // Step 2: verify the OTP -> returns a single-use reset token.
        case 'verify_password_otp':
            academy_require_post();
            $email = required_param('email', PARAM_RAW_TRIMMED);
            $otp   = required_param('otp', PARAM_ALPHANUM);
            try {
                $data = \local_academy\password_reset_manager::verify_otp($email, $otp);
            } catch (\moodle_exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
            academy_respond(['status' => 'success', 'data' => $data]);
            break;

        // Step 3: set the new password using the verified reset token.
        case 'reset_password':
            academy_require_post();
            $resettoken  = required_param('resettoken', PARAM_ALPHANUM);
            $newpassword = required_param('newpassword', PARAM_RAW);
            try {
                $data = \local_academy\password_reset_manager::reset_password($resettoken, $newpassword);
            } catch (\moodle_exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
            academy_respond(['status' => 'success', 'data' => $data]);
            break;

        // Logged-in user changes their own password (needs the current one).
        case 'change_password':
            academy_require_post();
            $current     = required_param('currentpassword', PARAM_RAW);
            $newpassword = required_param('newpassword', PARAM_RAW);
            try {
                $data = \local_academy\password_reset_manager::change_password($userid, $current, $newpassword);
            } catch (\moodle_exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
            academy_respond(['status' => 'success', 'data' => $data]);
            break;

        // ── Courses: is this course free? ───────────────────────────────────────────
        // Free = no active pricing rule. Returns price/currency too when paid.
        case 'is_course_free':
            $courseid = required_param('courseid', PARAM_INT);
            $isfree = !class_exists('\local_payments\price_resolver')
                || !\local_payments\price_resolver::has_pricing($courseid);
            $data = ['courseid' => (int) $courseid, 'is_free' => $isfree];
            if (!$isfree) {
                try {
                    $p = \local_payments\price_resolver::resolve($courseid, $userid);
                    $data['price']    = (float) $p->price;
                    $data['currency'] = $p->currency;
                } catch (\Throwable $e) {
                    $data['is_free'] = true; // no rule resolvable for this user -> free
                }
            }
            academy_respond(['status' => 'success', 'data' => $data]);
            break;

        // ── Courses: self-enrol into a FREE course ──────────────────────────────────
        // Lets a student register themselves on a course that has NO active pricing.
        // Paid courses are rejected — they must go through the payment flow — so this
        // can't be used to bypass payment.
        case 'enrol_free_course':
            academy_require_post();
            $courseid = required_param('courseid', PARAM_INT);
            if (!class_exists('\local_payments\price_resolver')) {
                academy_respond(['status' => 'fail', 'error' => 'Payments module not available']);
            }
            if ($courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid, 'visible' => 1])) {
                academy_respond(['status' => 'fail', 'error' => 'Course not available']);
            }
            if (\local_payments\price_resolver::has_pricing($courseid)) {
                academy_respond(['status' => 'fail', 'error' => 'This course is not free']);
            }
            $enrolled = \local_payments\enrollment_handler::enrol_user($userid, (int) $courseid, 5);
            academy_respond([
                'status' => $enrolled ? 'success' : 'fail',
                'data'   => ['courseid' => (int) $courseid, 'enrolled' => $enrolled],
            ]);
            break;

        // Current user's profile with ready-to-use (token-embedded) image URLs.
        case 'get_my_profile':
            academy_respond(['status' => 'success', 'data' => \local_academy\profile_manager::get_my_profile($USER, $token)]);
            break;

        // ── Teachers (instructor directory) ─────────────────────────────────────────
        // Same names/params/response as the old academy so existing clients work.

        // Admin: full teacher directory with filters + pagination (manageplatform).
        case 'get_all_teachers':
            if (!has_capability('local/academy:manageplatform', context_system::instance())) {
                academy_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
            }
            $filters = [];
            foreach (['courseid', 'categoryid', 'page', 'perpage'] as $f) {
                if (isset($_REQUEST[$f]) && $_REQUEST[$f] !== '') {
                    $filters[$f] = required_param($f, PARAM_INT);
                }
            }
            if (isset($_REQUEST['search']) && $_REQUEST['search'] !== '') {
                $filters['search'] = required_param('search', PARAM_TEXT);
            }
            academy_respond(['status' => 'success', 'data' => \local_academy\teacher_manager::get_all_teachers($filters)]);
            break;

        // Public: browse instructors (bare array, email dropped). Optional subject.
        case 'browse_teachers':
            $subject = optional_param('subject', '', PARAM_TEXT);
            academy_respond(['status' => 'success', 'data' => \local_academy\teacher_manager::browse_teachers($subject)]);
            break;

        // Public: a single instructor's profile + the courses they teach.
        case 'get_teacher':
            $teacherid = required_param('teacherid', PARAM_INT);
            try {
                $teacher = \local_academy\teacher_manager::get_teacher($teacherid);
            } catch (\moodle_exception $e) {
                academy_respond(['status' => 'fail', 'error' => get_string('err_teachernotfound', 'local_academy')]);
            }
            academy_respond(['status' => 'success', 'data' => $teacher]);
            break;

        // Just the courses a given instructor teaches (superset helper).
        case 'get_teacher_courses':
            $teacherid = required_param('teacherid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\teacher_manager::get_teacher_courses($teacherid)]);
            break;

        default:
            academy_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy')]);
    }
} catch (\Throwable $e) {
    // Log the real cause for developers, but never leak internal exception text
    // (DB errors, paths, stack internals) to the client.
    debugging('local_academy api error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    academy_respond(['status' => 'fail', 'error' => get_string('err_internal', 'local_academy')]);
}
