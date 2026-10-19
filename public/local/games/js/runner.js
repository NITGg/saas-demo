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
 * Learning Run - game 22.
 *
 * The doc says this is the one that excites older children most, because it
 * feels like a game rather than a test. Two things make that true and both are
 * in the doc: the question stays fixed at the top so the child is never reading
 * and dodging at the same time, and the answers arrive as boxes in three lanes.
 *
 * Steering is by the same three routes as Number Catcher - arrow keys, a finger
 * anywhere on the track, or two large buttons.
 *
 * @module     local_games/runner
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Hearts. */
    var HEARTS = 3;

    /** Right answers that finish the stage. */
    var TARGET = 10;

    /** Lanes the runner moves between. */
    var LANES = 3;

    /** Seconds a box takes to travel the track, first to last. */
    var TRAVEL_SLOW = 4.2;
    var TRAVEL_FAST = 2.6;

    /**
     * Gap between box waves, in milliseconds.
     *
     * Deliberately longer than the travel time above. When it was shorter the
     * next question appeared while the previous wave was still falling, and
     * the doc's promise that "the question stays fixed at the top" quietly
     * broke - the child was reading one question and catching the answers to
     * another.
     */
    var WAVE_START = 4600;
    var WAVE_MIN = 3000;

    window.LocalGames.register('runner', function (api) {

        var questions = [];
        var current = null;
        var boxes = [];
        var waveid = 0;
        var lane = 1;
        var hearts = HEARTS;
        var collected = 0;
        var wavetimer = 0;
        var frame = null;
        var lastframe = 0;
        var running = false;

        var nodes = {};
        var view = {w: 0, h: 0};

        /**
         * Build the track once.
         */
        function build() {
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-runner';
            wrap.innerHTML =
                '<p class="gc-runner__question" data-role="question" role="status"></p>'
                + '<div class="gc-runner__track" data-role="track">'
                + '<span class="gc-runner__hero" data-role="hero" aria-hidden="true">🏃</span>'
                + '</div>'
                + '<div class="gc-runner__controls">'
                + '<button type="button" class="gc-steer" data-steer="-1"></button>'
                + '<button type="button" class="gc-steer" data-steer="1"></button>'
                + '</div>';

            api.stage.appendChild(wrap);

            nodes.question = wrap.querySelector('[data-role="question"]');
            nodes.track = wrap.querySelector('[data-role="track"]');
            nodes.hero = wrap.querySelector('[data-role="hero"]');

            var leftbutton = wrap.querySelector('[data-steer="-1"]');
            var rightbutton = wrap.querySelector('[data-steer="1"]');
            leftbutton.appendChild(arrow('⬅️'));
            leftbutton.appendChild(label(api.strings.catch_left));
            rightbutton.appendChild(label(api.strings.catch_right));
            rightbutton.appendChild(arrow('➡️'));

            leftbutton.addEventListener('click', function () {
                move(-1);
            });
            rightbutton.addEventListener('click', function () {
                move(1);
            });

            bindPointer();
            document.addEventListener('keydown', onKeyDown);
            window.addEventListener('resize', resize);

            resize();
        }

        /**
         * @param {string} glyph
         * @return {HTMLElement}
         */
        function arrow(glyph) {
            var el = document.createElement('span');
            el.className = 'gc-steer__arrow';
            el.setAttribute('aria-hidden', 'true');
            el.textContent = glyph;
            return el;
        }

        /**
         * @param {string} text
         * @return {HTMLElement}
         */
        function label(text) {
            var el = document.createElement('span');
            el.className = 'gc-steer__label';
            el.textContent = text;
            return el;
        }

        /**
         * Cache the track size.
         */
        function resize() {
            if (!nodes.track) {
                return;
            }
            var box = nodes.track.getBoundingClientRect();
            view.w = box.width;
            view.h = box.height;
            placeHero();
        }

        /**
         * Ask the next question and clear the track under it.
         */
        function ask() {
            if (!questions.length) {
                questions = api.questions(30);
            }
            var next = questions.shift();
            // A bank that came back empty must not take the game down with it.
            // Keeping the question already on screen is a far better failure
            // than a thrown exception that silently kills the animation loop.
            if (!next) {
                return;
            }
            current = next;
            nodes.question.textContent = current.question;
            api.say(current.question);
        }

        /**
         * Send one wave of boxes down the track - one per lane, one of them
         * right.
         */
        function wave() {
            // The right answer and ONE wrong one, in two of the three lanes.
            //
            // Filling all three lanes looked more like a game and played much
            // worse: with a box in every lane the child cannot dodge, only
            // choose, and a moment's hesitation costs a heart. The doc asks
            // them to "collect the right ones and avoid the wrong ones" -
            // avoiding needs somewhere to go, so one lane is always empty.
            var wrong = api.shuffle(current.options.filter(function (option) {
                return option !== current.answer;
            }))[0];

            waveid++;
            var lanes = api.shuffle([0, 1, 2]);
            var wave = [{option: current.answer, lane: lanes[0]}];
            if (wrong !== undefined) {
                wave.push({option: wrong, lane: lanes[1]});
            }

            wave.forEach(function (entry) {
                var el = document.createElement('span');
                el.className = 'gc-runner__box'
                    + (entry.option === current.answer ? '' : ' gc-runner__box--wrong');
                el.setAttribute('aria-hidden', 'true');
                el.textContent = entry.option;
                nodes.track.appendChild(el);

                var box = {
                    el: el,
                    wave: waveid,
                    lane: entry.lane,
                    option: entry.option,
                    // The answer this box was made for, carried with it. Judging
                    // against whatever question happens to be on screen when it
                    // lands marked every catch wrong once the waves overlapped.
                    answer: current.answer,
                    at: 0,
                    dead: false,
                    // Set once this wave's question has been answered: the box
                    // finishes its fall as scenery.
                    spent: false
                };

                boxes.push(box);
                // Before the browser can paint it in the corner.
                place(box);
            });
        }

        /**
         * Move the runner between lanes.
         *
         * @param {number} step -1 or 1
         */
        function move(step) {
            lane = Math.max(0, Math.min(LANES - 1, lane + step));
            placeHero();
        }

        /**
         * Put the runner in its lane.
         */
        function placeHero() {
            if (!nodes.hero) {
                return;
            }
            // Physical left: the boxes are placed from the physical left edge
            // too, so the runner has to share their frame or the lanes would
            // be mirrored against the thing they are meant to line up with.
            nodes.hero.style.left = (((lane + 0.5) / LANES) * 100) + "%";
        }

        /**
         * Drag anywhere on the track to change lane.
         */
        function bindPointer() {
            var pick = function (event) {
                var box = nodes.track.getBoundingClientRect();
                // Straight physical measurement, no direction flip: the lanes
                // themselves are physical, so the finger lands in the lane it
                // is over. Mirroring here would send the runner to the lane on
                // the opposite side of the one being touched.
                var across = (event.clientX - box.left) / box.width;
                lane = Math.max(0, Math.min(LANES - 1, Math.floor(across * LANES)));
                placeHero();
            };

            nodes.track.addEventListener('pointerdown', pick);
            nodes.track.addEventListener('pointermove', function (event) {
                if (event.buttons) {
                    pick(event);
                }
            });
        }

        /**
         * @param {KeyboardEvent} event
         */
        function onKeyDown(event) {
            if (event.key === 'ArrowLeft') {
                move(-1);
            } else if (event.key === 'ArrowRight') {
                move(1);
            } else {
                return;
            }
            event.preventDefault();
        }

        /**
         * Put a box where its progress down the track says it is.
         *
         * Called as each box is created as well as on every frame. Without the
         * first call a box has no transform for one whole frame, so the browser
         * paints it in the track's top-left corner before the loop moves it.
         *
         * @param {Object} box
         */
        function place(box) {
            var w = box.el.offsetWidth || 80;
            var h = box.el.offsetHeight || 44;

            box.el.style.transform = 'translate('
                + (((box.lane + 0.5) / LANES) * view.w - w / 2) + 'px, '
                + (box.at * (view.h + h) - h) + 'px)';
        }

        /**
         * One frame.
         *
         * @param {number} dt seconds
         */
        function update(dt) {
            var progress = Math.min(collected / TARGET, 1);
            var speed = 1 / (TRAVEL_SLOW - (TRAVEL_SLOW - TRAVEL_FAST) * progress);

            boxes.forEach(function (box) {
                if (box.dead) {
                    return;
                }
                box.at += speed * dt;
                place(box);

                // The runner stands near the bottom of the track. A box whose
                // wave has already been answered is scenery: it keeps falling
                // and the runner passes straight through it.
                if (!box.spent && box.at > 0.8 && box.at < 1 && box.lane === lane) {
                    box.dead = true;
                    hit(box);
                }
                if (box.at > 1.1) {
                    box.dead = true;
                    drop(box);
                }
            });

            boxes = boxes.filter(function (box) {
                return !box.dead;
            });

            wavetimer -= dt * 1000;
            if (wavetimer <= 0) {
                ask();
                wave();
                wavetimer = WAVE_START - (WAVE_START - WAVE_MIN) * progress;
            }
        }

        /**
         * The runner touched a box.
         *
         * @param {Object} box
         */
        function hit(box) {
            box.el.classList.add('gc-runner__box--hit');
            window.setTimeout(function () {
                remove(box);
            }, 260);

            if (box.option === box.answer) {
                // The question is answered. Its wrong box is still mid-fall,
                // and a child who kept running used to be able to catch that
                // too and lose a heart for a question they had just got right.
                spendWave(box.wave);
                collected++;
                api.correct();
                api.setProgress(collected, TARGET);

                if (collected >= TARGET) {
                    finish();
                }
                return;
            }

            hearts--;
            api.wrong();
            api.setLives(Math.max(hearts, 0));
            if (hearts <= 0) {
                finish();
            }
        }

        /**
         * Close a wave: everything still falling from it becomes harmless.
         *
         * @param {number} id the wave counter the boxes were stamped with
         */
        function spendWave(id) {
            boxes.forEach(function (box) {
                if (box.wave === id && !box.dead && !box.spent) {
                    box.spent = true;
                    box.el.classList.add('gc-runner__box--spent');
                }
            });
        }

        /**
         * A box went past untouched. Missing costs nothing - only a wrong grab
         * does.
         *
         * @param {Object} box
         */
        function drop(box) {
            remove(box);
        }

        /**
         * @param {Object} box
         */
        function remove(box) {
            if (box.el.parentNode) {
                box.el.parentNode.removeChild(box.el);
            }
        }

        /**
         * End the stage.
         */
        function finish() {
            if (!running) {
                return;
            }
            stop();
            // The goal is a whole stage with every heart intact.
            window.setTimeout(function () {
                api.finish(undefined, (collected >= TARGET && hearts === HEARTS) ? 1 : 0);
            }, 400);
        }

        /**
         * @param {number} timestamp
         */
        function loop(timestamp) {
            if (!running) {
                return;
            }
            // Clamped at BOTH ends. A backgrounded tab hands back a huge delta on
            // return, and a clock that appears to run backwards - a device time
            // change, or a first frame stamped before lastframe was set - would
            // otherwise drive every timer in the game the wrong way.
            var dt = Math.max(0, Math.min((timestamp - lastframe) / 1000, 0.05));
            lastframe = timestamp;
            update(dt);
            frame = window.requestAnimationFrame(loop);
        }

        /**
         * Tear down. Safe to call twice.
         */
        function stop() {
            running = false;
            if (frame) {
                window.cancelAnimationFrame(frame);
                frame = null;
            }
            document.removeEventListener('keydown', onKeyDown);
            window.removeEventListener('resize', resize);
        }

        return {
            start: function () {
                boxes = [];
                lane = 1;
                hearts = HEARTS;
                collected = 0;
                wavetimer = 600;
                questions = api.questions(30);

                build();
                ask();
                api.setLives(hearts);
                api.setProgress(0, TARGET);

                running = true;
                lastframe = window.performance.now();
                frame = window.requestAnimationFrame(loop);
            },
            stop: stop
        };
    });
}());
