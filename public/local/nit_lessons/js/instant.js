/**
 * Instant lessons ("Get a tutor now") in the browser.
 *
 *  - #nit-instant-student : subject picker → searching countdown → straight into the room.
 *  - #nit-instant-teacher : "Available now" switch.
 *  - Online teachers (any page): polls every few seconds and pops up each request with
 *    Accept / Decline; Accept opens the room.
 *
 * Config comes from window.NIT_INSTANT (set by PHP): url, sesskey, strings, teacher, online.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var C = window.NIT_INSTANT;
    if (!C) {
        return;
    }
    var S = C.strings;

    function call(action, params) {
        var body = new URLSearchParams(Object.assign({action: action, sesskey: C.sesskey}, params || {}));
        return fetch(C.url, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function(r) {
                return r.json();
            })
            .then(function(r) {
                if (!r.ok) {
                    throw new Error(r.message || 'Error');
                }
                return r.data;
            });
    }

    function el(html) {
        var d = document.createElement('div');
        d.innerHTML = html.trim();
        return d.firstChild;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    function beep() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var o = ctx.createOscillator();
            o.frequency.value = 880;
            o.connect(ctx.destination);
            o.start();
            setTimeout(function() {
                o.stop();
                ctx.close();
            }, 350);
        } catch (e) {
            // Sound is optional.
        }
    }

    // ── Student ────────────────────────────────────────────────────────────
    function student(root) {
        var timer = null;

        function render(state) {
            clearTimeout(timer);
            if (!state.enabled) {
                root.innerHTML = '';
                return;
            }
            if (state.open) {
                searching(state.open);
                return;
            }
            if (!state.subjects.length) {
                root.innerHTML = '<div class="nit-instant card p-3 mb-3"><h3 class="h5 mb-1">' + esc(S.title) +
                    '</h3><p class="mb-0 text-muted">' + esc(S.nobodyonline) + '</p></div>';
                return;
            }
            var options = state.subjects.map(function(s) {
                return '<option value="' + esc(s.subject) + '">' + esc(s.label || s.subject) + ' (' +
                    esc(S.online.replace('{$a}', s.teachers)) + ')</option>';
            }).join('');
            root.innerHTML = '<div class="nit-instant card p-3 mb-3"><h3 class="h5">' + esc(S.title) + '</h3>' +
                '<p class="text-muted">' + esc(S.intro) + '</p>' +
                '<div class="d-flex flex-wrap gap-2 align-items-end">' +
                '<select class="form-select" style="max-width:320px" data-subject>' + options + '</select>' +
                '<input class="form-control" style="max-width:360px" data-note placeholder="' + esc(S.noteph) + '">' +
                '<button class="btn btn-primary" data-go>' + esc(S.go) + '</button></div>' +
                '<div class="text-danger mt-2" data-error></div></div>';
            root.querySelector('[data-go]').addEventListener('click', function(e) {
                e.target.disabled = true;
                call('request', {subject: root.querySelector('[data-subject]').value,
                    note: root.querySelector('[data-note]').value})
                    .then(searching)
                    .catch(function(err) {
                        e.target.disabled = false;
                        root.querySelector('[data-error]').textContent = err.message;
                    });
            });
        }

        function searching(st) {
            clearTimeout(timer);
            if (st.status === 'matched' && st.lessonid) {
                root.innerHTML = '<div class="nit-instant card p-3 mb-3 border-success"><strong>' + esc(S.matched) +
                    '</strong></div>';
                window.location.href = C.joinurl + st.lessonid;
                return;
            }
            if (st.status !== 'searching') {
                root.innerHTML = '<div class="nit-instant card p-3 mb-3"><h3 class="h5">' +
                    esc(st.status === 'cancelled' ? S.cancelled : S.expired) + '</h3>' +
                    '<p class="text-muted">' + esc(S.expiredhelp) + '</p>' +
                    '<button class="btn btn-primary" data-again>' + esc(S.again) + '</button></div>';
                root.querySelector('[data-again]').addEventListener('click', load);
                return;
            }
            root.innerHTML = '<div class="nit-instant card p-3 mb-3 border-primary"><h3 class="h5">' +
                esc(S.searching) + '</h3><p class="mb-2">' + esc(st.subject) + ' · ' +
                esc(S.asking.replace('{$a}', st.teachers)) + ' · <span data-left>' + st.secondsleft + '</span>s</p>' +
                '<div class="progress mb-2" style="height:6px"><div class="progress-bar progress-bar-striped ' +
                'progress-bar-animated" style="width:100%"></div></div>' +
                '<button class="btn btn-outline-secondary btn-sm" data-cancel>' + esc(S.cancel) + '</button></div>';
            root.querySelector('[data-cancel]').addEventListener('click', function() {
                call('cancel', {requestid: st.id}).then(searching);
            });
            timer = setTimeout(function() {
                call('status', {requestid: st.id}).then(searching).catch(load);
            }, 2000);
        }

        function load() {
            call('subjects').then(render).catch(function() {
                root.innerHTML = '';
            });
        }
        load();
    }

    // ── Teacher switch ─────────────────────────────────────────────────────
    function teacherSwitch(root) {
        function draw(online) {
            root.innerHTML = '<div class="nit-instant card p-3 mb-3"><div class="form-check form-switch">' +
                '<input class="form-check-input" type="checkbox" id="nit-instant-online"' + (online ? ' checked' : '') + '>' +
                '<label class="form-check-label fw-bold" for="nit-instant-online">' + esc(S.available) + '</label></div>' +
                '<p class="text-muted mb-0 mt-1">' + esc(S.availablehelp) + '</p>' +
                '<div class="text-danger mt-2" data-error></div></div>';
            root.querySelector('input').addEventListener('change', function(e) {
                var on = e.target.checked;
                call(on ? 'online' : 'offline').then(function() {
                    C.online = on;
                    if (on) {
                        beep(); // Unlocks sound for the prompts that follow.
                        if (window.Notification && Notification.permission === 'default') {
                            Notification.requestPermission();
                        }
                        poll();
                    }
                }).catch(function(err) {
                    e.target.checked = false;
                    root.querySelector('[data-error]').textContent = err.message;
                });
            });
        }
        draw(C.online);
    }

    // ── Teacher prompts (any page) ─────────────────────────────────────────
    var shown = {};
    var polling = false;

    function prompt(o) {
        if (shown[o.requestid]) {
            return;
        }
        shown[o.requestid] = true;
        beep();
        if (document.hidden && window.Notification && Notification.permission === 'granted') {
            new Notification(S.prompttitle, {body: o.subject + ' · ' + o.studentname});
        }
        var box = el('<div class="nit-instant-prompt card shadow p-3" role="alertdialog" style="position:fixed;' +
            'inset-inline-end:16px;bottom:16px;z-index:10050;max-width:360px;width:calc(100% - 32px)">' +
            '<h3 class="h5 mb-1">' + esc(S.prompttitle) + '</h3>' +
            '<p class="mb-1"><strong>' + esc(o.subject) + '</strong> · ' + esc(o.studentname) + ' · ' +
            esc(S.minutes.replace('{$a}', o.duration)) + '</p>' +
            (o.note ? '<p class="mb-1 text-muted">' + esc(o.note) + '</p>' : '') +
            '<p class="mb-2 small text-muted">' + esc(S.flex.replace('{$a}', o.flex)) + ' · <span data-left>' +
            o.secondsleft + '</span>s</p><div class="d-flex gap-2">' +
            '<button class="btn btn-success" data-accept>' + esc(S.accept) + '</button>' +
            '<button class="btn btn-outline-secondary" data-decline>' + esc(S.decline) + '</button></div>' +
            '<div class="text-danger mt-2" data-error></div></div>');
        document.body.appendChild(box);
        var left = o.secondsleft;
        var tick = setInterval(function() {
            left -= 1;
            var span = box.querySelector('[data-left]');
            if (span) {
                span.textContent = Math.max(0, left);
            }
            if (left <= 0) {
                clearInterval(tick);
                box.remove();
            }
        }, 1000);
        box.querySelector('[data-accept]').addEventListener('click', function(e) {
            e.target.disabled = true;
            call('accept', {requestid: o.requestid}).then(function(d) {
                window.location.href = d.joinurl;
            }).catch(function(err) {
                box.querySelector('[data-error]').textContent = err.message;
                setTimeout(function() {
                    clearInterval(tick);
                    box.remove();
                }, 2500);
            });
        });
        box.querySelector('[data-decline]').addEventListener('click', function() {
            clearInterval(tick);
            box.remove();
            call('decline', {requestid: o.requestid});
        });
    }

    function poll() {
        if (polling || !C.online) {
            return;
        }
        polling = true;
        (function loop() {
            if (!C.online) {
                polling = false;
                return;
            }
            call('poll').then(function(d) {
                (d.offers || []).forEach(prompt);
            }).catch(function() {
                // Try again on the next tick.
            }).then(function() {
                setTimeout(loop, document.hidden ? 8000 : 4000);
            });
        })();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var s = document.getElementById('nit-instant-student');
        if (s) {
            student(s);
        }
        var t = document.getElementById('nit-instant-teacher');
        if (t && C.teacher) {
            teacherSwitch(t);
        }
        if (C.teacher && C.online) {
            poll();
        }
    });
})();
