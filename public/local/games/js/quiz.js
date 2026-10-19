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
 * General Knowledge - game 10.
 *
 * A question, some answers, tap one. The doc calls this the most important
 * game in the corner and it is right, but not because of what happens on this
 * screen: five other games are built on the same bank of questions, and this is
 * the one that shows them plainly.
 *
 * A wrong answer marks the right one rather than hiding it. A child who does
 * not know the answer has just asked to be told it.
 *
 * @module     local_games/quiz
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Questions in a round. The badge wants twenty right. */
    var QUESTIONS = 20;

    window.LocalGames.register('quiz', function (api) {

        var queue = [];
        var asked = 0;
        var current = null;
        var locked = false;

        var nodes = {};

        /**
         * Draw the question and its answers.
         */
        function render() {
            api.setProgress(asked, QUESTIONS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-quiz';

            var topic = document.createElement('p');
            topic.className = 'gc-quiz__topic';
            topic.textContent = api.strings['topic_' + current.topic] || '';
            wrap.appendChild(topic);

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
            api.say(current.question);
        }

        /**
         * Score the tap and move on.
         *
         * @param {HTMLElement} button
         * @param {string} option
         */
        function answered(button, option) {
            if (locked) {
                return;
            }
            locked = true;
            asked++;

            if (option === current.answer) {
                button.classList.add('gc-quiz__answer--ok');
                api.correct();
            } else {
                button.classList.add('gc-quiz__answer--no');
                api.wrong();
                // Show what it should have been. Being told is the point.
                Array.prototype.forEach.call(nodes.answers.children, function (el) {
                    if (el.textContent === current.answer) {
                        el.classList.add('gc-quiz__answer--ok');
                    }
                });
            }

            api.setProgress(asked, QUESTIONS);
            // A beat to see which answer was the right one before it goes.
            window.setTimeout(next, 2600);
        }

        /**
         * Next question, or the end of the round.
         */
        function next() {
            locked = false;
            if (!queue.length) {
                api.finish();
                return;
            }
            current = queue.shift();
            render();
        }

        return {
            start: function () {
                asked = 0;
                locked = false;
                queue = api.questions(QUESTIONS);
                next();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
