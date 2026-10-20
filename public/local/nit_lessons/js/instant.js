/**
 * Instant lessons ("Get a tutor now") in the browser.
 *
 *  - #nit-instant-student : subject picker → searching countdown → straight into the room.
 *  - #nit-instant-teacher : "Available now" switch.
 *  - Online teachers (any page): polls every few seconds and pops up each request with
 *    Accept / Decline; Accept opens the room.
 *
 * Config comes from window.NIT_INSTANT (set by PHP): url, sesskey, strings, teacher, online, ws.
 *
 * Realtime: when ws is set (the academy notify-ws relay), the page joins its private Socket.IO room
 * and the server's {type: instant} signals make it fetch the new state at once; polling continues
 * slowly as a safety net. Without ws (or when the relay is down) it simply polls.
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
        var body;
        if (params instanceof FormData) {
            body = params;
            body.append('action', action);
            body.append('sesskey', C.sesskey);
        } else {
            body = new URLSearchParams(Object.assign({action: action, sesskey: C.sesskey}, params || {}));
        }
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

    // ── Realtime (notify-ws) ───────────────────────────────────────────────
    var listeners = [];
    var live = false;

    function onSignal(fn) {
        listeners.push(fn);
    }

    function connect() {
        if (!C.ws) {
            return;
        }
        var script = document.createElement('script');
        script.src = C.ws.url + C.ws.path + '/socket.io.js';
        script.onload = function() {
            if (!window.io) {
                return;
            }
            var socket = window.io(C.ws.url, {path: C.ws.path, auth: {token: C.ws.token},
                transports: ['websocket', 'polling']});
            socket.on('connect', function() {
                live = true;
            });
            socket.on('disconnect', function() {
                live = false;
            });
            socket.on('notification', function(p) {
                if (p && p.type === 'instant') {
                    listeners.forEach(function(fn) {
                        fn(p);
                    });
                }
            });
        };
        document.head.appendChild(script);
    }

    // ── Student ────────────────────────────────────────────────────────────
    function student(root) {
        var timer = null;
        var state = null;
        var last = {subject: '', label: ''};

        function card(inner, cls) {
            return '<div class="nit-instant card p-3 mb-3 ' + (cls || '') + '">' + inner + '</div>';
        }

        // "Notify me when a tutor is online" + "Book a lesson" for a subject (raw value + label).
        function fallback(subject, label) {
            var book = state.bookurl + '&search=' + encodeURIComponent(label || '');
            var html = '<div class="d-flex flex-wrap gap-2 mt-2">';
            if (subject) {
                html += '<button class="btn btn-outline-primary btn-sm" data-watch="' + esc(subject) + '">' +
                    esc(S.notifyme) + '</button>';
            }
            return html + '<a class="btn btn-outline-secondary btn-sm" href="' + esc(book) + '">' + esc(S.booklater) +
                '</a></div>';
        }

        function bindWatch() {
            root.querySelectorAll('[data-watch]').forEach(function(b) {
                b.addEventListener('click', function() {
                    b.disabled = true;
                    call('watch', {subject: b.getAttribute('data-watch')}).then(function() {
                        b.textContent = S.notifyset;
                    }).catch(function(err) {
                        b.disabled = false;
                        b.textContent = err.message;
                    });
                });
            });
        }

        function render(st) {
            clearTimeout(timer);
            state = st;
            if (!st.enabled) {
                root.innerHTML = '';
                return;
            }
            if (st.open) {
                searching(st.open);
                return;
            }
            if (!st.subjects.length) {
                var all = st.all.map(function(s) {
                    return '<option value="' + esc(s.subject) + '">' + esc(s.label) + '</option>';
                }).join('');
                root.innerHTML = card('<h3 class="h5 mb-1">' + esc(S.title) + '</h3><p class="text-muted mb-2">' +
                    esc(S.nobodyonline) + '</p><div class="d-flex flex-wrap gap-2"><select class="form-select" ' +
                    'style="max-width:320px" data-allsubject>' + all + '</select><button class="btn btn-outline-primary" ' +
                    'data-watchsel>' + esc(S.notifyme) + '</button></div><div class="small mt-2" data-msg></div>');
                root.querySelector('[data-watchsel]').addEventListener('click', function(e) {
                    e.target.disabled = true;
                    call('watch', {subject: root.querySelector('[data-allsubject]').value}).then(function() {
                        root.querySelector('[data-msg]').textContent = S.notifyset;
                    }).catch(function(err) {
                        e.target.disabled = false;
                        root.querySelector('[data-msg]').textContent = err.message;
                    });
                });
                return;
            }
            var options = st.subjects.map(function(s) {
                return '<option value="' + esc(s.subject) + '">' + esc(s.label || s.subject) + ' (' +
                    esc(S.online.replace('{$a}', s.teachers)) + ')</option>';
            }).join('');
            root.innerHTML = card('<h3 class="h5">' + esc(S.title) + '</h3><p class="text-muted">' + esc(S.intro) + '</p>' +
                '<div class="d-flex flex-wrap gap-2 align-items-end">' +
                '<select class="form-select" style="max-width:320px" data-subject>' + options + '</select>' +
                '<input class="form-control" style="max-width:360px" data-note placeholder="' + esc(S.noteph) + '">' +
                '<label class="btn btn-outline-secondary mb-0">' + esc(S.photo) +
                '<input type="file" accept="image/*" capture="environment" hidden data-photo></label>' +
                '<button class="btn btn-primary" data-go>' + esc(S.go) + '</button></div>' +
                '<div class="small text-muted mt-1" data-photoname></div><div class="text-danger mt-2" data-error></div>');
            var photo = root.querySelector('[data-photo]');
            photo.addEventListener('change', function() {
                root.querySelector('[data-photoname]').textContent = photo.files.length ? photo.files[0].name : '';
            });
            root.querySelector('[data-go]').addEventListener('click', function(e) {
                var select = root.querySelector('[data-subject]');
                last = {subject: select.value, label: select.options[select.selectedIndex].text.replace(/ \(.*\)$/, '')};
                var form = new FormData();
                form.append('subject', select.value);
                form.append('note', root.querySelector('[data-note]').value);
                if (photo.files.length) {
                    form.append('photo', photo.files[0]);
                }
                e.target.disabled = true;
                call('request', form).then(searching).catch(function(err) {
                    e.target.disabled = false;
                    root.querySelector('[data-error]').textContent = err.message;
                });
            });
        }

        function searching(st) {
            clearTimeout(timer);
            if (st.status === 'matched' && st.lessonid) {
                root.innerHTML = card('<strong>' + esc(S.matched) + '</strong>', 'border-success');
                window.location.href = C.joinurl + st.lessonid;
                return;
            }
            if (st.status !== 'searching') {
                root.innerHTML = card('<h3 class="h5">' + esc(st.status === 'cancelled' ? S.cancelled : S.expired) +
                    '</h3><p class="text-muted mb-1">' + esc(S.expiredhelp) + '</p>' +
                    '<button class="btn btn-primary btn-sm" data-again>' + esc(S.again) + '</button>' +
                    fallback(last.subject, last.label || st.subject));
                root.querySelector('[data-again]').addEventListener('click', load);
                bindWatch();
                return;
            }
            root.innerHTML = card('<h3 class="h5">' + esc(S.searching) + '</h3><p class="mb-2"><bdi>' + esc(st.subject) +
                '</bdi> · ' + esc(S.asking.replace('{$a}', st.teachers)) + ' · <span data-left>' + st.secondsleft +
                '</span>s</p><div class="progress mb-2" style="height:6px"><div class="progress-bar progress-bar-striped ' +
                'progress-bar-animated" style="width:100%"></div></div>' +
                '<button class="btn btn-outline-secondary btn-sm" data-cancel>' + esc(S.cancel) + '</button>',
                'border-primary');
            root.querySelector('[data-cancel]').addEventListener('click', function() {
                call('cancel', {requestid: st.id}).then(searching);
            });
            current = st.id;
            timer = setTimeout(function() {
                call('status', {requestid: st.id}).then(searching).catch(load);
            }, live ? 10000 : 2000);
        }

        var current = 0;
        onSignal(function(p) {
            if (p.what === 'status' && current && p.requestid === current) {
                clearTimeout(timer);
                call('status', {requestid: current}).then(searching).catch(load);
            }
        });

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
    var boxes = {};
    var polling = false;

    function closePrompt(requestid) {
        if (boxes[requestid]) {
            boxes[requestid]();
        }
    }

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
            '<p class="mb-1"><strong><bdi>' + esc(o.subject) + '</bdi></strong> · <bdi>' + esc(o.studentname) +
            '</bdi> · <bdi>' + esc(S.minutes.replace('{$a}', o.duration)) + '</bdi></p>' +
            (o.note ? '<p class="mb-1 text-muted">' + esc(o.note) + '</p>' : '') +
            (o.photourl ? '<a href="' + esc(o.photourl) + '" target="_blank" rel="noopener"><img src="' + esc(o.photourl) +
                '" alt="" style="max-width:100%;max-height:160px;border-radius:6px" class="mb-2"></a>' : '') +
            '<p class="mb-2 small text-muted">' + esc(S.flex.replace('{$a}', o.flex)) + ' · <span data-left>' +
            o.secondsleft + '</span>s</p><div class="d-flex gap-2">' +
            '<button class="btn btn-success" data-accept>' + esc(S.accept) + '</button>' +
            '<button class="btn btn-outline-secondary" data-decline>' + esc(S.decline) + '</button></div>' +
            '<div class="text-danger mt-2" data-error></div></div>');
        document.body.appendChild(box);
        boxes[o.requestid] = function() {
            clearInterval(tick);
            box.remove();
            delete boxes[o.requestid];
        };
        var left = o.secondsleft;
        var tick = setInterval(function() {
            left -= 1;
            var span = box.querySelector('[data-left]');
            if (span) {
                span.textContent = Math.max(0, left);
            }
            if (left <= 0) {
                closePrompt(o.requestid);
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
                var open = {};
                (d.offers || []).forEach(function(o) {
                    open[o.requestid] = true;
                    prompt(o);
                });
                Object.keys(boxes).forEach(function(id) {
                    if (!open[id]) {
                        closePrompt(id);
                    }
                });
            }).catch(function() {
                // Try again on the next tick.
            }).then(function() {
                // Live: the heartbeat only has to keep presence fresh (60 s); signals bring prompts.
                setTimeout(loop, live ? 20000 : (document.hidden ? 8000 : 4000));
            });
        })();
    }

    onSignal(function(p) {
        if (p.what === 'closed') {
            closePrompt(p.requestid);
        } else if (p.what === 'offer' && C.online) {
            // One extra fetch now; the regular heartbeat loop carries on unchanged.
            call('poll').then(function(d) {
                (d.offers || []).forEach(prompt);
            }).catch(function() {
                // The next heartbeat will bring it.
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        connect();
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
