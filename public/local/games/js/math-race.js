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
 * Math Race - game 01.
 *
 * A sum, three answers, and a runner that moves one step down the track for
 * every correct one. The pace picks up as the round goes on, which is the
 * "race" in the name.
 *
 * The clock is deliberately toothless: running out of time re-queues the
 * question exactly like a wrong answer does, and neither ends the round. The
 * design doc's rule is that a mistake is never a punishment.
 *
 * @module     local_games/math-race
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** How many correct answers finish the round. */
    var TARGET = 12;

    /** A hard stop, so a struggling child is never trapped in one round. */
    var MAX_ATTEMPTS = 26;

    /** Seconds on the clock for the first question, and for the last. */
    var TIME_START = 13;
    var TIME_FLOOR = 7;

    window.LocalGames.register('math-race', function (api) {

        var queue = [];
        var solved = 0;
        var attempts = 0;
        var ticker = null;
        var deadline = 0;
        var allowed = TIME_START;
        var current = null;
        var locked = false;

        var nodes = {};

        /**
         * Build one question, harder as the round goes on.
         *
         * Which sums the game may ask is not decided here any more: the ranges
         * are rows an administrator edits in Game control, and api.sum() draws
         * one of them. The game is still endless - it generates inside whatever
         * rules it has been given - so a class can be put on times tables only
         * without anybody touching this file.
         *
         * @return {?{text: string, answer: number}} null when no rules are set
         */
        function makeQuestion() {
            return api.sum();
        }

        /**
         * Three answers: the right one plus two believable near-misses.
         *
         * @param {number} answer
         * @return {number[]} shuffled
         */
        function makeOptions(answer) {
            var options = [answer];
            var offsets = api.shuffle([1, -1, 2, -2, 10, -10, 3, -3]);

            for (var i = 0; i < offsets.length && options.length < 3; i++) {
                var candidate = answer + offsets[i];
                if (candidate >= 0 && options.indexOf(candidate) === -1) {
                    options.push(candidate);
                }
            }

            // Tiny answers can run out of non-negative neighbours.
            while (options.length < 3) {
                var filler = answer + options.length + 3;
                if (options.indexOf(filler) === -1) {
                    options.push(filler);
                }
            }

            return api.shuffle(options);
        }

        /**
         * The sum as a sentence, so the voice reads "seven plus five" rather
         * than spelling out a symbol it has no word for.
         *
         * @param {string} text e.g. "7 × 5"
         * @return {string}
         */
        function spoken(text) {
            return text
                .replace('+', ' ' + api.strings.op_plus + ' ')
                .replace('-', ' ' + api.strings.op_minus + ' ')
                .replace('×', ' ' + api.strings.op_times + ' ');
        }

        /**
         * Lay out the track, the clock and the question card once per round.
         */
        function build() {
            var wrap = document.createElement('div');
            wrap.className = 'gc-race';
            wrap.innerHTML =
                '<div class="gc-race__track">' +
                    '<div class="gc-race__runner" aria-hidden="true">🏃</div>' +
                    '<div class="gc-race__flag" aria-hidden="true">🏁</div>' +
                '</div>' +
                '<div class="gc-race__timer"><span class="gc-race__timerbar"></span></div>' +
                '<div class="gc-race__question" data-role="question"></div>' +
                '<div class="gc-race__answers" data-role="answers"></div>';

            api.stage.appendChild(wrap);

            nodes.runner = wrap.querySelector('.gc-race__runner');
            nodes.bar = wrap.querySelector('.gc-race__timerbar');
            nodes.question = wrap.querySelector('[data-role="question"]');
            nodes.answers = wrap.querySelector('[data-role="answers"]');
        }

        /**
         * Draw the next question from the queue and start its clock.
         */
        function ask() {
            if (solved >= TARGET || attempts >= MAX_ATTEMPTS || !queue.length) {
                end();
                return;
            }

            current = queue.shift();
            locked = false;

            // The clock tightens as the runner gets further along.
            var progress = solved / TARGET;
            allowed = TIME_START - (TIME_START - TIME_FLOOR) * progress;
            deadline = Date.now() + allowed * 1000;

            api.setProgress(solved, TARGET);
            nodes.question.textContent = api.fmt(current.text) + ' = ?';
            nodes.question.setAttribute('dir', 'ltr');

            nodes.answers.innerHTML = '';
            makeOptions(current.answer).forEach(function (value) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-answer';
                button.textContent = api.fmt(value);
                button.addEventListener('click', function () {
                    answer(button, value);
                });
                nodes.answers.appendChild(button);
            });

            api.say(spoken(current.text) + '. ' + api.strings.race_question);
        }

        /**
         * Handle a tap on one of the three answers.
         *
         * @param {HTMLElement} button the one tapped
         * @param {number} value its number
         */
        function answer(button, value) {
            if (locked) {
                return;
            }
            locked = true;
            attempts++;

            if (value === current.answer) {
                button.classList.add('gc-answer--ok');
                solved++;
                api.setProgress(solved, TARGET);
                api.correct();
                moveRunner();
            } else {
                button.classList.add('gc-answer--no');
                api.wrong();
                // Straight back into the queue - it will come round again.
                queue.push(current);
            }

            window.setTimeout(ask, 750);
        }

        /**
         * Time ran out: same treatment as a wrong answer, minus the buzzer.
         */
        function timeout() {
            if (locked) {
                return;
            }
            locked = true;
            attempts++;
            queue.push(current);
            api.wrong();
            window.setTimeout(ask, 750);
        }

        /**
         * Slide the runner to match how much of the round is done.
         */
        function moveRunner() {
            var percent = Math.min(solved / TARGET, 1) * 88;
            nodes.runner.style.insetInlineStart = percent + '%';
        }

        /**
         * One tick of the clock bar.
         */
        function tick() {
            if (!current || locked) {
                return;
            }
            var left = Math.max(deadline - Date.now(), 0);
            nodes.bar.style.width = (left / (allowed * 1000) * 100) + '%';
            if (left <= 0) {
                timeout();
            }
        }

        /**
         * Wrap the round up.
         */
        function end() {
            stop();
            api.finish(solved);
        }

        /**
         * Drop the clock. Safe to call twice.
         */
        function stop() {
            if (ticker) {
                window.clearInterval(ticker);
                ticker = null;
            }
            current = null;
        }

        return {
            start: function () {
                queue = [];
                solved = 0;
                attempts = 0;
                for (var i = 0; i < TARGET; i++) {
                    var question = makeQuestion();
                    // No rules set means no sums to ask. Finishing the round at
                    // once is a quiet end rather than an empty screen the child
                    // has to work out how to leave.
                    if (!question) {
                        api.finish(0);
                        return;
                    }
                    queue.push(question);
                }
                build();
                moveRunner();
                ticker = window.setInterval(tick, 100);
                ask();
            },
            stop: stop
        };
    });
}());
