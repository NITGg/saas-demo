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
 * Word Builder - game 05.
 *
 * Seven letters, and as many words as the child can find in them. Unlike Letter
 * Order there is no single right answer, so the letters cannot be picked at
 * random: a set that hides only one word is a dead end. The set is grown from a
 * seed word, one letter at a time, always choosing the letter that opens up the
 * most words in the bank.
 *
 * @module     local_games/word-builder
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Letter sets in a round. */
    var SETS = 3;

    /** How many letters the child gets. */
    var SIZE = 7;

    /** A set is only worth playing if it hides at least this many words. */
    var MIN_WORDS = 4;

    /** The most words one board asks for. More than this reads as a wall. */
    var MAX_TARGETS = 6;

    window.LocalGames.register('word-builder', function (api) {

        var setIndex = 0;
        var pool = [];
        var current = null;
        var typed = [];
        var found = [];
        var revealed = {};

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
         * How many of each letter a list holds.
         *
         * @param {string[]} list
         * @return {Object}
         */
        function counts(list) {
            var out = {};
            list.forEach(function (letter) {
                out[letter] = (out[letter] || 0) + 1;
            });
            return out;
        }

        /**
         * Whether a word can be spelled from a letter pile.
         *
         * @param {string} word
         * @param {Object} have letter => count
         * @return {boolean}
         */
        function buildable(word, have) {
            var need = counts(letters(word));
            for (var letter in need) {
                if (Object.prototype.hasOwnProperty.call(need, letter)) {
                    if ((have[letter] || 0) < need[letter]) {
                        return false;
                    }
                }
            }
            return true;
        }

        /**
         * Every bank word a pile can spell.
         *
         * @param {string[]} pile
         * @return {string[]}
         */
        function solutions(pile) {
            var have = counts(pile);
            return pool.filter(function (word) {
                return buildable(word, have);
            });
        }

        /**
         * Grow a playable seven-letter set out of one seed word.
         *
         * @return {{pile: string[], words: string[]}}
         */
        function makeSet() {
            var seeds = api.shuffle(pool.filter(function (word) {
                var size = letters(word).length;
                return size >= 4 && size <= SIZE;
            }));

            var best = null;

            for (var attempt = 0; attempt < seeds.length && attempt < 12; attempt++) {
                var pile = letters(seeds[attempt]).slice(0, SIZE);

                // Add whichever letter unlocks the most words, until the pile is
                // full. Candidates are the letters the bank actually uses, so a
                // rare letter never wastes a slot.
                while (pile.length < SIZE) {
                    var candidates = api.shuffle(alphabet());
                    var pick = candidates[0];
                    var pickScore = -1;

                    candidates.forEach(function (letter) {
                        var score = solutions(pile.concat([letter])).length;
                        if (score > pickScore) {
                            pickScore = score;
                            pick = letter;
                        }
                    });

                    pile.push(pick);
                }

                var words = shortlist(solutions(pile));
                if (!best || words.length > best.words.length) {
                    best = {pile: pile, words: words};
                }
                if (words.length >= MIN_WORDS) {
                    break;
                }
            }

            return best;
        }

        /**
         * Trim a set of answers to something a child can hold in their head.
         *
         * A good pile can spell a dozen words, and a board asking for twelve
         * reads as a wall. Short words come first because they are the way in,
         * and the longest is always kept because it is the one worth five.
         *
         * @param {string[]} words
         * @return {string[]}
         */
        function shortlist(words) {
            var byLength = words.slice().sort(function (a, b) {
                return letters(a).length - letters(b).length;
            });

            var longest = byLength[byLength.length - 1];
            var out = byLength.slice(0, MAX_TARGETS);

            if (longest && out.indexOf(longest) === -1) {
                out[out.length - 1] = longest;
            }

            return out;
        }

        /**
         * Every distinct letter the bank uses.
         *
         * @return {string[]}
         */
        function alphabet() {
            var seen = {};
            pool.forEach(function (word) {
                letters(word).forEach(function (letter) {
                    seen[letter] = true;
                });
            });
            return Object.keys(seen);
        }

        /**
         * What a word is worth, per the design doc: two points for three
         * letters, five for five or more.
         *
         * @param {string} word
         * @return {number}
         */
        function value(word) {
            var size = letters(word).length;
            if (size >= 5) {
                return 5;
            }
            return size >= 4 ? 3 : 2;
        }

        /**
         * Draw the letter pad, the word being built and the words found so far.
         */
        function render() {
            api.setProgress(setIndex, SETS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-build';

            nodes.typed = document.createElement('div');
            nodes.typed.className = 'gc-build__typed';
            wrap.appendChild(nodes.typed);

            nodes.pad = document.createElement('div');
            nodes.pad.className = 'gc-build__pad';
            wrap.appendChild(nodes.pad);

            var actions = document.createElement('div');
            actions.className = 'gc-build__actions';

            var submit = document.createElement('button');
            submit.type = 'button';
            submit.className = 'gc-btn';
            submit.textContent = api.strings.build_submit;
            submit.addEventListener('click', judge);
            actions.appendChild(submit);

            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'gc-btn gc-btn--ghost';
            clear.textContent = api.strings.build_clear;
            clear.addEventListener('click', function () {
                typed = [];
                drawTyped();
                drawPad();
            });
            actions.appendChild(clear);

            var hint = document.createElement('button');
            hint.type = 'button';
            hint.className = 'gc-btn gc-btn--ghost';
            hint.textContent = api.strings.build_hint;
            hint.addEventListener('click', giveHint);
            actions.appendChild(hint);

            var skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'gc-btn gc-btn--ghost';
            skip.textContent = api.strings.build_next;
            skip.addEventListener('click', nextSet);
            actions.appendChild(skip);

            wrap.appendChild(actions);

            var heading = document.createElement('p');
            heading.className = 'gc-build__foundcount';
            nodes.count = heading;
            wrap.appendChild(heading);

            nodes.targets = document.createElement('ul');
            nodes.targets.className = 'gc-build__targets';
            nodes.targets.setAttribute('aria-label', api.strings.build_targets);
            wrap.appendChild(nodes.targets);

            api.stage.appendChild(wrap);

            drawTyped();
            drawPad();
            drawTargets();
        }

        /**
         * Redraw the word under construction.
         */
        function drawTyped() {
            nodes.typed.innerHTML = '';

            if (!typed.length) {
                nodes.typed.classList.add('gc-build__typed--empty');
                return;
            }
            nodes.typed.classList.remove('gc-build__typed--empty');

            typed.forEach(function (entry) {
                var tile = document.createElement('span');
                tile.className = 'gc-tile gc-tile--static';
                tile.textContent = entry.letter;
                nodes.typed.appendChild(tile);
            });
        }

        /**
         * Redraw the seven letters, greying out the ones already in use.
         */
        function drawPad() {
            nodes.pad.innerHTML = '';

            current.pile.forEach(function (letter, index) {
                var inUse = typed.some(function (entry) {
                    return entry.index === index;
                });

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-tile' + (inUse ? ' gc-tile--used' : '');
                button.textContent = letter;
                button.disabled = inUse;
                button.addEventListener('click', function () {
                    typed.push({letter: letter, index: index});
                    drawTyped();
                    drawPad();
                });
                nodes.pad.appendChild(button);
            });
        }

        /**
         * Draw the board of words to find.
         *
         * This is what turned the game from guesswork into a puzzle. Before it
         * the child faced seven letters and no idea what was hiding in them -
         * nothing told them whether to look for a three-letter word or a six,
         * or how many there were. Now every answer has a row of empty boxes:
         * the shape of the word is given, the letters are not.
         */
        function drawTargets() {
            nodes.count.textContent = api.strings.build_found.replace('{$a}', api.fmt(found.length))
                + ' / ' + api.fmt(current.words.length);

            nodes.targets.innerHTML = '';

            current.words.forEach(function (word) {
                var done = found.indexOf(word) !== -1;
                var shown = revealed[word] || 0;

                var row = document.createElement('li');
                row.className = 'gc-build__target' + (done ? ' gc-build__target--done' : '');

                letters(word).forEach(function (letter, index) {
                    var box = document.createElement('span');
                    box.className = 'gc-build__box';
                    // A found word shows everything; otherwise only the letters
                    // a hint has already paid for.
                    if (done || index < shown) {
                        box.textContent = letter;
                        box.classList.add('gc-build__box--shown');
                    }
                    row.appendChild(box);
                });

                nodes.targets.appendChild(row);
            });
        }

        /**
         * Reveal one more letter of the easiest word still missing.
         *
         * Hints cost nothing. The design doc's rule is that a child is never
         * punished for not knowing something, and a hint they have to pay for
         * is a hint they will not use.
         */
        function giveHint() {
            var candidates = current.words.filter(function (word) {
                return found.indexOf(word) === -1
                    // Never hand over the last letter: finishing the word is
                    // the part worth doing.
                    && (revealed[word] || 0) < letters(word).length - 1;
            }).sort(function (a, b) {
                return letters(a).length - letters(b).length;
            });

            if (!candidates.length) {
                api.wrong(api.strings.build_nohints);
                return;
            }

            var word = candidates[0];
            revealed[word] = (revealed[word] || 0) + 1;
            drawTargets();
        }

        /**
         * Check the word the child built.
         */
        function judge() {
            var word = typed.map(function (entry) {
                return entry.letter;
            }).join('');

            typed = [];
            drawTyped();
            drawPad();

            if (!word) {
                return;
            }

            if (found.indexOf(word) !== -1) {
                api.wrong(api.strings.build_already);
                return;
            }

            if (current.words.indexOf(word) === -1) {
                api.wrong(api.strings.build_notaword);
                return;
            }

            found.push(word);
            // One correct event per word, worth more points for a longer one.
            // The badge counts words; the HUD counts points.
            api.correct(word, value(word));
            api.say(word);
            drawTargets();

            if (found.length === current.words.length) {
                window.setTimeout(nextSet, 1000);
            }
        }

        /**
         * Move on to a fresh pile, or end the round.
         */
        function nextSet() {
            setIndex++;
            api.setProgress(setIndex, SETS);
            if (setIndex >= SETS) {
                api.finish();
                return;
            }
            startSet();
        }

        /**
         * Deal a new pile.
         */
        function startSet() {
            current = makeSet();
            typed = [];
            found = [];
            revealed = {};
            render();
        }

        return {
            start: function () {
                // The picture bank plus the wider vocabulary, as one list.
                //
                // Judging against the picture bank alone was the bug a child
                // would meet within a minute: the bank holds forty words, so a
                // perfectly good word they had just spelled came back as "not a
                // word we know". The same list also drives the pile generator
                // and the "words left" counter, so all three now agree on what
                // counts as a word.
                var seen = {};
                pool = [];

                api.words.map(function (entry) {
                    return entry.word;
                }).concat(api.wordlist).forEach(function (word) {
                    // Two-letter words are dropped from this game entirely.
                    // The doc scores from three letters up, and a board asking
                    // for a two-box word is asking the child to guess rather
                    // than to build. Dropping them from the pool rather than
                    // just from the board keeps the answers and what the game
                    // accepts as a word the same list.
                    if (word && !seen[word] && letters(word).length >= 3) {
                        seen[word] = true;
                        pool.push(word);
                    }
                });

                setIndex = 0;
                startSet();
            },
            stop: function () {
                typed = [];
            }
        };
    });
}());
