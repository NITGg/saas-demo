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
 * Question Wheel - game 15.
 *
 * The wheel picks the SUBJECT of the next question. The doc puts a warning in
 * bold about this: it must never be a prize wheel, never a random reward. So
 * every segment here is a topic, every spin ends in a question, and the only
 * thing a child can win is the answer.
 *
 * The badge asks for one right answer from each of the four topics, which is
 * also the reason to keep spinning.
 *
 * @module     local_games/wheel
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Spins in a round. */
    var SPINS = 10;

    /**
     * How much speed the wheel keeps per frame once it is let go.
     *
     * Tuned so the hardest flick a child can give it settles in about three
     * seconds and a gentle one in under two - long enough to feel like a spin,
     * short enough that they are not waiting to be asked a question.
     */
    var FRICTION = 0.972;

    /** Degrees per second below which a flick counts as a tap. */
    var MIN_FLICK = 240;

    /** A ceiling, so a hard swipe does not spin for half a minute. */
    var MAX_FLICK = 1800;

    /** The wheel's segments, in order round the face. */
    var TOPICS = [
        {key: 'math', emoji: '🔢', colour: '#4b7be0'},
        {key: 'science', emoji: '🔬', colour: '#3fa877'},
        {key: 'language', emoji: '🔤', colour: '#9b5fd0'},
        {key: 'animals', emoji: '🐘', colour: '#e0894b'}
    ];

    window.LocalGames.register('wheel', function (api) {

        var spins = 0;
        var angle = 0;
        var velocity = 0;
        var frame = null;
        var spinning = false;
        var answered = {};

        var nodes = {};

        /**
         * Build the wheel, the pointer and the question slot.
         */
        function render() {
            api.setProgress(spins, SPINS);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-wheel';

            var stack = document.createElement('div');
            stack.className = 'gc-wheel__stack';

            var pointer = document.createElement('div');
            pointer.className = 'gc-wheel__pointer';
            pointer.setAttribute('aria-hidden', 'true');
            pointer.textContent = '▼';
            stack.appendChild(pointer);

            nodes.face = document.createElement('button');
            nodes.face.type = 'button';
            nodes.face.className = 'gc-wheel__face';
            nodes.face.setAttribute('aria-label', api.strings.wheel_spin);

            // Four equal segments drawn as one conic gradient, with the topic
            // marks laid on top.
            var step = 360 / TOPICS.length;
            var stops = TOPICS.map(function (topic, i) {
                return topic.colour + ' ' + (i * step) + 'deg ' + ((i + 1) * step) + 'deg';
            });
            nodes.face.style.background = 'conic-gradient(' + stops.join(', ') + ')';

            TOPICS.forEach(function (topic, i) {
                var mark = document.createElement('span');
                mark.className = 'gc-wheel__segment';
                mark.setAttribute('aria-hidden', 'true');
                mark.textContent = topic.emoji;
                // Sit each mark in the middle of its own wedge.
                mark.style.transform = 'rotate(' + (i * step + step / 2) + 'deg) translateY(-4.6rem)';
                nodes.face.appendChild(mark);
            });

            stack.appendChild(nodes.face);
            wrap.appendChild(stack);

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'gc-btn gc-btn--big';
            button.textContent = api.strings.wheel_spin;
            button.addEventListener('click', spin);
            nodes.spin = button;
            wrap.appendChild(button);

            nodes.slot = document.createElement('div');
            nodes.slot.className = 'gc-wheel__slot';
            wrap.appendChild(nodes.slot);

            nodes.topics = document.createElement('ul');
            nodes.topics.className = 'gc-wheel__topics';
            nodes.topics.setAttribute('aria-label', api.strings.wheel_topics);
            wrap.appendChild(nodes.topics);

            api.stage.appendChild(wrap);
            bindDrag();
            drawTopics();
        }

        /**
         * The tally of topics already answered - what the badge is counting.
         */
        function drawTopics() {
            nodes.topics.innerHTML = '';

            TOPICS.forEach(function (topic) {
                var li = document.createElement('li');
                li.className = 'gc-wheel__topic'
                    + (answered[topic.key] ? ' gc-wheel__topic--done' : '');
                li.innerHTML = '<span aria-hidden="true">' + topic.emoji + '</span>';

                var name = document.createElement('span');
                name.textContent = api.strings['topic_' + topic.key] || topic.key;
                li.appendChild(name);

                nodes.topics.appendChild(li);
            });
        }

        /**
         * Let the child turn the wheel with their hand.
         *
         * The wheel follows the finger while it is held, remembers how fast it
         * was moving when let go, and carries on from there. A wheel that only
         * responds to a button is a button with a picture of a wheel on it -
         * the doc's "the child does not know what is coming" only works if
         * turning it feels like turning something.
         */
        function bindDrag() {
            var dragging = false;
            var lastangle = 0;
            var lasttime = 0;

            /**
             * The angle of a pointer around the middle of the wheel.
             *
             * @param {PointerEvent} event
             * @return {number} degrees
             */
            var angleOf = function (event) {
                var box = nodes.face.getBoundingClientRect();
                var dx = event.clientX - (box.left + box.width / 2);
                var dy = event.clientY - (box.top + box.height / 2);
                return Math.atan2(dy, dx) * 180 / Math.PI;
            };

            nodes.face.addEventListener('pointerdown', function (event) {
                if (spinning || spins >= SPINS) {
                    return;
                }
                dragging = true;
                velocity = 0;
                lastangle = angleOf(event);
                lasttime = event.timeStamp;
                // No easing while the hand is on it: the wheel should sit
                // exactly where the finger has put it.
                nodes.face.style.transition = 'none';
                try {
                    nodes.face.setPointerCapture(event.pointerId);
                } catch (e) {
                    // Capture is a convenience.
                }
            });

            nodes.face.addEventListener('pointermove', function (event) {
                if (!dragging) {
                    return;
                }
                var now = angleOf(event);
                var moved = now - lastangle;

                // Crossing the -180/180 seam looks like a whole turn otherwise.
                if (moved > 180) {
                    moved -= 360;
                }
                if (moved < -180) {
                    moved += 360;
                }

                var elapsed = Math.max(event.timeStamp - lasttime, 1);
                velocity = moved / elapsed * 1000;
                angle += moved;
                lastangle = now;
                lasttime = event.timeStamp;

                nodes.face.style.transform = 'rotate(' + angle + 'deg)';
            });

            var release = function () {
                if (!dragging) {
                    return;
                }
                dragging = false;
                // A flick that barely moved is a tap: give it a push of its own
                // rather than letting the wheel sit there.
                if (Math.abs(velocity) < MIN_FLICK) {
                    velocity = (velocity < 0 ? -1 : 1) * (MIN_FLICK + Math.random() * 260);
                }
                velocity = Math.max(-MAX_FLICK, Math.min(MAX_FLICK, velocity));
                coast();
            };

            nodes.face.addEventListener('pointerup', release);
            nodes.face.addEventListener('pointercancel', release);
        }

        /**
         * Let go: the wheel keeps turning and slows to a stop under friction,
         * so how hard it was thrown decides how long it runs.
         */
        function coast() {
            spinning = true;
            nodes.spin.disabled = true;
            nodes.slot.innerHTML = '';

            var last = window.performance.now();

            var step = function (now) {
                var dt = Math.min((now - last) / 1000, 0.05);
                last = now;

                angle += velocity * dt;
                velocity *= Math.pow(FRICTION, dt * 60);
                nodes.face.style.transform = 'rotate(' + angle + 'deg)';

                if (Math.abs(velocity) > 12) {
                    frame = window.requestAnimationFrame(step);
                    return;
                }

                frame = null;
                settleWheel();
            };

            frame = window.requestAnimationFrame(step);
        }

        /**
         * Whichever wedge ended up under the pointer is the topic. Nothing is
         * chosen in advance - where it stops is where it stops.
         */
        function settleWheel() {
            var step = 360 / TOPICS.length;
            // The pointer sits at the top; work back through the rotation to
            // find which wedge is under it.
            var under = ((-angle % 360) + 360) % 360;
            var index = Math.floor(under / step) % TOPICS.length;

            spinning = false;
            ask(TOPICS[index]);
        }

        /**
         * The button, for anyone who would rather not drag - and for keyboards.
         */
        function spin() {
            if (spinning || spins >= SPINS) {
                return;
            }
            velocity = api.random(700, 1500);
            coast();
        }

        /**
         * Ask a question from the topic the wheel chose.
         *
         * @param {Object} topic
         */
        function ask(topic) {
            var name = api.strings['topic_' + topic.key] || topic.key;
            var question = api.questions(1, topic.key)[0];

            if (!question) {
                // No questions in that topic: spin again rather than stall.
                nodes.spin.disabled = false;
                return;
            }

            nodes.slot.innerHTML = '';

            var landed = document.createElement('p');
            landed.className = 'gc-wheel__landed';
            landed.textContent = api.strings.wheel_landed.replace('{$a}', name);
            nodes.slot.appendChild(landed);

            var ask = document.createElement('p');
            ask.className = 'gc-quiz__question';
            ask.textContent = question.question;
            nodes.slot.appendChild(ask);

            var row = document.createElement('div');
            row.className = 'gc-quiz__answers';

            question.options.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-quiz__answer';
                button.textContent = option;
                button.addEventListener('click', function () {
                    settle(button, option, question, topic, row);
                });
                row.appendChild(button);
            });
            nodes.slot.appendChild(row);

            api.say(landed.textContent + '. ' + question.question);
        }

        /**
         * Score the answer and get ready for the next spin.
         *
         * @param {HTMLElement} button
         * @param {string} option
         * @param {Object} question
         * @param {Object} topic
         * @param {HTMLElement} row
         */
        function settle(button, option, question, topic, row) {
            Array.prototype.forEach.call(row.children, function (el) {
                el.disabled = true;
            });

            if (option === question.answer) {
                button.classList.add('gc-quiz__answer--ok');
                answered[topic.key] = true;
                api.correct();
            } else {
                button.classList.add('gc-quiz__answer--no');
                Array.prototype.forEach.call(row.children, function (el) {
                    if (el.textContent === question.answer) {
                        el.classList.add('gc-quiz__answer--ok');
                    }
                });
                api.wrong();
            }

            drawTopics();
            spins++;
            api.setProgress(spins, SPINS);

            window.setTimeout(function () {
                if (spins >= SPINS) {
                    // The goal is how many of the four topics were answered
                    // correctly at least once.
                    api.finish(undefined, Object.keys(answered).length);
                    return;
                }
                nodes.spin.disabled = false;
                nodes.slot.innerHTML = '';
            }, 1600);
        }

        return {
            start: function () {
                spins = 0;
                angle = 0;
                velocity = 0;
                spinning = false;
                answered = {};
                render();
            },
            stop: function () {
                spinning = true;
                if (frame) {
                    window.cancelAnimationFrame(frame);
                    frame = null;
                }
            }
        };
    });
}());
