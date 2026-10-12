
// ── NIT academies: Jitsi customisations (moodle/server/jitsi/nit-rooms.js) ────
// Appended to /config/custom-config.js (and the live /config/config.js) in the Jitsi web
// container; everything below is ours and is replaced as one block on each update.

// 1. The academy's look in its rooms: Jitsi's own dynamic branding, from the academy's
// mod/jitsi/branding.php (colours, logo, icons), plus the site's font and pill shapes.
// Only for the academies listed here (slug => site); the others keep Jitsi's look.
(function () {
    try {
        var sites = {
            'academy2026': 'https://academy2026.nitg-eg.com/bassthalk'
        };
        var room = String(window.location.pathname.split('/').pop() || '').toLowerCase();
        var m = room.match(/^nit_([a-z0-9-]+)_[0-9]+_[a-f0-9]+$/);
        var site = m ? sites[m[1]] : null;
        if (!site) {
            return;
        }
        config.dynamicBrandingUrl = site + '/mod/jitsi/branding.php';

        var head = document.head || document.documentElement;
        var font = document.createElement('link');
        font.rel = 'stylesheet';
        font.href = 'https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&family=Tajawal:wght@400;500;700&display=swap';
        head.appendChild(font);
        var css = document.createElement('style');
        css.textContent = 'body, button, input, textarea { font-family: "Almarai", "Tajawal", sans-serif !important; }'
            + ' .toolbox-content-items { border-radius: 9999px !important; padding: 6px 12px !important; }'
            + ' .toolbox-button .toolbox-icon { border-radius: 9999px !important; }';
        head.appendChild(css);
    } catch (e) { /* never break the page */ }
})();

// 2. The whiteboard in the recording. Jitsi puts it on the main video of everyone (the
// recorder too) when a host opens it, but hides it again only on that host's screen; the
// host's page (mod/jitsi/dock.js) says whether it is on, in an endpoint message
// {"nitWhiteboard": true|false}. Kept here in window.nitBoardOn for block 3 (course rooms),
// and applied here in private lesson rooms (block 3 leaves those as they are).
// Runs in Jibri's window only, in NIT academy rooms only.
(function () {
    try {
        var hash = String(window.location.hash || '');
        var room = String(window.location.pathname.split('/').pop() || '').toLowerCase();
        if (hash.indexOf('config.iAmRecorder=true') === -1 || room.indexOf('nit_') !== 0) {
            return;
        }
        var lesson = room.split('_').pop().length >= 12;
        window.nitBoardOn = true;
        var apply = function () {
            if (!lesson || !window.APP || !APP.store) {
                return;
            }
            var state = APP.store.getState();
            var parts = state['features/base/participants'];
            var here = parts && parts.remote && parts.remote.has('whiteboard');
            var pinned = parts && parts.pinnedParticipant;
            if (here && window.nitBoardOn) {
                APP.store.dispatch({ type: 'PIN_PARTICIPANT', participant: { id: 'whiteboard' } });
            } else if (!window.nitBoardOn && pinned === 'whiteboard') {
                APP.store.dispatch({ type: 'PIN_PARTICIPANT', participant: { id: null } });
            }
        };
        var hooked = null;
        setInterval(function () {
            var conf = window.APP && APP.conference && APP.conference._room;
            if (conf && conf !== hooked) {
                hooked = conf;
                conf.on('conference.endpoint_message_received', function (from, data) {
                    try {
                        var msg = data && data.name === 'endpoint-text-message' ? JSON.parse(data.text) : null;
                        if (msg && typeof msg.nitWhiteboard === 'boolean' && from && from.getRole() === 'moderator'
                                && msg.nitWhiteboard !== window.nitBoardOn) {
                            window.nitBoardOn = msg.nitWhiteboard;
                            apply(); // on a change only: a screen share pinned since stays
                        }
                    } catch (e) { /* not ours */ }
                });
            }
        }, 500);
    } catch (e) { /* never break the page */ }
})();

// 3. The recording of a course room shows the teacher only (or the whiteboard, block 2).
// Runs in Jibri's window only (Jibri opens the meeting with config.iAmRecorder=true in
// the URL) and only in NIT academy course rooms (nit_<slug>_<cmid>_<8-char hash>).
// Everyone else's screen, the old academy's rooms (academy_jitsi_*) and private lesson
// rooms (a 12-char hash, see mod/jitsi/lib.php jitsi_room_name(): one teacher and one
// student, recorded as they are, screen shares included) are untouched.
// In the recording: no thumbnails, no tile view, no notifications, and the main video is
// the teacher (the room's moderator), or the teacher's screen while they share it, or the
// whiteboard while the teacher has it on (it holds the main video then, so whatever video
// the bridge sends is not shown). The audio is everyone's.
// Never a student's frame, even for an instant:
//  1. Jitsi does not move a new screen share onto the main video by itself;
//  2. the recorder receives one video only, the pinned teacher's (lastN 1);
//  3. on every state change (not on a timer) the teacher is pinned back, and the main
//     video is hidden whenever it holds anyone else; both happen in the same JavaScript
//     task as the change, so the browser never paints a student's frame.
(function () {
    try {
        var hash = String(window.location.hash || '');
        var room = String(window.location.pathname.split('/').pop() || '').toLowerCase();
        if (hash.indexOf('config.iAmRecorder=true') === -1 || room.indexOf('nit_') !== 0
                || room.split('_').pop().length >= 12) {
            return;
        }
        config.filmstrip = Object.assign({}, config.filmstrip, { disabled: true, disableStageFilmstrip: true });
        config.disableTileView = true;
        config.tileView = Object.assign({}, config.tileView, { disabled: true });
        config.autoPinLatestScreenShare = false; // newer Jitsi versions
        config.channelLastN = 1;
        config.notifications = [];

        var css = document.createElement('style');
        css.textContent = 'body.nit-rec-hide #largeVideoContainer, body.nit-rec-hide #largeVideoWrapper,'
            + ' body.nit-rec-hide #largeVideo { visibility: hidden !important; }';
        (document.head || document.documentElement).appendChild(css);

        var teacherId = null;
        var busy = false;
        var hide = function (on) {
            var body = document.body;
            if (body && body.classList.contains('nit-rec-hide') !== on) {
                body.classList.toggle('nit-rec-hide', on);
            }
        };
        var check = function () {
            if (busy || !window.APP || !APP.store) {
                return;
            }
            busy = true;
            try {
                var state = APP.store.getState();
                var parts = state['features/base/participants'];
                if (!parts || !parts.remote) {
                    hide(true);
                    return;
                }
                var people = Array.from(parts.remote.values());
                var here = function (id) {
                    return people.some(function (p) { return p.id === id; });
                };
                // The teacher: the first moderator in the call (kept while they stay).
                if (!teacherId || !here(teacherId)) {
                    var first = people.find(function (p) { return !p.fakeParticipant && p.role === 'moderator'; });
                    teacherId = first ? first.id : null;
                }
                // Their screen share (a virtual participant "<owner id>-v1") wins over the camera.
                var target = teacherId;
                if (teacherId) {
                    var share = people.find(function (p) {
                        return p.fakeParticipant && String(p.id).indexOf(teacherId + '-') === 0;
                    });
                    if (share) {
                        target = share.id;
                    } else if (window.nitBoardOn && here('whiteboard')) {
                        target = 'whiteboard'; // the whiteboard wins over the camera (block 2)
                    }
                }
                if (target && parts.pinnedParticipant !== target) {
                    APP.store.dispatch({ type: 'PIN_PARTICIPANT', participant: { id: target } });
                }
                // Show the main video only while it holds the teacher.
                var large = (APP.store.getState()['features/large-video'] || {}).participantId;
                hide(!target || large !== target);
            } catch (e) {
                hide(true);
            } finally {
                busy = false;
            }
        };
        // This Jitsi version reads the auto-pin from interface_config.js, which loads after
        // this file: switch it off as soon as it exists (before the call starts).
        var noAutoPin = function () {
            if (window.interfaceConfig && typeof window.interfaceConfig === 'object') {
                window.interfaceConfig.AUTO_PIN_LATEST_SCREEN_SHARE = false;
            }
        };
        var hooked = false;
        var hook = setInterval(function () {
            noAutoPin();
            if (!hooked && window.APP && APP.store && APP.store.subscribe) {
                hooked = true;
                APP.store.subscribe(check);
            }
            check(); // also a slow safety net
        }, 200);
        hide(true);
        document.addEventListener('DOMContentLoaded', function () { noAutoPin(); hide(true); check(); });
    } catch (e) { /* never break the page */ }
})();
