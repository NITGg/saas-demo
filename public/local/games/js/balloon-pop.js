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
 * Balloon Pop - game 14.
 *
 * Balloons rise carrying numbers; only the ones matching the rule should be
 * popped. The doc is firm that the rule must be very short, because the child
 * reads it while the balloons are already moving - so every rule here is four
 * or five words and never changes mid-flight.
 *
 * A wrong balloon shakes and keeps flying rather than bursting. Nothing is
 * taken away for a mistake.
 *
 * @module     local_games/balloon-pop
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Balloons to pop correctly to finish. The badge wants twenty-five. */
    var TARGET = 25;

    /** How many correct pops before a new rule. */
    var RULE_EVERY = 8;

    /** Seconds for a balloon to rise the whole way, first to last. */
    var RISE_SLOW = 8;
    var RISE_FAST = 4.5;

    /** Gap between balloons, in milliseconds. */
    var SPAWN_START = 900;
    var SPAWN_MIN = 480;

    /** Columns the sky is divided into, so balloons never queue up. */
    var COLUMNS = 5;

    /** Balloon colours - decorative only, never a hint. */
    var COLOURS = ['#e04b4b', '#4b7be0', '#3fa877', '#e0c84b', '#9b5fd0', '#e07ab0'];

    window.LocalGames.register('balloon-pop', function (api) {

        var balloons = [];
        var rule = null;
        var popped = 0;
        var spawntimer = 0;
        var bag = [];
        var frame = null;
        var lastframe = 0;
        var running = false;

        var nodes = {};
        var view = {w: 0, h: 0};

        /**
         * Pick a short rule.
         *
         * The rules the game may set are rows an administrator edits in Game
         * control; the shell turns one of them into a test and a label. Balloon
         * Pop and Number Catcher ask the same kind of thing and used to carry a
         * copy of this each.
         *
         * @return {?{test: Function, label: string}} null when no rules are set
         */
        function makeRule() {
            return api.numberRule('balloon');
        }

        /**
         * Announce a rule on screen and aloud.
         */
        function setRule() {
            rule = makeRule();
            // No rules set means nothing to ask for. Ending the round is a quiet
            // finish rather than a screen the child cannot get out of.
            if (!rule) {
                api.finish(0);
                return false;
            }
            nodes.rule.textContent = rule.label;
            // Make the change visible. Swapping the words silently meant a
            // child carried on hunting for the old rule without noticing.
            nodes.rule.classList.remove("gc-balloons__rule--new");
            void nodes.rule.offsetWidth;
            nodes.rule.classList.add("gc-balloons__rule--new");
            api.say(rule.label);

            return true;
        }

        /**
         * Release one balloon.
         */
        function spawn() {
            var wantmatch = Math.random() < 0.5;
            var value;
            var guard = 0;

            do {
                value = api.random(1, 40);
                guard++;
            } while (rule.test(value) !== wantmatch && guard < 25);

            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'gc-balloon';
            el.style.setProperty('--gc-balloon-colour', api.pick(COLOURS));

            var face = document.createElement('span');
            face.className = 'gc-balloon__value';
            face.textContent = api.fmt(value);
            el.appendChild(face);

            var string = document.createElement('span');
            string.className = 'gc-balloon__string';
            string.setAttribute('aria-hidden', 'true');
            el.appendChild(string);

            // A shuffled bag of columns rather than a free random x OR a plain
            // rotation. Free random queued them into a diagonal; cycling
            // 0,1,2,3,4 traded that for a staircase. Drawing without
            // replacement covers the sky evenly and still looks unplanned.
            if (!bag.length) {
                bag = api.shuffle([0, 1, 2, 3, 4]);
            }
            var column = bag.shift() + 0.5;
            var balloon = {
                el: el,
                value: value,
                at: 0,
                // Its own sway, and its own place in that sway, so no two
                // balloons lean the same way at the same moment.
                drift: (Math.random() - 0.5) * 0.09,
                phase: Math.random() * Math.PI * 2,
                x: (column / COLUMNS) + (Math.random() - 0.5) * 0.08,
                dead: false
            };

            el.addEventListener('click', function () {
                pop(balloon);
            });

            nodes.sky.appendChild(el);
            // Before the browser gets a chance to paint it. The frame loop
            // would not reach this balloon until the next tick, and until then
            // an untransformed absolutely positioned balloon sits in the
            // sky's top-left corner in plain sight.
            place(balloon);
            balloons.push(balloon);
        }

        /**
         * Put a balloon where its own progress up the sky says it is.
         *
         * Called the moment a balloon is created as well as on every frame.
         * A balloon appended without this has no transform yet, so the browser
         * paints it at the sky's top-left corner for one whole frame before
         * the next tick moves it - which is what the corner-flicker every
         * spawn interval actually was.
         *
         * @param {Object} balloon
         */
        function place(balloon) {
            var sway = Math.sin(balloon.at * 5 + balloon.phase) * balloon.drift;
            var w = balloon.el.offsetWidth || 60;
            var h = balloon.el.offsetHeight || 80;

            balloon.el.style.transform = 'translate('
                + ((balloon.x + sway) * view.w - w / 2) + 'px, '
                + ((1 - balloon.at) * (view.h + h) - h / 2) + 'px)';
        }

        /**
         * Move everything and clear what has floated away.
         *
         * @param {number} dt seconds
         */
        function update(dt) {
            var progress = Math.min(popped / TARGET, 1);
            var rise = 1 / (RISE_SLOW - (RISE_SLOW - RISE_FAST) * progress);

            balloons.forEach(function (balloon) {
                if (balloon.dead) {
                    return;
                }
                balloon.at += rise * dt;
                place(balloon);

                if (balloon.at > 1.1) {
                    balloon.dead = true;
                    remove(balloon);
                }
            });

            balloons = balloons.filter(function (balloon) {
                return !balloon.dead;
            });

            spawntimer -= dt * 1000;
            if (spawntimer <= 0) {
                spawn();
                spawntimer = SPAWN_START - (SPAWN_START - SPAWN_MIN) * progress;
            }
        }

        /**
         * Take a balloon off the sky.
         *
         * @param {Object} balloon
         */
        function remove(balloon) {
            if (balloon.el.parentNode) {
                balloon.el.parentNode.removeChild(balloon.el);
            }
        }

        /**
         * Leave a burst where a balloon popped.
         *
         * @param {Object} balloon
         */
        function burst(balloon) {
            var box = balloon.el.getBoundingClientRect();
            var sky = nodes.sky.getBoundingClientRect();

            var mark = document.createElement("span");
            mark.className = "gc-balloon__burst";
            mark.setAttribute("aria-hidden", "true");
            mark.textContent = "💥";
            mark.style.left = (box.left + box.width / 2 - sky.left) + "px";
            mark.style.top = (box.top + box.height / 2 - sky.top) + "px";
            nodes.sky.appendChild(mark);

            window.setTimeout(function () {
                if (mark.parentNode) {
                    mark.parentNode.removeChild(mark);
                }
            }, 520);
        }

        /**
         * A balloon was tapped.
         *
         * @param {Object} balloon
         */
        function pop(balloon) {
            if (balloon.dead || !running) {
                return;
            }

            if (!rule.test(balloon.value)) {
                // Wrong one: it shakes and carries on. The doc says so, and it
                // is the right call - a burst balloon would feel like a loss.
                balloon.el.classList.remove('gc-balloon--shake');
                // Reading offsetWidth restarts the animation.
                void balloon.el.offsetWidth;
                balloon.el.classList.add('gc-balloon--shake');
                api.wrong();
                return;
            }

            balloon.dead = true;
            burst(balloon);
            balloon.el.classList.add("gc-balloon--pop");
            window.setTimeout(function () {
                remove(balloon);
            }, 420);

            popped++;
            api.correct();
            api.setProgress(popped, TARGET);

            if (popped % RULE_EVERY === 0 && popped < TARGET) {
                setRule();
            }
            if (popped >= TARGET) {
                stop();
                window.setTimeout(function () {
                    api.finish();
                }, 500);
            }
        }

        /**
         * One frame.
         *
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
         * Build the sky.
         */
        function build() {
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-balloons';
            wrap.innerHTML = '<p class="gc-balloons__rule" data-role="rule" role="status"></p>'
                + '<div class="gc-balloons__sky" data-role="sky"></div>';

            api.stage.appendChild(wrap);
            nodes.rule = wrap.querySelector('[data-role="rule"]');
            nodes.sky = wrap.querySelector('[data-role="sky"]');

            var box = nodes.sky.getBoundingClientRect();
            view.w = box.width;
            view.h = box.height;
        }

        /**
         * Stop the loop. Safe to call twice.
         */
        function stop() {
            running = false;
            if (frame) {
                window.cancelAnimationFrame(frame);
                frame = null;
            }
        }

        return {
            start: function () {
                balloons = [];
                popped = 0;
                bag = [];
                spawntimer = 300;

                build();
                if (!setRule()) {
                    return;
                }
                api.setProgress(0, TARGET);

                running = true;
                lastframe = window.performance.now();
                frame = window.requestAnimationFrame(loop);
            },
            stop: stop
        };
    });
}());
