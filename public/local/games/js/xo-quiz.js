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
 * Noughts and Crosses Quiz - game 12.
 *
 * The board is the ordinary game; the questions are the price of a square.
 *
 * The computer plays to win but not perfectly - it takes a win, blocks a loss,
 * and otherwise plays sensibly rather than optimally. A machine that can never
 * be beaten is not an opponent, it is a wall, and the badge asks a child to win
 * three matches.
 *
 * @module     local_games/xo-quiz
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /** Matches in a round; the badge wants all three won. */
    var MATCHES = 3;

    /** The eight ways to make a line. */
    var LINES = [
        [0, 1, 2], [3, 4, 5], [6, 7, 8],
        [0, 3, 6], [1, 4, 7], [2, 5, 8],
        [0, 4, 8], [2, 4, 6]
    ];

    window.LocalGames.register('xo-quiz', function (api) {

        var board = [];
        var match = 0;
        var wins = 0;
        var questions = [];
        var pending = null;
        var busy = false;

        var nodes = {};

        /**
         * Start a fresh board.
         */
        function deal() {
            board = new Array(9).fill('');
            pending = null;
            busy = false;
        }

        /**
         * Draw the board and the message strip.
         */
        function render() {
            api.setProgress(match, MATCHES);
            api.stage.innerHTML = '';

            var wrap = document.createElement('div');
            wrap.className = 'gc-xo';

            nodes.status = document.createElement('p');
            nodes.status.className = 'gc-xo__status';
            nodes.status.setAttribute('role', 'status');
            nodes.status.textContent = api.strings.xo_round
                .replace('{$a}', api.fmt(match + 1))
                .replace('{$b}', api.fmt(MATCHES));
            wrap.appendChild(nodes.status);

            nodes.board = document.createElement('div');
            nodes.board.className = 'gc-xo__board';

            for (var i = 0; i < 9; i++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'gc-xo__cell';
                cell.dataset.at = String(i);
                cell.addEventListener('click', function (event) {
                    choose(Number(event.currentTarget.dataset.at));
                });
                nodes.board.appendChild(cell);
            }
            wrap.appendChild(nodes.board);

            nodes.quiz = document.createElement('div');
            nodes.quiz.className = 'gc-xo__quiz';
            wrap.appendChild(nodes.quiz);

            api.stage.appendChild(wrap);
            paint();
        }

        /**
         * Put the marks on screen.
         */
        function paint() {
            Array.prototype.forEach.call(nodes.board.children, function (cell, i) {
                cell.textContent = board[i] === 'x' ? '❌' : (board[i] === 'o' ? '⭕' : '');
                cell.disabled = board[i] !== '' || busy;
                cell.classList.toggle('gc-xo__cell--taken', board[i] !== '');
            });
        }

        /**
         * The child picked a square: ask for its question.
         *
         * @param {number} at
         */
        function choose(at) {
            if (busy || board[at] !== '') {
                return;
            }
            if (!questions.length) {
                questions = api.questions(20);
            }
            var q = questions.shift();
            if (!q) {
                return;
            }

            busy = true;
            pending = {at: at, q: q};
            paint();
            askQuestion();
        }

        /**
         * Show the question that guards the chosen square.
         */
        function askQuestion() {
            nodes.quiz.innerHTML = '';
            nodes.quiz.classList.add('gc-xo__quiz--open');

            var ask = document.createElement('p');
            ask.className = 'gc-xo__question';
            ask.textContent = pending.q.question;
            nodes.quiz.appendChild(ask);

            var row = document.createElement('div');
            row.className = 'gc-xo__answers';

            pending.q.options.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'gc-quiz__answer';
                button.textContent = option;
                button.addEventListener('click', function () {
                    settle(option === pending.q.answer);
                });
                row.appendChild(button);
            });
            nodes.quiz.appendChild(row);

            api.say(pending.q.question);
        }

        /**
         * Right answer takes the square; a wrong one hands over the turn.
         *
         * @param {boolean} right
         */
        function settle(right) {
            var at = pending.at;
            nodes.quiz.innerHTML = '';
            nodes.quiz.classList.remove('gc-xo__quiz--open');
            pending = null;

            if (right) {
                api.correct();
                board[at] = 'x';
            } else {
                api.wrong(api.strings.xo_missed);
            }

            paint();

            if (finished()) {
                return;
            }

            nodes.status.textContent = api.strings.xo_thinking;
            window.setTimeout(function () {
                board[computerMove()] = 'o';
                paint();
                if (!finished()) {
                    busy = false;
                    nodes.status.textContent = api.strings.xo_yourturn;
                    paint();
                }
            }, 700);
        }

        /**
         * Where the computer plays.
         *
         * Win, then block, then the centre, then a corner, then whatever is
         * left. Good enough to be worth beating, beatable enough to be worth
         * playing.
         *
         * @return {number}
         */
        function computerMove() {
            var take = lineFor('o');
            if (take !== -1) {
                return take;
            }
            var block = lineFor('x');
            if (block !== -1) {
                return block;
            }
            if (board[4] === '') {
                return 4;
            }
            var corners = api.shuffle([0, 2, 6, 8]).filter(function (i) {
                return board[i] === '';
            });
            if (corners.length) {
                return corners[0];
            }
            return board.indexOf('');
        }

        /**
         * The square that completes a line for a mark, or -1.
         *
         * @param {string} mark
         * @return {number}
         */
        function lineFor(mark) {
            for (var i = 0; i < LINES.length; i++) {
                var line = LINES[i];
                var mine = line.filter(function (at) {
                    return board[at] === mark;
                });
                var free = line.filter(function (at) {
                    return board[at] === '';
                });
                if (mine.length === 2 && free.length === 1) {
                    return free[0];
                }
            }
            return -1;
        }

        /**
         * Whether the match is over, and what to do about it.
         *
         * @return {boolean}
         */
        function finished() {
            var winner = ['x', 'o'].filter(function (mark) {
                return LINES.some(function (line) {
                    return line.every(function (at) {
                        return board[at] === mark;
                    });
                });
            })[0];

            var full = board.every(function (cell) {
                return cell !== '';
            });

            if (!winner && !full) {
                return false;
            }

            busy = true;
            paint();

            if (winner === 'x') {
                wins++;
                nodes.status.textContent = api.strings.xo_youwin;
                // The win itself is worth points on top of the answers.
                api.correct(api.strings.xo_youwin, 3);
            } else if (winner === 'o') {
                nodes.status.textContent = api.strings.xo_youlose;
            } else {
                nodes.status.textContent = api.strings.xo_draw;
            }

            window.setTimeout(nextMatch, 1800);
            return true;
        }

        /**
         * Next match, or the end of the round.
         */
        function nextMatch() {
            match++;
            api.setProgress(match, MATCHES);

            if (match >= MATCHES) {
                // The goal this game reports is matches won, which is what the
                // badge asks for - right answers alone cannot say it.
                api.finish(undefined, wins);
                return;
            }
            deal();
            render();
        }

        return {
            start: function () {
                match = 0;
                wins = 0;
                questions = api.questions(30);
                deal();
                render();
            },
            stop: function () {
                busy = true;
            }
        };
    });
}());
