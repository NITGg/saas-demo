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
 * Who Am I - game 17.
 *
 * Clues arrive one at a time and the answer is worth less with each one: ten
 * points from the first clue, six from the second, three from the third. That
 * slope is the whole game - asking for another clue is a real decision, not a
 * free action.
 *
 * Asking for a clue is never punished beyond the points. There is no limit on
 * wrong guesses either; a child who works it out on the third clue has still
 * worked it out.
 *
 * @module     local_games/who-am-i
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Riddles in a round. */
    var RIDDLES = 8;

    /** What an answer is worth after one, two or three clues. */
    var WORTH = [10, 6, 3];

    window.LocalGames.register('who-am-i', function (api) {

        var queue = [];
        var current = null;
        var shown = 1;
        var solved = 0;
        var firstclue = 0;
        var locked = false;

        var nodes = {};

        /**
         * Draw the clues so far, the choices, and the "another clue" button.
         */
        function render() {
            api.setProgress(solved, RIDDLES);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-who';

            var mark = document.createElement('div');
            mark.className = 'gc-who__mark';
            mark.setAttribute('aria-hidden', 'true');
            mark.textContent = '❓';
            wrap.appendChild(mark);

            nodes.clues = document.createElement('ul');
            nodes.clues.className = 'gc-who__clues';
            wrap.appendChild(nodes.clues);

            nodes.worth = document.createElement('p');
            nodes.worth.className = 'gc-who__worth';
            wrap.appendChild(nodes.worth);

            nodes.hint = document.createElement('button');
            nodes.hint.type = 'button';
            nodes.hint.className = 'gc-btn gc-btn--ghost';
            nodes.hint.textContent = api.strings.who_hint;
            nodes.hint.addEventListener('click', moreClue);
            wrap.appendChild(nodes.hint);

            nodes.choices = document.createElement('div');
            nodes.choices.className = 'gc-who__choices';
            wrap.appendChild(nodes.choices);

            api.stage.appendChild(wrap);

            drawClues();
            drawChoices();
        }

        /**
         * Show the clues revealed so far.
         */
        function drawClues() {
            nodes.clues.innerHTML = '';

            current.clues.slice(0, shown).forEach(function (clue, i) {
                var li = document.createElement('li');
                li.className = 'gc-who__clue';
                li.textContent = api.fmt(i + 1) + '. ' + clue;
                nodes.clues.appendChild(li);
            });

            nodes.worth.textContent = api.strings.who_worth.replace('{$a}', api.fmt(WORTH[shown - 1]));
            nodes.hint.disabled = shown >= current.clues.length;
            api.say(current.clues[shown - 1]);
        }

        /**
         * Four faces to choose from - the answer and three others.
         */
        function drawChoices() {
            nodes.choices.innerHTML = '';

            var others = api.shuffle(api.whoami.filter(function (entry) {
                return entry.answer !== current.answer;
            })).slice(0, 3);

            api.shuffle([current].concat(others)).forEach(function (entry) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-who__choice';
                button.innerHTML = '<span class="gc-who__face" aria-hidden="true">'
                    + entry.emoji + '</span>';

                var name = document.createElement('span');
                name.className = 'gc-who__name';
                name.textContent = entry.answer;
                button.appendChild(name);

                button.addEventListener('click', function () {
                    guess(button, entry.answer);
                });
                nodes.choices.appendChild(button);
            });
        }

        /**
         * Reveal the next clue, at the cost of what the answer is worth.
         */
        function moreClue() {
            if (locked || shown >= current.clues.length) {
                return;
            }
            shown++;
            drawClues();
        }

        /**
         * Judge a guess.
         *
         * @param {HTMLElement} button
         * @param {string} answer
         */
        function guess(button, answer) {
            if (locked) {
                return;
            }

            if (answer !== current.answer) {
                button.classList.add('gc-who__choice--no');
                api.wrong();
                // A wrong guess reveals the next clue instead of ending the
                // riddle - being closer is the consolation.
                moreClue();
                return;
            }

            locked = true;
            button.classList.add('gc-who__choice--ok');

            if (shown === 1) {
                firstclue++;
            }
            solved++;
            api.correct(
                api.strings.who_answer.replace('{$a}', current.answer),
                WORTH[shown - 1]
            );
            api.say(current.answer);
            api.setProgress(solved, RIDDLES);

            window.setTimeout(next, 1600);
        }

        /**
         * Next riddle, or the end.
         */
        function next() {
            locked = false;
            shown = 1;

            if (!queue.length) {
                // The goal is riddles solved from the first clue alone.
                api.finish(undefined, firstclue);
                return;
            }
            current = queue.shift();
            render();
        }

        return {
            start: function () {
                solved = 0;
                firstclue = 0;
                locked = false;
                shown = 1;
                queue = api.shuffle(api.whoami).slice(0, RIDDLES);
                next();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
