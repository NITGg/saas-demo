<?php
/**
 * Academy token-authenticated JSON API for the mobile app.
 *
 *   GET|POST /local/academy/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<readable>","errorcode":"<code>"}
 *
 * HTTP 401 = missing/dead token (log the user out), 403 = academy suspended or
 * expired. State-changing calls require POST. Optional `lang=ar|en` picks the
 * language of names and messages. See docs/MOBILE_API.md.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

use local_academy\api\endpoint as api;

api::boot();
$USER = api::authenticate();
$userid = (int) $USER->id;
$token = api::token();
if (($lang = optional_param('lang', '', PARAM_LANG)) !== '') {
    force_current_language($lang);
}

// The quiz + teacher managers append this token to returned file/image URLs so
// clients can load them directly (webservice/pluginfile.php + token).
\local_academy\quiz_manager::set_token($token);
\local_academy\teacher_manager::set_token($token);

api::run(function (string $function) use ($USER, $userid, $token, $DB) {
    $isadmin = has_capability('local/academy:manageplatform', context_system::instance());

    switch ($function) {
        // ── Quiz API ──────────────────────────────────────────────────────────────

        // List quizzes. Students only see their enrolled courses; admins see all.
        case 'get_quizzes':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            return \local_academy\quiz_manager::get_quizzes($userid, $isadmin, $courseid);

        // Get a quiz with structured questions. Correct answers shown to admin only.
        case 'get_quiz':
            $cmid = required_param('cmid', PARAM_INT);
            return \local_academy\quiz_manager::get_quiz($cmid, $userid, $isadmin, $isadmin);

        // Start a new attempt (acts as the token's user).
        case 'start_quiz_attempt':
            api::require_post();
            return \local_academy\quiz_manager::start_attempt(required_param('quizid', PARAM_INT), $userid);

        // Submit answers and finish an attempt (one-shot: grade all + close).
        case 'submit_quiz_attempt':
            api::require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            $answers = api::json_param('answers');
            return \local_academy\quiz_manager::submit_attempt($attemptid, $userid, $answers);

        // Save the answer to ONE question without finishing the attempt.
        case 'save_quiz_answer':
            api::require_post();
            $attemptid  = required_param('attemptid', PARAM_INT);
            $questionid = required_param('questionid', PARAM_INT);
            $raw        = required_param('answer', PARAM_RAW);
            // Accept either a JSON value ("3" or "[3,5]") or a bare scalar (3).
            $answer = json_decode($raw, true);
            if ($answer === null && trim($raw) !== 'null') {
                $answer = is_numeric($raw) ? (int) $raw : $raw;
            }
            return \local_academy\quiz_manager::save_answer($attemptid, $userid, $questionid, $answer);

        // Submit all saved answers and finish the attempt.
        case 'finish_quiz_attempt':
            api::require_post();
            return \local_academy\quiz_manager::finish_attempt(required_param('attemptid', PARAM_INT), $userid);

        // Review a finished attempt. Correct answers shown to admin only.
        case 'get_quiz_attempt':
            return \local_academy\quiz_manager::get_attempt(required_param('attemptid', PARAM_INT), $userid, $isadmin);

        // The current user's attempts on a quiz.
        case 'get_my_quiz_attempts':
            return \local_academy\quiz_manager::get_my_attempts(required_param('quizid', PARAM_INT), $userid);

        // ── Password reset (OTP) + change password ──────────────────────────────────
        // Forgot-password calls are pre-login: send the shared registration token
        // (getsettings.php → admin_token). change_password uses the user's own token.

        // Step 1: email a 6-digit OTP. Always answers generic success.
        case 'request_password_otp':
            api::require_post();
            return \local_academy\password_reset_manager::request_otp(required_param('email', PARAM_RAW_TRIMMED));

        // Step 2: verify the OTP → a single-use reset token.
        case 'verify_password_otp':
            api::require_post();
            return \local_academy\password_reset_manager::verify_otp(
                required_param('email', PARAM_RAW_TRIMMED), required_param('otp', PARAM_ALPHANUM));

        // Step 3: set the new password using the verified reset token.
        case 'reset_password':
            api::require_post();
            return \local_academy\password_reset_manager::reset_password(
                required_param('resettoken', PARAM_ALPHANUM), required_param('newpassword', PARAM_RAW));

        // Signed-in user changes their own password (needs the current one).
        // Note: every web-service token of the user is invalidated → log in again.
        case 'change_password':
            api::require_post();
            return \local_academy\password_reset_manager::change_password($userid,
                required_param('currentpassword', PARAM_RAW), required_param('newpassword', PARAM_RAW));

        // ── Bassthalk pages (public: the shared token browses as a visitor) ─────────

        // Home: "كورسات مختارة" — picked courses + the year filter.
        case 'get_home_selected':
            return \local_academy\api\pages::home_selected(api::public_viewer());

        // Home: "المدرسين عندنا" — teachers + year / study system / division filter
        // (`me` pre-selects the signed-in student's own values).
        case 'get_home_teachers':
            api::public_viewer();
            return \local_academy\api\pages::home_teachers();

        // Home: "المحاضرات المقترحة" — the latest courses.
        case 'get_home_lessons':
            return \local_academy\api\pages::home_lessons(api::public_viewer());

        // The public teacher page.
        case 'get_teacher_page':
            $teacherid = required_param('teacherid', PARAM_INT);
            return \local_academy\api\pages::teacher($teacherid, api::public_viewer());

        // The course details page: about, teachers, price card, lessons with states.
        case 'get_course_page':
            $courseid = required_param('courseid', PARAM_INT);
            return \local_academy\api\pages::course($courseid, api::public_viewer());

        // ── Student registration (pre-login: shared registration token) ──────────

        // The 3-step registration form: fields, steps, dropdown options, password rules.
        case 'get_registration_form':
            return \local_academy\local\registration::form();

        // Create the student account (active at once) and sign it in: returns the
        // NEW user's token. Field errors come back as errorcode invalidregistration
        // with `errors` {field: message}.
        case 'register_student':
            api::require_post();
            $raw = [];
            foreach (\local_academy\local\registration::FIELDS as $name) {
                $raw[$name] = optional_param($name, '', PARAM_RAW);
            }
            $agreed = optional_param('agree', 0, PARAM_BOOL);
            try {
                $newuser = \local_academy\local\registration::register($raw, (bool) $agreed);
            } catch (\local_academy\local\invalid_registration $e) {
                api::emit(['status' => 'fail', 'error' => $e->getMessage(),
                    'errorcode' => 'invalidregistration', 'errors' => $e->errors]);
            }
            // Become the new student and mint their mobile token (as /login/token.php does).
            \core\session\manager::set_user($newuser);
            $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'enabled' => 1]);
            if (!$service) {
                api::fail('servicenotavailable', get_string('servicenotavailable', 'webservice'));
            }
            $tokenrec = \core_external\util::generate_token_for_current_user($service);
            \core_external\util::log_token_request($tokenrec);
            return [
                'userid'       => (int) $newuser->id,
                'token'        => $tokenrec->token,
                'privatetoken' => is_https() ? ($tokenrec->privatetoken ?? null) : null,
                'profile'      => \local_academy\profile_manager::get_full_profile($newuser, $tokenrec->token),
            ];

        // ── Courses ───────────────────────────────────────────────────────────────

        // Free = no active pricing rule. Returns price/currency too when paid.
        case 'is_course_free':
            $courseid = required_param('courseid', PARAM_INT);
            $isfree = !class_exists('\local_payments\price_resolver')
                || !\local_payments\price_resolver::has_pricing($courseid);
            $data = ['courseid' => $courseid, 'is_free' => $isfree];
            if (!$isfree) {
                try {
                    $p = \local_payments\price_resolver::resolve($courseid, $userid);
                    $data['price']    = (float) $p->price;
                    $data['currency'] = $p->currency;
                } catch (\Throwable $e) {
                    $data['is_free'] = true; // No rule resolvable for this user → free.
                }
            }
            return $data;

        // Self-enrol into a FREE course. Paid courses are rejected (they go through
        // the payment flow), so this cannot bypass payment.
        case 'enrol_free_course':
            api::require_post();
            $courseid = required_param('courseid', PARAM_INT);
            if (!class_exists('\local_payments\price_resolver')) {
                api::fail('notinstalled', 'Payments module not available');
            }
            if ($courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid, 'visible' => 1])) {
                api::fail('coursenotfound', get_string('err_coursenotfound', 'local_academy'));
            }
            if (\local_payments\price_resolver::has_pricing($courseid)) {
                api::fail('coursenotfree', get_string('err_coursenotfree', 'local_academy'));
            }
            $enrolled = \local_payments\enrollment_handler::enrol_user($userid, $courseid, 5);
            if (!$enrolled) {
                api::fail('enrolfailed', get_string('err_enrolfailed', 'local_academy'));
            }
            return ['courseid' => $courseid, 'enrolled' => true];

        // A course's lessons in order with the learner's state (locked, for sale,
        // completed, watched %) + where to resume.
        case 'get_course_lessons':
            return \local_academy\api\courses::lessons(required_param('courseid', PARAM_INT));

        // Record that a lesson was opened (view event + view completion). Fails
        // with lessonlocked / lessonforsale when the lesson cannot be opened yet.
        case 'log_lesson_view':
            api::require_post();
            return \local_academy\api\courses::log_view(required_param('cmid', PARAM_INT));

        // Certificates in my courses + PDF download URLs.
        case 'get_my_certificates':
            return \local_academy\api\courses::my_certificates($userid, $token);

        // ── Profile ───────────────────────────────────────────────────────────────

        // Basic profile (unchanged legacy shape).
        case 'get_my_profile':
            return \local_academy\profile_manager::get_my_profile($USER, $token);

        // Full profile: basic + phone, bio, language, roles + every academy field.
        case 'get_full_profile':
            return \local_academy\profile_manager::get_full_profile($USER, $token);

        // Update my profile. Send only what changes; `fields` is a JSON object
        // {shortname: value} for the academy fields (dropdowns take an option `value`).
        case 'update_my_profile':
            api::require_post();
            $data = [];
            foreach (['firstname', 'lastname', 'phone', 'bio', 'language'] as $key) {
                if (isset($_POST[$key])) {
                    $data[$key] = required_param($key, PARAM_RAW);
                }
            }
            if (isset($_POST['fields'])) {
                $data['fields'] = api::json_param('fields');
            }
            \local_academy\profile_manager::update_profile($userid, $data);
            return \local_academy\profile_manager::get_full_profile($USER, $token);

        // The academy profile fields as a form spec (labels, groups, options) —
        // also usable before login with the shared registration token.
        case 'get_profile_fields':
            return \local_academy\profile_manager::field_specs();

        // Years (course categories), study systems and their divisions.
        case 'get_academic_structure':
            $years = array_map(static fn(array $y) => [
                'id' => $y['id'], 'value' => $y['key'], 'name' => $y['name'], 'path' => $y['ids'],
            ], \local_academy\local\academic_structure::years());
            $systems = [];
            foreach (\local_academy\local\academic_structure::get()['systems'] as $system) {
                $systems[] = [
                    'value' => $system['name'],
                    'name' => format_string($system['name'], true, ['context' => context_system::instance(), 'escape' => false]),
                    'divisions' => array_map(static fn(string $d) => [
                        'value' => $d,
                        'name' => format_string($d, true, ['context' => context_system::instance(), 'escape' => false]),
                    ], $system['divisions']),
                ];
            }
            return ['years' => $years, 'systems' => $systems];

        // ── Teachers (instructor directory) ─────────────────────────────────────────

        // Admin: full teacher directory with filters + pagination.
        case 'get_all_teachers':
            api::require_capability('local/academy:manageplatform');
            $filters = [];
            foreach (['courseid', 'categoryid', 'page', 'perpage'] as $f) {
                if (isset($_REQUEST[$f]) && $_REQUEST[$f] !== '') {
                    $filters[$f] = required_param($f, PARAM_INT);
                }
            }
            if (isset($_REQUEST['search']) && $_REQUEST['search'] !== '') {
                $filters['search'] = required_param('search', PARAM_TEXT);
            }
            return \local_academy\teacher_manager::get_all_teachers($filters);

        // Browse instructors (bare array, email dropped).
        case 'browse_teachers':
            return \local_academy\teacher_manager::browse_teachers(optional_param('subject', '', PARAM_TEXT));

        // A single instructor's profile + the courses they teach.
        case 'get_teacher':
            try {
                return \local_academy\teacher_manager::get_teacher(required_param('teacherid', PARAM_INT));
            } catch (\moodle_exception $e) {
                api::fail('teachernotfound', get_string('err_teachernotfound', 'local_academy'));
            }
            return null;

        // Just the courses a given instructor teaches.
        case 'get_teacher_courses':
            return \local_academy\teacher_manager::get_teacher_courses(required_param('teacherid', PARAM_INT));

        // ── Licence / subscription (admin or owner) ─────────────────────────────
        // Package resources, live usage vs caps, and the subscription term.
        case 'get_license_status':
            api::require_capability('local/academy:manageplatform');
            if (!class_exists('\local_license\license')) {
                api::fail('notinstalled', 'The licence plugin is not installed on this academy.');
            }
            return \local_academy\license_status::build();
    }
    return api::unknown();
});
