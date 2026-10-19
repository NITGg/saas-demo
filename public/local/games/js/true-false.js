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
 * True or False - game 11.
 *
 * The doc calls this the easiest game in the set and puts it with the youngest
 * children, the ones still finding reading hard. So it is two enormous buttons
 * and one sentence, the sentence is read aloud, and every answer - right or
 * wrong - is followed by the reason in one line.
 *
 * @module     local_games/true-false
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Statements in a round. The badge wants fifteen right in a row. */
    var STATEMENTS = 18;

    window.LocalGames.register('true-false', function (api) {

        var queue = [];
        var asked = 0;
        var current = null;
        var locked = false;

        var nodes = {};

        /**
         * Draw the statement and the two buttons.
         */
        function render() {
            api.setProgress(asked, STATEMENTS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-tf';

            var text = document.createElement('p');
            text.className = 'gc-tf__statement';
            text.textContent = current.text;
            wrap.appendChild(text);

            var row = document.createElement('div');
            row.className = 'gc-tf__buttons';

            [true, false].forEach(function (value) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-tf__button gc-tf__button--' + (value ? 'yes' : 'no');
                button.innerHTML = '<span class="gc-tf__mark" aria-hidden="true">'
                    + (value ? '✅' : '❌') + '</span>';

                var label = document.createElement('span');
                label.className = 'gc-tf__label';
                label.textContent = value ? api.strings.tf_true : api.strings.tf_false;
                button.appendChild(label);

                button.addEventListener('click', function () {
                    answered(value);
                });
                row.appendChild(button);
            });
            wrap.appendChild(row);

            nodes.why = document.createElement('p');
            nodes.why.className = 'gc-tf__why';
            nodes.why.setAttribute('role', 'status');
            wrap.appendChild(nodes.why);

            api.stage.appendChild(wrap);
            api.say(current.text);
        }

        /**
         * Score the answer and show why.
         *
         * @param {boolean} value what the child said
         */
        function answered(value) {
            if (locked) {
                return;
            }
            locked = true;
            asked++;

            if (value === current.true) {
                api.correct();
            } else {
                api.wrong();
            }

            // The reason is shown either way: a child who guessed right still
            // learns something, and one who guessed wrong is not left wondering.
            nodes.why.textContent = current.why;
            nodes.why.classList.add('gc-tf__why--shown');
            api.say(current.why);

            api.setProgress(asked, STATEMENTS);
            // Long enough to read the reason and take it in. The whole point of
            // this game is the line that explains the answer, and 2.2 seconds
            // went by before a child had finished reading it.
            window.setTimeout(next, 4200);
        }

        /**
         * Next statement, or the end.
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
                queue = api.shuffle(api.truefalse).slice(0, STATEMENTS);
                next();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
