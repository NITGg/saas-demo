<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * View page for mod_jitsi.
 *
 * Handles session access restrictions, Jitsi Meet embed (with user identity and
 * language), collaborative whiteboard tab, and post-session recording display.
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

// Use cm_info so Moodle availability conditions (date restrictions etc.) are loaded.
$modinfo = get_fast_modinfo(get_course(
    $DB->get_field('course_modules', 'course', ['id' => $id], MUST_EXIST)
));
$cm      = $modinfo->get_cm($id);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jitsi   = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jitsi:view', $context);

// Live sessions (Jitsi) are a Professional-tier feature. If the academy's licence
// doesn't include it, show an upgrade notice instead of the room.
if (!jitsi_feature_enabled()) {
    $PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
    $PAGE->set_context($context);
    $PAGE->set_title(format_string($jitsi->name));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification(get_string('featurelocked', 'jitsi'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// ── Moodle availability / date restriction check ──────────────────────────
// If the activity has "available until" in the past, block access for students.
$is_teacher = has_capability('mod/jitsi:moderate', $context);
if (!$is_teacher && !$cm->available) {
    $PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
    $PAGE->set_context($context);
    $PAGE->set_title(format_string($jitsi->name));
    $PAGE->set_heading(format_string($course->fullname));
    $session_for_locked = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification(
        $cm->availableinfo ?: get_string('sessionended', 'jitsi'),
        'warning'
    );
    jitsi_print_recordings($session_for_locked ?: null, $context, $is_teacher, $cm->id);
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));

// -------------------------------------------------------------------------
// Access control – check linked academy session (if any).
// -------------------------------------------------------------------------
// $is_teacher already set above.

// Standalone ended check (no linked session — teacher ended via AJAX).
if (get_config('mod_jitsi', 'ended_' . $cm->id)) {
    $session_for_ended = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
    jitsi_print_recordings($session_for_ended ?: null, $context, $is_teacher, $cm->id);
    echo $OUTPUT->footer();
    exit;
}

$session    = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);

if ($session) {

    // ── Restrict a linked lesson/academy room to its related participants ──────
    // Only the session's assigned teacher and the whitelisted students may enter.
    // $is_teacher above is the course-wide mod/jitsi:moderate capability, but every
    // lesson room lives in one shared lessons course (and create_for_lesson() enrols
    // each teacher there as editingteacher), so without this gate any other teacher
    // or manager could open someone else's room as a moderator. Pin access — and
    // moderator status — to the people actually related to this session.
    $is_session_teacher     = ((int)$session->teacherid === (int)$USER->id);
    $is_whitelisted_student = $DB->record_exists('academy_session_students', [
        'sessionid' => $session->id,
        'userid'    => $USER->id,
    ]);

    if (!$is_session_teacher && !$is_whitelisted_student && !is_siteadmin()) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($jitsi->name));
        echo $OUTPUT->notification(get_string('notallowed', 'jitsi'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }

    // Moderator is the assigned teacher only (site admins keep moderator for support).
    $is_teacher = $is_session_teacher || (is_siteadmin() && !$is_whitelisted_student);

    // For everyone (teachers included): once the session is marked 'ended',
    // the room is closed — refreshing should not allow rejoining.
    if ($session->status === 'ended') {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($jitsi->name));
        echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
        jitsi_print_recordings($session, $context, $is_teacher);
        echo $OUTPUT->footer();
        exit;
    }

    if (!$is_teacher) {
        // 1. Student must be on the allowed list.
        $allowed = $DB->record_exists('academy_session_students', [
            'sessionid' => $session->id,
            'userid'    => $USER->id,
        ]);
        if (!$allowed) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(get_string('notallowed', 'jitsi'), 'warning');
            echo $OUTPUT->footer();
            exit;
        }

        $now       = time();
        $open_from = $session->start_time - 1800;
        $open_until = $session->start_time + ($session->duration * 60);

        // 2. Too early.
        if ($now < $open_from) {
            $mins = ceil(($open_from - $now) / 60);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(
                get_string('sessionopening', 'jitsi', $mins),
                'info'
            );
            echo $OUTPUT->footer();
            exit;
        }

        // 3. Time window passed (but status not yet 'ended' — lifecycle cron hasn't run).
        if ($now > $open_until) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
            jitsi_print_recordings($session, $context, $is_teacher);
            echo $OUTPUT->footer();
            exit;
        }

        // 4. Hold the student out of the room until the teacher is actually in the call.
        //    teacher_joined_at is stamped by teacher_present.php on the teacher's
        //    videoConferenceJoined event (and cleared when they leave). The waiting page
        //    auto-reloads, so the student drops into the room the moment the teacher arrives.
        if (empty($session->teacher_joined_at)) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo html_writer::tag('div',
                $OUTPUT->notification(get_string('waitingforteacher', 'jitsi'), 'info'),
                ['id' => 'jitsi-waiting']);
            echo html_writer::script('setTimeout(function(){ location.reload(); }, 5000);');
            echo $OUTPUT->footer();
            exit;
        }

        // 5. Record attendance (first join only).
        if (!$DB->record_exists('academy_session_attendance', ['sessionid' => $session->id, 'userid' => $USER->id])) {
            $att                   = new stdClass();
            $att->sessionid        = $session->id;
            $att->userid           = $USER->id;
            $att->joined_at        = $now;
            $att->duration_seconds = 0;
            $DB->insert_record('academy_session_attendance', $att);
        }

        // Audit timeline: record when the student entered the meeting room (distinct from the
        // teacher's start/join). record_once so a reload/rejoin adds no duplicate rows.
        if (!$is_teacher && class_exists('\local_academy\audit_manager')
                && $DB->get_manager()->table_exists('academy_lessons')) {
            $lessonid = $DB->get_field('academy_lessons', 'id', ['sessionid' => $session->id]);
            if ($lessonid) {
                \local_academy\audit_manager::record_once($lessonid, 'student_joined', $USER->id, 'student');
            }
        }
    }
}

// Completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// -------------------------------------------------------------------------
// Build Jitsi configuration.
// -------------------------------------------------------------------------
$jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';

// Unique, stable room name per activity instance — SITE-SALTED so tenants sharing
// the one Jitsi server never collide (see jitsi_room_name()).
$jitsi_room = jitsi_room_name($jitsi, $cm);

$display_name = fullname($USER);
$user_email   = $USER->email;

// JWT token — tells Jitsi who is moderator.
$jitsi_jwt = \local_academysessions\jitsi_jwt::generate(
    $jitsi_room, $display_name, $user_email, $is_teacher
);

// Jibri recording is not wired in this phase (recording moves to VdoCipher later),
// so the JS record hooks are disabled — empty URLs make the guards below skip them.
$jibri_auto_record_url = '';
$jibri_auto_record_cmid = $cm->id;
$jibri_auto_record_token = sesskey();

// Map Moodle lang codes to Jitsi / i18n codes.
$lang_map = [
    'ar'    => 'ar',
    'en'    => 'en',
    'fr'    => 'fr',
    'de'    => 'de',
    'es'    => 'es',
    'pt'    => 'pt',
    'tr'    => 'tr',
    'ru'    => 'ru',
    'zh_cn' => 'zh',
    'ja'    => 'ja',
];
$moodle_lang  = current_language();
$jitsi_lang   = $lang_map[$moodle_lang] ?? 'en';

// Teacher: full control panel.
$toolbar_teacher = [
    'microphone', 'camera', 'desktop',
    'chat',
    'invite',
    'raisehand', 'participants-pane', 'mute-everyone',
    'whiteboard', 'etherpad',
    'select-background', 'noisesuppression',
    'tileview', 'filmstrip', 'videoquality', 'stats',
    'security', 'closedcaptions', 'shortcuts',
    'fullscreen', 'hangup',
];
// Students: useful personal controls only.
$toolbar_student = [
    'microphone', 'camera', 'desktop',
    'chat', 'raisehand', 'whiteboard',
    'select-background', 'noisesuppression',
    'tileview', 'videoquality',
    'fullscreen', 'hangup',
];
$toolbar         = $is_teacher ? $toolbar_teacher : $toolbar_student;

// Jitsi is now on HTTPS (mkcert cert, port 8443 → container 443).
// External API requires HTTPS — this is now satisfied.
$jitsi_scheme = 'https';

// Whiteboard — self-hosted Excalidraw frontend (port 9091).
// Port 9090 is the Socket.IO relay only; the drawable UI is on 9091.
$excalidraw_app = get_config('local_academysessions', 'excalidraw_app')
    ?: 'https://academy2026.nitg-eg.com/whiteboard';
$wb_room = 'academy_wb_jitsi_' . $cm->id;
$wb_url  = rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room);

// Session token for AJAX end-session call.
$sesskey      = sesskey();
$session_id   = $session ? (int)$session->id : 0;
$end_ajax_url = (new moodle_url('/mod/jitsi/ajax.php'))->out(false);

// -------------------------------------------------------------------------
// Output.
// -------------------------------------------------------------------------
echo $OUTPUT->header();

// Load Jitsi External API as a static <script> tag.
echo '<script src="' . s($jitsi_scheme . '://' . $jitsi_host . '/external_api.js') . '"></script>' . "\n";

// NOTE: no explicit $OUTPUT->heading() here — the theme's activity header
// (full_header) already renders the activity name; echoing it again showed the
// title twice ("jitsi test / jitsi test").

if (!empty($jitsi->intro)) {
    echo $OUTPUT->box(format_module_intro('jitsi', $jitsi, $cm->id), 'generalbox mod_introbox');
}

echo '<div id="jitsi-activity-container" style="margin:15px 0;">';

// ── Tab bar ──────────────────────────────────────────────────────────────
echo '<div id="jitsi-tabs" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">';
echo '<button id="tab-video" onclick="jitsiSwitchTab(\'video\')" class="btn btn-primary btn-sm" style="border-radius:16px;">'
     . get_string('tab_video', 'jitsi') . '</button>';
echo '<button id="tab-whiteboard" onclick="jitsiSwitchTab(\'whiteboard\')" class="btn btn-outline-secondary btn-sm" style="border-radius:16px;">'
     . get_string('tab_whiteboard', 'jitsi') . '</button>';
if ($is_teacher) {
    echo '<span class="badge badge-info ml-auto" style="align-self:center;font-size:12px;">'
         . get_string('youarehost', 'jitsi') . '</span>';
    // Recording (Jibri) is not wired in this phase — no Stop Recording button.
}
echo '</div>';

// ── Jitsi External API container ─────────────────────────────────────────
echo '<div id="panel-video" style="width:100%;height:620px;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.15);">';
echo '<div id="jitsi-container" style="width:100%;height:100%;"></div>';
echo '</div>';

// ── Whiteboard panel ─────────────────────────────────────────────────────
echo '<div id="panel-whiteboard" style="display:none;width:100%;height:620px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">';
echo '<iframe src="' . s($wb_url) . '" style="width:100%;height:100%;border:none;" allow="clipboard-read;clipboard-write"></iframe>';
echo '</div>';

// ── Post-call panel (hidden until call ends) ──────────────────────────────
echo '<div id="panel-ended" style="display:none;padding:30px;text-align:center;background:#f8f9fa;border-radius:8px;">';
echo '<h4 style="color:#495057;">' . get_string('sessionended', 'jitsi') . '</h4>';
echo '<p class="text-muted" id="ended-sub"></p>';
echo '<div id="ended-recordings"></div>';
echo '</div>';

echo '</div>'; // #jitsi-activity-container

// Security settings stored on the activity.
$room_password  = !empty($jitsi->roompassword)  ? $jitsi->roompassword  : '';
$lobby_enabled  = !empty($jitsi->lobby_enabled);

$js_config = json_encode([
    'isTeacher'       => (bool)$is_teacher,
    'cmId'            => (int)$cm->id,
    'sessionId'       => $session_id,
    'sesskey'         => $sesskey,
    'endUrl'          => $end_ajax_url,
    'jitsiHost'       => $jitsi_host,
    'jitsiRoom'       => $jitsi_room,
    'jitsiName'       => $jitsi->name,
    'jitsiLang'       => $jitsi_lang,
    'displayName'     => $display_name,
    'userEmail'       => $user_email,
    'toolbarButtons'  => $toolbar,
    'jwt'             => $jitsi_jwt,
    'roomPassword'    => $room_password,
    'lobbyEnabled'    => $lobby_enabled,
    'teacherPresentUrl' => $CFG->wwwroot . '/mod/jitsi/teacher_present.php',
    'autoRecordUrl'     => $jibri_auto_record_url,
    'autoRecordStopUrl' => '',
    'autoRecordCmid'    => (int)$jibri_auto_record_cmid,
    'autoRecordToken'   => $jibri_auto_record_token,
]);

echo <<<HTML
<script>
(function() {
    var CFG = {$js_config};
    var api = null;

    function initJitsiAPI() {
        if (typeof JitsiMeetExternalAPI === 'undefined') {
            document.getElementById('jitsi-container').innerHTML =
                '<div style="padding:40px;text-align:center;color:#dc3545;">'
                + '<strong>Could not load Jitsi API.</strong><br>'
                + 'Your browser may be blocking <code>https://' + CFG.jitsiHost + '/external_api.js</code> '
                + 'due to an untrusted SSL certificate.<br>'
                + 'Please open <a href="https://' + CFG.jitsiHost + '" target="_blank">https://' + CFG.jitsiHost + '</a> '
                + 'in a new tab, accept the certificate warning, then refresh this page.'
                + '</div>';
            return;
        }
        api = new JitsiMeetExternalAPI(CFG.jitsiHost, {
            roomName: CFG.jitsiRoom,
            jwt: CFG.jwt,
            parentNode: document.getElementById('jitsi-container'),
            width: '100%',
            height: '100%',
            configOverwrite: {
                startWithAudioMuted: true,
                startWithVideoMuted: true,
                prejoinPageEnabled: false,
                disableDeepLinking: true,
                disableInviteFunctions: !CFG.isTeacher,
                enableClosePage: false,
                subject: CFG.jitsiName,
                defaultLanguage: CFG.jitsiLang,
                toolbarButtons: CFG.toolbarButtons,
                fileRecordingsEnabled: CFG.isTeacher,
                localRecording: { enabled: false },
                liveStreamingEnabled: true,
                hiddenPremeetingButtons: [],
                disableProfile: false,
                enableNoisyMicDetection: true,
                enableNoAudioDetection: true,
                channelLastN: -1,
                startWithAudioMuted: true,
                startWithVideoMuted: false,
                disableSelfViewSettings: false,
                disableRemoteMute: !CFG.isTeacher,
                remoteVideoMenu: { disabled: false, disableKick: !CFG.isTeacher, disableGrantModerator: !CFG.isTeacher },
                breakoutRooms: { hideAddRoomButton: !CFG.isTeacher, hideAutoAssignButton: !CFG.isTeacher, hideJoinRoomButton: false },
                participantsPane: { hideModeratorSettingsTab: !CFG.isTeacher, hideMoreActionsButton: false, hideMuteAllButton: !CFG.isTeacher },
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                SHOW_POWERED_BY: false,
                DISPLAY_WELCOME_FOOTER: false,
                HIDE_INVITE_MORE_HEADER: !CFG.isTeacher,
                TOOLBAR_ALWAYS_VISIBLE: false,
                ENFORCE_NOTIFICATION_AUTO_DISMISS_TIMEOUT: 5000,
            },
            userInfo: {
                displayName: CFG.displayName,
                email: CFG.userEmail
            }
        });
        // readyToClose fires when teacher clicks "End meeting for all" — ended=true marks the session.
        api.addEventListener('readyToClose', function() { onSessionLeft(true); });
        // videoConferenceLeft fires on plain leave — teacher leaving without ending doesn't mark session.
        api.addEventListener('videoConferenceLeft', function() { onSessionLeft(false); });

        // Auto-submit password for everyone so no one gets a prompt
        // (Moodle already gates who can reach this page).
        if (CFG.roomPassword) {
            api.addEventListener('passwordRequired', function() {
                api.executeCommand('password', CFG.roomPassword);
            });
        }

        // Teacher: set password on first join and enable lobby if configured.
        // JWT moderator role automatically bypasses the lobby on rejoin.
        // Tell the backend whether the teacher is currently in the call, so the student
        // entry gate in view.php only opens while the teacher is present.
        function setTeacherPresent(present) {
            fetch(CFG.teacherPresentUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cmid=' + CFG.autoRecordCmid + '&sesskey=' + CFG.autoRecordToken + '&present=' + (present ? 1 : 0),
                keepalive: true
            }).catch(function() {});
        }

        if (CFG.isTeacher) {
            api.addEventListener('videoConferenceJoined', function() {
                setTeacherPresent(true);
                if (CFG.roomPassword) {
                    api.executeCommand('password', CFG.roomPassword);
                }
                if (CFG.lobbyEnabled) {
                    api.executeCommand('toggleLobby', true);
                }
                // Jibri auto-recording is disabled in this phase (CFG.autoRecordUrl
                // is empty); teacher presence is still tracked above for student gating.
                if (CFG.autoRecordUrl) {
                    fetch(CFG.autoRecordUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'cmid=' + CFG.autoRecordCmid + '&sesskey=' + CFG.autoRecordToken
                    }).catch(function() {});
                }
            });

            // Re-gate students when the teacher leaves or ends the meeting (and stop
            // Jibri if it was ever enabled).
            function stopJibriRecording() {
                setTeacherPresent(false);
                if (CFG.autoRecordStopUrl) {
                    fetch(CFG.autoRecordStopUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'cmid=' + CFG.autoRecordCmid + '&sesskey=' + CFG.autoRecordToken
                    }).catch(function() {});
                }
            }
            api.addEventListener('videoConferenceLeft', stopJibriRecording);
            api.addEventListener('readyToClose', stopJibriRecording);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJitsiAPI);
    } else {
        initJitsiAPI();
    }

    // Called by the Stop Recording button in the Moodle tab bar.
    window.jitsiStopRecording = function() {
        var btn = document.getElementById('btn-stop-rec');
        if (btn) { btn.disabled = true; btn.textContent = 'Stopping…'; }
        fetch(CFG.autoRecordStopUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'cmid=' + CFG.autoRecordCmid + '&sesskey=' + CFG.autoRecordToken
        }).then(function() {
            if (btn) btn.style.display = 'none';
        }).catch(function() {
            if (btn) { btn.disabled = false; btn.textContent = '⏹ Stop Recording'; }
        });
    };

    var _sessionEndCalled = false;

    function onSessionLeft(teacherEndedForAll) {
        if (api) { try { api.dispose(); } catch(x) {} api = null; }

        document.getElementById('jitsi-tabs').style.display = 'none';
        document.getElementById('panel-video').style.display = 'none';
        document.getElementById('panel-whiteboard').style.display = 'none';
        document.getElementById('panel-ended').style.display = 'block';

        if (teacherEndedForAll && CFG.isTeacher && !_sessionEndCalled) {
            _sessionEndCalled = true;
            document.getElementById('ended-sub').textContent = 'Ending session…';

            // Determine which AJAX function to call:
            // end_session  — if there is a linked academy session
            // end_room     — standalone activity (no linked session)
            var body = CFG.sessionId
                ? 'function=end_session&sesskey=' + encodeURIComponent(CFG.sesskey) + '&sessionid=' + CFG.sessionId
                : 'function=end_room&sesskey=' + encodeURIComponent(CFG.sesskey) + '&cmid=' + CFG.cmId;

            fetch(CFG.endUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('ended-sub').textContent =
                    data.status === 'success'
                        ? 'Session ended. Recordings will appear below once processed.'
                        : 'Session could not be marked ended: ' + (data.error || '');
                loadRecordingsAjax();
            }).catch(function() {
                document.getElementById('ended-sub').textContent =
                    'Session ended (could not update server status).';
                loadRecordingsAjax();
            });
        } else if (teacherEndedForAll && !CFG.isTeacher) {
            // Student: teacher ended the meeting for all.
            document.getElementById('ended-sub').textContent =
                'The host has ended this session. Recordings will appear below once processed.';
            loadRecordingsAjax();
        } else {
            document.getElementById('ended-sub').textContent =
                'You left the session. Recordings will appear below once processed.';
            loadRecordingsAjax();
        }
    }

    function loadRecordingsAjax() {
        if (!CFG.sessionId) return;
        var recParams = CFG.sessionId
            ? 'sessionid=' + CFG.sessionId + '&cmid=' + CFG.cmId
            : 'cmid=' + CFG.cmId;
        fetch(CFG.endUrl + '?function=get_session_recordings&' + recParams
              + '&sesskey=' + encodeURIComponent(CFG.sesskey))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                var box = document.getElementById('ended-recordings');
                if (!data.data || data.data.length === 0) {
                    box.innerHTML = '<p class="text-muted mt-3">No recordings available yet.</p>';
                    return;
                }
                var html = '<h5 class="mt-4">Session Recordings</h5>';
                data.data.forEach(function(rec) {
                    html += '<div class="card mb-3" style="border-radius:8px;text-align:left;">';
                    html += '<div class="card-body">';
                    html += '<h6>' + (rec.title || 'Recording') + '</h6>';
                    if (rec.embed_url) {
                        html += '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">';
                        html += '<iframe src="' + rec.embed_url + '" loading="lazy"'
                            + ' style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"'
                            + ' allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"'
                            + ' allowfullscreen></iframe></div>';
                    } else {
                        html += '<p class="text-muted">Recording is being processed.</p>';
                    }
                    if (rec.storage && CFG.isTeacher) {
                        html += '<small class="text-muted">Storage: ' + rec.storage + '</small>';
                    }
                    html += '</div></div>';
                });
                box.innerHTML = html;
            })
            .catch(function() {
                document.getElementById('ended-recordings').innerHTML =
                    '<p class="text-muted">Could not load recordings right now.</p>';
            });
    }

    /* ── tab switcher ───────────────────────────────────────────────── */
    window.jitsiSwitchTab = function(tab) {
        var vp = document.getElementById('panel-video');
        var wp = document.getElementById('panel-whiteboard');
        var tv = document.getElementById('tab-video');
        var tw = document.getElementById('tab-whiteboard');
        if (tab === 'video') {
            vp.style.display = 'block'; wp.style.display = 'none';
            tv.className = 'btn btn-primary btn-sm'; tv.style.borderRadius = '16px';
            tw.className = 'btn btn-outline-secondary btn-sm'; tw.style.borderRadius = '16px';
        } else {
            vp.style.display = 'none'; wp.style.display = 'block';
            tv.className = 'btn btn-outline-secondary btn-sm'; tv.style.borderRadius = '16px';
            tw.className = 'btn btn-primary btn-sm'; tw.style.borderRadius = '16px';
        }
    };
})();
</script>
HTML;

// Show recordings below the conference:
// - If there's a linked session: teacher always sees them; students see after session window ends.
// - If standalone (no session): always show if any recordings exist.
if ($session) {
    $show_recs = $is_teacher;
    if (!$is_teacher) {
        $now        = time();
        $open_until = $session->start_time + ($session->duration * 60);
        $show_recs  = ($now > $open_until);
    }
    if ($show_recs) {
        jitsi_print_recordings($session, $context, $is_teacher, $cm->id);
    }
} else {
    // Standalone room — show recordings if any exist for this cmid.
    $has_recs = $DB->record_exists('academy_session_recordings', ['cmid' => $cm->id]);
    if ($has_recs) {
        jitsi_print_recordings(null, $context, $is_teacher, $cm->id);
    }
}

echo $OUTPUT->footer();

// -------------------------------------------------------------------------
// Helper: render this activity's recordings, played from VdoCipher.
// -------------------------------------------------------------------------
function jitsi_print_recordings($session, $context, $is_teacher, $cmid = null) {
    global $DB, $USER, $cm;

    $cmid = $cmid ?: ($cm->id ?? 0);
    if (!$cmid) {
        return;
    }
    // Recordings for this activity (linked by cmid) OR by the session, that have a
    // VdoCipher video id. Newest first.
    $params = ['cmid' => $cmid];
    $where  = 'cmid = :cmid';
    if ($session) {
        $where .= ' OR sessionid = :sid';
        $params['sid'] = $session->id;
    }
    $rows = $DB->get_records_select('academy_session_recordings',
        "($where) AND vdocipher_videoid IS NOT NULL AND vdocipher_videoid <> ''",
        $params, 'timecreated DESC');

    echo '<div class="jitsi-recordings" style="margin-top:24px;">';
    echo $OUTPUT->heading(get_string('recordings', 'jitsi'), 4);

    if (!$rows) {
        echo '<p class="text-muted">' . get_string('norecordings', 'jitsi') . '</p></div>';
        return;
    }

    $canplay = class_exists('\local_vdocipher\playback_service');
    foreach ($rows as $rec) {
        $title = format_string($rec->title ?: get_string('recording', 'jitsi'));
        echo '<div class="jitsi-recording" style="margin:0 0 20px;">';
        echo '<div style="font-weight:600;margin:0 0 6px;">' . $title . '</div>';

        $embedded = false;
        if ($canplay) {
            try {
                $data = \local_vdocipher\playback_service::mint($rec->vdocipher_videoid, $USER);
                if (!empty($data['otp']) && !empty($data['playbackInfo'])) {
                    $src = 'https://player.vdocipher.com/v2/?otp=' . rawurlencode($data['otp'])
                        . '&playbackInfo=' . rawurlencode($data['playbackInfo']);
                    echo '<div style="position:relative;padding-top:56.25%;border-radius:8px;overflow:hidden;">'
                        . '<iframe src="' . s($src) . '" style="position:absolute;inset:0;width:100%;height:100%;border:0;" '
                        . 'allow="encrypted-media" allowfullscreen></iframe></div>';
                    $embedded = true;
                }
            } catch (\Throwable $e) {
                $embedded = false;
            }
        }
        if (!$embedded) {
            // Still transcoding (VdoCipher not ready) or playback unavailable.
            echo '<p class="text-muted">' . get_string('recordingprocessing', 'jitsi') . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
}
