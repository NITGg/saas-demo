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
 * language, and Jitsi's whiteboard driven from the room's dock), and post-session
 * recording display.
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

// ── Access: who may enter, and as moderator or not ─────────────────────────
// One rule for the web page and the mobile APIs: \mod_jitsi\local\access. A linked
// lesson/academy room is only for its teacher and invited students; the moderator is
// the session's teacher (site admins too, for support); students wait outside until
// the teacher is in the call.
$decision   = \mod_jitsi\local\access::check($cm, (int) $USER->id);
$is_teacher = $decision->moderator;
$session    = $decision->session;

$PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));
// A lesson room is reached from its lesson card; the lessons course behind it (one room
// per lesson) is not a course to browse, so no course index of every lesson room.
if (jitsi_is_lesson_room($cm)) {
    $PAGE->set_show_course_index(false);
}

if ($decision->code === \mod_jitsi\local\access::NOT_ALLOWED) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification($decision->message, 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Opening the activity only shows the room; the call starts after "Join" (join=1), so
// someone browsing the course never lands in the meeting (nor starts its recording).
$wantsjoin = optional_param('join', 0, PARAM_BOOL);

// Closed for now (waiting for the teacher, not open yet, ended, unavailable): the page
// keeps its tabs (recordings...) and the video tab says why there is no call.
// A student waiting for the teacher still gets the "Join" step, like the teacher; the
// wait shows once they press it.
$closed = !$decision->allowed
    && !($decision->code === \mod_jitsi\local\access::WAITING && !$wantsjoin);
$joining = $decision->allowed && $wantsjoin;

// A student who joined: open their attendance row.
if ($joining) {
    \mod_jitsi\local\access::record_entry($decision, (int) $USER->id);
}

// Completion tracking.
if ($decision->allowed) {
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

// -------------------------------------------------------------------------
// Build Jitsi configuration.
// -------------------------------------------------------------------------
$jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';

// Unique, stable room name per activity instance — SITE-SALTED so tenants sharing
// the one Jitsi server never collide (see jitsi_room_name()).
$jitsi_room = jitsi_room_name($jitsi, $cm);

$display_name = fullname($USER);
$user_email   = $USER->email;

// JWT token — tells Jitsi who is moderator. Only for someone joining the call (the key
// is the way into the room: never for a student still waiting outside).
$jitsi_jwt = $joining ? \local_academysessions\jitsi_jwt::generate(
    $jitsi_room, $display_name, $user_email, $is_teacher
) : '';

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

// Jitsi's own toolbar is hidden: the room has its own controls in the site's style
// (templates/dock.mustache + dock.js, through the Jitsi IFrame API).
$toolbar = [];
// A student sees the teacher only (not the other students), in every room: no tile view,
// no filmstrip, the teacher pinned on the main video (see the script below).
$isolate_students = !$is_teacher;
$teacher_names   = jitsi_teacher_names($cm, $session);

// Jitsi is now on HTTPS (mkcert cert, port 8443 → container 443).
// External API requires HTTPS — this is now satisfied.
$jitsi_scheme = 'https';

// Session token for AJAX end-session call.
$sesskey      = sesskey();
$session_id   = $session ? (int)$session->id : 0;
$end_ajax_url = (new moodle_url('/mod/jitsi/ajax.php'))->out(false);

// Auto-start the recording when the host joins (Jibri records -> finalize.sh ->
// Vimeo). Per-academy toggle: theme_nit/jitsi_autorecord (default ON), so an
// academy can turn it off site-wide.
// Only the lesson's own teacher (a course session's teachers) starts it: a site admin
// dropping in must not.
$autorecord = (get_config('theme_nit', 'jitsi_autorecord') !== '0')
    && (!$session || \mod_jitsi\local\access::is_session_teacher($session, (int) $USER->id, $cm));

$show_recs = jitsi_show_recordings_tab($is_teacher, $session);

// -------------------------------------------------------------------------
// Output.
// -------------------------------------------------------------------------
echo $OUTPUT->header();

// Load Jitsi External API as a static <script> tag (only once the user joins).
if ($joining) {
    echo '<script src="' . s($jitsi_scheme . '://' . $jitsi_host . '/external_api.js') . '"></script>' . "\n";
    $dockjs = new moodle_url('/mod/jitsi/dock.js', ['v' => filemtime(__DIR__ . '/dock.js')]);
    echo '<script src="' . s($dockjs->out(false)) . '"></script>' . "\n";
}

// NOTE: no explicit $OUTPUT->heading() here — the theme's activity header
// (full_header) already renders the activity name; echoing it again showed the
// title twice ("jitsi test / jitsi test").

if (!empty($jitsi->intro)) {
    echo $OUTPUT->box(format_module_intro('jitsi', $jitsi, $cm->id), 'generalbox mod_introbox');
}

// A scheduled room: when its session is.
if ($session && \local_academysessions\session_manager::is_course_session($session)) {
    echo html_writer::div(html_writer::tag('strong', get_string('sessiontime', 'jitsi') . ': ')
        . jitsi_session_time_html($session), 'jitsi-sessiontime alert alert-info', ['style' => 'margin:10px 0;']);
}

echo '<div id="jitsi-activity-container" style="margin:15px 0;">';

// ── Tab bar ──────────────────────────────────────────────────────────────
echo '<div id="jitsi-tabs" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">';
echo '<button id="tab-video" onclick="jitsiSwitchTab(\'video\')" class="btn btn-primary btn-sm" style="border-radius:16px;">'
     . get_string('tab_video', 'jitsi') . '</button>';
if ($show_recs) {
    echo '<button id="tab-recordings" onclick="jitsiSwitchTab(\'recordings\')" class="btn btn-outline-secondary btn-sm" style="border-radius:16px;">'
         . get_string('recordings', 'jitsi') . '</button>';
}
if ($is_teacher) {
    echo '<button id="tab-attendance" onclick="jitsiSwitchTab(\'attendance\')" class="btn btn-outline-secondary btn-sm" style="border-radius:16px;">'
        . get_string('attendancelog', 'jitsi') . '</button>';
    echo '<span class="badge badge-info ml-auto" style="align-self:center;font-size:12px;">'
         . get_string('youarehost', 'jitsi') . '</span>';
}
echo '</div>';

// ── Jitsi External API container (or the "Join" step before it) ───────────
echo '<div id="panel-video"' . ($joining ? ' class="nitdock-panel"' : '')
    . ' style="position:relative;width:100%;height:' . ($joining ? '620px' : '360px')
    . ';border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.15);">';
if ($closed) {
    echo jitsi_closed_html($decision, $cm, $wantsjoin,
        \mod_jitsi\local\access::waiting_room($cm));
} else if (!$joining) {
    echo jitsi_prejoin_html($cm, $is_teacher && $autorecord);
}
echo '<div id="jitsi-container" style="width:100%;height:100%;' . ($joining ? '' : 'display:none;') . '"></div>';
if ($isolate_students) {
    // Covers the video while the teacher is not in the call, so a student never sees
    // another student on the main video (it would show whoever is speaking).
    echo '<div id="jitsi-teacher-away" style="display:none;position:absolute;inset:0;z-index:5;align-items:center;'
        . 'justify-content:center;text-align:center;padding:24px 24px 110px;background:#0e335d;color:#fff;font-size:18px;">'
        . get_string('teacheraway', 'jitsi') . '</div>';
}
if ($joining) {
    echo $OUTPUT->render_from_template('mod_jitsi/dock', jitsi_dock_context($is_teacher, $isolate_students));
}
echo '</div>';

// ── Recordings panel ─────────────────────────────────────────────────────
if ($show_recs) {
    echo '<div id="panel-recordings" style="display:none;">';
    jitsi_print_recordings($session, $context, $is_teacher, $cm->id, false);
    echo '</div>';
}

// ── Attendance log panel (the room's hosts) ──────────────────────────────
// attendance.php in the embedded layout (nitplayer=1), (re)loaded each time the tab opens,
// so changing the day or downloading the CSV happens inside it and never leaves the call.
if ($is_teacher) {
    $attendance_url = new moodle_url('/mod/jitsi/attendance.php', ['id' => $cm->id, 'nitplayer' => 1]);
    echo '<div id="panel-attendance" style="display:none;width:100%;height:620px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">';
    echo '<iframe data-reload="' . s($attendance_url->out(false)) . '" style="width:100%;height:100%;border:none;"></iframe>';
    echo '</div>';
}

// ── Post-call panel (hidden until call ends) ──────────────────────────────
echo '<div id="panel-ended" style="display:none;padding:30px;text-align:center;background:#f8f9fa;border-radius:8px;">';
echo '<h4 style="color:#495057;">' . get_string('sessionended', 'jitsi') . '</h4>';
echo '<p class="text-muted" id="ended-sub"></p>';
echo '<div id="ended-recordings"></div>';
echo '</div>';

echo '</div>'; // #jitsi-activity-container

// Security settings stored on the activity.
$room_password  = !empty($jitsi->roompassword)  ? $jitsi->roompassword  : '';
// Course room with "students wait for the teacher": a host turns the Jitsi lobby on when
// they join, students knock and the teacher lets them in from inside the meeting. Hosts'
// pages let other hosts out of the lobby at once. Lesson rooms: no lobby.
$lobby_enabled  = \mod_jitsi\local\access::waiting_room($cm);

$js_config = json_encode([
    'isTeacher'       => (bool)$is_teacher,
    'cmId'            => (int)$cm->id,
    'sessionId'       => $session_id,
    'sesskey'         => $sesskey,
    'endUrl'          => $end_ajax_url,
    'jitsiHost'       => $jitsi_host,
    'jitsiRoom'       => $jitsi_room,
    'jitsiName'       => format_string($jitsi->name, true, ['context' => $context]), // {mlang} etc. resolved
    'jitsiLang'       => $jitsi_lang,
    'displayName'     => $display_name,
    'userEmail'       => $user_email,
    'toolbarButtons'  => $toolbar,
    'jwt'             => $jitsi_jwt,
    'roomPassword'    => $room_password,
    'lobbyEnabled'    => $lobby_enabled,
    // A host's page lets these straight out of the lobby (other hosts never wait); a
    // student's knock is left to the teacher, who answers it inside the meeting.
    'autoAdmit'       => (bool) $is_teacher,
    'hostNames'       => \mod_jitsi\local\access::host_names($cm, $session ?: null),
    'isolateStudents' => (bool) $isolate_students,
    'awayText'        => get_string('teacheraway', 'jitsi'),
    'awayKnock'       => get_string('waitingadmit', 'jitsi'),
    'teacherNames'    => $teacher_names,
    'presenceUrl'     => (new moodle_url('/mod/jitsi/ajax.php'))->out(false),
    'cmid'              => (int)$cm->id,
    'teacherPresentUrl' => $CFG->wwwroot . '/mod/jitsi/teacher_present.php',
    'autoRecord'        => $autorecord,
    'str' => [
        'byTeacher' => get_string('ended_byteacher', 'jitsi'),
        'byHost' => get_string('ended_byhost', 'jitsi'),
        'left' => get_string('ended_left', 'jitsi'),
        'ending' => get_string('ended_ending', 'jitsi'),
        'failed' => get_string('ended_failed', 'jitsi'),
        'recNote' => get_string('ended_recnote', 'jitsi'),
        'recFailed' => get_string('ended_recfailed', 'jitsi'),
        'recordings' => get_string('recordings', 'jitsi'),
    ],
]);

// Tab switcher (also before joining: the recordings tab works without the call).
echo <<<HTML
<script>
window.jitsiSwitchTab = function(tab) {
    ['video', 'recordings', 'attendance'].forEach(function(name) {
        var panel = document.getElementById('panel-' + name);
        var button = document.getElementById('tab-' + name);
        if (!panel || !button) {
            return;
        }
        panel.style.display = name === tab ? 'block' : 'none';
        button.className = name === tab ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm';
        button.style.borderRadius = '16px';
    });
    // The attendance log: fresh each time it opens.
    var log = tab === 'attendance' && document.querySelector('#panel-attendance iframe');
    if (log) {
        log.src = log.getAttribute('data-reload');
    }
};
</script>
HTML;

// Waiting for the teacher: check again every few seconds, so the student goes on the
// moment the teacher arrives (into the call if they had pressed "Join"). Not while they
// are on another tab (a reload would stop the recording they are watching).
if ($closed && $decision->code === \mod_jitsi\local\access::WAITING) {
    echo html_writer::script("(function() {
        var check = function() {
            var video = document.getElementById('panel-video');
            if (video && video.style.display !== 'none') {
                location.reload();
            } else {
                setTimeout(check, 5000);
            }
        };
        setTimeout(check, 5000);
    })();");
}

if ($joining) {
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
                // Straight into the call (no "Join meeting" screen); a student held by the
                // lobby knocks at once. The name comes from Moodle's key and can't be changed
                // (a host's page recognises other hosts by it).
                prejoinConfig: { enabled: false },
                readOnlyName: true,
                lobby: { autoKnock: true },
                // A student's screen keeps the teacher on it (see below), not the latest
                // screen share.
                autoPinLatestScreenShare: !CFG.isolateStudents,
                disableDeepLinking: true,
                disableInviteFunctions: !CFG.isTeacher,
                enableClosePage: false,
                subject: CFG.jitsiName,
                // The room's own strip (dock) shows the live timer; no second title and timer.
                hideConferenceSubject: true,
                hideConferenceTimer: true,
                defaultLanguage: CFG.jitsiLang,
                toolbarButtons: CFG.toolbarButtons,
                fileRecordingsEnabled: CFG.isTeacher,
                localRecording: { enabled: false },
                liveStreamingEnabled: true,
                hiddenPremeetingButtons: [],
                disableProfile: false,
                enableNoisyMicDetection: true,
                enableNoAudioDetection: true,
                // A student in a lesson room receives one video (the pinned teacher) and
                // has no filmstrip / tile view to show other students; the host sees all.
                channelLastN: CFG.isolateStudents ? 1 : -1,
                disableTileView: CFG.isolateStudents,
                filmstrip: CFG.isolateStudents ? { disabled: true, disableStageFilmstrip: true } : {},
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
        // The room's own controls (dock.js), in place of Jitsi's toolbar.
        if (window.NitJitsiDock) {
            window.NitJitsiDock.init(api, document.getElementById('panel-video'), {isHost: CFG.isTeacher});
        }
        // readyToClose fires when teacher clicks "End meeting for all" — ended=true marks the session.
        api.addEventListener('readyToClose', function() { onSessionLeft(true); });
        // videoConferenceLeft fires on plain leave — teacher leaving without ending doesn't mark session.
        api.addEventListener('videoConferenceLeft', function() { onSessionLeft(false); });

        // A student leaving (hang-up, or closing / leaving the page) closes their
        // attendance row, so "in the call now" (live monitoring) and their time in the
        // session are right. Coming back in reopens it (view.php → record_entry).
        if (!CFG.isTeacher && CFG.sessionId) {
            var _studentLeftSent = false;
            var studentLeft = function() {
                if (_studentLeftSent) { return; }
                _studentLeftSent = true;
                fetch(CFG.endUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'function=leave_session&sesskey=' + encodeURIComponent(CFG.sesskey) + '&sessionid=' + CFG.sessionId,
                    keepalive: true
                }).catch(function() {});
            };
            api.addEventListener('videoConferenceLeft', studentLeft);
            api.addEventListener('readyToClose', studentLeft);
            window.addEventListener('pagehide', studentLeft);
        }

        // Lesson room host (teacher or admin): whoever knocks (a lobby someone turned on,
        // e.g. from the app) is let in at once. Reaching this room needs a key Moodle only
        // gives its teacher, its invited students and admins, so nobody asks anybody.
        if (CFG.autoAdmit) {
            api.addEventListener('knockingParticipant', function(e) {
                var p = e && e.participant;
                if (!p || !p.id) {
                    return;
                }
                // Another host (by name; names come from Moodle's key and can't be edited, see
                // readOnlyName), or anyone in a room that should have no lobby: straight in.
                // A student in a course room's waiting room: Jitsi shows the host the request
                // with Admit / Reject, inside the meeting.
                if (!CFG.lobbyEnabled || CFG.hostNames.indexOf(p.name) !== -1) {
                    api.executeCommand('answerKnockingParticipant', p.id, true);
                }
            });
        }

        // Student: the teacher only, in every room. Keep the teacher pinned on the main
        // video (their camera, their screen while they share it, or the whiteboard while
        // they have it on), tile view off and the
        // filmstrip hidden, so the student never shows (and, with lastN 1, never receives)
        // another student's camera or screen. While no teacher is in the call, cover the
        // video. NOTE: enforced in this page (and the app), not by the Jitsi server; see
        // docs/CAMERA-PRIVACY-STUDY.md.
        if (CFG.isolateStudents) {
            var myId = null, hosts = {}, sharing = [];
            var away = document.getElementById('jitsi-teacher-away');
            var pickTeacher = function(people) {
                var teacher = null;
                // By name in order (the course's teachers first), else anyone who is a host.
                CFG.teacherNames.forEach(function(name) {
                    people.forEach(function(p) {
                        if (!teacher && p.participantId !== myId && p.displayName === name) {
                            teacher = p.participantId;
                        }
                    });
                });
                people.forEach(function(p) {
                    if (!teacher && p.participantId !== myId && hosts[p.participantId]) {
                        teacher = p.participantId;
                    }
                });
                return teacher;
            };
            // The whiteboard (Jitsi's own, opened by the teacher from the dock): on the main
            // video in place of the teacher's camera while the teacher has it on, and back to the
            // teacher when they hide it (Jitsi hides it on their screen only; their page
            // says so, dock.js). Messages from anyone but a host are ignored.
            var board = {here: false, on: true};
            api.addEventListener('whiteboardStatusChanged', function(e) {
                var status = e && e.status;
                if (status === 'SHOWN' || status === 'INSTANTIATED') {
                    board.here = true;
                } else if (status === 'RESET') {
                    board.here = false;
                }
                keepTeacherOnly();
            });
            api.addEventListener('endpointTextMessageReceived', function(e) {
                var data = (e && e.data) || {};
                var from = data.senderInfo && data.senderInfo.id;
                var msg = null;
                try {
                    msg = JSON.parse(data.eventData && data.eventData.text);
                } catch (x) {
                    return;
                }
                if (msg && typeof msg.nitWhiteboard === 'boolean' && from && hosts[from]
                        && msg.nitWhiteboard !== board.on) {
                    board.on = msg.nitWhiteboard;
                    keepTeacherOnly();
                }
            });
            var keepTeacherOnly = function() {
                // Not in the call yet: still connecting, or knocking in the waiting room
                // (the teacher has to let them in), so not "the teacher is away".
                if (!myId) {
                    if (away) {
                        away.textContent = CFG.awayKnock;
                        away.style.display = CFG.lobbyEnabled ? 'flex' : 'none';
                    }
                    return;
                }
                if (away) {
                    away.textContent = CFG.awayText;
                }
                var teacher = pickTeacher((api.getParticipantsInfo && api.getParticipantsInfo()) || []);
                // The teacher's screen share wins over the whiteboard, the whiteboard over
                // their camera.
                if (board.here && board.on && !(teacher && sharing.indexOf(teacher) !== -1)) {
                    api.pinParticipant('whiteboard');
                } else if (teacher) {
                    api.pinParticipant(teacher, sharing.indexOf(teacher) !== -1 ? 'desktop' : 'camera');
                }
                if (away) {
                    away.style.display = teacher ? 'none' : 'flex';
                }
                api.executeCommand('setTileView', false);
            };
            // Who is a host comes from the room's roles (moderator), not only from names.
            var refreshHosts = function() {
                if (!api.getRoomsInfo) {
                    keepTeacherOnly();
                    return;
                }
                api.getRoomsInfo().then(function(info) {
                    (info.rooms || []).forEach(function(room) {
                        (room.participants || []).forEach(function(p) {
                            var id = p.id || (p.jid ? String(p.jid).split('/').pop() : null);
                            if (id && p.role === 'moderator') {
                                hosts[id] = true;
                            }
                        });
                    });
                    keepTeacherOnly();
                }).catch(keepTeacherOnly);
            };
            api.addEventListener('videoConferenceJoined', function(e) {
                myId = e.id;
                refreshHosts();
            });
            api.addEventListener('participantRoleChanged', function(e) {
                if (e.role === 'moderator') {
                    hosts[e.id] = true;
                }
                keepTeacherOnly();
            });
            ['participantJoined', 'participantLeft', 'displayNameChange'].forEach(function(ev) {
                api.addEventListener(ev, refreshHosts);
            });
            api.addEventListener('dominantSpeakerChanged', keepTeacherOnly);
            // Someone started / stopped sharing a screen: back to the teacher at once (their
            // screen if it is theirs), never another student's share.
            api.addEventListener('contentSharingParticipantsChanged', function(e) {
                sharing = (e && e.data) || [];
                keepTeacherOnly();
            });
            api.addEventListener('tileViewChanged', function(e) {
                if (e.enabled) {
                    api.executeCommand('setTileView', false);
                }
            });
            api.addEventListener('filmstripDisplayChanged', function(e) {
                if (e.visible) {
                    api.executeCommand('toggleFilmStrip');
                }
            });
            // A safety net for anything that moved the main video without an event.
            setInterval(keepTeacherOnly, 3000);
        }

        // The room's attendance log (attendance.php): everyone's every stretch in the call.
        var roomPresence = function(present) {
            fetch(CFG.presenceUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'function=room_presence&sesskey=' + encodeURIComponent(CFG.sesskey) + '&cmid=' + CFG.cmid
                    + '&present=' + (present ? 1 : 0),
                keepalive: true
            }).catch(function() {});
        };
        var _inCall = false;
        api.addEventListener('videoConferenceJoined', function() { _inCall = true; roomPresence(true); });
        // Heartbeat: the same "joined" every minute while in the call. If it stops (browser
        // crashed, connection lost) the server closes the stretch at the last one.
        setInterval(function() {
            if (_inCall) { roomPresence(true); }
        }, 60000);
        var leftCall = function() {
            if (_inCall) { _inCall = false; roomPresence(false); }
        };
        api.addEventListener('videoConferenceLeft', leftCall);
        api.addEventListener('readyToClose', leftCall);
        window.addEventListener('pagehide', leftCall);

        // Auto-submit password for everyone so no one gets a prompt
        // (Moodle already gates who can reach this page).
        if (CFG.roomPassword) {
            api.addEventListener('passwordRequired', function() {
                api.executeCommand('password', CFG.roomPassword);
            });
        }

        // Host: set the password on join and the lobby (standalone rooms only, see above).
        // Note: a JWT moderator does NOT skip a lobby on this server; they knock like anyone.
        // Tell the backend whether the teacher is currently in the call, so the student
        // entry gate in view.php only opens while the teacher is present.
        function setTeacherPresent(present) {
            fetch(CFG.teacherPresentUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cmid=' + CFG.cmid + '&sesskey=' + CFG.sesskey + '&present=' + (present ? 1 : 0),
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
                } else {
                    // Switch off a Jitsi lobby an earlier host may have turned on: the
                    // waiting room is Moodle's now, and hosts must never knock.
                    api.executeCommand('toggleLobby', false);
                }
                // Auto-start the recording a few seconds after the host joins (like the
                // old academy). Uses the Jitsi API — Jibri records the file, then
                // finalize.sh ships it to Vimeo. No direct Jibri call needed; the host's
                // JWT grants recording. Guarded so it only fires once and only when
                // auto-record is enabled for this activity.
                if (CFG.autoRecord) {
                    setTimeout(function () {
                        try { api.executeCommand('startRecording', { mode: 'file' }); }
                        catch (e) { /* Jibri busy / unavailable — teacher can start manually */ }
                    }, 4000);
                }
            });

            // Stop recording + re-gate students when the host leaves or ends the meeting.
            function onHostLeave() {
                setTeacherPresent(false);
                if (CFG.autoRecord) {
                    try { api.executeCommand('stopRecording', 'file'); } catch (e) {}
                }
            }
            api.addEventListener('videoConferenceLeft', onHostLeave);
            api.addEventListener('readyToClose', onHostLeave);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJitsiAPI);
    } else {
        initJitsiAPI();
    }

    var _sessionEndCalled = false;

    function onSessionLeft(teacherEndedForAll) {
        if (api) { try { api.dispose(); } catch(x) {} api = null; }

        document.getElementById('jitsi-tabs').style.display = 'none';
        document.getElementById('panel-video').style.display = 'none';
        var recPanel = document.getElementById('panel-recordings');
        if (recPanel) { recPanel.style.display = 'none'; }
        document.getElementById('panel-ended').style.display = 'block';

        var sub = document.getElementById('ended-sub');
        if (teacherEndedForAll && CFG.isTeacher && !_sessionEndCalled) {
            _sessionEndCalled = true;
            sub.textContent = CFG.str.ending;

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
                sub.textContent = data.status === 'success' ? CFG.str.byTeacher : CFG.str.failed;
                loadRecordingsAjax();
            }).catch(function() {
                sub.textContent = CFG.str.failed;
                loadRecordingsAjax();
            });
        } else {
            // A student when the teacher ended it for all; anyone who just left.
            sub.textContent = teacherEndedForAll && !CFG.isTeacher ? CFG.str.byHost : CFG.str.left;
            loadRecordingsAjax();
        }
    }

    // The room's recordings so far (the one just made appears a few minutes later, once
    // Jibri has shipped it to Vimeo), with the same rules as the "Session recordings" tab.
    function loadRecordingsAjax() {
        var box = document.getElementById('ended-recordings');
        var note = function() {
            var p = document.createElement('p');
            p.className = 'text-muted mt-3';
            p.textContent = CFG.str.recNote;
            return p;
        };
        fetch(CFG.endUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'function=get_session_recordings&sesskey=' + encodeURIComponent(CFG.sesskey) + '&cmid=' + CFG.cmId
        }).then(function(r) { return r.json(); })
        .then(function(data) {
            box.innerHTML = '';
            box.appendChild(note());
            if (!data.data || data.data.length === 0) {
                return;
            }
            var heading = document.createElement('h5');
            heading.className = 'mt-4';
            heading.textContent = CFG.str.recordings;
            box.appendChild(heading);
            data.data.forEach(function(rec) {
                var card = document.createElement('div');
                card.className = 'card mb-3';
                card.style.cssText = 'border-radius:8px;text-align:start;';
                var body = document.createElement('div');
                body.className = 'card-body';
                var title = document.createElement('h6');
                title.textContent = rec.title;
                body.appendChild(title);
                var frame = document.createElement('div');
                frame.style.cssText = 'position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;';
                var iframe = document.createElement('iframe');
                iframe.src = rec.embed_url;
                iframe.loading = 'lazy';
                iframe.allow = 'autoplay; fullscreen; picture-in-picture';
                iframe.allowFullscreen = true;
                iframe.style.cssText = 'border:none;position:absolute;inset:0;height:100%;width:100%;';
                frame.appendChild(iframe);
                body.appendChild(frame);
                card.appendChild(body);
                box.appendChild(card);
            });
        })
        .catch(function() {
            box.textContent = CFG.str.recFailed;
        });
    }

})();
</script>
HTML;
}

echo $OUTPUT->footer();

// -------------------------------------------------------------------------
// Helper: render this activity's recordings, played from Vimeo.
// -------------------------------------------------------------------------
function jitsi_print_recordings($session, $context, $is_teacher, $cmid = null, $heading = true) {
    global $DB, $OUTPUT, $USER, $cm;

    $cmid = $cmid ?: ($cm->id ?? 0);
    if (!$cmid) {
        return;
    }
    // Recordings for this activity (linked by cmid) OR by the session, that have a
    // Vimeo video id. Newest first.
    $params = ['cmid' => $cmid];
    $where  = 'cmid = :cmid';
    if ($session) {
        $where .= ' OR sessionid = :sid';
        $params['sid'] = $session->id;
    }
    $rows = $DB->get_records_select('academy_session_recordings',
        "($where) AND vimeo_videoid IS NOT NULL AND vimeo_videoid <> ''",
        $params, 'timecreated DESC');

    echo '<div class="jitsi-recordings" style="margin-top:' . ($heading ? '24px' : '0') . ';">';
    if ($heading) {
        echo $OUTPUT->heading(get_string('recordings', 'jitsi'), 4);
    }

    if (!$rows) {
        echo '<p class="text-muted">' . get_string('norecordings', 'jitsi') . '</p></div>';
        return;
    }

    foreach ($rows as $rec) {
        $title = format_string($rec->title ?: get_string('recording', 'jitsi'));
        echo '<div class="jitsi-recording" style="margin:0 0 20px;">';
        echo '<div style="font-weight:600;margin:0 0 6px;">' . $title . '</div>';

        // Vimeo player. The recording's Vimeo video is private with THIS academy's
        // domain whitelisted (record_notify.php), so the plain player URL embeds here.
        $src = 'https://player.vimeo.com/video/' . rawurlencode($rec->vimeo_videoid);
        echo '<div style="position:relative;padding-top:56.25%;border-radius:8px;overflow:hidden;">'
            . '<iframe src="' . s($src) . '" style="position:absolute;inset:0;width:100%;height:100%;border:0;" '
            . 'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
        echo '</div>';
    }
    echo '</div>';
}
