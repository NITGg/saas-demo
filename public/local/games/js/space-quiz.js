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
 * Space Trip - game 16.
 *
 * The same questions again, but this time they are distance. The rocket moves
 * one step per right answer and stays put on a wrong one - it never goes
 * backwards, so a bad patch costs time and nothing else.
 *
 * Three stages, each further than the last, exactly as the doc lays out.
 *
 * @module     local_games/space-quiz
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** The journey: how many right answers each planet is away. */
    var STAGES = [
        {key: 'moon', emoji: '🌕', steps: 5},
        {key: 'mars', emoji: '🔴', steps: 7},
        {key: 'jupiter', emoji: '🪐', steps: 9}
    ];

    window.LocalGames.register('space-quiz', function (api) {

        var stage = 0;
        var step = 0;
        var arrived = 0;
        var questions = [];
        var current = null;
        var locked = false;

        var nodes = {};

        /**
         * Draw the track and the current question.
         */
        function render() {
            var here = STAGES[stage];
            api.setProgress(stage, STAGES.length);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-space';

            var title = document.createElement('p');
            title.className = 'gc-space__stage';
            title.textContent = api.strings.space_stage
                .replace('{$a}', api.fmt(stage + 1))
                .replace('{$b}', api.strings['space_' + here.key] || here.key);
            wrap.appendChild(title);

            var track = document.createElement("div");
            track.className = "gc-space__track";

            nodes.trail = document.createElement("span");
            nodes.trail.className = "gc-space__trail";
            nodes.trail.setAttribute("aria-hidden", "true");
            track.appendChild(nodes.trail);

            nodes.rocket = document.createElement("span");
            nodes.rocket.className = "gc-space__rocket";
            nodes.rocket.setAttribute("aria-hidden", "true");
            nodes.rocket.textContent = "🚀";
            track.appendChild(nodes.rocket);

            nodes.planet = document.createElement("span");
            nodes.planet.className = "gc-space__planet";
            nodes.planet.setAttribute("aria-hidden", "true");
            nodes.planet.textContent = here.emoji;
            track.appendChild(nodes.planet);

            wrap.appendChild(track);

            var ask = document.createElement('p');
            ask.className = 'gc-quiz__question';
            ask.textContent = current.question;
            wrap.appendChild(ask);

            nodes.answers = document.createElement('div');
            nodes.answers.className = 'gc-quiz__answers';

            current.options.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-quiz__answer';
                button.textContent = option;
                button.addEventListener('click', function () {
                    answered(button, option);
                });
                nodes.answers.appendChild(button);
            });
            wrap.appendChild(nodes.answers);

            api.stage.appendChild(wrap);
            moveRocket();
            api.say(current.question);
        }

        /**
         * Slide the rocket to match how far along the stage is.
         */
        function moveRocket(thrust) {
            var here = STAGES[stage];
            var along = Math.min(step / here.steps, 1);
            // Logical throughout, so the journey runs the way the child reads.
            var at = (7 + along * 78) + "%";

            nodes.rocket.style.insetInlineStart = at;
            nodes.trail.style.inlineSize = at;
            nodes.planet.classList.toggle("gc-space__planet--near", along > 0.7);

            if (!thrust) {
                return;
            }
            // The rocket lifts as it moves and that is the whole effect. There
            // was a flame emoji riding alongside it; it read as a stray icon
            // sitting next to the rocket rather than as an exhaust, so it is
            // gone.
            nodes.rocket.classList.remove("gc-space__rocket--moving");
            void nodes.rocket.offsetWidth;
            nodes.rocket.classList.add("gc-space__rocket--moving");
        }

        /**
         * Score the answer.
         *
         * @param {HTMLElement} button
         * @param {string} option
         */
        function answered(button, option) {
            if (locked) {
                return;
            }
            locked = true;

            if (option === current.answer) {
                button.classList.add('gc-quiz__answer--ok');
                step++;
                api.correct();
                moveRocket(true);
            } else {
                button.classList.add('gc-quiz__answer--no');
                Array.prototype.forEach.call(nodes.answers.children, function (el) {
                    if (el.textContent === current.answer) {
                        el.classList.add('gc-quiz__answer--ok');
                    }
                });
                api.wrong();
            }

            window.setTimeout(next, 1400);
        }

        /**
         * Either the next question, the next planet, or the end.
         */
        function next() {
            locked = false;

            if (step >= STAGES[stage].steps) {
                arrive();
                return;
            }
            current = nextQuestion();
            render();
        }

        /**
         * Reached the planet.
         */
        function arrive() {
            var here = STAGES[stage];
            arrived++;
            api.correct(
                api.strings.space_arrived.replace('{$a}', api.strings['space_' + here.key] || here.key),
                3
            );

            stage++;
            step = 0;
            api.setProgress(stage, STAGES.length);

            if (stage >= STAGES.length) {
                // The goal is reaching the last planet, which is the badge.
                window.setTimeout(function () {
                    api.finish(undefined, 1);
                }, 900);
                return;
            }

            window.setTimeout(function () {
                current = nextQuestion();
                render();
            }, 1200);
        }

        /**
         * Pull the next question, refilling the pile when it runs dry.
         *
         * @return {Object}
         */
        function nextQuestion() {
            if (!questions.length) {
                questions = api.questions(30);
            }
            // Falls back to whatever is on screen rather than undefined.
            return questions.shift() || current;
        }

        return {
            start: function () {
                stage = 0;
                step = 0;
                arrived = 0;
                locked = false;
                questions = api.questions(30);
                current = nextQuestion();
                render();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
