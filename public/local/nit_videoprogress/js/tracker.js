/**
 * Video progress tracker: resume where the student stopped, and report how much
 * of the video they actually played.
 *
 * Plain script (no AMD build in this codebase). It reads its settings from the
 * strip printed by local_nit_videoprogress\ui::tracker() and talks to the
 * provider's own player API, attached to the iframe the module already renders:
 *  - VdoCipher: https://player.vdocipher.com/v2/api.js (VdoPlayer.getInstance)
 *  - Vimeo:     https://player.vimeo.com/api/player.js (Vimeo.Player)
 *
 * Only normal playback marks a 1% slice as watched; seeking forward does not.
 *
 * @module     local_nit_videoprogress/tracker
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    var SLICES = 100;
    /** Report at most this often while playing (ms). */
    var FLUSH_EVERY = 15000;
    /** A step bigger than this (s) between two time updates is a seek, not playback. */
    var MAX_STEP = 3;

    var root = document.querySelector('[data-nitvp]');
    if (!root || root.getAttribute('data-nitvp-started')) {
        return;
    }
    root.setAttribute('data-nitvp-started', '1');

    var cmid = parseInt(root.getAttribute('data-cmid'), 10);
    var provider = root.getAttribute('data-provider');
    var resumeAt = parseInt(root.getAttribute('data-resume'), 10) || 0;

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
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                var waited = 0;
                var timer = window.setInterval(function () {
                    waited += 100;
                    if (window[globalName]) {
                        window.clearInterval(timer);
                        resolve();
                    } else if (waited > 15000) {
                        window.clearInterval(timer);
                        reject(new Error(src + ' did not load'));
                    }
                }, 100);
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

    /**
     * Number or 0.
     *
     * @param {*} n
     * @return {number}
     */
    function num(n) {
        return (typeof n === 'number' && isFinite(n) && n > 0) ? n : 0;
    }

    /**
     * Player adapters: the same small surface for every provider.
     * on(event, fn) with events time / play / pause / ended; seek(s); time(); duration().
     */
    var adapters = {
        vdocipher: function () {
            var iframe = document.querySelector('iframe[src*="player.vdocipher.com"]');
            if (!iframe) {
                return Promise.resolve(null);
            }
            return loadScript('https://player.vdocipher.com/v2/api.js', 'VdoPlayer').then(function () {
                var player = window.VdoPlayer.getInstance(iframe);
                var v = player && player.video;
                if (!v) {
                    return null;
                }
                var map = {time: 'timeupdate', play: 'play', pause: 'pause', ended: 'ended', meta: 'loadedmetadata'};
                return {
                    on: function (evt, fn) {
                        v.addEventListener(map[evt], fn);
                    },
                    seek: function (s) {
                        v.currentTime = s;
                    },
                    time: function () {
                        return num(v.currentTime);
                    },
                    duration: function () {
                        return num(v.duration);
                    }
                };
            });
        },
        vimeo: function () {
            var iframe = document.querySelector('iframe[src*="player.vimeo.com"]');
            if (!iframe) {
                return Promise.resolve(null);
            }
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
                var cur = 0;
                var dur = 0;
                p.on('timeupdate', function (d) {
                    cur = num(d.seconds);
                    dur = num(d.duration) || dur;
                });
                var known = p.getDuration().then(function (d) {
                    dur = num(d) || dur;
                }).catch(function () {
                    return null;
                });
                var map = {time: 'timeupdate', play: 'play', pause: 'pause', ended: 'ended', meta: 'loaded'};
                return {
                    on: function (evt, fn) {
                        p.on(map[evt], fn);
                        if (evt === 'meta') {
                            // "loaded" may have fired before we attached.
                            known.then(fn);
                        }
                    },
                    seek: function (s) {
                        p.setCurrentTime(s).catch(function () {
                            return null;
                        });
                        cur = s;
                    },
                    time: function () {
                        return cur;
                    },
                    duration: function () {
                        return dur;
                    }
                };
            });
        }
    };

    if (!adapters[provider]) {
        return;
    }

    var pending = {};
    var dirty = false;
    // Nothing is reported until the student has pressed play: a visit without
    // playing must never overwrite the saved position with the player's 0.
    var played = false;
    var last = null;
    var lastSent = Date.now();
    var sending = false;

    /**
     * Show the saved percent.
     *
     * @param {number} percent
     */
    function showPercent(percent) {
        percent = Math.max(0, Math.min(100, Math.round(percent)));
        var bar = root.querySelector('[data-nitvp-bar]');
        var pct = root.querySelector('[data-nitvp-pct]');
        if (bar) {
            bar.style.width = percent + '%';
            bar.parentNode.setAttribute('aria-valuenow', String(percent));
        }
        if (pct) {
            pct.textContent = percent + '%';
        }
    }

    /**
     * Send the position and the newly watched slices.
     *
     * @param {Object} player adapter
     * @param {boolean} leaving the page is closing (keep the request alive)
     */
    function flush(player, leaving) {
        if (!played || !dirty || (sending && !leaving)) {
            return;
        }
        var slices = Object.keys(pending);
        pending = {};
        dirty = false;
        sending = true;
        lastSent = Date.now();
        var methodname = 'local_nit_videoprogress_save';
        var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey +
            '&info=' + encodeURIComponent(methodname);
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: !!leaving,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{index: 0, methodname: methodname, args: {
                cmid: cmid,
                position: Math.floor(player.time()),
                duration: Math.floor(player.duration()),
                slices: slices.join(',')
            }}])
        }).then(function (r) {
            return r.json();
        }).then(function (res) {
            sending = false;
            if (res && res[0] && !res[0].error && res[0].data) {
                showPercent(res[0].data.percent);
            } else {
                throw new Error((res && res[0] && res[0].exception && res[0].exception.message) || 'save failed');
            }
        }).catch(function (e) {
            sending = false;
            // Keep what was not saved for the next report.
            slices.forEach(function (s) {
                pending[s] = 1;
            });
            dirty = true;
            window.console.warn('nit_videoprogress: ', e);
        });
    }

    /**
     * Record playback between the previous and the current time update.
     *
     * @param {Object} player adapter
     */
    function onTime(player) {
        var t = player.time();
        var d = player.duration();
        if (d > 0 && last !== null && t > last && t - last <= MAX_STEP) {
            var from = Math.floor(last / d * SLICES);
            var to = Math.floor(Math.min(t, d - 0.001) / d * SLICES);
            for (var i = Math.max(0, from); i <= Math.min(SLICES - 1, to); i++) {
                pending[i] = 1;
            }
        }
        if (last === null || Math.floor(t) !== Math.floor(last)) {
            dirty = true;
        }
        last = t;
        if (Date.now() - lastSent >= FLUSH_EVERY) {
            flush(player, false);
        }
    }

    adapters[provider]().then(function (player) {
        if (!player) {
            return;
        }

        // Resume once: as soon as the player knows the length, or at the latest
        // when the student presses play (some players ignore an earlier seek).
        var resumed = resumeAt <= 0;
        var notice = root.querySelector('[data-nitvp-resume]');
        var tryResume = function (force) {
            if (resumed) {
                return;
            }
            var d = player.duration();
            if (!d && !force) {
                return;
            }
            resumed = true;
            // Near the end = finished (same rule as progress::margin(): 10 s, or a tenth of a short video).
            if (d > 0 && resumeAt >= d - Math.min(10, Math.max(1, Math.floor(d / 10)))) {
                return;
            }
            player.seek(resumeAt);
            last = resumeAt;
            if (notice) {
                notice.hidden = false;
            }
        };
        tryResume(false);
        player.on('meta', function () {
            tryResume(false);
        });
        player.on('play', function () {
            tryResume(true);
            played = true;
            // Playback starts here: without this the first time update only sets "last",
            // so the stretch before it (the whole first slice of a short video) was never
            // counted and a fully watched short video stayed at 99%.
            if (last === null) {
                last = player.time();
            }
        });

        var restart = root.querySelector('[data-nitvp-restart]');
        if (restart) {
            restart.addEventListener('click', function () {
                player.seek(0);
                last = 0;
                dirty = true;
                if (notice) {
                    notice.hidden = true;
                }
            });
        }

        player.on('time', function () {
            onTime(player);
        });
        player.on('pause', function () {
            flush(player, false);
        });
        player.on('ended', function () {
            var d = player.duration();
            if (d > 0 && last !== null && d - last <= MAX_STEP) {
                for (var i = Math.floor(last / d * SLICES); i < SLICES; i++) {
                    pending[i] = 1;
                }
            }
            dirty = true;
            flush(player, false);
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                flush(player, true);
            }
        });
        window.addEventListener('pagehide', function () {
            flush(player, true);
        });
    }).catch(function (e) {
        window.console.warn('nit_videoprogress: player API unavailable', e);
    });
}());
