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
 * Crossword - game 07.
 *
 * A small grid built fresh every round: the longest word goes down the middle,
 * and each word after it is hung off a letter of one already placed, so the
 * crossings the doc describes are real - filling one word really does hand the
 * child letters in another.
 *
 * Letters are entered from an on-screen pad rather than the keyboard. A child
 * on a tablet has no Arabic keyboard, and a child on a laptop may not have the
 * layout installed; the pad is the only input that works everywhere.
 *
 * @module     local_games/crossword
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Grid side. Big enough for six short words with room to cross. */
    var SIZE = 11;

    /** Words the generator aims for; the badge asks for five. */
    var TARGET = 5;

    /** Letters on the pad, including decoys. */
    var PAD = 16;

    window.LocalGames.register('crossword', function (api) {

        var grid = [];
        var entries = [];
        var active = null;
        var solved = 0;

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
         * An empty grid.
         */
        function blank() {
            grid = [];
            for (var r = 0; r < SIZE; r++) {
                grid.push(new Array(SIZE).fill(null));
            }
            entries = [];
        }

        /**
         * Whether a word may be written at a position.
         *
         * Beyond the obvious - in bounds, and matching wherever it crosses
         * something - a crossword also needs breathing room: a new letter may
         * not sit shoulder to shoulder with an unrelated word, and the cells
         * just before and after the word must be empty, or two words would run
         * together into one unreadable string.
         *
         * @param {string[]} chars
         * @param {number} row
         * @param {number} col
         * @param {boolean} across
         * @return {number} how many letters it would share, or -1 if it cannot go there
         */
        function score(chars, row, col, across) {
            var dr = across ? 0 : 1;
            var dc = across ? 1 : 0;
            var crossings = 0;

            var endr = row + dr * chars.length;
            var endc = col + dc * chars.length;

            if (row < 0 || col < 0 || endr > SIZE || endc > SIZE) {
                return -1;
            }

            // The cell before the first letter and after the last must be empty.
            if (inside(row - dr, col - dc) && grid[row - dr][col - dc] !== null) {
                return -1;
            }
            if (inside(endr, endc) && grid[endr][endc] !== null) {
                return -1;
            }

            for (var i = 0; i < chars.length; i++) {
                var r = row + dr * i;
                var c = col + dc * i;
                var here = grid[r][c];

                if (here !== null) {
                    if (here !== chars[i]) {
                        return -1;
                    }
                    crossings++;
                    continue;
                }

                // An empty cell we are about to fill must not touch another word
                // sideways.
                var sider = across ? 1 : 0;
                var sidec = across ? 0 : 1;
                if (inside(r - sider, c - sidec) && grid[r - sider][c - sidec] !== null) {
                    return -1;
                }
                if (inside(r + sider, c + sidec) && grid[r + sider][c + sidec] !== null) {
                    return -1;
                }
            }

            return crossings;
        }

        /**
         * @param {number} r
         * @param {number} c
         * @return {boolean}
         */
        function inside(r, c) {
            return r >= 0 && r < SIZE && c >= 0 && c < SIZE;
        }

        /**
         * Write a word into the grid.
         *
         * @param {Object} entry bank entry
         * @param {number} row
         * @param {number} col
         * @param {boolean} across
         */
        function write(entry, row, col, across) {
            var chars = letters(entry.word);
            var cells = [];

            for (var i = 0; i < chars.length; i++) {
                var r = row + (across ? 0 : i);
                var c = col + (across ? i : 0);
                grid[r][c] = chars[i];
                cells.push(r * SIZE + c);
            }

            entries.push({
                word: entry.word,
                clue: entry.clue,
                across: across,
                cells: cells,
                solved: false,
                number: 0
            });
        }

        /**
         * Try to hang a word off one already on the grid.
         *
         * Every crossing is scored and the best one wins, which keeps the puzzle
         * knotted together instead of sprawling.
         *
         * @param {Object} entry
         * @return {boolean}
         */
        function hang(entry) {
            var chars = letters(entry.word);
            var best = null;

            for (var i = 0; i < chars.length; i++) {
                for (var r = 0; r < SIZE; r++) {
                    for (var c = 0; c < SIZE; c++) {
                        if (grid[r][c] !== chars[i]) {
                            continue;
                        }

                        // Cross whatever is here the other way round.
                        [true, false].forEach(function (across) {
                            var startr = across ? r : r - i;
                            var startc = across ? c - i : c;
                            var got = score(chars, startr, startc, across);

                            if (got > 0 && (!best || got > best.got)) {
                                best = {row: startr, col: startc, across: across, got: got};
                            }
                        });
                    }
                }
            }

            if (!best) {
                return false;
            }
            write(entry, best.row, best.col, best.across);
            return true;
        }

        /**
         * Build a grid, retrying until enough words interlock.
         *
         * @return {boolean} whether a usable puzzle came out
         */
        function build() {
            for (var attempt = 0; attempt < 30; attempt++) {
                blank();

                var pool = api.shuffle(api.words.filter(function (item) {
                    var size = letters(item.word).length;
                    return size >= 3 && size <= 6 && item.clue;
                }));

                // The longest word first, across the middle.
                pool.sort(function (a, b) {
                    return letters(b.word).length - letters(a.word).length;
                });

                var seed = pool.shift();
                var seedchars = letters(seed.word);
                write(seed, Math.floor(SIZE / 2), Math.floor((SIZE - seedchars.length) / 2), true);

                for (var i = 0; i < pool.length && entries.length < TARGET; i++) {
                    hang(pool[i]);
                }

                if (entries.length >= TARGET) {
                    number();
                    return true;
                }
            }

            return entries.length >= 2;
        }

        /**
         * Number the entries the way a crossword does: by where they start,
         * reading the grid in order.
         */
        function number() {
            var order = entries.slice().sort(function (a, b) {
                return a.cells[0] - b.cells[0];
            });

            var next = 1;
            var seen = {};

            order.forEach(function (entry) {
                var start = entry.cells[0];
                if (seen[start] === undefined) {
                    seen[start] = next++;
                }
                entry.number = seen[start];
            });
        }

        /**
         * Draw the grid, the clue strip and the letter pad.
         */
        function render() {
            api.setProgress(solved, entries.length);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-cross';

            nodes.clue = document.createElement('p');
            nodes.clue.className = 'gc-cross__clue';
            nodes.clue.setAttribute('role', 'status');
            nodes.clue.setAttribute('aria-live', 'polite');
            nodes.clue.textContent = api.strings.cross_pickcell;
            wrap.appendChild(nodes.clue);

            nodes.grid = document.createElement('div');
            nodes.grid.className = 'gc-cross__grid';
            nodes.grid.style.gridTemplateColumns = 'repeat(' + SIZE + ', 1fr)';

            for (var r = 0; r < SIZE; r++) {
                for (var c = 0; c < SIZE; c++) {
                    var index = r * SIZE + c;
                    var cell = document.createElement('div');
                    cell.dataset.index = String(index);

                    if (grid[r][c] === null) {
                        cell.className = 'gc-cross__cell gc-cross__cell--blocked';
                        nodes.grid.appendChild(cell);
                        continue;
                    }

                    cell.className = 'gc-cross__cell';

                    var starts = entries.filter(function (entry) {
                        return entry.cells[0] === index;
                    });
                    if (starts.length) {
                        var tag = document.createElement('span');
                        tag.className = 'gc-cross__number';
                        tag.textContent = api.fmt(starts[0].number);
                        cell.appendChild(tag);
                    }

                    var glyph = document.createElement('span');
                    glyph.className = 'gc-cross__letter';
                    cell.appendChild(glyph);

                    /* eslint-disable-next-line no-loop-func */
                    cell.addEventListener('click', (function (at) {
                        return function () {
                            pick(at);
                        };
                    }(index)));

                    nodes.grid.appendChild(cell);
                }
            }
            wrap.appendChild(nodes.grid);

            nodes.pad = document.createElement('div');
            nodes.pad.className = 'gc-cross__pad';
            wrap.appendChild(nodes.pad);

            api.stage.appendChild(wrap);

            drawPad();
        }

        /**
         * Build the letter pad: every letter the answers need, plus decoys so
         * the pad is not a solution key in itself.
         */
        function drawPad() {
            var needed = {};
            entries.forEach(function (entry) {
                letters(entry.word).forEach(function (letter) {
                    needed[letter] = true;
                });
            });

            var keys = Object.keys(needed);
            var extras = api.shuffle(alphabet().filter(function (letter) {
                return !needed[letter];
            }));

            while (keys.length < PAD && extras.length) {
                keys.push(extras.shift());
            }

            nodes.pad.innerHTML = '';

            api.shuffle(keys).forEach(function (letter) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-tile';
                button.textContent = letter;
                button.addEventListener('click', function () {
                    type(letter);
                });
                nodes.pad.appendChild(button);
            });

            var back = document.createElement('button');
            back.type = 'button';
            back.className = 'gc-tile gc-tile--wide';
            back.textContent = '⌫';
            back.setAttribute('aria-label', api.strings.cross_backspace);
            back.addEventListener('click', erase);
            nodes.pad.appendChild(back);
        }

        /**
         * Every distinct letter in the bank.
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
         * Choose the word a tapped cell belongs to.
         *
         * A cell at a crossing belongs to two words; tapping it again switches
         * to the other one, which is how every crossword behaves.
         *
         * @param {number} index
         */
        function pick(index) {
            var here = entries.filter(function (entry) {
                return !entry.solved && entry.cells.indexOf(index) !== -1;
            });

            if (!here.length) {
                return;
            }

            var next = here[0];
            if (active && here.length > 1) {
                var position = here.indexOf(active);
                if (position !== -1) {
                    next = here[(position + 1) % here.length];
                }
            }

            active = next;
            nodes.clue.textContent = api.fmt(active.number) + ' '
                + (active.across ? api.strings.cross_across : api.strings.cross_down)
                + ' — ' + active.clue;
            api.say(active.clue);
            paint();
        }

        /**
         * Put a letter in the active word's first empty cell.
         *
         * @param {string} letter
         */
        function type(letter) {
            if (!active) {
                return;
            }

            var at = active.cells.filter(function (index) {
                return !cellLetter(index);
            })[0];

            if (at === undefined) {
                return;
            }

            setCell(at, letter);
            paint();

            var full = active.cells.every(function (index) {
                return cellLetter(index);
            });
            if (full) {
                judge();
            }
        }

        /**
         * Take back the last letter of the active word.
         */
        function erase() {
            if (!active) {
                return;
            }

            var filled = active.cells.filter(function (index) {
                return cellLetter(index) && !isLocked(index);
            });
            var at = filled[filled.length - 1];

            if (at !== undefined) {
                setCell(at, '');
                paint();
            }
        }

        /**
         * Is the active word right?
         */
        function judge() {
            var built = active.cells.map(cellLetter).join('');

            if (built === active.word) {
                active.solved = true;
                solved++;
                active.cells.forEach(function (index) {
                    cellNode(index).classList.add('gc-cross__cell--solved');
                });
                api.correct(active.word);
                api.say(active.word);
                active = null;
                nodes.clue.textContent = api.strings.cross_pickcell;
                api.setProgress(solved, entries.length);
                paint();

                if (solved === entries.length) {
                    window.setTimeout(function () {
                        api.finish();
                    }, 900);
                }
                return;
            }

            api.wrong();
            // Clear only what this word owns and no crossing letter another
            // solved word has already earned.
            active.cells.forEach(function (index) {
                if (!isLocked(index)) {
                    setCell(index, '');
                }
            });
            paint();
        }

        /**
         * The letter currently written in a cell.
         *
         * @param {number} index
         * @return {string}
         */
        function cellLetter(index) {
            var node = cellNode(index);
            return node ? node.querySelector('.gc-cross__letter').textContent : '';
        }

        /**
         * Write a letter into a cell.
         *
         * @param {number} index
         * @param {string} letter
         */
        function setCell(index, letter) {
            cellNode(index).querySelector('.gc-cross__letter').textContent = letter;
        }

        /**
         * Whether a cell belongs to a word already solved.
         *
         * @param {number} index
         * @return {boolean}
         */
        function isLocked(index) {
            return entries.some(function (entry) {
                return entry.solved && entry.cells.indexOf(index) !== -1;
            });
        }

        /**
         * @param {number} index
         * @return {HTMLElement}
         */
        function cellNode(index) {
            return nodes.grid.querySelector('[data-index="' + index + '"]');
        }

        /**
         * Highlight the word being worked on.
         */
        function paint() {
            var all = nodes.grid.querySelectorAll('.gc-cross__cell');
            Array.prototype.forEach.call(all, function (cell) {
                cell.classList.remove('gc-cross__cell--active');
            });

            if (!active) {
                return;
            }
            active.cells.forEach(function (index) {
                cellNode(index).classList.add('gc-cross__cell--active');
            });
        }

        return {
            start: function () {
                solved = 0;
                active = null;

                if (!build()) {
                    // The bank is too small or too oddly shaped to interlock.
                    // Ending the round is better than showing an empty grid.
                    api.finish();
                    return;
                }
                render();
            },
            stop: function () {
                active = null;
            }
        };
    });
}());
