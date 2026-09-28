/**
 * VdoCipher player adapter for the AI video assistant.
 *
 * The only file in the plugin that knows how to talk to a specific video
 * provider. It answers four questions and nothing else: is there a player, how
 * long is it, where is the viewer, and jump there. Supporting Vimeo or a plain
 * <video> means writing a sibling of this file — nothing else changes.
 *
 * Verified against a DRM video on production: DRM does not restrict the JS API.
 * getInstance() attaches to the iframe the module already renders, so the embed
 * markup needs no change; only api.js has to be present.
 *
 * @module     local_nit_ai/player_vdocipher
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    var API_SRC = 'https://player.vdocipher.com/v2/api.js';

    window.NITAI = window.NITAI || {};
    window.NITAI.players = window.NITAI.players || {};

    /**
     * Load VdoCipher's player API once, however many players are on the page.
     *
     * @return {Promise} resolves when window.VdoPlayer is usable
     */
    function loadApi() {
        if (window.NITAI.vdoApiPromise) {
            return window.NITAI.vdoApiPromise;
        }
        window.NITAI.vdoApiPromise = new Promise(function (resolve, reject) {
            if (window.VdoPlayer) {
                resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = API_SRC;
            script.async = true;
            script.onload = function () {
                resolve();
            };
            script.onerror = function () {
                reject(new Error('vdocipher api.js failed to load'));
            };
            document.head.appendChild(script);
        });
        return window.NITAI.vdoApiPromise;
    }

    /**
     * Build an adapter for the VdoCipher player on this page.
     *
     * Resolves with null when there is no player or the API will not attach —
     * the assistant then runs without playback awareness rather than breaking.
     *
     * @return {Promise} resolves with the adapter, or null
     */
    window.NITAI.players.vdocipher = function () {
        var iframe = document.querySelector('iframe[src*="player.vdocipher.com"]');
        if (!iframe) {
            return Promise.resolve(null);
        }

        return loadApi().then(function () {
            var player = window.VdoPlayer.getInstance(iframe);
            if (!player || !player.video) {
                return null;
            }
            return {
                /**
                 * Playback position in whole seconds, or -1 if not known yet.
                 *
                 * @return {number}
                 */
                getCurrentTime: function () {
                    var t = player.video.currentTime;
                    return (typeof t === 'number' && !isNaN(t)) ? Math.floor(t) : -1;
                },

                /**
                 * Video duration in whole seconds, or 0 if not known yet.
                 *
                 * @return {number}
                 */
                getDuration: function () {
                    var d = player.video.duration;
                    return (typeof d === 'number' && !isNaN(d)) ? Math.floor(d) : 0;
                },

                /**
                 * Jump to a point in the video and start playing from there.
                 *
                 * @param {number} seconds
                 */
                seekTo: function (seconds) {
                    try {
                        player.video.currentTime = seconds;
                        var playing = player.video.play();
                        if (playing && playing.catch) {
                            // Autoplay can be refused; the seek still happened.
                            playing.catch(function () {
                                return null;
                            });
                        }
                    } catch (e) {
                        window.console.warn('nit_ai: seek failed', e);
                    }
                }
            };
        }).catch(function (e) {
            window.console.warn('nit_ai: vdocipher player API unavailable', e);
            return null;
        });
    };
}());
