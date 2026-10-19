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
 * Letter Order - game 04.
 *
 * A picture, its letters shuffled underneath, and slots to drop them into.
 * The child taps a letter to place it and taps a placed letter to take it back;
 * nothing is judged until the last slot is filled, so an experiment costs
 * nothing.
 *
 * Arabic letters are shown in their isolated forms because each one is its own
 * tile. That is the form a child learns first, and joining them inside separate
 * boxes would be a lie about where one letter ends.
 *
 * @module     local_games/letter-order
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Words in a round. The badge asks for fifteen, so a clean round earns it. */
    var WORDS = 15;

    window.LocalGames.register('letter-order', function (api) {

        var queue = [];
        var current = null;
        var tiles = [];
        var placed = [];
        var solved = 0;
        var locked = false;

        var nodes = {};

        /**
         * Split a word into the letters the child will see.
         *
         * Array.from walks code points, so a word never breaks in the middle of
         * a character.
         *
         * @param {string} word
         * @return {string[]}
         */
        function letters(word) {
            return Array.from(word);
        }

        /**
         * Build the round's word list: short words first, longer ones later.
         *
         * @return {Object[]}
         */
        function buildQueue() {
            var pool = api.words.filter(function (entry) {
                var size = letters(entry.word).length;
                return size >= 3 && size <= 6;
            });

            return api.shuffle(pool).slice(0, WORDS).sort(function (a, b) {
                return letters(a.word).length - letters(b.word).length;
            });
        }

        /**
         * Lay out the picture, the empty slots and the shuffled tiles.
         */
        function render() {
            api.setProgress(solved, WORDS);
            var target = letters(current.word);

            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-order';

            var face = document.createElement('div');
            face.className = 'gc-order__face';
            face.setAttribute('aria-hidden', 'true');
            face.textContent = current.emoji;
            wrap.appendChild(face);

            var hint = document.createElement('p');
            hint.className = 'gc-order__hint';
            hint.textContent = api.strings.order_hint.replace('{$a}', api.fmt(target.length));
            wrap.appendChild(hint);

            nodes.slots = document.createElement('div');
            nodes.slots.className = 'gc-order__slots';
            wrap.appendChild(nodes.slots);

            nodes.tray = document.createElement('div');
            nodes.tray.className = 'gc-order__tray';
            wrap.appendChild(nodes.tray);

            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'gc-btn gc-btn--ghost gc-order__clear';
            clear.textContent = api.strings.order_clear;
            clear.addEventListener('click', function () {
                if (!locked) {
                    reset();
                }
            });
            wrap.appendChild(clear);

            api.stage.appendChild(wrap);

            // Shuffle until the tiles are not already in the answer's order -
            // a word that starts solved teaches nothing.
            var shuffled;
            do {
                shuffled = api.shuffle(target);
            } while (target.length > 1 && shuffled.join('') === current.word);

            tiles = shuffled.map(function (letter, index) {
                return {letter: letter, index: index, used: false};
            });
            placed = [];

            drawTray();
            drawSlots();

            api.say(api.strings.order_hint.replace('{$a}', String(target.length)));
        }

        /**
         * Redraw the letters still waiting to be used.
         */
        function drawTray() {
            nodes.tray.innerHTML = '';

            tiles.forEach(function (tile) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-tile' + (tile.used ? ' gc-tile--used' : '');
                button.textContent = tile.letter;
                button.disabled = tile.used;
                button.addEventListener('click', function () {
                    place(tile);
                });
                nodes.tray.appendChild(button);
            });
        }

        /**
         * Redraw the answer row.
         */
        function drawSlots() {
            var target = letters(current.word);
            nodes.slots.innerHTML = '';

            for (var i = 0; i < target.length; i++) {
                var tile = placed[i];
                var slot = document.createElement('button');
                slot.type = 'button';
                slot.className = 'gc-slot' + (tile ? ' gc-slot--filled' : '');
                slot.textContent = tile ? tile.letter : '';
                slot.disabled = !tile;

                if (tile) {
                    /* eslint-disable-next-line no-loop-func */
                    slot.addEventListener('click', (function (position) {
                        return function () {
                            takeBack(position);
                        };
                    }(i)));
                }

                nodes.slots.appendChild(slot);
            }
        }

        /**
         * Drop a tile into the next free slot.
         *
         * @param {Object} tile
         */
        function place(tile) {
            if (locked || tile.used) {
                return;
            }
            tile.used = true;
            placed.push(tile);
            drawTray();
            drawSlots();

            if (placed.length === letters(current.word).length) {
                judge();
            }
        }

        /**
         * Pull a tile back out of the answer row.
         *
         * @param {number} position
         */
        function takeBack(position) {
            if (locked) {
                return;
            }
            var tile = placed.splice(position, 1)[0];
            if (tile) {
                tile.used = false;
            }
            drawTray();
            drawSlots();
        }

        /**
         * The row is full: is it the word?
         */
        function judge() {
            locked = true;

            var built = placed.map(function (tile) {
                return tile.letter;
            }).join('');

            if (built === current.word) {
                solved++;
                nodes.slots.classList.add('gc-order__slots--ok');
                api.setProgress(solved, WORDS);
                api.correct();
                api.say(current.word);
                window.setTimeout(next, 1100);
                return;
            }

            nodes.slots.classList.add('gc-order__slots--no');
            api.wrong();
            // Hand every tile back rather than ending the word: the doc's rule
            // is that a mistake costs nothing but another try.
            window.setTimeout(function () {
                nodes.slots.classList.remove('gc-order__slots--no');
                locked = false;
                reset();
            }, 900);
        }

        /**
         * Empty the answer row without changing the word.
         */
        function reset() {
            tiles.forEach(function (tile) {
                tile.used = false;
            });
            placed = [];
            drawTray();
            drawSlots();
        }

        /**
         * Move to the next word, or end the round.
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
                solved = 0;
                locked = false;
                queue = buildQueue();
                next();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
