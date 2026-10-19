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
 * Pick the Answer - game 13.
 *
 * The same questions as game 10, but the answers drift across the screen and
 * have to be caught. The doc asks for more points the faster the tap, so the
 * value of a target falls as it crosses - from three points down to one.
 *
 * Targets are ordinary buttons moved by an animation loop rather than canvas
 * shapes, so a child using a keyboard or a screen reader can still play: the
 * answers are real, focusable, readable text.
 *
 * @module     local_games/target-answer
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Questions in a round. */
    var QUESTIONS = 12;

    /** Seconds a target takes to cross the stage, first question to last. */
    var CROSS_SLOW = 15;
    var CROSS_FAST = 10;

    /**
     * Seconds the targets hold still after a new question appears.
     *
     * Without this the child was reading the question while the answers were
     * already leaving - so the game tested reading speed, which is not what it
     * is for. The question is read aloud during the pause too.
     */
    var READING_PAUSE = 2.2;

    window.LocalGames.register('target-answer', function (api) {

        var queue = [];
        var asked = 0;
        var current = null;
        var targets = [];
        var frame = null;
        var lastframe = 0;
        var running = false;
        var locked = false;
        var reading = 0;

        var nodes = {};
        var view = {w: 0, h: 0};

        /**
         * Build the fixed furniture once per question.
         */
        function render() {
            api.setProgress(asked, QUESTIONS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-target';

            nodes.question = document.createElement('p');
            nodes.question.className = 'gc-target__question';
            nodes.question.textContent = current.question;
            wrap.appendChild(nodes.question);

            nodes.field = document.createElement('div');
            nodes.field.className = 'gc-target__field';
            wrap.appendChild(nodes.field);

            api.stage.appendChild(wrap);

            var box = nodes.field.getBoundingClientRect();
            view.w = box.width;
            view.h = box.height;

            spawn();
            // Hold everything still while the question is read.
            reading = READING_PAUSE;
            api.say(current.question);
        }

        /**
         * Put one target on the field for each option.
         */
        function spawn() {
            targets = [];
            nodes.field.innerHTML = '';

            var progress = asked / QUESTIONS;
            var seconds = CROSS_SLOW - (CROSS_SLOW - CROSS_FAST) * progress;
            var lanes = api.shuffle(current.options.map(function (unused, i) {
                return i;
            }));

            current.options.forEach(function (option, i) {
                var el = document.createElement('button');
                el.type = 'button';
                el.className = 'gc-target__mark';
                el.innerHTML = '<span aria-hidden="true">🎯</span>';

                var label = document.createElement('span');
                label.className = 'gc-target__text';
                label.textContent = option;
                el.appendChild(label);

                el.addEventListener('click', function () {
                    hit(option, target);
                });
                nodes.field.appendChild(el);

                // Each target owns a lane so two never sit on top of each other,
                // and they set off from alternating sides.
                var lane = lanes[i];
                var lanes_count = current.options.length;
                var target = {
                    el: el,
                    option: option,
                    // Distance travelled, 0 to 1, plus the direction of travel.
                    at: -0.15 - i * 0.18,
                    speed: 1 / seconds,
                    y: (lane + 0.5) / lanes_count,
                    dead: false
                };
                targets.push(target);
            });

            place();
        }

        /**
         * Move every target to where it currently is.
         */
        function place() {
            targets.forEach(function (target) {
                var w = target.el.offsetWidth || 120;
                var h = target.el.offsetHeight || 48;
                // `at` runs from just off one edge to just off the other.
                var x = target.at * (view.w + w) - w / 2;
                target.el.style.transform = 'translate('
                    + x + 'px, ' + (target.y * view.h - h / 2) + 'px)';
            });
        }

        /**
         * One frame.
         *
         * @param {number} timestamp
         */
        function loop(timestamp) {
            if (!running) {
                return;
            }
            // Clamped at BOTH ends. A backgrounded tab hands back a huge delta on
            // return, and a clock that appears to run backwards - a device time
            // change, or a first frame stamped before lastframe was set - would
            // otherwise drive every timer in the game the wrong way.
            var dt = Math.max(0, Math.min((timestamp - lastframe) / 1000, 0.05));
            lastframe = timestamp;

            if (reading > 0) {
                reading -= dt;
                frame = window.requestAnimationFrame(loop);
                return;
            }

            var gone = 0;
            targets.forEach(function (target) {
                if (target.dead) {
                    gone++;
                    return;
                }
                target.at += target.speed * dt;
                if (target.at > 1.15) {
                    target.dead = true;
                    target.el.style.display = 'none';
                    gone++;
                }
            });
            place();

            // Every target has crossed and nothing was caught: move on rather
            // than leaving the child staring at an empty field.
            if (gone === targets.length && !locked) {
                locked = true;
                api.wrong();
                window.setTimeout(next, 700);
            }

            frame = window.requestAnimationFrame(loop);
        }

        /**
         * A target was tapped.
         *
         * @param {string} option
         * @param {Object} target
         */
        function hit(option, target) {
            if (locked || target.dead) {
                return;
            }
            locked = true;

            if (option === current.answer) {
                // Three points while it is still early in its crossing, then
                // two, then one - the doc asks for speed to be worth something.
                var worth = target.at < 0.35 ? 3 : (target.at < 0.7 ? 2 : 1);
                target.el.classList.add('gc-target__mark--hit');
                api.correct(undefined, worth);
            } else {
                target.el.classList.add('gc-target__mark--miss');
                api.wrong();
            }

            window.setTimeout(next, 750);
        }

        /**
         * Next question, or the end.
         */
        function next() {
            asked++;
            locked = false;
            api.setProgress(asked, QUESTIONS);

            if (!queue.length) {
                stop();
                api.finish();
                return;
            }
            current = queue.shift();
            render();
        }

        /**
         * Drop the loop. Safe to call twice.
         */
        function stop() {
            running = false;
            if (frame) {
                window.cancelAnimationFrame(frame);
                frame = null;
            }
        }

        return {
            start: function () {
                asked = 0;
                locked = false;
                queue = api.questions(QUESTIONS);
                current = queue.shift();
                render();

                running = true;
                lastframe = window.performance.now();
                frame = window.requestAnimationFrame(loop);
            },
            stop: stop
        };
    });
}());
