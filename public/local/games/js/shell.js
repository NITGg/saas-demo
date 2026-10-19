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
 * The runtime every game in the corner shares.
 *
 * It owns the parts that must behave the same way in all 24 games - the HUD,
 * the start and end cards, sound, the score bookkeeping and saving the round -
 * so a game file only has to describe how its own round is played.
 *
 * A game registers itself:
 *
 *   LocalGames.register('math-race', function (api) {
 *       return {
 *           start: function () { ... },   // build a round into api.stage
 *           stop:  function () { ... }    // optional: clear timers/animation
 *       };
 *   });
 *
 * @module     local_games/shell
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    var ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    var factories = {};
    var booted = false;

    /** Where the sound choice is kept between games. */
    var SOUND_KEY = 'local_games_sound';

    /**
     * Has the child turned the sound off?
     *
     * The choice belongs to the corner, not to one game: a child who silences
     * Math Race because they are on a bus does not want Number Catcher to
     * start talking the moment they open it. localStorage keeps it per device,
     * which is the right scope - the same account on a tablet at home may well
     * want the sound on.
     *
     * Every access is guarded: private windows and blocked site data both make
     * localStorage throw, and a game must not die over a sound setting.
     *
     * @return {boolean}
     */
    function soundWanted() {
        try {
            return window.localStorage.getItem(SOUND_KEY) !== 'off';
        } catch (e) {
            return true;
        }
    }

    /**
     * Remember the choice for the next game.
     *
     * @param {boolean} enabled
     */
    function rememberSound(enabled) {
        try {
            window.localStorage.setItem(SOUND_KEY, enabled ? 'on' : 'off');
        } catch (e) {
            // Nothing to do; the setting simply will not survive this page.
        }
    }

    /**
     * Sound: short tones for feedback, and the browser's own voice for text.
     *
     * Everything here is best-effort. A device with no Web Audio, no voices, or
     * a blocked autoplay policy just stays quiet - a silent game is still a
     * playable game.
     */
    var audio = {
        enabled: soundWanted(),
        ctx: null,

        context: function () {
            if (!this.ctx) {
                var Ctor = window.AudioContext || window.webkitAudioContext;
                if (!Ctor) {
                    return null;
                }
                try {
                    this.ctx = new Ctor();
                } catch (e) {
                    return null;
                }
            }
            // Browsers start the context suspended until a gesture; every call
            // here follows a tap, so this is the moment to wake it.
            if (this.ctx.state === 'suspended' && this.ctx.resume) {
                this.ctx.resume();
            }
            return this.ctx;
        },

        /**
         * One short tone.
         *
         * @param {number} freq frequency in Hz
         * @param {number} duration seconds
         * @param {string} type oscillator type
         */
        tone: function (freq, duration, type) {
            if (!this.enabled) {
                return;
            }
            var ctx = this.context();
            if (!ctx) {
                return;
            }
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = type || 'sine';
            osc.frequency.value = freq;
            // A tiny fade out; a square-edged stop clicks.
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + duration);
        },

        /** Rising two-note chime: that was right. */
        ding: function () {
            this.tone(660, 0.12);
            var self = this;
            window.setTimeout(function () {
                self.tone(880, 0.16);
            }, 110);
        },

        /** Low, soft note: not right - deliberately not a harsh buzzer. */
        buzz: function () {
            this.tone(220, 0.18, 'triangle');
        },

        /** Little fanfare for the end of a round. */
        fanfare: function () {
            var notes = [523, 659, 784, 1046];
            var self = this;
            notes.forEach(function (freq, i) {
                window.setTimeout(function () {
                    self.tone(freq, 0.18);
                }, i * 130);
            });
        },

        /**
         * Read a line out loud.
         *
         * @param {string} text
         * @param {string} lang BCP-47 tag
         */
        say: function (text, lang) {
            if (!this.enabled || !window.speechSynthesis || !text) {
                return;
            }
            try {
                window.speechSynthesis.cancel();
                var utterance = new window.SpeechSynthesisUtterance(text);
                utterance.lang = lang || document.documentElement.lang || 'ar-EG';
                utterance.rate = 0.95;
                window.speechSynthesis.speak(utterance);
            } catch (e) {
                // No voices installed, or speech blocked. Stay quiet.
            }
        },

        silence: function () {
            if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        }
    };

    /**
     * Build the object a game is handed.
     *
     * @param {Object} config the JSON blob from the page
     * @param {Object} dom cached elements
     * @return {Object}
     */
    function makeApi(config, dom) {
        var strings = config.strings || {};
        var lang = config.arabicdigits ? 'ar-EG' : (document.documentElement.lang || 'en');

        // Round bookkeeping. Reset by api.reset() before every round.
        //
        // `correct` counts events and is what badge rules are written against
        // ("10 words in one round"); `score` counts points and is what the HUD
        // shows. They are the same number in every game that scores one point
        // per answer, and they part ways in Word Builder, where a longer word
        // is worth more without being more than one word.
        var round = {correct: 0, wrong: 0, streak: 0, best: 0, score: 0};

        var api = {
            stage: dom.stage,
            strings: strings,
            lang: lang,
            audio: audio,

            // The word bank and the shop shelf, straight from the language
            // pack: [{word, emoji, clue}] and [{emoji, name}].
            words: config.words || [],
            shopitems: config.shopitems || [],

            // The wider vocabulary, for games that only need to know whether
            // something is a word.
            wordlist: config.wordlist || [],

            // The question bank six games are built on, plus the smaller banks
            // for True or False, Who Am I and Colour Challenge.
            quiz: config.quiz || [],
            truefalse: config.truefalse || [],
            whoami: config.whoami || [],
            colours: config.colours || [],
            sumrules: config.sumrules || [],
            numberrules: config.numberrules || [],


            /**
             * A number as the child reads it: Arabic-Indic digits in Arabic.
             *
             * @param {number} n
             * @return {string}
             */
            fmt: function (n) {
                var text = String(n);
                if (!config.arabicdigits) {
                    return text;
                }
                return text.replace(/[0-9]/g, function (d) {
                    return ARABIC_DIGITS[Number(d)];
                });
            },

            /**
             * Random integer, both ends included.
             *
             * @param {number} min
             * @param {number} max
             * @return {number}
             */
            random: function (min, max) {
                return Math.floor(Math.random() * (max - min + 1)) + min;
            },

            /**
             * One item from an array.
             *
             * @param {Array} items
             * @return {*}
             */
            pick: function (items) {
                return items[Math.floor(Math.random() * items.length)];
            },

            /**
             * A shuffled copy.
             *
             * @param {Array} items
             * @return {Array}
             */
            shuffle: function (items) {
                var out = items.slice();
                for (var i = out.length - 1; i > 0; i--) {
                    var j = Math.floor(Math.random() * (i + 1));
                    var tmp = out[i];
                    out[i] = out[j];
                    out[j] = tmp;
                }
                return out;
            },

            say: function (text) {
                audio.say(text, lang);
            },

            /**
             * A shuffled run of questions, ready to ask.
             *
             * Six games ask questions and all six were about to grow their own
             * copy of "pick some questions, shuffle the options, remember which
             * one is right". It belongs here once.
             *
             * @param {number} count how many questions
             * @param {string} [topic] restrict to one wheel segment
             * @return {Array<{question: string, answer: string, options: string[], topic: string}>}
             */
            questions: function (count, topic) {
                var pool = api.quiz.filter(function (entry) {
                    return !topic || entry.topic === topic;
                });

                return api.shuffle(pool).slice(0, count).map(function (entry) {
                    return {
                        topic: entry.topic,
                        question: entry.question,
                        answer: entry.answer,
                        // Three choices for a young child, four once there are
                        // enough wrong answers to make a fourth meaningful.
                        options: api.shuffle([entry.answer].concat(entry.wrong.slice(0, 3)))
                    };
                });
            },

            /**
             * One arithmetic question, drawn from the rules the admin set.
             *
             * Math Race used to hard-code its own ranges, which meant nobody
             * running the site could put the class on times tables only. The
             * ranges are rows now, and this turns one of them into a sum - so
             * the game is still endless, inside whatever it has been given.
             *
             * @return {?{text: string, answer: number}} null when there are no rules
             */
            sum: function () {
                if (!api.sumrules.length) {
                    return null;
                }

                var rule = api.pick(api.sumrules);
                var a = api.random(rule.mina, rule.maxa);
                var b = api.random(rule.minb, rule.maxb);

                if (rule.op === 'minus') {
                    // Never a negative answer: the child has not met one yet.
                    if (b > a) {
                        var swap = a;
                        a = b;
                        b = swap;
                    }
                    return {text: a + ' - ' + b, answer: a - b};
                }
                if (rule.op === 'times') {
                    return {text: a + ' × ' + b, answer: a * b};
                }

                return {text: a + ' + ' + b, answer: a + b};
            },

            /**
             * One "catch only these" rule, drawn from the rows the admin set.
             *
             * Number Catcher and Balloon Pop ask the same kind of thing and used
             * to carry two copies of this. The wording differs between them, so
             * the caller says which set of strings to read.
             *
             * @param {string} prefix string prefix, "catch" or "balloon"
             * @return {?{test: Function, label: string}} null when there are no rules
             */
            numberRule: function (prefix) {
                if (!api.numberrules.length) {
                    return null;
                }

                var rule = api.pick(api.numberrules);
                var lo = Math.min(rule.minn, rule.maxn);
                var hi = Math.max(rule.minn, rule.maxn);
                var n = hi > 0 ? api.random(lo > 0 ? lo : 1, hi) : 0;
                var strings = api.strings;

                var say = function (key, value) {
                    var text = strings[prefix + '_rule_' + key] || '';
                    return value === undefined ? text : text.replace('{$a}', api.fmt(value));
                };

                switch (rule.kind) {
                    case 'odd':
                        return {test: function (v) {
                            return Math.abs(v % 2) === 1;
                        }, label: say('odd')};
                    case 'greater':
                        return {test: function (v) {
                            return v > n;
                        }, label: say('greater', n)};
                    case 'less':
                        return {test: function (v) {
                            return v < n;
                        }, label: say('less', n)};
                    case 'divisible':
                        return {test: function (v) {
                            return n > 0 && v % n === 0;
                        }, label: say('divisible', n)};
                    case 'equals':
                        return {test: function (v) {
                            return v === n;
                        }, label: say('equals', n), target: n};
                    default:
                        return {test: function (v) {
                            return v % 2 === 0;
                        }, label: say('even')};
                }
            },

            /** Start a fresh round's counters. */
            reset: function () {
                round = {correct: 0, wrong: 0, streak: 0, best: 0, score: 0};
                setHud(dom, 0, 0);
                hide(dom, 'lives-wrap');
                hide(dom, 'progress-wrap');
            },

            /**
             * Say how far through the round the child is.
             *
             * Every game calls this. A game with no visible end is a game a
             * child stops trusting - they cannot tell whether stopping now
             * loses anything.
             *
             * @param {number} done steps finished
             * @param {number} total steps in the round
             */
            setProgress: function (done, total) {
                var wrap = dom.root.querySelector('[data-hud="progress-wrap"]');
                var value = dom.root.querySelector('[data-hud="progress"]');

                wrap.classList.remove('gc-hud__item--hidden');
                value.textContent = api.fmt(Math.min(done, total)) + ' / ' + api.fmt(total);
                value.setAttribute('aria-label', (strings.progress || '{$a} / {$b}')
                    .replace('{$a}', String(done))
                    .replace('{$b}', String(total)));
                wrap.setAttribute('title', strings.progresslabel || '');
            },

            /**
             * The child got it right.
             *
             * @param {string} [message] what to show instead of the default praise
             * @param {number} [points] what this answer is worth; defaults to 1
             */
            correct: function (message, points) {
                round.correct++;
                round.score += typeof points === 'number' ? points : 1;
                round.streak++;
                round.best = Math.max(round.best, round.streak);
                setHud(dom, round.score, round.streak);
                audio.ding();
                flash(dom, 'ok', message || strings.correct);
            },

            /**
             * Not right. Never framed as a loss - the doc is explicit about it.
             *
             * @param {string} [message]
             */
            wrong: function (message) {
                round.wrong++;
                round.streak = 0;
                setHud(dom, round.score, round.streak);
                audio.buzz();
                flash(dom, 'no', message || strings.wrong);
            },

            /**
             * Show the tries left, as hearts.
             *
             * @param {number} lives
             */
            setLives: function (lives) {
                var wrap = dom.root.querySelector('[data-hud="lives-wrap"]');
                var value = dom.root.querySelector('[data-hud="lives"]');
                wrap.classList.remove('gc-hud__item--hidden');
                value.textContent = lives > 0 ? new Array(lives + 1).join('❤️') : '💔';
            },

            /** The round's running numbers, should a game want them. */
            stats: function () {
                return {
                    correct: round.correct,
                    wrong: round.wrong,
                    streak: round.best,
                    score: round.score
                };
            },

            /**
             * End the round, save it, and show the end card.
             *
             * @param {number} [score] the game own score; defaults to the points collected
             * @param {number} [goal] how many times the game own goal was met - matches
             *        won, planets reached, a board cleared inside its budget
             */
            finish: function (score, goal) {
                finishRound(
                    config,
                    dom,
                    round,
                    typeof score === "number" ? score : round.score,
                    typeof goal === "number" ? goal : 0
                );
            }
        };

        return api;
    }

    /**
     * Write the two HUD numbers.
     *
     * @param {Object} dom
     * @param {number} score
     * @param {number} streak
     */
    function setHud(dom, score, streak) {
        dom.root.querySelector('[data-hud="score"]').textContent = String(score);
        dom.root.querySelector('[data-hud="streak"]').textContent = String(streak);
    }

    /**
     * Hide one of the optional HUD slots again between rounds.
     *
     * @param {Object} dom
     * @param {string} slot the data-hud name of the wrapper
     */
    function hide(dom, slot) {
        dom.root.querySelector('[data-hud="' + slot + '"]').classList.add('gc-hud__item--hidden');
    }

    /**
     * A message floating over the stage.
     *
     * It stays up for two and a half seconds. The first version cleared after
     * 900ms, which is fine for "well done" - the child already knows - but far
     * too quick for the messages that actually say something, like "that is
     * not a word we know". A message a child cannot finish reading is the same
     * as no message.
     *
     * Only one is ever on screen: a new message replaces the old rather than
     * stacking on top of it.
     *
     * @param {Object} dom
     * @param {string} kind 'ok' or 'no'
     * @param {string} text
     */
    function flash(dom, kind, text) {
        if (!text) {
            return;
        }

        var previous = dom.stage.querySelector('.gc-flash');
        if (previous && previous.parentNode) {
            previous.parentNode.removeChild(previous);
        }

        var el = document.createElement('div');
        el.className = 'gc-flash gc-flash--' + kind;
        el.setAttribute('role', 'status');
        el.textContent = text;
        dom.stage.appendChild(el);

        window.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 2500);
    }

    /**
     * Save the round and show what it earned.
     *
     * @param {Object} config
     * @param {Object} dom
     * @param {Object} round
     * @param {number} score
     */
    function finishRound(config, dom, round, score, goal) {
        var strings = config.strings || {};

        audio.silence();
        audio.fanfare();

        var end = dom.root.querySelector('[data-role="end"]');
        var title = end.querySelector('[data-role="end-title"]');
        var text = end.querySelector('[data-role="end-text"]');
        var badges = end.querySelector('[data-role="end-badges"]');
        var again = end.querySelector('[data-action="again"]');

        title.textContent = strings.roundover || '';
        text.textContent = strings.saving || '';
        badges.innerHTML = '';
        again.textContent = strings.playagain || '';
        end.classList.remove('gc-overlay--hidden');

        // Anything hosting the shell - today the Game activity, which has to
        // record the round against a course module the corner knows nothing
        // about - gets told the round is over. It is announced here rather than
        // after the save so a host that does its own bookkeeping is not waiting
        // on the corner's network call, and it carries the numbers rather than
        // a promise so a listener cannot change what gets saved.
        dom.root.dispatchEvent(new CustomEvent('local-games:roundfinished', {
            bubbles: true,
            detail: {
                gameid: config.gameid,
                correct: round.correct,
                wrong: round.wrong,
                streak: round.best,
                score: score,
                goal: goal || 0
            }
        }));

        submit(config, round, score, goal).then(function (result) {
            text.textContent = (strings.yougot || '{$a}').replace('{$a}', String(score));

            (result.newbadges || []).forEach(function (badge) {
                var el = document.createElement('div');
                el.className = 'gc-newbadge';
                el.innerHTML = '<span class="gc-newbadge__icon" aria-hidden="true">🏅</span>';
                var name = document.createElement('span');
                name.className = 'gc-newbadge__name';
                name.textContent = (strings.newbadge || '') + ' ' + badge.name;
                el.appendChild(name);
                badges.appendChild(el);
            });

            if ((result.newbadges || []).length) {
                audio.say(strings.newbadge + ' ' + result.newbadges[0].name, config.arabicdigits ? 'ar-EG' : 'en');
            }
        }).catch(function () {
            // The round still happened; only the saving failed.
            text.textContent = strings.savefailed || '';
        });
    }

    /**
     * Post the round to local_games_submit_result.
     *
     * @param {Object} config
     * @param {Object} round
     * @param {number} score
     * @return {Promise<Object>}
     */
    function submit(config, round, score, goal) {
        var url = config.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(config.sesskey)
            + '&info=local_games_submit_result';

        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{
                index: 0,
                methodname: 'local_games_submit_result',
                args: {
                    gameid: config.gameid,
                    correct: round.correct,
                    wrong: round.wrong,
                    streak: round.best,
                    score: score,
                    goal: goal || 0
                }
            }])
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('http ' + response.status);
            }
            return response.json();
        }).then(function (payload) {
            var first = payload && payload[0];
            if (!first || first.error) {
                throw new Error('service error');
            }
            return first.data;
        });
    }

    /**
     * Wire the page up and hand the stage to the registered game.
     */
    function boot() {
        if (booted) {
            return;
        }

        var root = document.querySelector('.gc-play');
        if (!root) {
            return;
        }

        var confignode = root.querySelector('[data-role="config"]');
        if (!confignode) {
            return;
        }

        var config;
        try {
            config = JSON.parse(confignode.textContent);
        } catch (e) {
            return;
        }

        var factory = factories[config.gameid];
        if (!factory) {
            // The game file has not registered yet; boot() runs again when it does.
            return;
        }

        booted = true;

        var dom = {
            root: root,
            stage: root.querySelector('[data-role="stage"]'),
            start: root.querySelector('[data-role="start"]'),
            end: root.querySelector('[data-role="end"]')
        };

        var api = makeApi(config, dom);
        var game = factory(api);

        var play = function () {
            dom.start.classList.add('gc-overlay--hidden');
            dom.end.classList.add('gc-overlay--hidden');
            dom.stage.innerHTML = '';
            api.reset();
            if (game.stop) {
                game.stop();
            }
            game.start();
        };

        root.querySelector('[data-action="start"]').addEventListener('click', play);
        dom.end.querySelector('[data-action="again"]').addEventListener('click', play);

        var soundbutton = root.querySelector('[data-action="sound"]');

        /**
         * Put the button in the state the sound is actually in.
         */
        var paintSound = function () {
            var label = audio.enabled ? config.strings.sound_on : config.strings.sound_off;
            soundbutton.setAttribute('aria-pressed', audio.enabled ? 'true' : 'false');
            soundbutton.setAttribute('title', label);
            soundbutton.querySelector('.sr-only').textContent = label;
            soundbutton.firstElementChild.textContent = audio.enabled ? '🔊' : '🔇';
        };

        // The markup ships with the sound on, so a page opened after the child
        // muted the corner has to be corrected before they see it.
        paintSound();

        soundbutton.addEventListener('click', function () {
            audio.enabled = !audio.enabled;
            if (!audio.enabled) {
                audio.silence();
            }
            rememberSound(audio.enabled);
            paintSound();
        });

        // Leaving mid-round must not leave a voice talking to an empty room.
        window.addEventListener('pagehide', function () {
            audio.silence();
            if (game.stop) {
                game.stop();
            }
        });
    }

    window.LocalGames = {

        /**
         * A game file announces itself here.
         *
         * @param {string} gameid registry slug
         * @param {Function} factory receives the api, returns {start, stop}
         */
        register: function (gameid, factory) {
            factories[gameid] = factory;
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        }
    };
}());
