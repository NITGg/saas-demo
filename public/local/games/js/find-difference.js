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
 * Spot the Difference - game 20.
 *
 * The doc flags this as the most expensive game in the set, because every
 * board would need two hand-drawn pictures. It also says to leave it until the
 * cheaper games are done - and the reason it can be built now is that the
 * scenes are generated: a grid of emoji, copied, with a few cells changed.
 *
 * That makes every board free and endless, at the cost of the pictures being
 * arrangements rather than drawings. When there is art to use, only makeScene()
 * has to change.
 *
 * @module     local_games/find-difference
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Scene size. */
    var COLS = 4;
    var ROWS = 4;

    /** Differences per board. */
    var DIFFERENCES = 5;

    /** Boards in a round. The badge asks for fifteen finds with no mistakes. */
    var BOARDS = 3;

    window.LocalGames.register('find-difference', function (api) {

        var board = 0;
        var left = [];
        var right = [];
        var changed = [];
        var found = [];
        var locked = false;

        var nodes = {};

        /**
         * Build a scene and a nearly identical copy of it.
         */
        function makeScene() {
            var faces = api.words.filter(function (entry) {
                return entry.emoji;
            }).map(function (entry) {
                return entry.emoji;
            });

            var cells = COLS * ROWS;
            left = [];
            for (var i = 0; i < cells; i++) {
                left.push(api.pick(faces));
            }

            right = left.slice();
            changed = api.shuffle(left.map(function (unused, at) {
                return at;
            })).slice(0, DIFFERENCES);

            changed.forEach(function (at) {
                var swap;
                do {
                    swap = api.pick(faces);
                } while (swap === left[at]);
                right[at] = swap;
            });

            found = [];
            locked = false;
        }

        /**
         * Draw both scenes side by side.
         */
        function render() {
            api.setProgress(board, BOARDS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-diff';

            nodes.count = document.createElement('p');
            nodes.count.className = 'gc-diff__count';
            wrap.appendChild(nodes.count);

            var pair = document.createElement('div');
            pair.className = 'gc-diff__pair';

            pair.appendChild(scene(left, api.strings.diff_left, false));
            pair.appendChild(scene(right, api.strings.diff_right, true));

            wrap.appendChild(pair);
            api.stage.appendChild(wrap);
            drawCount();
        }

        /**
         * One scene.
         *
         * @param {string[]} faces
         * @param {string} label
         * @param {boolean} clickable whether this is the one being searched
         * @return {HTMLElement}
         */
        function scene(faces, label, clickable) {
            var box = document.createElement('div');
            box.className = 'gc-diff__scene';

            var caption = document.createElement('p');
            caption.className = 'gc-diff__label';
            caption.textContent = label;
            box.appendChild(caption);

            var grid = document.createElement('div');
            grid.className = 'gc-diff__grid';
            grid.style.gridTemplateColumns = 'repeat(' + COLS + ', 1fr)';

            faces.forEach(function (face, at) {
                var cell = document.createElement(clickable ? 'button' : 'span');
                cell.className = 'gc-diff__cell';
                cell.setAttribute('aria-hidden', clickable ? 'false' : 'true');
                cell.textContent = face;

                if (clickable) {
                    cell.type = 'button';
                    cell.dataset.at = String(at);
                    cell.addEventListener('click', function () {
                        tap(at, cell);
                    });
                }
                grid.appendChild(cell);
            });

            box.appendChild(grid);
            if (clickable) {
                nodes.grid = grid;
            }
            return box;
        }

        /**
         * How many are left.
         */
        function drawCount() {
            nodes.count.textContent = api.strings.diff_remaining
                .replace('{$a}', api.fmt(DIFFERENCES - found.length));
        }

        /**
         * A cell was tapped.
         *
         * @param {number} at
         * @param {HTMLElement} cell
         */
        function tap(at, cell) {
            if (locked || found.indexOf(at) !== -1) {
                return;
            }

            if (changed.indexOf(at) === -1) {
                cell.classList.add('gc-diff__cell--no');
                window.setTimeout(function () {
                    cell.classList.remove('gc-diff__cell--no');
                }, 500);
                api.wrong();
                return;
            }

            found.push(at);
            cell.classList.add('gc-diff__cell--found');
            cell.disabled = true;
            api.correct();
            drawCount();

            if (found.length === DIFFERENCES) {
                locked = true;
                // A beat to see the last circle land, then the board fades out
                // and the new one fades in - swapping the pictures instantly
                // read as a glitch rather than as a new puzzle.
                window.setTimeout(function () {
                    api.stage.classList.add("gc-stage--leaving");
                }, 900);
                window.setTimeout(nextBoard, 1700);
            }
        }

        /**
         * Next board, or the end.
         */
        function nextBoard() {
            board++;
            api.setProgress(board, BOARDS);

            if (board >= BOARDS) {
                api.finish();
                return;
            }
            makeScene();
            render();
            api.stage.classList.remove("gc-stage--leaving");
        }

        return {
            start: function () {
                board = 0;
                makeScene();
                render();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
