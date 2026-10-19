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
 * Number Catcher - game 02.
 *
 * Numbers rain down, a rule sits at the top, and the basket catches only the
 * numbers that satisfy it. Letting a number fall costs nothing; catching the
 * wrong one costs a try. That asymmetry is the whole game: it rewards reading
 * before grabbing.
 *
 * The rule changes every few catches so the round stays a mental-arithmetic
 * exercise rather than a reflex test.
 *
 * @module     local_games/math-catcher
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Wrong catches allowed before the round ends. */
    var LIVES = 3;

    /** Correct catches that finish the round. */
    var TARGET = 25;

    /** How many correct catches before a fresh rule. */
    var RULE_EVERY = 6;

    /** Falling speed in pixels per second, first rule to last. */
    var FALL_START = 95;
    var FALL_MAX = 200;

    /** Gap between spawns in milliseconds. */
    var SPAWN_START = 1150;
    var SPAWN_MIN = 620;

    /** Basket travel in pixels per second when held down. */
    var BASKET_SPEED = 460;

    window.LocalGames.register('math-catcher', function (api) {

        var canvas = null;
        var ctx = null;
        var nodes = {};

        var frame = null;
        var lastframe = 0;
        var spawntimer = 0;

        var tokens = [];
        var rule = null;
        var lives = LIVES;
        var caught = 0;
        var running = false;

        var basket = {x: 0, width: 110, height: 26};
        var steering = 0;

        // The play area in CSS pixels. The canvas backing store is bigger on a
        // retina screen; everything the game reasons about stays in these.
        var view = {w: 0, h: 0};

        var colours = {};

        /**
         * Read the palette off the page so the canvas matches the site theme.
         */
        function readColours() {
            var styles = window.getComputedStyle(document.documentElement);
            var get = function (name, fallback) {
                var value = styles.getPropertyValue(name);
                return value && value.trim() ? value.trim() : fallback;
            };
            colours = {
                token: get('--nit-brand-surface', '#121e2d'),
                tokenborder: get('--nit-brand-borderprimary', '#223244'),
                tokentext: get('--nit-brand-textprimary', '#eef3f9'),
                basket: get('--nit-brand-primary', '#5488c4')
            };
        }

        /**
         * Pick the next rule, and the wording that goes with it.
         *
         * Which rules the game may set is no longer decided here: they are rows
         * an administrator edits in Game control, and the shell turns one of
         * them into a test and a label. "Equal to n" is the only kind that
         * changes what falls - the tokens become little sums, so the child has
         * to do the arithmetic before they move.
         *
         * @return {?{test: Function, label: string, expressions: boolean}} null when no rules are set
         */
        function makeRule() {
            var rule = api.numberRule('catch');
            if (!rule) {
                return null;
            }

            rule.expressions = rule.target !== undefined;

            return rule;
        }

        /**
         * Announce a new rule on screen and out loud.
         */
        function setRule() {
            rule = makeRule();
            // No rules set means nothing to ask for. Ending the round is a
            // quiet finish rather than a screen the child cannot get out of.
            if (!rule) {
                api.finish(0);
                return false;
            }
            api.setProgress(caught, TARGET);
            nodes.rule.textContent = rule.label;
            api.say(rule.label);
            return true;
        }

        /**
         * Drop one number, biased so roughly half of them are catchable - a
         * screen with nothing worth catching is just waiting.
         */
        function spawn() {
            var wantmatch = Math.random() < 0.5;
            var value;
            var label;
            var guard = 0;

            do {
                if (rule.expressions) {
                    var a = api.random(1, 15);
                    var b = api.random(1, 12);
                    if (Math.random() < 0.5 && a > b) {
                        value = a - b;
                        label = a + ' - ' + b;
                    } else {
                        value = a + b;
                        label = a + ' + ' + b;
                    }
                } else {
                    value = api.random(1, 40);
                    label = String(value);
                }
                guard++;
            } while (rule.test(value) !== wantmatch && guard < 25);

            var radius = rule.expressions ? 36 : 28;
            tokens.push({
                x: api.random(radius + 6, Math.max(view.w - radius - 6, radius + 7)),
                y: -radius,
                r: radius,
                value: value,
                label: label,
                dead: false
            });
        }

        /**
         * Advance the world by one frame.
         *
         * @param {number} dt seconds since the last frame
         */
        function update(dt) {
            var progress = Math.min(caught / TARGET, 1);
            var fall = FALL_START + (FALL_MAX - FALL_START) * progress;

            basket.x += steering * BASKET_SPEED * dt;
            basket.x = Math.max(basket.width / 2, Math.min(view.w - basket.width / 2, basket.x));

            var basketTop = view.h - basket.height - 14;

            tokens.forEach(function (token) {
                token.y += fall * dt;

                if (token.dead) {
                    return;
                }

                var withinX = Math.abs(token.x - basket.x) < (basket.width / 2 + token.r * 0.55);
                var withinY = token.y + token.r >= basketTop && token.y - token.r <= view.h;

                if (withinX && withinY) {
                    token.dead = true;
                    catchToken(token);
                }
            });

            tokens = tokens.filter(function (token) {
                // Missing a number is free, by design - only a wrong catch costs.
                return !token.dead && token.y - token.r < view.h + 40;
            });

            spawntimer -= dt * 1000;
            if (spawntimer <= 0) {
                spawn();
                spawntimer = SPAWN_START - (SPAWN_START - SPAWN_MIN) * progress;
            }
        }

        /**
         * Score a catch and decide whether the round carries on.
         *
         * @param {Object} token
         */
        function catchToken(token) {
            if (rule.test(token.value)) {
                caught++;
                api.setProgress(caught, TARGET);
                api.correct();
                api.setProgress(caught, TARGET);
                if (caught % RULE_EVERY === 0 && caught < TARGET) {
                    setRule();
                }
                if (caught >= TARGET) {
                    end();
                }
                return;
            }

            lives--;
            api.wrong();
            api.setLives(Math.max(lives, 0));
            if (lives <= 0) {
                end();
            }
        }

        /**
         * Paint the frame.
         */
        function draw() {
            ctx.clearRect(0, 0, view.w, view.h);

            tokens.forEach(function (token) {
                ctx.beginPath();
                ctx.arc(token.x, token.y, token.r, 0, Math.PI * 2);
                ctx.fillStyle = colours.token;
                ctx.fill();
                ctx.lineWidth = 3;
                // Every token looks identical on purpose. Colour-coding the ones
                // that match would answer the question for the child.
                ctx.strokeStyle = colours.tokenborder;
                ctx.stroke();

                ctx.fillStyle = colours.tokentext;
                ctx.font = 'bold ' + (token.r * 0.72) + 'px system-ui, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(api.fmt(token.label), token.x, token.y);
            });

            var top = view.h - basket.height - 14;
            ctx.fillStyle = colours.basket;
            roundedRect(basket.x - basket.width / 2, top, basket.width, basket.height, 10);
            ctx.fill();

            ctx.font = '26px system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText('🧺', basket.x, top + 2);
        }

        /**
         * Path a rounded rectangle onto the context.
         *
         * @param {number} x
         * @param {number} y
         * @param {number} w
         * @param {number} h
         * @param {number} r
         */
        function roundedRect(x, y, w, h, r) {
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + w, y, x + w, y + h, r);
            ctx.arcTo(x + w, y + h, x, y + h, r);
            ctx.arcTo(x, y + h, x, y, r);
            ctx.arcTo(x, y, x + w, y, r);
            ctx.closePath();
        }

        /**
         * The animation loop.
         *
         * @param {number} timestamp
         */
        function loop(timestamp) {
            if (!running) {
                return;
            }
            // A backgrounded tab hands back a huge delta; clamp it so nothing
            // teleports past the basket on return.
            // Clamped at BOTH ends. A backgrounded tab hands back a huge delta on
            // return, and a clock that appears to run backwards - a device time
            // change, or a first frame stamped before lastframe was set - would
            // otherwise drive every timer in the game the wrong way.
            var dt = Math.max(0, Math.min((timestamp - lastframe) / 1000, 0.05));
            lastframe = timestamp;

            update(dt);
            draw();

            frame = window.requestAnimationFrame(loop);
        }

        /**
         * Keep the canvas the size it is drawn at, on resize and on rotate.
         */
        function resize() {
            if (!canvas) {
                return;
            }
            var rect = canvas.getBoundingClientRect();
            var ratio = window.devicePixelRatio || 1;

            view.w = Math.round(rect.width);
            view.h = Math.round(rect.height);

            // Back the canvas with real device pixels, then scale the context so
            // the game can keep drawing in CSS pixels.
            canvas.width = Math.round(view.w * ratio);
            canvas.height = Math.round(view.h * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

            basket.width = Math.max(90, Math.min(150, view.w * 0.22));
            basket.x = Math.min(Math.max(basket.x || view.w / 2, basket.width / 2), view.w - basket.width / 2);
        }

        /**
         * Build the stage: rule strip, canvas, and the two big steering buttons.
         */
        function build() {
            var wrap = document.createElement('div');
            wrap.className = 'gc-catch';
            wrap.innerHTML =
                '<p class="gc-catch__rule" data-role="rule" role="status" aria-live="polite"></p>' +
                '<canvas class="gc-catch__canvas" data-role="canvas" role="img"></canvas>' +
                '<div class="gc-catch__controls">' +
                    '<button type="button" class="gc-steer" data-steer="-1"></button>' +
                    '<button type="button" class="gc-steer" data-steer="1"></button>' +
                '</div>';

            api.stage.appendChild(wrap);

            nodes.rule = wrap.querySelector('[data-role="rule"]');
            canvas = wrap.querySelector('[data-role="canvas"]');
            ctx = canvas.getContext('2d');

            var left = wrap.querySelector('[data-steer="-1"]');
            var right = wrap.querySelector('[data-steer="1"]');

            // A steering pad is not text: the button that moves the basket left
            // has to sit on the left in Arabic too. The container is pinned to
            // ltr in the stylesheet so the grid keeps its physical order, and
            // each arrow is placed on the button's outer edge - arrow first on
            // the left button, arrow last on the right one.
            left.appendChild(arrow('⬅️'));
            left.appendChild(label(api.strings.catch_left));
            right.appendChild(label(api.strings.catch_right));
            right.appendChild(arrow('➡️'));

            canvas.setAttribute('aria-label', api.strings.catch_howto || '');

            bindSteering(left, -1);
            bindSteering(right, 1);
            bindPointer();

            document.addEventListener('keydown', onKeyDown);
            document.addEventListener('keyup', onKeyUp);
            window.addEventListener('resize', resize);
        }

        /**
         * A directional arrow, marked decorative - the button's own label is
         * what a screen reader should read.
         *
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
         * The button's word. Isolated so an Arabic label cannot drag the arrow
         * around it through bidi reordering.
         *
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
         * Hold-to-move on one of the big buttons, mouse or finger.
         *
         * @param {HTMLElement} button
         * @param {number} direction -1 or 1
         */
        function bindSteering(button, direction) {
            var press = function (event) {
                event.preventDefault();
                steering = direction;
            };
            var release = function () {
                if (steering === direction) {
                    steering = 0;
                }
            };
            button.addEventListener('pointerdown', press);
            button.addEventListener('pointerup', release);
            button.addEventListener('pointerleave', release);
            button.addEventListener('pointercancel', release);
        }

        /**
         * Drag the basket straight to the finger. On a phone this is how a
         * child will actually play.
         */
        function bindPointer() {
            var moveTo = function (event) {
                var rect = canvas.getBoundingClientRect();
                basket.x = event.clientX - rect.left;
            };
            canvas.addEventListener('pointerdown', function (event) {
                canvas.setPointerCapture(event.pointerId);
                moveTo(event);
            });
            canvas.addEventListener('pointermove', function (event) {
                if (event.buttons) {
                    moveTo(event);
                }
            });
        }

        /**
         * @param {KeyboardEvent} event
         */
        function onKeyDown(event) {
            if (event.key === 'ArrowLeft') {
                steering = -1;
            } else if (event.key === 'ArrowRight') {
                steering = 1;
            } else {
                return;
            }
            event.preventDefault();
        }

        /**
         * @param {KeyboardEvent} event
         */
        function onKeyUp(event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                steering = 0;
            }
        }

        /**
         * Finish and report.
         */
        function end() {
            if (!running) {
                return;
            }
            stop();
            api.finish(caught);
        }

        /**
         * Tear the loop and the listeners down. Safe to call twice.
         */
        function stop() {
            running = false;
            if (frame) {
                window.cancelAnimationFrame(frame);
                frame = null;
            }
            document.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('keyup', onKeyUp);
            window.removeEventListener('resize', resize);
        }

        return {
            start: function () {
                tokens = [];
                lives = LIVES;
                caught = 0;
                steering = 0;
                spawntimer = 400;

                build();
                readColours();
                resize();
                if (!setRule()) {
                    return;
                }
                api.setLives(lives);

                running = true;
                lastframe = window.performance.now();
                frame = window.requestAnimationFrame(loop);
            },
            stop: stop
        };
    });
}());
