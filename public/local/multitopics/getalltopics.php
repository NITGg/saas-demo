<?php
/**
 * GET /local/multitopics/getalltopics.php?courseid={id}&wstoken={token}
 *
 * Returns full course structure for the mobile app:
 *   - Course metadata + availability
 *   - other_fields: reserved course-level extras (empty object)
 *   - parents[]: top-level sections with nested topics and activities
 *
 * Auth: `wstoken` (or `token`) validated by \local_academy\token_auth (401 when
 * missing/invalid); 403 siteunavailable when the academy licence is locked.
 * Visibility: sections where uservisible = true; activities the user can open
 * (uservisible) plus restricted ones Moodle shows greyed-out on the course page
 * (restricted = true + restrictioninfo, and no playable/downloadable URLs).
 * Per activity: locked (lesson-order lock), forsale (needs buying, local_nit_finance),
 * completed, completiontracking, watched_percent — from \local_academy\player::walk().
 * Locked / for-sale / restricted activities never carry fileurl / otpurl / embedurl.
 * previewseconds > 0 on a for-sale video lesson: its free first seconds play via
 * /local/nit_finance/api.php?function=get_lesson_preview.
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

header('Content-Type: application/json; charset=utf-8');

// ── Helper: JSON error exit ────────────────────────────────────────────────
function api_error(string $errorcode, string $message, int $http = 400): void {
    http_response_code($http);
    echo json_encode([
        'exception' => 'moodle_exception',
        'errorcode' => $errorcode,
        'message'   => $message,
    ]);
    exit;
}

// ── Helper: classify a mimetype so the app can pick a native player ─────────
// Lets the client render a File activity natively (video/audio/image/pdf) instead
// of opening the mod/resource/view.php webview.
function mt_media_type(string $mime): string {
    $mime = strtolower($mime);
    if (strpos($mime, 'video/') === 0) { return 'video'; }
    if (strpos($mime, 'audio/') === 0) { return 'audio'; }
    if (strpos($mime, 'image/') === 0) { return 'image'; }
    if ($mime === 'application/pdf') { return 'pdf'; }
    if (strpos($mime, 'word') !== false || strpos($mime, 'excel') !== false
        || strpos($mime, 'powerpoint') !== false || strpos($mime, 'officedocument') !== false
        || strpos($mime, 'text/') === 0) { return 'document'; }
    return 'file';
}

// ── Helper: make a protected file URL loadable by a token client ────────────
// Rewrites /pluginfile.php (needs a browser session) to /webservice/pluginfile.php
// and appends the token with the correct separator. Plain pluginfile.php ignores
// tokens, and when slasharguments is off the URL already has "?file=...", so a
// naive "?token=" would produce a broken double "?". Both are handled here.
function mt_ws_fileurl(\moodle_url $url, string $token): string {
    $s = $url->out(false);
    $s = str_replace('/pluginfile.php', '/webservice/pluginfile.php', $s);
    $s .= (strpos($s, '?') !== false ? '&' : '?') . 'token=' . $token;
    return $s;
}

// ── 1. Validate the token (`wstoken`, or `token` like our other endpoints) ──
$wstoken  = \local_academy\api\endpoint::request_token();
$courseid = optional_param('courseid', 0,  PARAM_INT);

if (!$wstoken) {
    api_error('invalidtoken', 'Token is required', 401);
}
if (!$courseid) {
    api_error('invalidparameter', 'courseid is required');
}

// Full web-service token validation (expiry, IP restriction, enabled service,
// live / confirmed / non-suspended local account) — the one shared check.
$tokenuser = \local_academy\token_auth::validate($wstoken);
if (!$tokenuser) {
    api_error('invalidtoken', 'Invalid token', 401);
}
\core\session\manager::set_user($tokenuser);

// Academy licence lock (suspended / expired past grace) — same rule as every
// other mobile endpoint; site admins stay through.
if (\local_academy\api\endpoint::site_locked((int) $USER->id)) {
    api_error('siteunavailable', get_string('err_siteunavailable', 'local_academy'), 403);
}

// ── 2. Load course ─────────────────────────────────────────────────────────
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    api_error('invalidcourseid', 'Course not found', 404);
}

// Check enrolment/access.
$course_context = context_course::instance($course->id);
if (!is_enrolled($course_context, $USER) && !has_capability('moodle/course:view', $course_context)) {
    api_error('nopermissions', 'Not enrolled in this course', 403);
}

$course_available = true;
$course_status    = 'available';
$now = time();
if (!empty($course->startdate) && $course->startdate > $now) {
    $course_available = false;
    $course_status    = 'not_started';
} elseif (!empty($course->enddate) && $course->enddate < $now) {
    $course_status = 'ended';
}
if ($course->visible == 0 && !has_capability('moodle/course:viewhiddencourses', $course_context)) {
    $course_available = false;
    $course_status    = 'hidden';
}

// ── 3. other_fields ───────────────────────────────────────────────────────
// Reserved course-level extras. Always an empty object in the new academy.
$other_fields = (object)[];

// ── 4. Course sections and activities ─────────────────────────────────────
$modinfo = get_fast_modinfo($course, $USER->id);

// Map section number → section info.
$sections = $modinfo->get_section_info_all();

// Detect multitopic section parents via section info.
// In the multitopic format each section has a 'parent' field (section number of parent).
// Fall back to a flat top-level-only structure if not present.
$section_parent = [];   // sectionnum => parent sectionnum (0 = top-level)
$is_multitopic  = ($course->format === 'multitopics');
// Fetch every section's parent in ONE query (keyed by section number) instead of
// one query per section (N+1). If the 'parent' column is absent, treat all as
// top-level.
$dbsections = [];
if ($is_multitopic) {
    try {
        $dbsections = $DB->get_records('course_sections', ['course' => $courseid], '', 'section, parent');
    } catch (\Exception $e) {
        $dbsections = [];
    }
}
foreach ($sections as $snum => $sinfo) {
    $section_parent[$snum] = isset($dbsections[$snum]->parent) ? (int) $dbsections[$snum]->parent : 0;
}

/**
 * Human-readable restriction text for a course module — the "Not available unless: …"
 * conditions (dates, groups, grades, profile fields, …) shown against a greyed-out item.
 *
 * $cm->availableinfo only contains the conditions the student is allowed to see (those
 * flagged "display greyed-out with info"); when every condition is hidden it is empty
 * even though the item is restricted. In that case we fall back to the full restriction
 * description (the same list the teacher sees when editing) so the app always gets a reason.
 *
 * @return string plain text (tags stripped), or '' when the item is available.
 */
function build_restriction_info(cm_info $cm): string {
    if ($cm->available) {
        return '';
    }
    $course = $cm->get_course();
    $html   = '';
    if (!empty($cm->availableinfo)) {
        // Student-facing reason (respects each condition's show/hide flag).
        $html = \core_availability\info::format_info($cm->availableinfo, $course);
    } else {
        // Full restriction description regardless of per-condition visibility.
        $info = new \core_availability\info_module($cm);
        $full = $info->get_full_information();
        if ($full) {
            $html = \core_availability\info::format_info($full, $course);
        }
    }
    // Collapse the HTML list into a clean single-line-per-condition string.
    return trim(html_to_text($html, 0, false));
}

/**
 * Distinct question-type tokens a quiz DEMANDS to render natively.
 *
 * The app decides native-vs-webview by comparing what an activity requires against
 * its OWN installed supported set (which grows with app version). So the server
 * only states facts: one "qtype:<rawname>" per DISTINCT Moodle question type the
 * quiz contains, using the exact Moodle qtype string (multichoice, truefalse,
 * essay, shortanswer, …) with no renaming or grouping — the app matches on the
 * literal string.
 *
 * Special slots are still honest facts, not decisions:
 *   - a random-draw slot has no concrete type at list time  → "qtype:random"
 *   - a deleted/unknown question becomes                     → "qtype:missingtype"
 * No current app renders either, so they naturally fall back to the webview.
 *
 * An empty array means the quiz has no answerable questions — nothing special is
 * required, so the app keeps its default (native by modname).
 *
 * @return string[] distinct "qtype:*" tokens (order not significant)
 */
function mt_quiz_requires(cm_info $cm): array {
    $context = context_module::instance($cm->id);
    try {
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure(
            (int)$cm->instance, $context);
    } catch (\Throwable $e) {
        // Never let a quiz-structure error break the whole course listing;
        // an empty requires just means "no special demand known".
        return [];
    }

    $tokens = [];   // used as a set: "qtype:x" => true
    foreach ($structure as $row) {
        if (!empty($row->random)) {
            $tokens['qtype:random'] = true;
        } else if (!empty($row->qtype)) {
            $tokens['qtype:' . $row->qtype] = true;
        }
    }
    return array_keys($tokens);
}

/**
 * The learner state of one activity: lesson-order lock, for sale, completion and
 * watched %. Lessons come from \local_academy\player::walk() (the same walk the
 * web player uses); anything the walk skips (labels, restricted items) is never
 * locked / for sale and gets its completion straight from completion_info.
 *
 * @param cm_info $cm
 * @param array $lessons cmid => walk() lesson entry
 * @param completion_info $completion
 * @param int $userid
 * @return array{locked:bool, forsale:bool, completed:bool, completiontracking:string, watched_percent:?int}
 */
function mt_lesson_state(cm_info $cm, array $lessons, completion_info $completion, int $userid): array {
    $l = $lessons[(int) $cm->id] ?? null;
    if ($l) {
        return [
            'locked'             => (bool) $l['locked'],
            'forsale'            => (bool) $l['forsale'],
            'completed'          => (bool) $l['done'],
            'completiontracking' => !$l['tracked'] ? 'none' : ($l['manual'] ? 'manual' : 'auto'),
            'watched_percent'    => $l['watched'] !== null ? (int) $l['watched'] : null,
        ];
    }
    $mode = $completion->is_enabled($cm);
    $done = false;
    if ($mode != COMPLETION_TRACKING_NONE) {
        $cdata = $completion->get_data($cm, false, $userid);
        $done = in_array((int) $cdata->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }
    return [
        'locked'             => false,
        'forsale'            => false,
        'completed'          => $done,
        'completiontracking' => $mode == COMPLETION_TRACKING_NONE ? 'none'
            : ($mode == COMPLETION_TRACKING_MANUAL ? 'manual' : 'auto'),
        'watched_percent'    => null,
    ];
}

// Build activity list for each section.
function build_activities(array $cms, object $modinfo, string $wstoken, string $wwwroot,
                           object $DB, object $USER, array $lessons, completion_info $completion): array {
    global $CFG;
    $result = [];
    foreach ($cms as $cmid) {
        $cm = $modinfo->get_cm($cmid);
        // Activities the user can open, plus restricted ones Moodle shows greyed-out
        // on the course page ("Not available unless: …"). Fully hidden ones are skipped.
        if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
            continue;
        }

        $url      = $wwwroot . '/mod/' . $cm->modname . '/view.php?id=' . $cm->id;
        $modicon  = $wwwroot . '/theme/image.php/' . (isset($CFG->theme) ? $CFG->theme : 'boost')
                    . '/' . $cm->modname . '/icon';

        $act = [
            'id'              => (string)$cm->id,
            'modname'         => $cm->modname,
            'name'            => format_string($cm->name),
            'sectionnum'      => (string)$cm->sectionnum,
            'visible'         => (bool)$cm->visible,
            'uservisible'     => (bool)$cm->uservisible,
            'url'             => $url,
            'tags'            => [],
            'modicon'         => $modicon,
            'resourcetype'    => '',
            'mediatype'       => '',
            'fileurl'         => '',
            // Secure DRM video (VdoCipher) attached to a resource2 activity. When
            // isvdocipher is true the app plays via the VdoCipher SDK using an OTP
            // fetched from otpurl (short-lived, watermarked) — no fileurl/webview.
            'isvdocipher'     => false,
            'videoid'         => '',
            'otpurl'          => '',
            // Vimeo video attached to a resource2 (or vimeo) activity. When
            // isvimeo is true the app plays the Vimeo embed at embedurl — a
            // domain-private embed (no OTP). embedurl is fetched right before
            // playback from /local/vimeo/api.php?function=get_playback.
            'isvimeo'         => false,
            'embedurl'        => '',
            // Distinguishes a downloadable certificate (customcert) from a plain
            // PDF resource, so the app can show certificate-specific UI (download,
            // share, "your certificate", etc.).
            'iscertificate'   => false,
            // Access restrictions (e.g. date/group/grade conditions): a restricted
            // cm is shown greyed-out with its "not available unless..." text and
            // cannot be opened (no file/video URLs). Staff who bypass restrictions
            // get restricted=false but still see the restriction text.
            'restricted'      => !$cm->uservisible,
            'restrictioninfo' => build_restriction_info($cm),
        ];
        // Lesson-order lock, for sale, completion, watched % (player walk).
        $act += mt_lesson_state($cm, $lessons, $completion, (int) $USER->id);

        // ── Resource: get file URL and type ─────────────────────────────────
        if ($cm->modname === 'resource') {
            $fs   = get_file_storage();
            $ctx  = context_module::instance($cm->id);
            $files = $fs->get_area_files($ctx->id, 'mod_resource', 'content', false, 'sortorder DESC, id ASC', false);
            if ($files) {
                $file = reset($files);
                $mime = $file->get_mimetype();
                $act['resourcetype'] = $mime;
                $act['mediatype']    = mt_media_type($mime);
                $act['fileurl']      = mt_ws_fileurl(\moodle_url::make_pluginfile_url(
                    $ctx->id, 'mod_resource', 'content', $file->get_itemid(),
                    $file->get_filepath(), $file->get_filename()
                ), $wstoken);
            }
        }

        // ── resource2: custom fork of mod_resource ──────────────────────────
        if ($cm->modname === 'resource2') {
            // A VdoCipher video attached to this activity takes precedence over any
            // uploaded file: the app plays it securely via the SDK, never a webview.
            $vrow = $DB->get_record('local_vdocipher_videos', ['cmid' => $cm->id]);
            if ($vrow) {
                $act['mediatype']    = 'vdocipher';
                $act['isvdocipher']  = true;
                $act['videoid']      = $vrow->videoid;
                $act['resourcetype'] = 'video/vdocipher';
                // Client GETs this right before playback to obtain a short-lived,
                // watermarked OTP + playbackInfo.
                $act['otpurl']       = $wwwroot . '/local/vdocipher/api.php?function=get_playback&cmid='
                                       . $cm->id . '&token=' . $wstoken;
            } else if ($vimrow = $DB->get_record('local_vimeo_videos', ['cmid' => $cm->id])) {
                // A Vimeo video also takes precedence over any uploaded file: the
                // app plays the domain-private embed, never a webview.
                $act['mediatype']    = 'vimeo';
                $act['isvimeo']      = true;
                $act['videoid']      = $vimrow->videoid;
                $act['resourcetype'] = 'video/vimeo';
                // Client GETs this right before playback to obtain the embed URL.
                $act['embedurl']     = $wwwroot . '/local/vimeo/api.php?function=get_playback&cmid='
                                       . $cm->id . '&token=' . $wstoken;
            } else {
                $fs   = get_file_storage();
                $ctx  = context_module::instance($cm->id);
                $files = $fs->get_area_files($ctx->id, 'mod_resource2', 'content', false, 'sortorder DESC, id ASC', false);
                if ($files) {
                    $file = reset($files);
                    $mime = $file->get_mimetype();
                    $act['resourcetype'] = $mime;
                    $act['mediatype']    = mt_media_type($mime);
                    $act['fileurl']      = mt_ws_fileurl(\moodle_url::make_pluginfile_url(
                        $ctx->id, 'mod_resource2', 'content', $file->get_itemid(),
                        $file->get_filepath(), $file->get_filename()
                    ), $wstoken);
                }
            }
        }

        // ── vdocipher: secure DRM video activity (mod_vdocipher) ────────────
        if ($cm->modname === 'vdocipher') {
            $vrow = $DB->get_record('local_vdocipher_videos', ['cmid' => $cm->id]);
            if ($vrow && $vrow->videoid !== '') {
                $act['mediatype']    = 'vdocipher';
                $act['isvdocipher']  = true;
                $act['videoid']      = $vrow->videoid;
                $act['resourcetype'] = 'video/vdocipher';
                $act['otpurl']       = $wwwroot . '/local/vdocipher/api.php?function=get_playback&cmid='
                                       . $cm->id . '&token=' . $wstoken;
            }
        }

        // ── vimeo: Vimeo-backed video activity (mod_vimeo) ──────────────────
        if ($cm->modname === 'vimeo') {
            $vimrow = $DB->get_record('local_vimeo_videos', ['cmid' => $cm->id]);
            if ($vimrow && $vimrow->videoid !== '') {
                $act['mediatype']    = 'vimeo';
                $act['isvimeo']      = true;
                $act['videoid']      = $vimrow->videoid;
                $act['resourcetype'] = 'video/vimeo';
                $act['embedurl']     = $wwwroot . '/local/vimeo/api.php?function=get_playback&cmid='
                                       . $cm->id . '&token=' . $wstoken;
            }
        }

        // ── testnew: custom single-PDF activity, get file URL and type ──────
        if ($cm->modname === 'testnew') {
            $fs   = get_file_storage();
            $ctx  = context_module::instance($cm->id);
            $files = $fs->get_area_files($ctx->id, 'mod_testnew', 'content', 0, 'sortorder DESC, id ASC', false);
            if ($files) {
                $file = reset($files);
                $mime = $file->get_mimetype();
                $act['resourcetype'] = $mime;
                $act['mediatype']    = mt_media_type($mime);
                $act['fileurl']      = mt_ws_fileurl(\moodle_url::make_pluginfile_url(
                    $ctx->id, 'mod_testnew', 'content', $file->get_itemid(),
                    $file->get_filepath(), $file->get_filename()
                ), $wstoken);
            }
        }

        // ── Assign: description, dates, submission type/status instead of webview ──
        if ($cm->modname === 'assign') {
            $assignrec = $DB->get_record('assign', ['id' => $cm->instance]);
            if ($assignrec) {
                $assign_ctx = context_module::instance($cm->id);
                $intro = file_rewrite_pluginfile_urls($assignrec->intro, 'pluginfile.php',
                    $assign_ctx->id, 'mod_assign', 'intro', null);
                $act['intro']                    = format_text($intro, $assignrec->introformat,
                    ['context' => $assign_ctx]);
                $act['allowsubmissionsfromdate']  = (int)$assignrec->allowsubmissionsfromdate;
                $act['duedate']                   = (int)$assignrec->duedate;
                $act['cutoffdate']                = (int)$assignrec->cutoffdate;
                $act['grade']                     = (int)$assignrec->grade;

                // Material/resources attached to the assignment (e.g. instructions PDF)
                // — stored separately from the intro text in the introattachment filearea.
                $fs = get_file_storage();
                $intro_files = $fs->get_area_files($assign_ctx->id, 'mod_assign', 'introattachment',
                    0, 'sortorder DESC, id ASC', false);
                $materials = [];
                foreach ($intro_files as $file) {
                    $materials[] = [
                        'filename'     => $file->get_filename(),
                        'filesize'     => (int)$file->get_filesize(),
                        'mimetype'     => $file->get_mimetype(),
                        'fileurl'      => mt_ws_fileurl(\moodle_url::make_pluginfile_url(
                            $assign_ctx->id, 'mod_assign', 'introattachment', $file->get_itemid(),
                            $file->get_filepath(), $file->get_filename()
                        ), $wstoken),
                    ];
                }
                $act['introattachments'] = $materials;

                // Enabled submission types (file / onlinetext / etc.) — what "type" this
                // assignment accepts, since modname alone is always just "assign".
                $plugin_rows = $DB->get_records_select('assign_plugin_config',
                    "assignment = ? AND subtype = ? AND name = ? AND value = ?",
                    [$assignrec->id, 'assignsubmission', 'enabled', '1']);
                $submission_types = [];
                foreach ($plugin_rows as $row) {
                    $submission_types[] = $row->plugin;
                }
                $act['submissiontypes'] = $submission_types;

                // Current user's submission status/grade.
                $submission = $DB->get_record('assign_submission',
                    ['assignment' => $assignrec->id, 'userid' => $USER->id, 'latest' => 1]);
                $grade = $DB->get_record('assign_grades',
                    ['assignment' => $assignrec->id, 'userid' => $USER->id]);
                $act['submissionstatus'] = [
                    'status'       => $submission->status ?? 'new',
                    'timemodified' => (int)($submission->timemodified ?? 0),
                    'grade'        => $grade ? (float)$grade->grade : null,
                ];

                // Assignment-specific open/closed window (from/due/cutoff dates),
                // independent of the general Moodle access restriction above.
                $time_now    = time();
                $is_open     = true;
                $window_info = 'open';
                if (!empty($assignrec->allowsubmissionsfromdate) && $assignrec->allowsubmissionsfromdate > $time_now) {
                    $is_open     = false;
                    $window_info = 'not_open_yet';
                } elseif (!empty($assignrec->cutoffdate) && $assignrec->cutoffdate < $time_now) {
                    $is_open     = false;
                    $window_info = 'closed';
                } elseif (!empty($assignrec->duedate) && $assignrec->duedate < $time_now) {
                    $window_info = 'overdue';
                }
                $act['submissionwindow'] = [
                    'isopen' => $is_open,
                    'status' => $window_info,
                ];
            }
        }

        // ── Quiz: tell the app HOW to open it (native vs webview) ───────────
        // The native quiz UI can only render some question types; a quiz holding
        // anything else must open in the webview or the student gets stuck on a
        // question the app cannot display. We do NOT decide that here — we state
        // the facts (which qtypes the quiz contains) and let the app compare them
        // against its own installed supported set. See mt_quiz_requires().
        if ($cm->modname === 'quiz') {
            $act['nativerender'] = [
                'requires'     => mt_quiz_requires($cm),
                'forcewebview' => false,
                'fallbackurl'  => $wwwroot . '/mod/quiz/view.php?id=' . $cm->id,
            ];
        }

        // ── URL module ───────────────────────────────────────────────────────
        if ($cm->modname === 'url') {
            $urlrec = $DB->get_record('url', ['id' => $cm->instance], 'externalurl');
            if ($urlrec) {
                $act['fileurl'] = $urlrec->externalurl;
            }
        }

        // ── customcert: stream the certificate PDF via a token endpoint so the
        //    app opens it natively instead of the mod/customcert webview. ───────
        if ($cm->modname === 'customcert') {
            $act['iscertificate'] = true;             // the certificate flag the app checks
            $act['mediatype']     = 'pdf';            // still a PDF, so it renders as one
            $act['resourcetype']  = 'application/pdf';
            // Explicit download URL (same protected PDF stream) for a "Download
            // certificate" action.
            $act['downloadurl']   = $wwwroot . '/local/academy/certificate.php?cmid='
                . $cm->id . '&token=' . $wstoken;
            $act['fileurl']       = $act['downloadurl'];
        }

        // Something the user may not open yet (restricted, locked by lesson order,
        // or not bought) must not carry a playable / downloadable URL.
        if ($act['restricted'] || $act['locked'] || $act['forsale']) {
            $act['fileurl']  = '';
            $act['otpurl']   = '';
            $act['embedurl'] = '';
            if (isset($act['downloadurl'])) {
                $act['downloadurl'] = '';
            }
            if (!empty($act['introattachments'])) {
                foreach ($act['introattachments'] as &$material) {
                    $material['fileurl'] = '';
                }
                unset($material);
            }
        }

        // A video lesson not bought yet may have free first minutes: the app plays
        // them via /local/nit_finance/api.php?function=get_lesson_preview.
        $act['previewseconds'] = 0;
        if ($act['forsale'] && !$act['restricted'] && !$act['locked']
                && class_exists('\local_nit_finance\local\preview')) {
            $act['previewseconds'] = \local_nit_finance\local\preview::for_user((int) $USER->id, $cm);
        }

        $result[] = $act;
    }
    return $result;
}

// ── 5. Build parents/topics structure ─────────────────────────────────────
$parents_map = [];   // sectionnum => parent entry
$topics_map  = [];   // sectionnum => topic entry (child of a parent)

$wwwroot = $CFG->wwwroot;

// Learner state per lesson (lock order, for sale, completion, watched %) — one walk.
$lessons = [];
foreach (\local_academy\player::walk($course)['lessons'] as $lesson) {
    $lessons[$lesson['cmid']] = $lesson;
}
$completion = new completion_info($course);

foreach ($sections as $snum => $sinfo) {
    if ($snum === 0) {
        continue; // Skip section 0 (general).
    }
    if (!$sinfo->uservisible) {
        continue;
    }

    $section_name = format_string($sinfo->name ?: get_section_name($course, $sinfo));
    $cms_in_sec   = $modinfo->sections[$snum] ?? [];
    $activities   = build_activities($cms_in_sec, $modinfo, $wstoken, $wwwroot, $DB, $USER, $lessons, $completion);

    $parent_snum = $section_parent[$snum] ?? 0;

    if ($parent_snum === 0) {
        // Top-level section.
        $parents_map[$snum] = [
            'id'         => (string)$sinfo->id,
            'sectionnum' => $snum,
            'name'       => $section_name,
            'parent'     => true,
            'activities' => $activities,
            'topics'     => [],
        ];
    } else {
        // Child section — belongs under $parent_snum.
        $topics_map[$snum] = [
            'parent_snum' => $parent_snum,
            'entry'       => [
                'id'         => (string)$sinfo->id,
                'sectionnum' => $snum,
                'name'       => $section_name,
                'activities' => $activities,
            ],
        ];
    }
}

// Attach topics to their parents.
foreach ($topics_map as $snum => $tdata) {
    $psnum = $tdata['parent_snum'];
    if (isset($parents_map[$psnum])) {
        $parents_map[$psnum]['topics'][] = $tdata['entry'];
    } else {
        // Parent not found — promote to top-level.
        $parents_map[$snum] = array_merge($tdata['entry'], ['parent' => true, 'topics' => []]);
    }
}

// ── 6. Build response ──────────────────────────────────────────────────────
$response = [
    'courseid'     => (int)$course->id,
    'fullname'     => format_string($course->fullname),
    'shortname'    => $course->shortname,
    'format'       => $course->format,
    'isavailable'  => $course_available,
    'status'       => $course_status,
    'other_fields' => $other_fields,
    'parents'      => array_values($parents_map),
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
