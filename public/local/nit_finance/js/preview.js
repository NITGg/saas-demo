/**
 * Free preview of a paid video lesson: once the free seconds have played (or
 * the student seeks past them), the player is removed and the end panel with
 * "Unlock the full lesson" is shown.
 *
 * Plain script (no AMD build in this codebase), like
 * local_nit_videoprogress/js/tracker.js. It reads its settings from the stage
 * printed by templates/preview_page.mustache and talks to the provider's player API:
 *  - VdoCipher: https://player.vdocipher.com/v2/api.js (VdoPlayer.getInstance)
 *  - Vimeo:     https://player.vimeo.com/api/player.js (Vimeo.Player)
 *
 * If the player API cannot be reached the preview fails closed: the player is
 * removed rather than left playing without a limit.
 *
 * @module     local_nit_finance/preview
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-nitfin-preview]');
    if (!root || root.getAttribute('data-nitfin-preview-started')) {
        return;
    }
    root.setAttribute('data-nitfin-preview-started', '1');

    var limit = parseFloat(root.getAttribute('data-seconds')) || 0;
    var provider = root.getAttribute('data-provider');
    var iframe = root.querySelector('iframe');
    var ended = false;

    /**
     * Remove the player and show the end panel.
     *
     * @param {boolean} failed the player API could not be reached
     */
    function end(failed) {
        if (ended) {
            return;
        }
        ended = true;
        if (iframe && iframe.parentNode) {
            iframe.parentNode.removeChild(iframe);
        }
        var box = root.querySelector('[data-nitfin-preview-end]');
        var done = root.querySelector('[data-nitfin-preview-ended]');
        var fail = root.querySelector('[data-nitfin-preview-failed]');
        if (done) {
            done.hidden = failed;
        }
        if (fail) {
            fail.hidden = !failed;
        }
        if (box) {
            box.hidden = false;
        }
    }

    /**
     * End the preview once the play head reaches the limit.
     *
     * @param {*} seconds
     */
    function check(seconds) {
        if (typeof seconds === 'number' && isFinite(seconds) && seconds >= limit) {
            end(false);
        }
    }

    if (!iframe || limit <= 0) {
        end(true);
        return;
    }

    /**
     * Load a provider script once (another plugin may already have added it).
     *
     * @param {string} src
     * @param {string} globalName the global the script defines
     * @return {Promise}
     */
    function loadScript(src, globalName) {
        return new Promise(function (resolve, reject) {
            if (window[globalName]) {
                resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = function () {
                resolve();
            };
            script.onerror = function () {
                reject(new Error(src + ' failed to load'));
            };
            document.head.appendChild(script);
        });
    }

    var watchers = {
        vdocipher: function () {
            return loadScript('https://player.vdocipher.com/v2/api.js', 'VdoPlayer').then(function () {
                var player = window.VdoPlayer.getInstance(iframe);
                var v = player && player.video;
                if (!v) {
                    throw new Error('VdoCipher player not available');
                }
                var onTime = function () {
                    check(v.currentTime);
                };
                v.addEventListener('timeupdate', onTime);
                v.addEventListener('seeking', onTime);
            });
        },
        vimeo: function () {
            var src = 'https://player.vimeo.com/api/player.js';
            // The SDK is UMD: with Moodle's RequireJS on the page it registers as
            // an AMD module instead of setting window.Vimeo, so load it that way.
            var load = (typeof window.require === 'function' && window.define && window.define.amd)
                ? new Promise(function (resolve, reject) {
                    window.require([src], resolve, reject);
                })
                : loadScript(src, 'Vimeo').then(function () {
                    return window.Vimeo.Player;
                });
            return load.then(function (Player) {
                var p = new Player(iframe);
                var onTime = function (d) {
                    check(d && d.seconds);
                };
                p.on('timeupdate', onTime);
                p.on('seeked', onTime);
                return p.ready();
            });
        }
    };

    if (!watchers[provider]) {
        end(true);
        return;
    }
    watchers[provider]().catch(function () {
        end(true);
    });
})();
