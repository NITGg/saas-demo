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
 * Match the Picture - game 06.
 *
 * Four pictures, four words, and a word goes under the picture it names.
 *
 * The doc asks for dragging, and dragging is what a child reaches for; but a
 * drag that misses by two pixels on a phone is a lost turn. So the same press
 * does both: move the finger and it drags, lift without moving and the word is
 * simply selected, waiting for a tap on a picture.
 *
 * @module     local_games/match-connect
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Pairs on one board. */
    var PAIRS = 4;

    /** Boards in a round. */
    var BOARDS = 4;

    /** Movement, in pixels, that turns a press into a drag rather than a tap. */
    var DRAG_THRESHOLD = 8;

    window.LocalGames.register('match-connect', function (api) {

        var board = 0;
        var pairs = [];
        var solved = 0;
        var selected = null;
        var ghost = null;

        var nodes = {};

        /**
         * Deal one board: four words that all have a picture.
         */
        function deal() {
            pairs = api.shuffle(api.words.filter(function (entry) {
                return entry.emoji;
            })).slice(0, PAIRS);
            solved = 0;
            selected = null;
        }

        /**
         * Draw the pictures and the shuffled words.
         */
        function render() {
            api.setProgress(board, BOARDS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-match';

            nodes.zones = document.createElement('div');
            nodes.zones.className = 'gc-match__zones';

            pairs.forEach(function (pair) {
                var zone = document.createElement('div');
                zone.className = 'gc-match__zone';
                zone.dataset.word = pair.word;

                var face = document.createElement('span');
                face.className = 'gc-match__face';
                face.setAttribute('aria-hidden', 'true');
                face.textContent = pair.emoji;
                zone.appendChild(face);

                var slot = document.createElement('span');
                slot.className = 'gc-match__slot';
                slot.textContent = api.strings.match_drop;
                zone.appendChild(slot);

                zone.addEventListener('click', function () {
                    if (selected) {
                        drop(selected, zone);
                    }
                });

                nodes.zones.appendChild(zone);
            });
            wrap.appendChild(nodes.zones);

            nodes.chips = document.createElement('div');
            nodes.chips.className = 'gc-match__chips';

            api.shuffle(pairs).forEach(function (pair) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'gc-match__chip';
                chip.dataset.word = pair.word;
                chip.textContent = pair.word;
                bindChip(chip);
                nodes.chips.appendChild(chip);
            });
            wrap.appendChild(nodes.chips);

            api.stage.appendChild(wrap);
        }

        /**
         * Give a word chip its press-drag-or-tap behaviour.
         *
         * @param {HTMLElement} chip
         */
        function bindChip(chip) {
            var start = null;
            var dragging = false;

            chip.addEventListener('pointerdown', function (event) {
                if (chip.classList.contains('gc-match__chip--done')) {
                    return;
                }
                start = {x: event.clientX, y: event.clientY};
                dragging = false;
                try {
                    chip.setPointerCapture(event.pointerId);
                } catch (e) {
                    // Capture is a nicety here; the press is already recorded.
                }
            });

            chip.addEventListener('pointermove', function (event) {
                if (!start) {
                    return;
                }
                var dx = event.clientX - start.x;
                var dy = event.clientY - start.y;

                if (!dragging && Math.abs(dx) + Math.abs(dy) > DRAG_THRESHOLD) {
                    dragging = true;
                    select(chip);
                    makeGhost(chip);
                }
                if (dragging) {
                    moveGhost(event.clientX, event.clientY);
                }
            });

            chip.addEventListener('pointerup', function (event) {
                if (!start) {
                    return;
                }
                var wasDragging = dragging;
                start = null;
                dragging = false;

                if (!wasDragging) {
                    // A press that never moved: select, or deselect if it was
                    // already the chosen one.
                    select(selected === chip ? null : chip);
                    return;
                }

                killGhost();
                var zone = zoneUnder(event.clientX, event.clientY);
                if (zone) {
                    drop(chip, zone);
                } else {
                    select(null);
                }
            });

            chip.addEventListener('pointercancel', function () {
                start = null;
                dragging = false;
                killGhost();
            });
        }

        /**
         * Mark one chip as the chosen word.
         *
         * @param {HTMLElement|null} chip
         */
        function select(chip) {
            if (selected) {
                selected.classList.remove('gc-match__chip--picked');
            }
            selected = chip;
            if (chip) {
                chip.classList.add('gc-match__chip--picked');
                api.say(chip.textContent);
            }
        }

        /**
         * The floating copy that follows the finger.
         *
         * @param {HTMLElement} chip
         */
        function makeGhost(chip) {
            killGhost();
            ghost = chip.cloneNode(true);
            ghost.className = 'gc-match__chip gc-match__ghost';
            document.body.appendChild(ghost);
        }

        /**
         * @param {number} x
         * @param {number} y
         */
        function moveGhost(x, y) {
            if (!ghost) {
                return;
            }
            ghost.style.left = x + 'px';
            ghost.style.top = y + 'px';
        }

        /**
         * Remove the floating copy.
         */
        function killGhost() {
            if (ghost && ghost.parentNode) {
                ghost.parentNode.removeChild(ghost);
            }
            ghost = null;
        }

        /**
         * Which picture is under a point, if any.
         *
         * The ghost is skipped because it sits under the finger by definition.
         *
         * @param {number} x
         * @param {number} y
         * @return {HTMLElement|null}
         */
        function zoneUnder(x, y) {
            if (ghost) {
                ghost.style.display = 'none';
            }
            var el = document.elementFromPoint(x, y);
            if (ghost) {
                ghost.style.display = '';
            }
            return el ? el.closest('.gc-match__zone') : null;
        }

        /**
         * Try to put a word under a picture.
         *
         * @param {HTMLElement} chip
         * @param {HTMLElement} zone
         */
        function drop(chip, zone) {
            if (zone.classList.contains('gc-match__zone--done')) {
                return;
            }

            if (chip.dataset.word !== zone.dataset.word) {
                select(null);
                zone.classList.add('gc-match__zone--no');
                api.wrong();
                window.setTimeout(function () {
                    zone.classList.remove('gc-match__zone--no');
                }, 600);
                return;
            }

            select(null);
            chip.classList.add('gc-match__chip--done');
            chip.disabled = true;
            zone.classList.add('gc-match__zone--done');
            zone.querySelector('.gc-match__slot').textContent = chip.dataset.word;

            solved++;
            api.correct();

            if (solved === pairs.length) {
                window.setTimeout(nextBoard, 900);
            }
        }

        /**
         * Deal the next board, or end the round.
         */
        function nextBoard() {
            board++;
            api.setProgress(board, BOARDS);
            if (board >= BOARDS) {
                api.finish();
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
                killGhost();
                selected = null;
            }
        };
    });
}());
