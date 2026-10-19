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
 * Math Shop - game 03.
 *
 * A budget, a shelf, and two questions per trip: what does it come to, and what
 * is left. Splitting the trip in two is the point of the game - a child who can
 * add but not subtract still gets the first half right, and the round tells
 * them so instead of marking the whole purchase wrong.
 *
 * @module     local_games/math-shop
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Purchases in a round - the badge asks for five clean ones. */
    var TRIPS = 5;

    window.LocalGames.register('math-shop', function (api) {

        var trip = 0;
        var basket = [];
        var budget = 0;
        var total = 0;
        var phase = 'total';
        var locked = false;

        var nodes = {};

        /**
         * Put together one shopping trip: two or three items the child can
         * afford, and a budget with change left over.
         */
        function makeTrip() {
            var shelf = api.shuffle(api.shopitems);
            var count = trip < 2 ? 2 : 3;

            basket = shelf.slice(0, count).map(function (item) {
                return {
                    emoji: item.emoji,
                    name: item.name,
                    // Prices stay whole and small: this is mental arithmetic,
                    // not a lesson in decimals.
                    price: api.random(3, trip < 2 ? 12 : 20)
                };
            });

            total = basket.reduce(function (sum, item) {
                return sum + item.price;
            }, 0);

            // Round the budget up to something a shopkeeper would hand over, and
            // keep the change positive so the second question always has an
            // answer a child can reach.
            budget = Math.ceil((total + api.random(5, 25)) / 5) * 5;
        }

        /**
         * Three plausible answers around the right one.
         *
         * @param {number} answer
         * @return {number[]}
         */
        function options(answer) {
            var out = [answer];
            var offsets = api.shuffle([1, -1, 2, -2, 5, -5, 10, -10]);

            for (var i = 0; i < offsets.length && out.length < 3; i++) {
                var candidate = answer + offsets[i];
                if (candidate >= 0 && out.indexOf(candidate) === -1) {
                    out.push(candidate);
                }
            }
            while (out.length < 3) {
                out.push(answer + out.length + 4);
            }

            return api.shuffle(out);
        }

        /**
         * Draw the shop for the current trip and ask the current question.
         */
        function render() {
            api.setProgress(trip, TRIPS);
            var question = phase === 'total' ? api.strings.shop_total : api.strings.shop_change;
            var answer = phase === 'total' ? total : budget - total;

            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-shop';

            var purse = document.createElement('p');
            purse.className = 'gc-shop__purse';
            purse.textContent = '💰 ' + api.strings.shop_youhave.replace('{$a}', api.fmt(budget));
            wrap.appendChild(purse);

            var shelf = document.createElement('ul');
            shelf.className = 'gc-shop__shelf';
            basket.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'gc-shop__item';
                li.innerHTML = '<span class="gc-shop__face" aria-hidden="true">' + item.emoji + '</span>';

                var name = document.createElement('span');
                name.className = 'gc-shop__name';
                name.textContent = item.name;
                li.appendChild(name);

                var price = document.createElement('span');
                price.className = 'gc-shop__price';
                price.textContent = api.fmt(item.price) + ' ' + api.strings.shop_pound;
                li.appendChild(price);

                shelf.appendChild(li);
            });
            wrap.appendChild(shelf);

            // Once the total is settled it stays on screen, so the change
            // question is arithmetic rather than a memory test.
            if (phase === 'change') {
                var settled = document.createElement('p');
                settled.className = 'gc-shop__settled';
                settled.textContent = api.strings.shop_total + ' ' + api.fmt(total) + ' ' + api.strings.shop_pound;
                wrap.appendChild(settled);
            }

            var ask = document.createElement('p');
            ask.className = 'gc-shop__question';
            ask.textContent = question;
            wrap.appendChild(ask);

            var row = document.createElement('div');
            row.className = 'gc-shop__answers';
            options(answer).forEach(function (value) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-answer';
                button.textContent = api.fmt(value);
                button.addEventListener('click', function () {
                    answered(button, value, answer);
                });
                row.appendChild(button);
            });
            wrap.appendChild(row);

            api.stage.appendChild(wrap);
            nodes.wrap = wrap;

            api.say(question);
        }

        /**
         * Score the tap and move the trip along.
         *
         * @param {HTMLElement} button
         * @param {number} value what was tapped
         * @param {number} answer what it should have been
         */
        function answered(button, value, answer) {
            if (locked) {
                return;
            }
            locked = true;

            if (value === answer) {
                button.classList.add('gc-answer--ok');
                api.correct();
            } else {
                button.classList.add('gc-answer--no');
                api.wrong();
            }

            window.setTimeout(function () {
                locked = false;

                // Right or wrong, the trip moves on - a wrong total does not
                // trap the child on the same question.
                if (phase === 'total') {
                    phase = 'change';
                    render();
                    return;
                }

                trip++;
                api.setProgress(trip, TRIPS);
                if (trip >= TRIPS) {
                    api.finish();
                    return;
                }
                phase = 'total';
                makeTrip();
                render();
            }, 850);
        }

        return {
            start: function () {
                trip = 0;
                phase = 'total';
                locked = false;
                makeTrip();
                render();
            },
            stop: function () {
                locked = true;
            }
        };
    });
}());
