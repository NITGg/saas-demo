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
 * Memory Cards - game 18.
 *
 * Twelve cards, six pairs, no reading required - the doc calls this the game
 * that suits the youngest children best, and the only thing on a card is a
 * picture.
 *
 * The badge asks for a board cleared in under twenty flips, which is why the
 * flip counter is on screen rather than hidden in the score.
 *
 * @module     local_games/memory-cards
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Pairs on the board. */
    var PAIRS = 6;

    /** Flips a board must be cleared in to earn the badge. */
    var FLIP_BUDGET = 20;

    window.LocalGames.register('memory-cards', function (api) {

        var cards = [];
        var open = [];
        var matched = 0;
        var flips = 0;
        var busy = false;

        var nodes = {};

        /**
         * Deal a shuffled board of pairs.
         */
        function deal() {
            var picked = api.shuffle(api.words.filter(function (entry) {
                return entry.emoji;
            })).slice(0, PAIRS);

            cards = api.shuffle(picked.concat(picked).map(function (entry, i) {
                return {id: i, key: entry.word, emoji: entry.emoji, up: false, done: false};
            }));

            open = [];
            matched = 0;
            flips = 0;
            busy = false;
        }

        /**
         * Draw the board.
         */
        function render() {
            api.setProgress(matched, PAIRS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-memory';

            nodes.flips = document.createElement('p');
            nodes.flips.className = 'gc-memory__flips';
            wrap.appendChild(nodes.flips);

            nodes.board = document.createElement('div');
            nodes.board.className = 'gc-memory__board';

            cards.forEach(function (card, i) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-memory__card';
                button.dataset.at = String(i);
                button.innerHTML = "<span class=\"gc-memory__inner\">"
                    + "<span class=\"gc-memory__back\" aria-hidden=\"true\">🎴</span>"
                    + "<span class=\"gc-memory__front\" aria-hidden=\"true\"></span>"
                    + "</span>";
                button.addEventListener('click', function () {
                    flip(card);
                });
                nodes.board.appendChild(button);
            });

            wrap.appendChild(nodes.board);
            api.stage.appendChild(wrap);
            paint();
        }

        /**
         * Put every card in the state it is in.
         */
        function paint() {
            nodes.flips.textContent = api.strings.memory_flips.replace('{$a}', api.fmt(flips));

            Array.prototype.forEach.call(nodes.board.children, function (el, i) {
                var card = cards[i];
                var up = card.up || card.done;
                var wasup = el.classList.contains('gc-memory__card--up');

                el.classList.toggle('gc-memory__card--up', up);
                el.classList.toggle('gc-memory__card--done', card.done);
                el.disabled = card.done || card.up || busy;
                el.querySelector('.gc-memory__front').textContent = card.emoji;

                // Only a card that has actually changed sides turns. Running
                // the animation on every paint would set the whole board
                // twitching each time one card was tapped.
                if (up !== wasup) {
                    el.classList.remove('gc-memory__card--turning');
                    // Reading offsetWidth restarts the animation.
                    void el.offsetWidth;
                    el.classList.add('gc-memory__card--turning');
                }
            });
        }

        /**
         * Turn a card face up.
         *
         * @param {Object} card
         */
        function flip(card) {
            if (busy || card.up || card.done) {
                return;
            }

            card.up = true;
            flips++;
            open.push(card);
            paint();

            if (open.length < 2) {
                return;
            }

            busy = true;
            var a = open[0];
            var b = open[1];

            if (a.key === b.key) {
                window.setTimeout(function () {
                    a.done = true;
                    b.done = true;
                    open = [];
                    matched++;
                    busy = false;
                    api.correct();
                    api.say(a.key);
                    api.setProgress(matched, PAIRS);
                    paint();

                    if (matched === PAIRS) {
                        window.setTimeout(done, 900);
                    }
                }, 420);
                return;
            }

            window.setTimeout(function () {
                a.up = false;
                b.up = false;
                open = [];
                busy = false;
                // A pair that did not match is not a wrong answer - nothing was
                // claimed. Only the flip count moves.
                paint();
            }, 1200);
        }

        /**
         * Board cleared.
         */
        function done() {
            // The goal is clearing it inside the flip budget - a count of
            // matches could never express "and quickly".
            api.finish(undefined, flips <= FLIP_BUDGET ? 1 : 0);
        }

        return {
            start: function () {
                deal();
                render();
            },
            stop: function () {
                busy = true;
            }
        };
    });
}());
