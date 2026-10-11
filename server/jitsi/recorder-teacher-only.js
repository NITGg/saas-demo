
// ── NIT academies: the recording shows the teacher only ─────────────────────
// Runs in Jibri's window only (Jibri opens the meeting with config.iAmRecorder=true in
// the URL) and only in NIT academy course rooms (nit_<slug>_<cmid>_<8-char hash>).
// Everyone else's screen, the old academy's rooms (academy_jitsi_*) and private lesson
// rooms (a 12-char hash, see mod/jitsi/lib.php jitsi_room_name(): one teacher and one
// student, recorded as they are, screen shares included) are untouched.
// In the recording: no thumbnails, no tile view, no notifications, and the main video is
// the teacher (the room's moderator), or the teacher's screen while they share it. The
// audio is everyone's.
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
