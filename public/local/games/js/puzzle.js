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
 * Jigsaw - game 19.
 *
 * The picture is a scene of small pictures - one per piece - drawn into a
 * single SVG and then sliced back apart, so a whole gallery of "artwork" costs
 * nothing to ship. The doc.s three difficulty steps (4, 9, 16 pieces) are the
 * three boards of a round.
 *
 * Pieces are swapped by tapping two of them rather than dragged. Dragging a
 * piece into a grid slot on a phone is fiddly and the doc's rule is that a
 * child should never lose a turn to the controls.
 *
 * @module     local_games/puzzle
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** The three boards of a round, in pieces per side. */
    var BOARDS = [2, 3, 4];

    window.LocalGames.register('puzzle', function (api) {

        var board = 0;
        var side = 2;
        var order = [];
        var picked = -1;
        var picture = null;
        var locked = false;

        var nodes = {};

        /**
         * Shuffle a board that is not already solved.
         */
        function deal() {
            side = BOARDS[board];
            // One picture per piece, so no piece is ever a blank corner.
            picture = api.shuffle(api.words.filter(function (entry) {
                return entry.emoji;
            })).slice(0, side * side).map(function (entry) {
                return entry.emoji;
            });

            var count = side * side;
            var solved = [];
            for (var i = 0; i < count; i++) {
                solved.push(i);
            }

            do {
                order = api.shuffle(solved);
            } while (isDone());

            picked = -1;
            locked = false;
        }

        /**
         * Is every piece home?
         *
         * @return {boolean}
         */
        function isDone() {
            return order.every(function (piece, at) {
                return piece === at;
            });
        }

        /**
         * Draw the board.
         */
        function render() {
            api.setProgress(board, BOARDS.length);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-puzzle';

            var peek = document.createElement('button');
            peek.type = 'button';
            peek.className = 'gc-btn gc-btn--ghost';
            peek.textContent = api.strings.puzzle_peek;
            peek.addEventListener('click', showPicture);
            wrap.appendChild(peek);

            nodes.board = document.createElement('div');
            nodes.board.className = 'gc-puzzle__board';
            nodes.board.style.gridTemplateColumns = 'repeat(' + side + ', 1fr)';
            wrap.appendChild(nodes.board);

            nodes.whole = document.createElement('div');
            nodes.whole.className = 'gc-puzzle__whole';
            nodes.whole.setAttribute('aria-hidden', 'true');
            nodes.whole.style.backgroundImage = "url(\"" + toImage(picture, side) + "\")";
            wrap.appendChild(nodes.whole);

            api.stage.appendChild(wrap);
            paint();

            // The doc asks for the finished picture first, for two seconds.
            showPicture();
        }

        /**
         * Flash the whole picture.
         */
        function showPicture() {
            nodes.whole.classList.add('gc-puzzle__whole--shown');
            window.setTimeout(function () {
                nodes.whole.classList.remove('gc-puzzle__whole--shown');
            }, 2000);
        }

        /**
         * The picture: a scene of small pictures, not one big one.
         *
         * Slicing a single emoji into four looked right on paper and was
         * unplayable in practice - each piece came out an abstract smear of
         * gradient with nothing in it to recognise, so there was no way to work
         * out where it belonged. A scene made of one emoji per piece gives
         * every piece something to be.
         *
         * @param {string[]} faces one per cell, reading order
         * @param {number} across cells per side
         * @return {string} a data URI
         */
        function toImage(faces, across) {
            var cell = 100 / across;
            var glyphs = faces.map(function (face, i) {
                var cx = (i % across) * cell + cell / 2;
                var cy = Math.floor(i / across) * cell + cell / 2;
                return '<text x="' + cx + '" y="' + cy + '" font-size="' + (cell * 0.72) + '"'
                    + ' text-anchor="middle" dominant-baseline="central">' + face + '</text>';
            }).join('');

            var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
                + glyphs + '</svg>';
            return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        }

        /**
         * Redraw every tile from the current order.
         */
        function paint() {
            var face = toImage(picture, side);
            nodes.board.innerHTML = "";

            order.forEach(function (piece, at) {
                var cell = document.createElement('button');
                cell.type = 'button';
                // Only the piece the child has in their hand is marked. An
                // earlier version outlined every piece that was already home in
                // green, which gave the whole answer away - the board could be
                // finished by swapping until the green stopped moving, without
                // ever looking at the picture.
                cell.className = 'gc-puzzle__piece'
                    + (at === picked ? ' gc-puzzle__piece--picked' : '');

                // The picture is one background image stretched across the whole
                // board and shifted per tile - the standard sprite slice.
                //
                // The first version drew the emoji as text sized in rem and
                // hoped that matched the tile, which it never did: the tile is
                // a percentage of the board and the font size was a guess, so
                // the pieces showed slivers of nothing. Percentages of the
                // element's own box cannot be wrong.
                cell.style.backgroundImage = 'url("' + face + '")';
                cell.style.backgroundSize = (side * 100) + '% ' + (side * 100) + '%';
                cell.style.backgroundPosition = ((piece % side) / (side - 1) * 100) + '% '
                    + (Math.floor(piece / side) / (side - 1) * 100) + '%';

                cell.addEventListener('click', function () {
                    tap(at);
                });
                nodes.board.appendChild(cell);
            });

        }

        /**
         * Pick a tile, or swap with the one already picked.
         *
         * @param {number} at
         */
        function tap(at) {
            if (locked) {
                return;
            }

            if (picked === -1) {
                picked = at;
                paint();
                return;
            }
            if (picked === at) {
                picked = -1;
                paint();
                return;
            }

            var was = order[at];
            order[at] = order[picked];
            order[picked] = was;

            // A swap that puts a piece home is the thing worth scoring; a swap
            // that does not is just a move, not a mistake.
            var landed = (order[at] === at ? 1 : 0) + (order[picked] === picked ? 1 : 0);
            picked = -1;
            paint();

            if (landed) {
                api.correct();
            }

            if (isDone()) {
                locked = true;
                window.setTimeout(nextBoard, 900);
            }
        }

        /**
         * Next board, or the end of the round.
         */
        function nextBoard() {
            board++;
            api.setProgress(board, BOARDS.length);

            if (board >= BOARDS.length) {
                // The goal is finishing the sixteen-piece board, which is the
                // last one, so reaching here at all earns it.
                api.finish(undefined, 1);
                return;
            }
            deal();
            render();
        }

        return {
            start: function () {
                board = 0;
                deal();
                render();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
