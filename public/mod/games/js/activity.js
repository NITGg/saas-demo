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
 * Tell the activity when a round finishes.
 *
 * The corner's shell saves the round to the corner - points, badges, personal
 * bests, all site-wide. It knows nothing about courses, and it should not: a
 * round is a round whether it was played from the hub or from a course page.
 *
 * So the shell announces the finished round as a DOM event and this listens for
 * it, which is the whole of the coupling between the two plugins. If the
 * activity is ever removed the shell carries on unchanged, and if this file
 * fails to load the child still plays the game and still keeps their points -
 * only the teacher's report and the completion tick are missed.
 *
 * @module     mod_games/activity
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /**
     * Post one finished round to mod_games_record_play.
     *
     * @param {string} wwwroot
     * @param {string} sesskey
     * @param {number} cmid
     * @param {Object} detail the shell's round summary
     * @return {Promise}
     */
    function record(wwwroot, sesskey, cmid, detail) {
        var url = wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(sesskey)
            + '&info=mod_games_record_play';

        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{
                index: 0,
                methodname: 'mod_games_record_play',
                args: {
                    cmid: cmid,
                    correct: detail.correct,
                    streak: detail.streak,
                    score: detail.score
                }
            }])
        });
    }

    /**
     * Start listening, once the page exists.
     */
    function boot() {
        var hook = document.querySelector('[data-mod-games-cmid]');
        if (!hook) {
            return;
        }

        var cmid = parseInt(hook.getAttribute('data-mod-games-cmid'), 10);
        var sesskey = hook.getAttribute('data-sesskey');
        if (!cmid || !sesskey) {
            return;
        }

        // The corner's own wwwroot is in the shell's config blob, and reading it
        // from there rather than guessing keeps this working on a site served
        // from a subdirectory.
        var wwwroot = window.location.origin;
        var confignode = document.querySelector('.gc-play [data-role="config"]');
        if (confignode) {
            try {
                wwwroot = JSON.parse(confignode.textContent).wwwroot || wwwroot;
            } catch (e) {
                // Malformed config is the shell's problem, not ours; the origin
                // is right in every deployment that is not in a subdirectory.
            }
        }

        // The event bubbles from .gc-play, so one listener on the document
        // covers a page however the shell is laid out inside it.
        document.addEventListener('local-games:roundfinished', function (event) {
            var detail = event.detail || {};
            record(wwwroot, sesskey, cmid, detail).catch(function () {
                // The round happened and the corner already saved it. Only this
                // activity's tally missed it, and the child must never be shown
                // an error over that in the middle of their end card.
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
