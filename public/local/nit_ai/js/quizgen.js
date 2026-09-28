/**
 * Drives the generator through a video, one slice per request.
 *
 * The loop lives in the browser rather than in PHP for a practical reason: a
 * forty minute video is a dozen calls to an AI provider, and one PHP request
 * holding all of them would hit a time limit somewhere in the middle with
 * nothing to show for it. Here every slice that lands is saved before the next
 * one starts, so a failure costs one slice.
 *
 * @module     local_nit_ai/quizgen
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function () {
    'use strict';

    /**
     * The batch number that means "go back for whatever was missed".
     * Kept in step with quizgen_generate::BATCH_GAPS.
     */
    var BATCH_GAPS = -1;

    /**
     * Call a Moodle web service.
     *
     * @param {string} methodname
     * @param {object} args
     * @return {Promise} resolves with the service's data
     */
    function callService(methodname, args) {
        var url = M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey +
            '&info=' + encodeURIComponent(methodname);

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{index: 0, methodname: methodname, args: args}])
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            var first = payload && payload[0];
            if (!first || first.error) {
                throw new Error((first && (first.exception || {}).message) || 'request failed');
            }
            return first.data;
        });
    }

    /**
     * Fill a template of the form "Part {a} of {b}".
     *
     * @param {string} template
     * @param {number} a
     * @param {number} b
     * @return {string}
     */
    function fill(template, a, b) {
        return template.replace('{a}', a).replace('{b}', b);
    }

    /**
     * Run one generation to the end.
     *
     * @param {HTMLElement} root
     */
    function start(root) {
        var cmid = parseInt(root.getAttribute('data-cmid'), 10);
        var runid = parseInt(root.getAttribute('data-runid'), 10);
        var batches = parseInt(root.getAttribute('data-batches'), 10) || 1;
        var maxGaps = parseInt(root.getAttribute('data-maxgaps'), 10) || 0;
        var reviewUrl = root.getAttribute('data-reviewurl');
        var strSlice = root.getAttribute('data-strslice') || '';
        var strGaps = root.getAttribute('data-strgaps') || '';

        var bar = root.querySelector('[data-region="bar"]');
        var progress = bar ? bar.parentNode : null;
        var step = root.querySelector('[data-region="step"]');
        var questions = root.querySelector('[data-region="questions"]');
        var percent = root.querySelector('[data-region="percent"]');
        var warning = root.querySelector('[data-region="warning"]');
        var error = root.querySelector('[data-region="error"]');

        /**
         * Move the bar. The main pass owns the first 90%; the gap passes, which
         * may not happen at all, share what is left.
         *
         * @param {number} done
         * @param {number} total
         */
        function setBar(done, total) {
            var pct = total ? Math.round((done / total) * 100) : 0;
            if (bar) {
                bar.style.width = pct + '%';
            }
            if (progress) {
                progress.setAttribute('aria-valuenow', pct);
            }
        }

        /**
         * Show what one call reported back.
         *
         * @param {object} data
         */
        function show(data) {
            if (questions) {
                questions.textContent = data.questions;
            }
            if (percent) {
                percent.textContent = data.percent + '%';
            }
            if (data.warning && warning) {
                warning.textContent = data.warning;
                warning.classList.remove('d-none');
            }
        }

        /**
         * Stop, and say why.
         *
         * @param {string} message
         */
        function fail(message) {
            if (error) {
                error.textContent = message;
                error.classList.remove('d-none');
            }
            if (step) {
                step.textContent = '';
            }
        }

        /**
         * Generate the numbered slices, in order.
         *
         * @param {number} index
         * @return {Promise}
         */
        function mainPass(index) {
            if (index >= batches) {
                return Promise.resolve(null);
            }

            if (step) {
                step.textContent = fill(strSlice, index + 1, batches);
            }

            return callService('local_nit_ai_quizgen_generate', {cmid: cmid, runid: runid, batch: index})
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.error);
                    }
                    show(data);
                    setBar((index + 1) * 0.9, batches);
                    return mainPass(index + 1);
                });
        }

        /**
         * Go back for the blocks nothing was asked about, up to the allowed
         * number of passes. A pass that finds nothing new ends the phase: the
         * remaining blocks are ones the model has now twice declined to examine,
         * and asking again would only cost money to be told the same thing.
         *
         * @param {number} pass
         * @param {number} lastGaps
         * @return {Promise}
         */
        function gapPass(pass, lastGaps) {
            if (pass >= maxGaps) {
                return Promise.resolve(null);
            }

            if (step) {
                step.textContent = strGaps;
            }

            return callService('local_nit_ai_quizgen_generate', {cmid: cmid, runid: runid, batch: BATCH_GAPS})
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.error);
                    }
                    show(data);
                    setBar(0.9 + ((pass + 1) / maxGaps) * 0.1, 1);

                    if (data.gaps === 0 || data.gaps >= lastGaps) {
                        return null;
                    }
                    return gapPass(pass + 1, data.gaps);
                });
        }

        mainPass(0)
            .then(function () {
                return gapPass(0, Number.MAX_SAFE_INTEGER);
            })
            .then(function () {
                setBar(1, 1);
                window.location.href = reviewUrl;
            })
            .catch(function (e) {
                fail(e.message);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-region="nitai-quizgen"]');
        if (root) {
            start(root);
        }
    });
}());
