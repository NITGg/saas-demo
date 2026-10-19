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
 * Colour Challenge - game 21.
 *
 * The doc calls this the simplest game in the set, for the youngest children:
 * no reading and no numbers. So the colour is asked for out loud as well as in
 * writing, and the answer is a circle of that colour and nothing else.
 *
 * Every circle also carries the colour's name for a screen reader, because a
 * game made entirely of colour is otherwise closed to a child who cannot see
 * it - and to one who cannot tell red from green.
 *
 * @module     local_games/color-challenge
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Circles offered each time. */
    var CHOICES = 4;

    window.LocalGames.register('color-challenge', function (api) {

        var queue = [];
        var asked = 0;
        var total = 0;
        var right = 0;
        var current = null;
        var locked = false;

        var nodes = {};

        /**
         * Draw the asked-for colour and the circles.
         */
        function render() {
            api.setProgress(asked, total);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-colour';

            var ask = document.createElement('p');
            ask.className = 'gc-colour__ask';
            ask.textContent = api.strings.colour_find.replace('{$a}', current.name);
            wrap.appendChild(ask);

            nodes.circles = document.createElement('div');
            nodes.circles.className = 'gc-colour__circles';

            var others = api.shuffle(api.colours.filter(function (colour) {
                return colour.name !== current.name;
            })).slice(0, CHOICES - 1);

            api.shuffle([current].concat(others)).forEach(function (colour) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-colour__circle';
                button.style.background = colour.hex;
                // The name is the accessible label, never shown - a child who
                // can read it would not need the game.
                button.setAttribute('aria-label', colour.name);
                button.addEventListener('click', function () {
                    answered(button, colour);
                });
                nodes.circles.appendChild(button);
            });

            wrap.appendChild(nodes.circles);
            api.stage.appendChild(wrap);

            api.say(ask.textContent);
        }

        /**
         * Score the tap.
         *
         * @param {HTMLElement} button
         * @param {Object} colour
         */
        function answered(button, colour) {
            if (locked) {
                return;
            }
            locked = true;
            asked++;

            if (colour.name === current.name) {
                button.classList.add('gc-colour__circle--ok');
                right++;
                api.correct();
            } else {
                button.classList.add('gc-colour__circle--no');
                api.wrong();
                // Point at the one that was asked for.
                Array.prototype.forEach.call(nodes.circles.children, function (el) {
                    if (el.getAttribute('aria-label') === current.name) {
                        el.classList.add('gc-colour__circle--ok');
                    }
                });
            }

            api.setProgress(asked, total);
            window.setTimeout(next, 1300);
        }

        /**
         * Next colour, or the end.
         */
        function next() {
            locked = false;

            if (!queue.length) {
                // The goal is every colour on the board answered correctly.
                api.finish(undefined, right === total ? 1 : 0);
                return;
            }
            current = queue.shift();
            render();
        }

        return {
            start: function () {
                // Every colour in the bank, once, in a random order - the badge
                // asks for all of them.
                queue = api.shuffle(api.colours);
                total = queue.length;
                asked = 0;
                right = 0;
                locked = false;
                next();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
