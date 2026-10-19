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
 * Say the Word - game 09.
 *
 * A picture, a word, and a microphone button. The browser's own speech
 * recognition listens and the game says whether the word came through.
 *
 * Two things this game has to get right that the others do not:
 *
 *  - The doc requires the child to be told before the microphone is opened.
 *    The start card carries that notice, and the browser is only asked for the
 *    microphone after the child has read it and pressed start.
 *  - Recognition is not exact. A child saying a word is compared after both
 *    sides are normalised - tashkeel stripped, alef and ya and ta-marbuta
 *    folded - and every alternative the engine offers is checked, because
 *    marking a correct child wrong is the worst thing this game could do.
 *
 * @module     local_games/speak-words
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Words in a round - the badge asks for ten. */
    var WORDS = 10;

    window.LocalGames.register('speak-words', function (api) {

        var queue = [];
        var current = null;
        var recogniser = null;
        var listening = false;
        var said = 0;

        var nodes = {};

        /**
         * The browser's speech recognition, under either of its two names.
         *
         * @return {Function|null}
         */
        function engine() {
            return window.SpeechRecognition || window.webkitSpeechRecognition || null;
        }

        /**
         * Fold away the differences that should not decide whether a child said
         * the word: diacritics, the shapes of alef, ta-marbuta against ha, alef
         * maqsura against ya, and any spacing or punctuation.
         *
         * @param {string} text
         * @return {string}
         */
        function normalise(text) {
            return String(text)
                .replace(/[ً-ْٰ]/g, '')
                .replace(/[آأإٱ]/g, 'ا')
                .replace(/ة/g, 'ه')
                .replace(/ى/g, 'ي')
                .replace(/[^\p{L}]/gu, '')
                .toLowerCase();
        }

        /**
         * Draw the "this browser cannot listen" card.
         */
        function unsupported() {
            api.stage.innerHTML = '';

            var panel = document.createElement('div');
            panel.className = 'gc-speak__notice';
            panel.innerHTML = '<div class="gc-speak__noticeface" aria-hidden="true">🎤</div>';

            var text = document.createElement('p');
            text.textContent = api.strings.speak_nomic;
            panel.appendChild(text);

            api.stage.appendChild(panel);
        }

        /**
         * Draw the current word and the microphone button.
         */
        function render() {
            api.setProgress(said, WORDS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-speak';

            var face = document.createElement('div');
            face.className = 'gc-speak__face';
            face.setAttribute('aria-hidden', 'true');
            face.textContent = current.emoji;
            wrap.appendChild(face);

            var ask = document.createElement('p');
            ask.className = 'gc-speak__ask';
            ask.textContent = api.strings.speak_saythis;
            wrap.appendChild(ask);

            var word = document.createElement('p');
            word.className = 'gc-speak__word';
            word.textContent = current.word;
            wrap.appendChild(word);

            nodes.button = document.createElement('button');
            nodes.button.type = 'button';
            nodes.button.className = 'gc-speak__mic';
            nodes.button.innerHTML = '<span aria-hidden="true">🎤</span>';

            var label = document.createElement('span');
            label.className = 'gc-speak__miclabel';
            label.textContent = api.strings.speak_tap;
            nodes.button.appendChild(label);

            nodes.button.addEventListener('click', listen);
            wrap.appendChild(nodes.button);

            nodes.status = document.createElement('p');
            nodes.status.className = 'gc-speak__status';
            nodes.status.setAttribute('role', 'status');
            nodes.status.setAttribute('aria-live', 'polite');
            wrap.appendChild(nodes.status);

            api.stage.appendChild(wrap);

            // Say the word once so a child who cannot read it yet still knows
            // what to repeat.
            api.say(current.word);
        }

        /**
         * Open the microphone for one attempt.
         */
        function listen() {
            if (listening) {
                return;
            }

            var Engine = engine();
            if (!Engine) {
                unsupported();
                return;
            }

            // The voice and the microphone must not talk over each other.
            api.audio.silence();

            recogniser = new Engine();
            recogniser.lang = api.lang;
            recogniser.interimResults = false;
            recogniser.maxAlternatives = 5;
            recogniser.continuous = false;

            recogniser.onstart = function () {
                listening = true;
                nodes.button.classList.add('gc-speak__mic--live');
                nodes.status.textContent = api.strings.speak_listening;
            };

            recogniser.onresult = function (event) {
                var result = event.results[0];
                var heard = [];
                for (var i = 0; i < result.length; i++) {
                    heard.push(result[i].transcript);
                }
                settle(heard);
            };

            recogniser.onerror = function (event) {
                stopListening();
                nodes.status.textContent = event.error === 'not-allowed'
                    ? api.strings.speak_denied
                    : api.strings.wrong;
            };

            recogniser.onend = function () {
                stopListening();
            };

            try {
                recogniser.start();
            } catch (e) {
                stopListening();
                nodes.status.textContent = api.strings.speak_denied;
            }
        }

        /**
         * Drop out of listening state.
         */
        function stopListening() {
            listening = false;
            if (nodes.button) {
                nodes.button.classList.remove('gc-speak__mic--live');
            }
        }

        /**
         * Judge what came back from the microphone.
         *
         * @param {string[]} heard every alternative the engine offered
         */
        function settle(heard) {
            var target = normalise(current.word);

            var hit = heard.some(function (text) {
                var said = normalise(text);
                // A child often says more than the word alone ("this is a cat"),
                // so containing the word counts.
                return said === target || said.indexOf(target) !== -1;
            });

            nodes.status.textContent = api.strings.speak_heard.replace('{$a}', heard[0] || '');

            if (hit) {
                said++;
                api.setProgress(said, WORDS);
                api.correct();
                window.setTimeout(next, 1100);
                return;
            }

            // Not recognised is not a failed round: the word comes back later.
            api.wrong();
            queue.push(current);
        }

        /**
         * Move to the next word, or end the round.
         */
        function next() {
            if (!queue.length) {
                api.finish();
                return;
            }
            current = queue.shift();
            render();
        }

        return {
            start: function () {
                if (!engine()) {
                    unsupported();
                    return;
                }
                said = 0;
                queue = api.shuffle(api.words.filter(function (entry) {
                    return entry.emoji;
                })).slice(0, WORDS);
                next();
            },
            stop: function () {
                if (recogniser) {
                    try {
                        recogniser.abort();
                    } catch (e) {
                        // Already stopped.
                    }
                    recogniser = null;
                }
                stopListening();
            }
        };
    });
}());
