// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The AI assistant chat widget: a button on every page that opens a chat panel
 * (a full-screen sheet on phones). Replies stream from chat.php; while a team
 * member has the conversation, new messages are fetched from api.php.
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var KEY_STORE = 'nitAsstKey';
    var POLL_OPEN = 5000;
    var POLL_CLOSED = 30000;

    var root = document.getElementById('nit-assistant');
    if (!root || root.dataset.ready) {
        return;
    }
    root.dataset.ready = '1';
    var cfg = JSON.parse(root.dataset.config || '{}');
    var S = cfg.strings || {};

    var state = {open: false, loaded: false, busy: false, status: null, lastId: 0, seen: {}, timer: null, unread: false};

    /**
     * The browser's visitor key (kept in localStorage; a new one if storage is blocked).
     *
     * @returns {string}
     */
    function visitorKey() {
        var key = null;
        try {
            key = window.localStorage.getItem(KEY_STORE);
        } catch (e) {
            key = null;
        }
        if (!key || !/^[A-Za-z0-9]{32,64}$/.test(key)) {
            var bytes = new Uint8Array(24);
            window.crypto.getRandomValues(bytes);
            key = Array.prototype.map.call(bytes, function(b) {
                return ('0' + b.toString(16)).slice(-2);
            }).join('');
            try {
                window.localStorage.setItem(KEY_STORE, key);
            } catch (e) {
                // Private window: the chat lasts for this page only.
            }
        }
        return key;
    }
    var key = visitorKey();

    // ── Building the widget ────────────────────────────────────────────────

    /**
     * Create an element.
     *
     * @param {string} tag
     * @param {string} cls
     * @param {Object} attrs
     * @returns {HTMLElement}
     */
    function el(tag, cls, attrs) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        Object.keys(attrs || {}).forEach(function(k) {
            node.setAttribute(k, attrs[k]);
        });
        return node;
    }

    var ICON_CHAT = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4H4'
        + 'a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="currentColor"/></svg>';
    var ICON_CLOSE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" '
        + 'stroke-width="2" stroke-linecap="round"/></svg>';
    var ICON_NEW = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" '
        + 'stroke-width="2" stroke-linecap="round"/></svg>';
    var ICON_SEND = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20l18-8L3 4v6l12 2-12 2z" '
        + 'fill="currentColor"/></svg>';

    var fab = el('button', 'nit-asst-fab', {type: 'button', 'aria-label': S.chat_open, 'aria-expanded': 'false'});
    fab.innerHTML = ICON_CHAT + '<span class="nit-asst-dot" hidden></span>';
    var panel = el('section', 'nit-asst-panel', {role: 'dialog', 'aria-label': S.chat_title, hidden: ''});
    var head = el('header', 'nit-asst-head');
    var title = el('h2', 'nit-asst-title');
    title.textContent = S.chat_title;
    var btnNew = el('button', 'nit-asst-icon', {type: 'button', 'aria-label': S.chat_new, title: S.chat_new});
    btnNew.innerHTML = ICON_NEW;
    var btnClose = el('button', 'nit-asst-icon', {type: 'button', 'aria-label': S.chat_close, title: S.chat_close});
    btnClose.innerHTML = ICON_CLOSE;
    head.append(title, btnNew, btnClose);
    var note = el('p', 'nit-asst-note');
    note.textContent = S.chat_ai_note;
    var log = el('div', 'nit-asst-log', {'aria-live': 'polite'});
    var form = el('form', 'nit-asst-form');
    var input = el('textarea', 'nit-asst-input', {rows: '1', maxlength: String(cfg.maxlength || 2000),
        placeholder: S.chat_placeholder, 'aria-label': S.chat_placeholder});
    var send = el('button', 'nit-asst-send', {type: 'submit', 'aria-label': S.chat_send});
    send.innerHTML = ICON_SEND;
    form.append(input, send);
    panel.append(head, note, log, form);
    root.append(fab, panel);

    // ── Messages ───────────────────────────────────────────────────────────

    /**
     * Escape text for HTML.
     *
     * @param {string} text
     * @returns {string}
     */
    function esc(text) {
        return String(text).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    /**
     * Light formatting of a reply: links, **bold**, bullet lines, line breaks.
     *
     * @param {string} text
     * @returns {string}
     */
    function format(text) {
        var html = esc(text);
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function(m, label, url) {
            return '<a href="' + url + '" rel="noopener">' + label + '</a>';
        });
        html = html.replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, function(m, pre, url) {
            return pre + '<a href="' + url + '" rel="noopener">' + url + '</a>';
        });
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/^\s*[-•]\s+/gm, '• ');
        return html.replace(/\n/g, '<br>');
    }

    /**
     * Add a bubble.
     *
     * @param {string} role visitor | assistant | staff | system
     * @param {string} text
     * @param {number} [id] stored message id (skips duplicates)
     * @returns {HTMLElement|null}
     */
    function bubble(role, text, id) {
        if (id) {
            if (state.seen[id]) {
                return null;
            }
            state.seen[id] = true;
            state.lastId = Math.max(state.lastId, id);
        }
        var node = el('div', 'nit-asst-msg nit-asst-' + role);
        if (role === 'staff') {
            var who = el('span', 'nit-asst-who');
            who.textContent = S.chat_team;
            node.appendChild(who);
        }
        var body = el('div', 'nit-asst-body', {dir: 'auto'}); // Arabic and English each read the right way.
        body.innerHTML = format(text);
        node.appendChild(body);
        log.appendChild(node);
        log.scrollTop = log.scrollHeight;
        return node;
    }

    /**
     * Show a status line (handoff, errors).
     *
     * @param {string} text
     * @param {boolean} [error]
     */
    function notice(text, error) {
        var node = el('div', 'nit-asst-notice' + (error ? ' nit-asst-error' : ''), {role: error ? 'alert' : 'status'});
        node.textContent = text;
        log.appendChild(node);
        log.scrollTop = log.scrollHeight;
    }

    /**
     * Buttons under a reply.
     *
     * @param {HTMLElement} node
     * @param {Object} action {label, url}
     */
    function addAction(node, action) {
        if (!/^https?:\/\//.test(action.url)) {
            return;
        }
        var bar = node.querySelector('.nit-asst-actions');
        if (!bar) {
            bar = el('div', 'nit-asst-actions');
            node.appendChild(bar);
        }
        var a = el('a', 'nit-asst-action', {href: action.url});
        a.textContent = action.label;
        bar.appendChild(a);
    }

    /**
     * The message shown for an error code.
     *
     * @param {string} code
     * @returns {string}
     */
    function errorText(code) {
        var map = {busy: S.chat_error_busy, full: S.chat_error_full, ratelimited: S.chat_error_ratelimited,
            session: S.chat_error_session, unavailable: S.msg_fallback, budget_exhausted: S.msg_fallback,
            model_unavailable: S.msg_fallback};
        return map[code] || S.chat_error_generic;
    }

    // ── Talking to the server ──────────────────────────────────────────────

    /**
     * Set the conversation status and poll while a person has it.
     *
     * @param {string|null} status
     */
    function setStatus(status) {
        state.status = status;
        root.classList.toggle('nit-asst-withteam', status === 'waiting_human' || status === 'human');
        schedulePoll();
    }

    /**
     * (Re)start polling when the team has the conversation.
     */
    function schedulePoll() {
        clearTimeout(state.timer);
        if (state.status !== 'waiting_human' && state.status !== 'human') {
            return;
        }
        state.timer = setTimeout(poll, state.open ? POLL_OPEN : POLL_CLOSED);
    }

    /**
     * Fetch new messages (team replies).
     *
     * @returns {Promise}
     */
    function poll() {
        var url = cfg.api + '?action=history&sesskey=' + encodeURIComponent(M.cfg.sesskey)
            + '&key=' + key + '&after=' + state.lastId;
        return fetch(url, {credentials: 'same-origin'}).then(function(r) {
            return r.json();
        }).then(function(data) {
            var fresh = false;
            (data.messages || []).forEach(function(m) {
                if (bubble(m.role, m.text, m.id) && m.role !== 'visitor') {
                    fresh = true;
                }
            });
            if (fresh && !state.open) {
                state.unread = true;
                fab.querySelector('.nit-asst-dot').hidden = false;
            }
            setStatus(data.status);
        }).catch(function() {
            schedulePoll();
        });
    }

    /**
     * Load the conversation the first time the panel opens.
     */
    function load() {
        state.loaded = true;
        bubble('assistant', cfg.greeting);
        poll();
    }

    /**
     * Read a server-sent event stream.
     *
     * @param {Response} response
     * @param {Function} onEvent function(event, data)
     * @returns {Promise}
     */
    function readStream(response, onEvent) {
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';
        /**
         * Read the next chunk.
         *
         * @returns {Promise}
         */
        function pump() {
            return reader.read().then(function(chunk) {
                buffer += decoder.decode(chunk.value || new Uint8Array(), {stream: !chunk.done});
                var parts = buffer.split('\n\n');
                buffer = parts.pop();
                parts.forEach(function(block) {
                    var ev = 'message';
                    var data = '';
                    block.split('\n').forEach(function(line) {
                        if (line.indexOf('event: ') === 0) {
                            ev = line.slice(7);
                        } else if (line.indexOf('data: ') === 0) {
                            data += line.slice(6);
                        }
                    });
                    try {
                        onEvent(ev, data ? JSON.parse(data) : {});
                    } catch (e) {
                        // Ignore a malformed event.
                    }
                });
                return chunk.done ? null : pump();
            });
        }
        return pump();
    }

    /**
     * Send the typed message.
     *
     * @param {string} text
     */
    function sendMessage(text) {
        state.busy = true;
        send.disabled = true;
        bubble('visitor', text);
        var reply = bubble('assistant', '');
        reply.classList.add('nit-asst-typing');
        reply.querySelector('.nit-asst-body').textContent = S.chat_thinking;
        var replyText = '';
        var gotError = false;

        var body = new FormData();
        body.append('sesskey', M.cfg.sesskey);
        body.append('message', text);
        body.append('key', key);
        body.append('page', window.location.pathname + window.location.search);

        fetch(cfg.chat, {method: 'POST', body: body, credentials: 'same-origin'}).then(function(response) {
            if (!response.ok || !response.body) {
                throw new Error('HTTP ' + response.status);
            }
            return readStream(response, function(ev, data) {
                if (ev === 'stored') {
                    state.seen[data.id] = true;
                    state.lastId = Math.max(state.lastId, data.id);
                } else if (ev === 'delta') {
                    if (reply.classList.contains('nit-asst-typing')) {
                        reply.classList.remove('nit-asst-typing');
                    }
                    replyText += data.text;
                    reply.querySelector('.nit-asst-body').innerHTML = format(replyText);
                    log.scrollTop = log.scrollHeight;
                } else if (ev === 'action') {
                    addAction(reply, data);
                } else if (ev === 'handoff') {
                    setStatus(data.status);
                } else if (ev === 'queued') {
                    reply.remove();
                    notice(data.status === 'human' ? S.chat_human : S.chat_waiting);
                    setStatus(data.status);
                } else if (ev === 'done') {
                    if (data.messageid) {
                        state.seen[data.messageid] = true;
                        state.lastId = Math.max(state.lastId, data.messageid);
                    }
                } else if (ev === 'error') {
                    gotError = true;
                    reply.remove();
                    notice(errorText(data.code), true);
                }
            });
        }).catch(function() {
            if (!gotError) {
                reply.remove();
                notice(S.chat_error_generic, true);
            }
        }).then(function() {
            if (reply.isConnected && replyText === '' && !reply.querySelector('.nit-asst-actions')) {
                reply.remove();
            } else if (reply.isConnected) {
                reply.classList.remove('nit-asst-typing');
            }
            state.busy = false;
            send.disabled = false;
            input.focus();
        });
    }

    // ── Events ─────────────────────────────────────────────────────────────

    /**
     * Open or close the panel.
     *
     * @param {boolean} open
     */
    function toggle(open) {
        state.open = open;
        panel.hidden = !open;
        root.classList.toggle('nit-asst-isopen', open);
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            state.unread = false;
            fab.querySelector('.nit-asst-dot').hidden = true;
            if (!state.loaded) {
                load();
            }
            input.focus();
        } else {
            fab.focus();
        }
        schedulePoll();
    }

    fab.addEventListener('click', function() {
        toggle(!state.open);
    });
    btnClose.addEventListener('click', function() {
        toggle(false);
    });
    panel.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            toggle(false);
        }
    });
    btnNew.addEventListener('click', function() {
        if (state.busy) {
            return;
        }
        var body = new FormData();
        body.append('sesskey', M.cfg.sesskey);
        body.append('action', 'reset');
        body.append('key', key);
        fetch(cfg.api, {method: 'POST', body: body, credentials: 'same-origin'}).finally(function() {
            log.innerHTML = '';
            state.seen = {};
            state.lastId = 0;
            setStatus(null);
            bubble('assistant', cfg.greeting);
            input.focus();
        });
    });
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text || state.busy) {
            return;
        }
        input.value = '';
        input.style.height = '';
        sendMessage(text);
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });
    input.addEventListener('input', function() {
        input.style.height = '';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    // A conversation the team has keeps polling (slowly) even before the panel opens,
    // so a reply shows as a dot on the button.
    fetch(cfg.api + '?action=history&sesskey=' + encodeURIComponent(M.cfg.sesskey) + '&key=' + key + '&after='
        + Number.MAX_SAFE_INTEGER, {credentials: 'same-origin'}).then(function(r) {
        return r.json();
    }).then(function(data) {
        if (!state.loaded && (data.status === 'waiting_human' || data.status === 'human')) {
            load();
        }
    }).catch(function() {
        // The chat still works when opened.
    });
})();
