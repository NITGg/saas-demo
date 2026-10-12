// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The room's own controls (templates/dock.mustache), in place of Jitsi's toolbar (hidden):
 * everything goes through the Jitsi IFrame API, Jitsi's public and stable interface, so
 * nothing here depends on Jitsi's own page or breaks when the Jitsi server is updated.
 *
 * A plain script (no build step), loaded by view.php after the call starts:
 *   NitJitsiDock.init(api, panel, {isHost: bool});
 *
 * @module     mod_jitsi/dock
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var init = function(api, panel, opts) {
        var dock = panel.querySelector('[data-region="nitdock"]');
        var top = panel.querySelector('[data-region="nitdock-top"]');
        if (!dock || !api) {
            return;
        }
        var state = {me: null, chatOpen: false, paneOpen: false, knocks: 0, hand: false, since: 0};
        var btn = function(action) {
            return dock.querySelector('[data-action="' + action + '"]');
        };
        var press = function(action, on, off) {
            var b = btn(action);
            if (b) {
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.classList.toggle('is-off', !!off);
            }
        };
        var badge = function(action, n) {
            var el = dock.querySelector('[data-region="badge-' + action + '"]');
            if (el) {
                el.textContent = n > 99 ? '99+' : String(n);
                el.hidden = !n;
            }
        };
        var popups = function(except) {
            dock.querySelectorAll('.nitdock-pop').forEach(function(p) {
                if (p !== except) {
                    p.hidden = true;
                }
            });
        };

        // ── In the call (a knocking student only has "leave" until let in) ───────
        api.addEventListener('videoConferenceJoined', function(e) {
            state.me = e.id;
            state.since = state.since || Date.now();
            dock.classList.remove('is-waiting');
            panel.classList.add('nitdock-on');
            api.isAudioMuted().then(function(m) { press('mic', !m, m); });
            api.isVideoMuted().then(function(m) { press('camera', !m, m); });
            count();
        });
        api.addEventListener('conferenceCreatedTimestamp', function(e) {
            if (e && e.timestamp) {
                state.since = e.timestamp;
            }
        });
        setInterval(function() {
            var el = top && top.querySelector('[data-region="timer"]');
            if (!el || !state.since) {
                return;
            }
            var s = Math.max(0, Math.floor((Date.now() - state.since) / 1000));
            var pad = function(n) { return (n < 10 ? '0' : '') + n; };
            el.textContent = (s >= 3600 ? Math.floor(s / 3600) + ':' : '') + pad(Math.floor(s / 60) % 60) + ':' + pad(s % 60);
        }, 1000);

        // ── What the call says back ──────────────────────────────────────────────
        api.addEventListener('audioMuteStatusChanged', function(e) { press('mic', !e.muted, e.muted); });
        api.addEventListener('videoMuteStatusChanged', function(e) { press('camera', !e.muted, e.muted); });
        api.addEventListener('screenSharingStatusChanged', function(e) { press('screen', e.on); });
        api.addEventListener('tileViewChanged', function(e) { press('tile', e.enabled); });
        api.addEventListener('raiseHandUpdated', function(e) {
            if (e.id === state.me) {
                state.hand = !!e.handRaised;
                press('hand', state.hand);
            }
        });
        api.addEventListener('chatUpdated', function(e) {
            state.chatOpen = !!e.isOpen;
            press('chat', state.chatOpen);
            badge('chat', state.chatOpen ? 0 : (e.unreadCount || 0));
        });
        api.addEventListener('participantsPaneToggled', function(e) {
            state.paneOpen = !!e.open;
            press('people', state.paneOpen);
            if (state.paneOpen) {
                state.knocks = 0;
            }
            count();
        });
        api.addEventListener('knockingParticipant', function() {
            if (!state.paneOpen) {
                state.knocks++;
                count();
            }
        });
        api.addEventListener('recordingStatusChanged', function(e) {
            var rec = top && top.querySelector('[data-region="rec"]');
            if (rec) {
                rec.hidden = !e.on;
            }
        });
        var count = function() {
            // The host's people button: who is waiting to come in, else how many are in.
            if (!opts.isHost) {
                return;
            }
            if (state.knocks) {
                badge('people', state.knocks);
                btn('people').classList.add('is-alert');
                return;
            }
            btn('people') && btn('people').classList.remove('is-alert');
            Promise.resolve(api.getNumberOfParticipants()).then(function(n) { badge('people', n > 1 ? n : 0); });
        };
        api.addEventListener('participantJoined', count);
        api.addEventListener('participantLeft', count);

        // ── The buttons ──────────────────────────────────────────────────────────
        var actions = {
            mic: function() { api.executeCommand('toggleAudio'); },
            camera: function() { api.executeCommand('toggleVideo'); },
            screen: function() { api.executeCommand('toggleShareScreen'); },
            chat: function() { api.executeCommand('toggleChat'); },
            hand: function() { api.executeCommand('toggleRaiseHand'); },
            people: function() { api.executeCommand('toggleParticipantsPane', !state.paneOpen); },
            tile: function() { api.executeCommand('toggleTileView'); },
            muteall: function() { api.executeCommand('muteEveryone', 'audio'); },
            settings: function() { toggle(dock.querySelector('[data-region="settings"]'), devices); },
            fullscreen: function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else if (panel.requestFullscreen) {
                    panel.requestFullscreen();
                }
            },
            leave: function() {
                var menu = dock.querySelector('[data-region="leave-menu"]');
                if (menu) {
                    toggle(menu);
                } else {
                    api.executeCommand('hangup', false);
                }
            },
            hangup: function() { api.executeCommand('hangup', false); },
            endall: function() { api.executeCommand('endConference'); },
        };
        var toggle = function(pop, onopen) {
            if (!pop) {
                return;
            }
            popups(pop);
            pop.hidden = !pop.hidden;
            if (!pop.hidden && onopen) {
                onopen();
            }
        };
        dock.addEventListener('click', function(e) {
            var b = e.target.closest('[data-action]');
            if (b && actions[b.getAttribute('data-action')]) {
                actions[b.getAttribute('data-action')]();
            }
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nitdock-pop') && !e.target.closest('[data-action="settings"], [data-action="leave"]')) {
                popups(null);
            }
        });
        document.addEventListener('fullscreenchange', function() {
            press('fullscreen', document.fullscreenElement === panel);
        });

        // ── Settings: devices, background blur, noise suppression ────────────────
        var devices = function() {
            Promise.all([api.getAvailableDevices(), api.getCurrentDevices()]).then(function(r) {
                var all = r[0] || {};
                var now = r[1] || {};
                ['audioInput', 'videoInput', 'audioOutput'].forEach(function(kind) {
                    var sel = dock.querySelector('select[data-device="' + kind + '"]');
                    if (!sel) {
                        return;
                    }
                    var list = all[kind] || [];
                    sel.innerHTML = '';
                    list.forEach(function(d) {
                        var o = document.createElement('option');
                        o.value = d.deviceId;
                        o.textContent = d.label || d.deviceId;
                        o.selected = !!(now[kind] && now[kind].deviceId === d.deviceId);
                        sel.appendChild(o);
                    });
                    if (kind === 'audioOutput') {
                        var row = dock.querySelector('[data-region="speaker-row"]');
                        if (row) {
                            row.hidden = !list.length;
                        }
                    }
                });
            }).catch(function() { /* devices unavailable */ });
        };
        dock.addEventListener('change', function(e) {
            var t = e.target;
            var kind = t.getAttribute('data-device');
            if (kind) {
                var label = t.options[t.selectedIndex] ? t.options[t.selectedIndex].textContent : '';
                var set = {audioInput: 'setAudioInputDevice', videoInput: 'setVideoInputDevice',
                    audioOutput: 'setAudioOutputDevice'}[kind];
                if (set && api[set]) {
                    api[set](label, t.value);
                }
            } else if (t.getAttribute('data-setting') === 'blur') {
                api.executeCommand('setBlurredBackground', t.checked ? 'slight-blur' : 'none');
            } else if (t.getAttribute('data-setting') === 'noise') {
                api.executeCommand('setNoiseSuppressionEnabled', {enabled: t.checked});
            }
        });
    };

    window.NitJitsiDock = {init: init};
})();
