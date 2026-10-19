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
 * Word Search - game 08.
 *
 * A grid of letters with words hidden in it, found by dragging from the first
 * letter to the last.
 *
 * The grid takes the page's own direction, which is what makes it work in both
 * languages without a second code path: a word written into rising column
 * numbers is laid out left-to-right in English and right-to-left in Arabic, so
 * in both it reads the way the child reads.
 *
 * @module     local_games/word-search
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Grid side. */
    var SIZE = 9;

    /** Words hidden in one grid. */
    var WORDS = 6;

    /** Across, down, and the forward diagonal. Nothing is written backwards. */
    var DIRECTIONS = [
        {dr: 0, dc: 1},
        {dr: 1, dc: 0},
        {dr: 1, dc: 1}
    ];

    window.LocalGames.register('word-search', function (api) {

        var grid = [];
        var hidden = [];
        var found = [];
        var drag = null;

        var nodes = {};

        /**
         * A word split into code points.
         *
         * @param {string} word
         * @return {string[]}
         */
        function letters(word) {
            return Array.from(word);
        }

        /**
         * Every distinct letter in the bank, used to fill the empty cells so the
         * padding never gives a word away by looking foreign.
         *
         * @return {string[]}
         */
        function alphabet() {
            var seen = {};
            api.words.forEach(function (entry) {
                letters(entry.word).forEach(function (letter) {
                    seen[letter] = true;
                });
            });
            return Object.keys(seen);
        }

        /**
         * Whether a word fits at a position without clashing with what is there.
         *
         * @param {string[]} chars
         * @param {number} row
         * @param {number} col
         * @param {Object} dir
         * @return {boolean}
         */
        function fits(chars, row, col, dir) {
            for (var i = 0; i < chars.length; i++) {
                var r = row + dir.dr * i;
                var c = col + dir.dc * i;
                if (r < 0 || r >= SIZE || c < 0 || c >= SIZE) {
                    return false;
                }
                // Crossing another word is fine as long as the shared cell holds
                // the same letter.
                if (grid[r][c] !== null && grid[r][c] !== chars[i]) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Write a word into the grid.
         *
         * @param {string} word
         * @return {boolean} whether it found a home
         */
        function place(word) {
            var chars = letters(word);
            var tries = 220;

            while (tries--) {
                var dir = api.pick(DIRECTIONS);
                var row = api.random(0, SIZE - 1);
                var col = api.random(0, SIZE - 1);

                if (!fits(chars, row, col, dir)) {
                    continue;
                }

                var cells = [];
                for (var i = 0; i < chars.length; i++) {
                    var r = row + dir.dr * i;
                    var c = col + dir.dc * i;
                    grid[r][c] = chars[i];
                    cells.push(r * SIZE + c);
                }
                hidden.push({word: word, cells: cells});
                return true;
            }

            return false;
        }

        /**
         * Build a grid and hide the round's words in it.
         */
        function build() {
            grid = [];
            hidden = [];
            found = [];

            for (var r = 0; r < SIZE; r++) {
                grid.push(new Array(SIZE).fill(null));
            }

            var pool = api.shuffle(api.words.filter(function (entry) {
                var size = letters(entry.word).length;
                return size >= 3 && size <= 6;
            }));

            for (var i = 0; i < pool.length && hidden.length < WORDS; i++) {
                place(pool[i].word);
            }

            // Everything still empty becomes filler.
            var pad = alphabet();
            for (var row = 0; row < SIZE; row++) {
                for (var col = 0; col < SIZE; col++) {
                    if (grid[row][col] === null) {
                        grid[row][col] = api.pick(pad);
                    }
                }
            }
        }

        /**
         * Draw the grid and the list of words to find.
         */
        function render() {
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-search';

            nodes.list = document.createElement('ul');
            nodes.list.className = 'gc-search__list';
            wrap.appendChild(nodes.list);

            nodes.grid = document.createElement('div');
            nodes.grid.className = 'gc-search__grid';
            nodes.grid.style.gridTemplateColumns = 'repeat(' + SIZE + ', 1fr)';

            for (var r = 0; r < SIZE; r++) {
                for (var c = 0; c < SIZE; c++) {
                    var cell = document.createElement('div');
                    cell.className = 'gc-search__cell';
                    cell.textContent = grid[r][c];
                    cell.dataset.index = String(r * SIZE + c);
                    nodes.grid.appendChild(cell);
                }
            }

            // One band follows the finger; a found word leaves its own behind.
            nodes.trace = document.createElement('div');
            nodes.trace.className = 'gc-search__band';
            nodes.trace.style.opacity = '0';
            nodes.grid.appendChild(nodes.trace);

            bindDrag();
            wrap.appendChild(nodes.grid);

            api.stage.appendChild(wrap);
            drawList();
        }

        /**
         * Redraw the word list, striking through the ones already found.
         */
        function drawList() {
            api.setProgress(found.length, hidden.length);
            nodes.list.innerHTML = '';

            var count = document.createElement('li');
            count.className = 'gc-search__count';
            count.textContent = api.strings.search_remaining.replace(
                '{$a}',
                api.fmt(hidden.length - found.length)
            );
            nodes.list.appendChild(count);

            hidden.forEach(function (entry) {
                var li = document.createElement('li');
                var done = found.indexOf(entry.word) !== -1;
                li.className = 'gc-search__word' + (done ? ' gc-search__word--done' : '');
                li.textContent = entry.word;
                nodes.list.appendChild(li);
            });
        }

        /**
         * Drag across the grid to trace a word.
         */
        function bindDrag() {
            nodes.grid.addEventListener('pointerdown', function (event) {
                var cell = cellAt(event);
                if (!cell) {
                    return;
                }
                event.preventDefault();
                // Start the trace before asking for capture. Capture is a
                // convenience - it keeps the drag alive if the finger slides
                // off the grid - but it can throw, and a throw here would
                // silently swallow the whole gesture.
                // The band starts on the first letter and stays up for the whole
                // gesture. It used to appear only once a second cell was
                // reached, so the first thing a child saw after pressing was
                // nothing - which reads as a broken control, and they let go.
                drag = {from: Number(cell.dataset.index), path: [Number(cell.dataset.index)]};
                paint(drag.path);
                try {
                    nodes.grid.setPointerCapture(event.pointerId);
                } catch (e) {
                    // No capture; the drag still works while the finger is on
                    // the grid, which is where it usually is.
                }
            });

            nodes.grid.addEventListener('pointermove', function (event) {
                if (!drag) {
                    return;
                }
                // The band tip follows the finger continuously, whether or not
                // the finger is over a square right now. Redrawing only when a
                // new square is entered made the line jump a whole cell at a
                // time; this is the same gesture drawn at pointer resolution.
                if (drag.path.length) {
                    stretch(drag.path[0], event.clientX, event.clientY);
                }

                var cell = cellAt(event);
                if (!cell) {
                    return;
                }
                var path = line(drag.from, Number(cell.dataset.index));
                // An unreachable square (nothing lines up with the start) keeps
                // the last good run highlighted.
                if (path.length) {
                    drag.path = path;
                    mark(path);
                }
            });

            nodes.grid.addEventListener('pointerup', function () {
                if (!drag) {
                    return;
                }
                var path = drag.path;
                drag = null;
                paint([]);
                if (path && path.length > 1) {
                    judge(path);
                }
            });

            nodes.grid.addEventListener('pointercancel', function () {
                drag = null;
                paint([]);
            });
        }

        /**
         * The grid cell under a pointer event.
         *
         * @param {PointerEvent} event
         * @return {HTMLElement|null}
         */
        function cellAt(event) {
            var el = document.elementFromPoint(event.clientX, event.clientY);
            return el && el.classList.contains('gc-search__cell') ? el : null;
        }

        /**
         * The straight run of cells between two indexes, or an empty array when
         * they do not line up.
         *
         * @param {number} from
         * @param {number} to
         * @return {number[]}
         */
        function line(from, to) {
            var r1 = Math.floor(from / SIZE);
            var c1 = from % SIZE;
            var r2 = Math.floor(to / SIZE);
            var c2 = to % SIZE;

            var dr = r2 - r1;
            var dc = c2 - c1;

            var straight = dr === 0 || dc === 0 || Math.abs(dr) === Math.abs(dc);
            if (!straight) {
                return [];
            }

            var steps = Math.max(Math.abs(dr), Math.abs(dc));
            var stepr = steps ? dr / steps : 0;
            var stepc = steps ? dc / steps : 0;

            var cells = [];
            for (var i = 0; i <= steps; i++) {
                cells.push((r1 + stepr * i) * SIZE + (c1 + stepc * i));
            }
            return cells;
        }

        /**
         * Highlight the cells currently being traced, and lay the band over
         * them.
         *
         * @param {number[]} cells
         */
        function paint(cells) {
            mark(cells);

            if (cells.length) {
                // A single cell draws a band over just that letter, so the
                // line exists from the first press onwards.
                band(nodes.trace, cells[0], cells[cells.length - 1]);
                nodes.trace.style.opacity = "1";
            } else {
                nodes.trace.style.opacity = "0";
            }
        }

        /**
         * Light up the letters a run covers.
         *
         *  {number[]} cells
         */
        function mark(cells) {
            var all = nodes.grid.querySelectorAll(".gc-search__cell");
            Array.prototype.forEach.call(all, function (cell) {
                cell.classList.toggle(
                    "gc-search__cell--tracing",
                    cells.indexOf(Number(cell.dataset.index)) !== -1
                );
            });
        }

        /**
         * Draw the band from a starting square to a raw pointer position.
         *
         *  {number} from cell index
         *  {number} x client x
         *  {number} y client y
         */
        function stretch(from, x, y) {
            var gridbox = nodes.grid.getBoundingClientRect();
            var a = cellNode(from).getBoundingClientRect();

            var ax = a.left + a.width / 2 - gridbox.left;
            var ay = a.top + a.height / 2 - gridbox.top;

            layBand(nodes.trace, ax, ay, x - gridbox.left, y - gridbox.top, a.width, a.height);
            nodes.trace.style.opacity = "1";
        }

        /**
         * Lay a capsule across the grid from one cell to another.
         *
         * Everything is measured off the real boxes rather than computed from
         * row and column numbers, so it lands correctly whichever way the grid
         * is reading - the same maths works in Arabic and in English.
         *
         * @param {HTMLElement} el the band to position
         * @param {number} from cell index
         * @param {number} to cell index
         */
        function band(el, from, to) {
            var gridbox = nodes.grid.getBoundingClientRect();
            var a = cellNode(from).getBoundingClientRect();
            var b = cellNode(to).getBoundingClientRect();

            layBand(
                el,
                a.left + a.width / 2 - gridbox.left,
                a.top + a.height / 2 - gridbox.top,
                b.left + b.width / 2 - gridbox.left,
                b.top + b.height / 2 - gridbox.top,
                a.width,
                a.height
            );
        }

        /**
         * Put the capsule between two points inside the grid.
         *
         * Split out from band() so the same geometry serves a run of squares
         * and a run that ends wherever the finger currently is.
         *
         * @param {HTMLElement} el
         * @param {number} ax start x, relative to the grid
         * @param {number} ay start y
         * @param {number} bx end x
         * @param {number} by end y
         * @param {number} cellw one cell's width
         * @param {number} cellh one cell's height
         */
        function layBand(el, ax, ay, bx, by, cellw, cellh) {
            var dx = bx - ax;
            var dy = by - ay;
            var thickness = cellh * 0.86;
            // The capsule runs centre to centre plus one cell, so it covers the
            // first and last letters rather than stopping at their middles.
            var length = Math.sqrt(dx * dx + dy * dy) + cellw;

            // Anchored at the first letter's centre and rotated about it, then
            // pushed back half a cell along its OWN axis. Anchoring at the
            // cell's left edge instead looks right for a word running left to
            // right and wrong for every other angle - in Arabic a row reads
            // right to left, the rotation is a half turn, and the band swung
            // out of the grid entirely.
            // Set here rather than in the stylesheet: transform-origin is a
            // physical property, so Moodle's RTL pass rewrites "0 50%" to
            // "100% 50%" for Arabic. The band would then pivot about its far
            // edge and swing right off the grid. Inline styles are written
            // after that pass and are left alone.
            el.style.transformOrigin = '0 50%';
            el.style.left = ax + 'px';
            el.style.top = (ay - thickness / 2) + 'px';
            el.style.width = length + 'px';
            el.style.height = thickness + 'px';
            el.style.transform = 'rotate(' + Math.atan2(dy, dx) + 'rad)'
                + " translateX(" + (-cellw / 2) + "px)";
        }

        /**
         * The DOM node for a cell index.
         *
         * @param {number} index
         * @return {HTMLElement}
         */
        function cellNode(index) {
            return nodes.grid.querySelector('[data-index="' + index + '"]');
        }

        /**
         * Is the traced run one of the hidden words?
         *
         * A run is checked in both directions, so a child who drags from the
         * last letter to the first is not told they are wrong.
         *
         * @param {number[]} path
         */
        function judge(path) {
            var forward = path.map(cellLetter).join('');
            var backward = path.slice().reverse().map(cellLetter).join('');

            var hit = hidden.filter(function (entry) {
                return found.indexOf(entry.word) === -1
                    && (entry.word === forward || entry.word === backward);
            })[0];

            if (!hit) {
                api.wrong();
                return;
            }

            found.push(hit.word);
            hit.cells.forEach(function (index) {
                cellNode(index).classList.add('gc-search__cell--found');
            });

            var locked = document.createElement('div');
            locked.className = 'gc-search__band gc-search__band--found';
            nodes.grid.appendChild(locked);
            band(locked, hit.cells[0], hit.cells[hit.cells.length - 1]);

            api.correct(hit.word);
            api.say(hit.word);
            drawList();

            if (found.length === hidden.length) {
                window.setTimeout(function () {
                    api.finish();
                }, 900);
            }
        }

        /**
         * The letter in a cell.
         *
         * @param {number} index
         * @return {string}
         */
        function cellLetter(index) {
            return grid[Math.floor(index / SIZE)][index % SIZE];
        }

        return {
            start: function () {
                build();
                render();
            },
            stop: function () {
                drag = null;
            }
        };
    });
}());
