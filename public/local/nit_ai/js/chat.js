/**
 * The student's chat panel beside a course video.
 *
 * Conversation state lives here and nowhere else: it is held in this closure
 * for as long as the page is open, sent back with each question so follow-ups
 * make sense, and gone the moment the page closes. Nothing is stored server
 * side, by decision.
 *
 * @module     local_nit_ai/chat
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    /** Turns kept in the browser and replayed with each question. */
    var HISTORY_TURNS = 6;

    /**
     * A cited moment: [4:12], [1:02:05], or the same without the brackets.
     *
     * The prompt asks for brackets, but models drop them often enough that
     * relying on them loses the jump links exactly when the answer is good.
     * Bare timecodes are accepted and then checked against the video's length,
     * so a number that cannot be a moment in this video stays plain text.
     */
    var TIMECODE = /\[?\b((?:\d{1,2}:)?\d{1,2}:\d{2})\b\]?/g;

    /**
     * Call a Moodle web service.
     *
     * @param {string} methodname
     * @param {object} args
     * @return {Promise} resolves with the service's data
     */
    function callService(methodname, args) {
        var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey +
            '&info=' + encodeURIComponent(methodname);

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{index: 0, methodname: methodname, args: args}])
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            var first = payload && payload[0];
            if (!first || first.error) {
                throw new Error((first && (first.exception || {}).message) || 'request failed');
            }
            return first.data;
        });
    }

    /**
     * Escape text for insertion as HTML.
     *
     * @param {string} text
     * @return {string}
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Convert "M:SS" or "H:MM:SS" to a number of seconds.
     *
     * @param {string} stamp
     * @return {number}
     */
    function toSeconds(stamp) {
        var parts = stamp.split(':').map(function (p) {
            return parseInt(p, 10);
        });
        return parts.reduce(function (total, part) {
            return (total * 60) + part;
        }, 0);
    }

    /**
     * Render an answer: escape it, then turn [MM:SS] citations into buttons that
     * move the player. Without a player adapter they stay as plain text.
     *
     * @param {string} text
     * @param {object|null} player
     * @param {string} jumpLabel with a {time} placeholder
     * @return {string} HTML
     */
    function renderAnswer(text, player, jumpLabel) {
        var html = escapeHtml(text).replace(/\n/g, '<br>');

        if (!player) {
            return html;
        }

        var duration = player.getDuration ? player.getDuration() : 0;

        return html.replace(TIMECODE, function (match, stamp) {
            var total = toSeconds(stamp);

            // Past the end of the video it is a number, not a moment in it.
            if (duration && total > duration + 5) {
                return match;
            }

            return '<button type="button" class="nitai-jump" data-seek="' + total + '" title="' +
                escapeHtml(jumpLabel.replace('{time}', stamp)) + '">' + escapeHtml(stamp) + '</button>';
        });
    }

    /**
     * Wire up one chat panel.
     *
     * @param {HTMLElement} root
     */
    function initPanel(root) {
        var cmid = parseInt(root.getAttribute('data-cmid'), 10);
        var adapter = root.getAttribute('data-adapter') || '';
        var strings = {
            thinking: root.getAttribute('data-str-thinking') || '',
            error: root.getAttribute('data-str-error') || '',
            jump: root.getAttribute('data-str-jump') || '{time}'
        };

        var log = root.querySelector('[data-region="log"]');
        var form = root.querySelector('[data-region="form"]');
        var input = root.querySelector('[data-region="input"]');
        var send = root.querySelector('[data-region="send"]');

        var history = [];
        var player = null;
        var busy = false;

        if (adapter && window.NITAI && window.NITAI.players && window.NITAI.players[adapter]) {
            window.NITAI.players[adapter]().then(function (instance) {
                player = instance;
            });
        }

        /**
         * Add a message to the visible log.
         *
         * @param {string} role user|assistant|error|pending
         * @param {string} text
         * @return {HTMLElement} the added element
         */
        function addMessage(role, text) {
            var intro = log.querySelector('.nitai-chat-intro');
            if (intro) {
                intro.remove();
            }

            var item = document.createElement('div');
            item.className = 'nitai-msg nitai-msg-' + role;

            if (role === 'assistant') {
                item.innerHTML = renderAnswer(text, player, strings.jump);
            } else {
                item.textContent = text;
            }

            log.appendChild(item);
            log.scrollTop = log.scrollHeight;
            return item;
        }

        /**
         * Ask the assistant, then render whatever comes back.
         *
         * @param {string} question
         */
        function ask(question) {
            busy = true;
            send.disabled = true;

            addMessage('user', question);
            var pending = addMessage('pending', strings.thinking);

            callService('local_nit_ai_ask', {
                cmid: cmid,
                question: question,
                currenttime: player ? player.getCurrentTime() : -1,
                history: history.slice(-HISTORY_TURNS)
            }).then(function (data) {
                pending.remove();
                if (!data.success) {
                    addMessage('error', data.error || strings.error);
                    return;
                }
                addMessage('assistant', data.answer);
                history.push({role: 'user', text: question});
                history.push({role: 'assistant', text: data.answer});
            }).catch(function () {
                pending.remove();
                addMessage('error', strings.error);
            }).then(function () {
                busy = false;
                send.disabled = false;
                input.focus();
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var question = input.value.trim();
            if (!question || busy) {
                return;
            }
            input.value = '';
            input.style.height = '';
            ask(question);
        });

        // Enter sends, Shift+Enter makes a new line.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit', {cancelable: true}));
            }
        });

        // Grow the box with the question instead of scrolling a one-line field.
        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        });

        log.addEventListener('click', function (e) {
            var button = e.target.closest('.nitai-jump');
            if (button && player) {
                player.seekTo(parseInt(button.getAttribute('data-seek'), 10));
            }
        });
    }

    /**
     * Find every chat panel on the page and wire it up.
     */
    function init() {
        var panels = document.querySelectorAll('[data-region="nitai-chat"]');
        Array.prototype.forEach.call(panels, function (panel) {
            if (!panel.hasAttribute('data-ready')) {
                panel.setAttribute('data-ready', '1');
                initPanel(panel);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
