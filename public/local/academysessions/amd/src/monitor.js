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
 * The live monitoring wall (monitor.php): refreshes the cards every few seconds
 * without reloading the page, and opens one lesson in a larger view (a modal that
 * keeps refreshing while it is open).
 *
 * - Refreshing stops while the browser tab is hidden and resumes when it is back.
 * - A failed refresh keeps the last cards on screen and says so; the next one tries again.
 *
 * No build step: amd/build/monitor.min.js is a plain copy of this file.
 *
 * @module     local_academysessions/monitor
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('local_academysessions/monitor', ['core/templates', 'core/modal'], function(TemplatesModule, ModalModule) {

    var Templates = TemplatesModule.default || TemplatesModule;
    var Modal = ModalModule.default || ModalModule;

    /**
     * GET the endpoint as JSON.
     *
     * @param {Object} cfg the page config
     * @param {Object} params query parameters
     * @return {Promise<Object>}
     */
    var get = function(cfg, params) {
        var query = new URLSearchParams(Object.assign({sesskey: cfg.sesskey}, params));
        return fetch(cfg.url + '?' + query.toString(), {credentials: 'same-origin', cache: 'no-store'})
            .then(function(response) {
                return response.json().then(function(data) {
                    if (!response.ok || data.status !== 'ok') {
                        throw new Error(data.error || response.statusText);
                    }
                    return data;
                });
            });
    };

    /**
     * Start the wall.
     *
     * @param {Object} cfg {url, sesskey, params, refresh (seconds), strings: {failed, paused, detailfailed}}
     */
    var init = function(cfg) {
        var root = document.querySelector('[data-region="local_academysessions-monitor"]');
        if (!root) {
            return;
        }
        var cards = root.querySelector('[data-region="cards"]');
        var error = root.querySelector('[data-region="error"]');
        var timer = null;
        var busy = false;
        var modal = null;
        var openid = 0;

        // The date field only matters for the "day" view.
        var view = root.querySelector('[data-action="view"]');
        var date = root.querySelector('[data-region="date"]');
        if (view && date) {
            view.addEventListener('change', function() {
                date.hidden = view.value !== 'day';
            });
        }

        var showError = function(message) {
            error.textContent = message;
            error.hidden = !message;
        };

        var refreshDetail = function() {
            if (!modal || !openid) {
                return Promise.resolve();
            }
            return get(cfg, {action: 'detail', sessionid: openid, logged: 1}).then(function(data) {
                return Templates.render('local_academysessions/monitor_detail', data.detail);
            }).then(function(html) {
                if (modal) {
                    modal.setBody(html);
                }
                return null;
            }).catch(function() {
                // Keep the last view; the wall shows the error.
            });
        };

        var refresh = function() {
            if (busy || document.hidden) {
                return;
            }
            busy = true;
            get(cfg, Object.assign({action: 'wall'}, cfg.params)).then(function(data) {
                return Templates.renderForPromise('local_academysessions/monitor_cards', data.wall);
            }).then(function(out) {
                Templates.replaceNodeContents(cards, out.html, out.js);
                showError('');
                return refreshDetail();
            }).catch(function() {
                showError(cfg.strings.failed);
            }).then(function() {
                busy = false;
                return null;
            });
        };

        var schedule = function() {
            clearInterval(timer);
            timer = setInterval(refresh, Math.max(5, cfg.refresh) * 1000);
        };

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(timer);
            } else {
                refresh();
                schedule();
            }
        });

        // Open one lesson in the larger view.
        cards.addEventListener('click', function(e) {
            var button = e.target.closest('[data-action="detail"]');
            if (!button) {
                return;
            }
            var sessionid = parseInt(button.getAttribute('data-sessionid'), 10);
            var title = button.closest('.nitmon-card').querySelector('.nitmon-title').textContent;
            get(cfg, {action: 'detail', sessionid: sessionid}).then(function(data) {
                return Modal.create({
                    title: title,
                    body: Templates.render('local_academysessions/monitor_detail', data.detail),
                    large: true,
                    removeOnClose: true,
                    show: true,
                });
            }).then(function(created) {
                modal = created;
                openid = sessionid;
                modal.getRoot().on('modal:hidden', function() {
                    modal = null;
                    openid = 0;
                    button.focus();
                });
                return null;
            }).catch(function() {
                showError(cfg.strings.detailfailed);
            });
        });

        schedule();
    };

    return {init: init};
});
