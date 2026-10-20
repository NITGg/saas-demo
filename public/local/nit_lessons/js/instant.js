/**
 * Instant lessons ("Get a tutor now") in the browser.
 *
 *  - #nit-instant-student : subject picker → searching countdown → straight into the room.
 *  - #nit-instant-teacher : "Available now" switch.
 *  - A student with an open request, on any other page: a floating box (searching / the tutor who
 *    accepted — Start lesson / Decline), so they can look at the tutor's profile and still confirm.
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

    function stars(r, n) {
        return n ? '★ ' + r.toFixed(1) + ' (' + n + ')' : esc(S.norating);
    }

    // The corner where prompts and the student's request box stack (newest at the bottom).
    function floatHost() {
        var host = document.getElementById('nit-ix-stack');
        if (!host) {
            host = el('<div id="nit-ix-stack" class="nit-ix-stack"></div>');
            document.body.appendChild(host);
        }
        return host;
    }

    // A countdown bar: shrinks from full to empty over `seconds`.
    function bar(seconds) {
        return '<div class="nit-ix-bar"><span style="animation-duration:' + Math.max(1, seconds) + 's"></span></div>';
    }

    // The tutor who accepted, with Start lesson / Decline (used in the page and in the floating box).
    function acceptedHtml(st) {
        var t = st.teacher;
        return '<div class="nit-ix-head nit-ix-head--ok"><span class="nit-ix-icon">✓</span><strong>' +
            esc(S.acceptedtitle.replace('{$a}', t.name)) + '</strong></div><div class="nit-ix-body">' +
            '<div class="nit-ix-meta"><span>' + stars(t.rating, t.ratingcount) + '</span><span class="nit-ix-chip">' +
            esc(S.flex.replace('{$a}', t.flex)) + '</span><span>' + esc(S.minutes.replace('{$a}', st.duration)) +
            '</span><a href="' + esc(t.profileurl) + '">' + esc(S.profile) + '</a></div>' +
            '<p class="nit-ix-muted">' + esc(S.confirmin.replace('{$a}', st.confirmsecondsleft)) + '</p>' +
            '<div class="nit-ix-actions"><button class="btn btn-success" data-confirm>' + esc(S.startlesson) +
            '</button><button class="btn btn-outline-secondary" data-reject>' + esc(S.decline) + '</button></div>' +
            '<div class="nit-ix-error" data-error></div></div>' + bar(st.confirmsecondsleft);
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

    // Start lesson / Decline on an accepted box. `after` reloads the state when confirming fails.
    function bindAccepted(root, st, after) {
        root.querySelector('[data-confirm]').addEventListener('click', function(e) {
            e.target.disabled = true;
            call('confirm', {requestid: st.id}).then(function(d) {
                window.location.href = d.joinurl;
            }).catch(function(err) {
                e.target.disabled = false;
                root.querySelector('[data-error]').textContent = err.message;
                setTimeout(after, 2500);
            });
        });
        root.querySelector('[data-reject]').addEventListener('click', function(e) {
            e.target.disabled = true;
            call('reject', {requestid: st.id}).then(after, after);
        });
    }

    // ── Student ────────────────────────────────────────────────────────────
    function student(root) {
        var timer = null;
        var state = null;
        var current = 0;
        var form = {year: '', subject: '', mode: 'normal', maxflex: 0, note: '', autostart: false};

        function card(inner, cls) {
            return '<div class="nit-instant card p-3 mb-3 ' + (cls || '') + '">' + inner + '</div>';
        }

        // ── 1. Choose year, subject, kind, max price; see the tutors ──
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
            form.year = form.year || st.years.current || '';
            var subjects = st.all.map(function(x) {
                var online = (st.subjects.filter(function(o) {
                    return o.subject === x.subject;
                })[0] || {}).teachers || 0;
                return '<option value="' + esc(x.subject) + '"' + (x.subject === form.subject ? ' selected' : '') + '>' +
                    esc(x.label) + (online ? ' (' + esc(S.online.replace('{$a}', online)) + ')' : '') + '</option>';
            }).join('');
            var years = '<option value="">' + esc(S.anyyear) + '</option>' + st.years.years.map(function(y) {
                return '<option value="' + esc(y.key) + '"' + (y.key === form.year ? ' selected' : '') + '>' +
                    esc(y.name) + '</option>';
            }).join('');
            var recent = st.recent ? card('<strong>' + esc(S.recenttitle.replace('{$a}', st.recent.teachername)) +
                '</strong><div class="d-flex flex-wrap gap-2 mt-2">' +
                (st.recent.rateurl && !st.recent.rated ? '<a class="btn btn-primary btn-sm" href="' +
                    esc(st.recent.rateurl) + '">' + esc(S.rate) + '</a>' : '') +
                '<a class="btn btn-outline-primary btn-sm" href="' + esc(st.recent.bookagainurl) + '">' +
                esc(S.bookagain) + '</a><button class="btn btn-outline-secondary btn-sm" data-fav="' +
                st.recent.teacherid + '">' + (st.recent.favourite ? '★ ' : '☆ ') + esc(S.favourite) + '</button></div>',
                'border-success') : '';
            root.innerHTML = recent + card('<h3 class="h5">' + esc(S.title) + '</h3>' +
                '<p class="text-muted mb-2">' + esc(S.intro) + '</p>' +
                (st.trial ? '<p class="alert alert-info py-2 mb-2">' + esc(S.trialnotice.replace('{$a}', st.trialflex)) +
                    '</p>' : '') +
                (st.peak ? '<p class="small text-warning mb-2">' + esc(S.peaknotice) + '</p>' : '') +
                '<div class="row g-2 align-items-end">' +
                '<div class="col-sm-3"><label class="form-label small mb-0">' + esc(S.year) + '</label>' +
                '<select class="form-select" data-year>' + years + '</select></div>' +
                '<div class="col-sm-3"><label class="form-label small mb-0">' + esc(S.subject) + '</label>' +
                '<select class="form-select" data-subject><option value="">' + esc(S.picksubject) + '</option>' +
                subjects + '</select></div>' +
                (st.quick && !st.trial ? '<div class="col-sm-3"><label class="form-label small mb-0">' + esc(S.kind) +
                    '</label><select class="form-select" data-mode><option value="normal">' +
                    esc(S.modenormal.replace('{$a}', st.minutes)) + '</option><option value="quick"' +
                    (form.mode === 'quick' ? ' selected' : '') + '>' + esc(S.modequick.replace('{$a}', st.quickminutes)) +
                    '</option></select></div>' : '') +
                '<div class="col-sm-3"><label class="form-label small mb-0">' + esc(S.maxprice) + '</label>' +
                '<input type="number" min="0" max="' + st.flexbalance + '" class="form-control" data-max placeholder="' +
                esc(S.any) + '" value="' + (form.maxflex || '') + '"></div></div>' +
                '<div class="row g-2 mt-1"><div class="col-sm-8"><input class="form-control" data-note placeholder="' +
                esc(S.noteph) + '" value="' + esc(form.note) + '"></div><div class="col-sm-4">' +
                '<label class="btn btn-outline-secondary w-100 mb-0">' + esc(S.photo) +
                '<input type="file" accept="image/*" capture="environment" hidden data-photo></label></div></div>' +
                '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="nit-instant-auto" data-auto' +
                (form.autostart ? ' checked' : '') + '><label class="form-check-label small" for="nit-instant-auto">' +
                esc(S.autostart) + '</label></div>' +
                '<div class="text-danger mt-2" data-error></div><div data-list class="mt-3"></div>');
            bindForm();
            if (form.subject) {
                loadTutors();
            }
        }

        function readForm() {
            var mode = root.querySelector('[data-mode]');
            form.year = root.querySelector('[data-year]').value;
            form.subject = root.querySelector('[data-subject]').value;
            form.mode = mode ? mode.value : 'normal';
            form.maxflex = parseInt(root.querySelector('[data-max]').value, 10) || 0;
            form.note = root.querySelector('[data-note]').value;
            form.autostart = root.querySelector('[data-auto]').checked;
        }

        function bindForm() {
            ['[data-year]', '[data-subject]', '[data-mode]', '[data-max]'].forEach(function(sel) {
                var el2 = root.querySelector(sel);
                if (el2) {
                    el2.addEventListener('change', function() {
                        readForm();
                        if (sel === '[data-max]' && form.maxflex > 0) {
                            form.autostart = true; // A capped price was agreed in advance: start right away.
                            root.querySelector('[data-auto]').checked = true;
                        }
                        if (sel === '[data-mode]' && form.mode === 'quick') {
                            form.autostart = true;
                            root.querySelector('[data-auto]').checked = true;
                        }
                        loadTutors();
                    });
                }
            });
            root.querySelectorAll('[data-fav]').forEach(bindFav);
        }

        function bindFav(b) {
            b.addEventListener('click', function() {
                call('favourite', {teacherid: b.getAttribute('data-fav')}).then(function(r) {
                    b.textContent = (r.favourite ? '★ ' : '☆ ') + S.favourite;
                });
            });
        }

        function loadTutors() {
            var list = root.querySelector('[data-list]');
            if (!form.subject) {
                list.innerHTML = '';
                return;
            }
            list.innerHTML = '<p class="text-muted">…</p>';
            call('tutors', {subject: form.subject, year: form.year, mode: form.mode, maxflex: form.maxflex})
                .then(function(d) {
                    var online = d.tutors.filter(function(t) {
                        return t.online;
                    }).length;
                    var html = online ? '<button class="btn btn-primary mb-3" data-askall>' +
                        esc(S.askall.replace('{$a}', online)) + '</button>' :
                        '<p class="text-muted">' + esc(S.nobodyonline) + '</p>';
                    html += d.tutors.length ? d.tutors.map(tutorCard).join('') : '<p class="text-muted">' +
                        esc(S.notutors) + '</p>';
                    list.innerHTML = html;
                    var all = list.querySelector('[data-askall]');
                    if (all) {
                        all.addEventListener('click', function() {
                            ask(0, all);
                        });
                    }
                    list.querySelectorAll('[data-ask]').forEach(function(b) {
                        b.addEventListener('click', function() {
                            ask(parseInt(b.getAttribute('data-ask'), 10), b);
                        });
                    });
                    list.querySelectorAll('[data-watchtutor]').forEach(function(b) {
                        b.addEventListener('click', function() {
                            call('favourite', {teacherid: b.getAttribute('data-watchtutor')}).then(function(r) {
                                b.textContent = r.favourite ? S.notifyset : S.notifyme;
                            });
                        });
                    });
                    list.querySelectorAll('[data-fav]').forEach(bindFav);
                })
                .catch(function(err) {
                    list.innerHTML = '<p class="text-danger">' + esc(err.message) + '</p>';
                });
        }

        function tutorCard(t) {
            var status = t.online ? '<span class="badge bg-success">' + esc(S.availablenow) + '</span>' :
                (t.busy ? '<span class="badge bg-secondary">' + esc(S.inlesson) + '</span>' :
                    '<span class="badge bg-light text-dark">' + esc(S.offline) + '</span>');
            var actions = t.online && !t.busy ?
                '<button class="btn btn-success btn-sm" data-ask="' + t.id + '">' + esc(S.askthis) + '</button>' :
                '<a class="btn btn-outline-primary btn-sm" href="' + esc(t.bookurl) + '">' + esc(S.booklater) + '</a>' +
                '<button class="btn btn-outline-secondary btn-sm" data-watchtutor="' + t.id + '">' +
                esc(t.favourite ? S.notifyset : S.notifyme) + '</button>';
            return '<div class="card p-2 mb-2"><div class="d-flex gap-3 align-items-center">' +
                '<img src="' + esc(t.pictureurl) + '" alt="" width="56" height="56" style="border-radius:50%">' +
                '<div class="flex-grow-1"><div><strong>' + esc(t.name) + '</strong> ' + status + '</div>' +
                '<div class="small text-muted"><bdi>' + esc(t.headline) + '</bdi></div>' +
                '<div class="small">' + stars(t.rating, t.ratingcount) +
                (t.reliability !== null ? ' · ⚡ ' + esc(S.reliability.replace('{$a}', t.reliability)) : '') +
                ' · <strong>' + esc(S.flex.replace('{$a}', t.flex)) + '</strong></div>' +
                (t.usually ? '<div class="small text-muted">' + esc(t.usually) + '</div>' : '') +
                '</div><div class="d-flex flex-column gap-1 align-items-end">' + actions +
                '<div class="d-flex gap-1"><a class="btn btn-link btn-sm p-0" href="' + esc(t.profileurl) +
                '" target="_blank">' + esc(S.profile) + '</a><button class="btn btn-link btn-sm p-0" data-fav="' + t.id + '">' +
                (t.favourite ? '★' : '☆') + '</button></div></div></div></div>';
        }

        function ask(teacherid, button) {
            readForm();
            var data = new FormData();
            data.append('subject', form.subject);
            data.append('note', form.note);
            data.append('mode', form.mode);
            data.append('maxflex', form.maxflex);
            data.append('autostart', form.autostart ? 1 : 0);
            data.append('teacherid', teacherid || 0);
            var photo = root.querySelector('[data-photo]');
            if (photo && photo.files.length) {
                data.append('photo', photo.files[0]);
            }
            button.disabled = true;
            call('request', data).then(searching).catch(function(err) {
                button.disabled = false;
                root.querySelector('[data-error]').textContent = err.message;
            });
        }

        // ── 2. Waiting, the tutor who accepted, the result ──
        function searching(st) {
            clearTimeout(timer);
            current = st.id;
            if (st.status === 'matched' && st.lessonid) {
                root.innerHTML = card('<strong>' + esc(S.matched) + '</strong>', 'border-success');
                window.location.href = C.joinurl + st.lessonid;
                return;
            }
            if (st.status === 'accepted' && st.teacher) {
                var key = st.id + ':' + st.teacher.id;
                if (root.getAttribute('data-shown') !== key) { // Keep the countdown bar running between polls.
                    root.setAttribute('data-shown', key);
                    root.innerHTML = '<div class="nit-ix-box">' + acceptedHtml(st) + '</div>';
                    bindAccepted(root, st, load);
                }
                timer = setTimeout(function() {
                    call('status', {requestid: st.id}).then(searching).catch(load);
                }, live ? 10000 : 2000);
                return;
            }
            root.removeAttribute('data-shown');
            if (st.status !== 'searching') {
                root.innerHTML = card('<h3 class="h5">' + esc(st.status === 'cancelled' ? S.cancelled : S.expired) +
                    '</h3><p class="text-muted mb-2">' + esc(st.targeted ? S.targetexpired : S.expiredhelp) + '</p>' +
                    '<button class="btn btn-primary btn-sm" data-again>' + esc(st.targeted ? S.askallinstead : S.again) +
                    '</button>');
                root.querySelector('[data-again]').addEventListener('click', load);
                return;
            }
            root.innerHTML = '<div class="nit-ix-box"><div class="nit-ix-head"><span class="nit-ix-icon nit-ix-spin">◌</span>' +
                '<strong>' + esc(st.targeted ? S.askingone : S.searching) + '</strong><span class="nit-ix-timer">' +
                st.secondsleft + 's</span></div><div class="nit-ix-body"><div class="nit-ix-meta"><span><bdi>' +
                esc(st.subject) + '</bdi></span><span>' + esc(S.asking.replace('{$a}', st.teachers)) + '</span></div>' +
                '<div class="nit-ix-actions"><button class="btn btn-outline-secondary btn-sm" data-cancel>' + esc(S.cancel) +
                '</button></div></div><div class="nit-ix-bar nit-ix-bar--busy"><span></span></div></div>';
            root.querySelector('[data-cancel]').addEventListener('click', function() {
                call('cancel', {requestid: st.id}).then(searching);
            });
            timer = setTimeout(function() {
                call('status', {requestid: st.id}).then(searching).catch(load);
            }, live ? 10000 : 2000);
        }

        onSignal(function(p) {
            if (p.what === 'status' && current && p.requestid === current) {
                clearTimeout(timer);
                call('status', {requestid: current}).then(searching).catch(load);
            }
        });

        function load() {
            current = 0;
            call('subjects').then(render).catch(function() {
                root.innerHTML = '';
            });
        }
        load();
    }

    // ── Student, any other page: the open request in a floating box ─────────
    function studentFloat(requestid) {
        var box = null;
        var timer = null;
        var shownkey = '';

        function close() {
            clearTimeout(timer);
            if (box) {
                box.remove();
                box = null;
            }
        }

        // Draw a state; the same state again keeps the box (and its countdown bar) as it is.
        function show(html, key) {
            if (key === shownkey && box) {
                return false;
            }
            shownkey = key;
            if (!box) {
                box = el('<div class="nit-ix-box nit-ix-float" role="alertdialog"></div>');
                floatHost().appendChild(box);
            }
            box.innerHTML = html;
            var x = box.querySelector('[data-close]');
            if (x) {
                x.addEventListener('click', close);
            }
            return true;
        }

        function next() {
            timer = setTimeout(refresh, live ? 10000 : 3000);
        }

        function refresh() {
            clearTimeout(timer);
            call('status', {requestid: requestid}).then(render).catch(close);
        }

        function closeBtn() {
            return '<button type="button" class="nit-ix-x" data-close aria-label="' + esc(S.close) + '">×</button>';
        }

        function render(st) {
            if (st.status === 'accepted' && st.teacher) {
                if (show(acceptedHtml(st), 'a' + st.teacher.id)) {
                    beep();
                    bindAccepted(box, st, refresh);
                }
                next();
            } else if (st.status === 'searching') {
                if (show('<div class="nit-ix-head"><span class="nit-ix-icon nit-ix-spin">◌</span><strong>' +
                        esc(st.targeted ? S.askingone : S.searching) + '</strong></div><div class="nit-ix-body">' +
                        '<div class="nit-ix-meta"><span><bdi>' + esc(st.subject) + '</bdi></span><span>' +
                        esc(S.asking.replace('{$a}', st.teachers)) + '</span></div><div class="nit-ix-actions">' +
                        '<a class="btn btn-outline-primary btn-sm" href="' + esc(C.bookurl) + '">' + esc(S.backtorequest) +
                        '</a><button class="btn btn-outline-secondary btn-sm" data-cancel>' + esc(S.cancel) +
                        '</button></div></div>', 's' + st.teachers)) {
                    box.querySelector('[data-cancel]').addEventListener('click', function(e) {
                        e.target.disabled = true;
                        call('cancel', {requestid: requestid}).then(render, close);
                    });
                }
                next();
            } else if (st.status === 'matched' && st.lessonid) {
                // This tab is in front: go in. A tab in the background only offers the link (no double join).
                if (!document.hidden) {
                    window.location.href = C.joinurl + st.lessonid;
                    return;
                }
                show('<div class="nit-ix-head nit-ix-head--ok"><span class="nit-ix-icon">✓</span><strong>' +
                    esc(S.matchedhere) + '</strong>' + closeBtn() + '</div><div class="nit-ix-body"><div class="nit-ix-actions">' +
                    '<a class="btn btn-success" href="' + esc(C.joinurl + st.lessonid) + '">' + esc(S.joinnow) +
                    '</a></div></div>', 'm');
            } else {
                show('<div class="nit-ix-head nit-ix-head--off"><strong>' +
                    esc(st.status === 'cancelled' ? S.cancelled : S.expired) + '</strong>' + closeBtn() + '</div>' +
                    '<div class="nit-ix-body"><p class="nit-ix-muted">' + esc(st.targeted ? S.targetexpired : S.expiredhelp) +
                    '</p><div class="nit-ix-actions"><a class="btn btn-primary btn-sm" href="' + esc(C.bookurl) + '">' +
                    esc(st.targeted ? S.askallinstead : S.again) + '</a></div></div>', 'x');
            }
        }

        onSignal(function(p) {
            if (p.what === 'status' && p.requestid === requestid) {
                refresh();
            }
        });
        refresh();
    }

    // ── Teacher switch ─────────────────────────────────────────────────────
    function teacherSwitch(root) {
        function draw(online) {
            root.innerHTML = '<div class="nit-ix-avail' + (online ? ' is-on' : '') + '">' +
                '<div class="nit-ix-avail__main"><span class="nit-ix-avail__icon" aria-hidden="true">⚡</span>' +
                '<div class="nit-ix-avail__text"><label class="nit-ix-avail__title" for="nit-instant-online">' +
                esc(S.available) + '</label><span class="nit-ix-avail__status"><i></i><span data-statustext>' +
                esc(online ? S.onlinestatus : S.offlinestatus) + '</span></span></div>' +
                '<div class="form-check form-switch nit-ix-switch"><input class="form-check-input" type="checkbox" ' +
                'role="switch" id="nit-instant-online"' + (online ? ' checked' : '') + '></div></div>' +
                '<p class="nit-ix-muted">' + esc(S.availablehelp) + '</p>' +
                '<div class="nit-ix-avail__oncall"><div class="form-check form-switch"><input class="form-check-input" ' +
                'type="checkbox" role="switch" id="nit-instant-oncall"' + (C.oncall ? ' checked' : '') + '>' +
                '<label class="form-check-label" for="nit-instant-oncall">' + esc(S.oncall) + '</label></div>' +
                '<div class="nit-ix-muted small">' + esc(S.oncallhelp) + '</div></div>' +
                '<div class="nit-ix-error" data-error></div></div>';
            root.querySelector('#nit-instant-oncall').addEventListener('change', function(e) {
                call(e.target.checked ? 'oncall' : 'offcall').catch(function(err) {
                    e.target.checked = false;
                    root.querySelector('[data-error]').textContent = err.message;
                });
            });
            root.querySelector('#nit-instant-online').addEventListener('change', function(e) {
                var on = e.target.checked;
                call(on ? 'online' : 'offline').then(function() {
                    C.online = on;
                    root.querySelector('.nit-ix-avail').classList.toggle('is-on', on);
                    root.querySelector('[data-statustext]').textContent = on ? S.onlinestatus : S.offlinestatus;
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
            var box = document.querySelector('[data-instant-request="' + o.requestid + '"] [data-summary]');
            if (box && o.aisummary && !box.textContent) {
                box.textContent = o.aisummary;
            }
            return;
        }
        shown[o.requestid] = true;
        beep();
        if (document.hidden && window.Notification && Notification.permission === 'granted') {
            new Notification(S.prompttitle, {body: o.subject + ' · ' + o.studentname});
        }
        var box = el('<div class="nit-ix-box nit-ix-float nit-instant-prompt" role="alertdialog" data-instant-request="' +
            o.requestid + '"><div class="nit-ix-head"><span class="nit-ix-icon">⚡</span><strong>' + esc(S.prompttitle) +
            '</strong><span class="nit-ix-timer"><span data-left>' + o.secondsleft + '</span>s</span></div>' +
            '<div class="nit-ix-body"><div class="nit-ix-subject"><bdi>' + esc(o.subject) + '</bdi></div>' +
            '<div class="nit-ix-meta"><span><bdi>' + esc(o.studentname) + '</bdi></span><span>' +
            esc(S.minutes.replace('{$a}', o.duration)) + '</span><span class="nit-ix-chip">' +
            esc(S.flex.replace('{$a}', o.flex)) + '</span>' +
            (o.trial ? '<span class="nit-ix-chip nit-ix-chip--info">' + esc(S.trialbadge) + '</span>' : '') +
            (o.mode === 'quick' ? '<span class="nit-ix-chip nit-ix-chip--warn">' + esc(S.quickbadge) + '</span>' : '') +
            '</div><p class="nit-ix-summary"><bdi data-summary>' + esc(o.aisummary || '') + '</bdi></p>' +
            (o.note ? '<blockquote class="nit-ix-note"><bdi>' + esc(o.note) + '</bdi></blockquote>' : '') +
            (o.photourl ? '<a href="' + esc(o.photourl) + '" target="_blank" rel="noopener"><img class="nit-ix-photo" src="' +
                esc(o.photourl) + '" alt=""></a>' : '') +
            '<div class="nit-ix-actions"><button class="btn btn-success" data-accept>' + esc(S.accept) + '</button>' +
            '<button class="btn btn-outline-secondary" data-decline>' + esc(S.decline) + '</button></div>' +
            '<div class="nit-ix-error" data-error></div></div>' + bar(o.secondsleft) + '</div>');
        floatHost().appendChild(box);
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
                if (d.waiting) {
                    clearInterval(tick);
                    box.querySelector('.nit-ix-actions').remove();
                    box.querySelector('.nit-ix-timer').remove();
                    box.querySelector('.nit-ix-bar').remove();
                    box.querySelector('[data-error]').className = 'nit-ix-waiting';
                    box.querySelector('[data-error]').textContent = S.waitingstudent;
                    return;
                }
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
                if (d.started && d.started.length) {
                    window.location.href = d.started[0].joinurl; // The student confirmed.
                    return;
                }
                var open = {};
                (d.waiting || []).forEach(function(w) {
                    open[w.requestid] = true;
                });
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
        } else if (p.what === 'started') {
            call('poll').then(function(d) {
                if (d.started && d.started.length) {
                    window.location.href = d.started[0].joinurl;
                }
            });
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
        if (!s && C.request) {
            studentFloat(C.request);
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
