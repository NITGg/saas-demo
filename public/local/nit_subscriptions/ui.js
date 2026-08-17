/*
 * NIT shared front-end UI helper: a reusable client-side paginator.
 *
 * Loaded as a plain (non-AMD) script via $PAGE->requires->js('/local/nit_subscriptions/ui.js', true)
 * so the per-page inline IIFEs can reuse it. Exposes AcademyUI.paginate (name kept from the reference
 * so the ported page scripts run unchanged).
 */
(function (w) {
    'use strict';

    var DEFAULT_LABELS = {
        info: 'Showing {from}–{to} of {total}',
        prev: '‹',
        next: '›'
    };

    function fill(tpl, map) {
        return String(tpl).replace(/\{(\w+)\}/g, function (m, k) {
            return (k in map) ? map[k] : m;
        });
    }

    // Build the list of page tokens to show: 1 … around-current … last, capped so the bar stays short.
    function pageTokens(current, total) {
        if (total <= 7) {
            var all = [];
            for (var i = 1; i <= total; i++) { all.push(i); }
            return all;
        }
        var tokens = [1];
        var start = Math.max(2, current - 1);
        var end = Math.min(total - 1, current + 1);
        if (start > 2) { tokens.push('...'); }
        for (var p = start; p <= end; p++) { tokens.push(p); }
        if (end < total - 1) { tokens.push('...'); }
        tokens.push(total);
        return tokens;
    }

    function paginate(opts) {
        var rows = opts.rows || [];
        var pageSize = opts.pageSize || 10;
        var render = opts.render;
        var pagerEl = opts.pagerEl;
        var labels = Object.assign({}, DEFAULT_LABELS, opts.labels || {});
        var current = 1;

        function totalPages() {
            return Math.max(1, Math.ceil(rows.length / pageSize));
        }

        function btn(label, opts2) {
            opts2 = opts2 || {};
            var b = document.createElement('button');
            b.type = 'button';
            b.innerHTML = label;
            if (opts2.cls) { b.className = opts2.cls; }
            if (opts2.disabled) { b.disabled = true; }
            if (opts2.onclick) { b.onclick = opts2.onclick; }
            if (opts2.label) { b.setAttribute('aria-label', opts2.label); }
            return b;
        }

        function go(p) {
            var tp = totalPages();
            current = Math.min(Math.max(1, p), tp);
            var startIdx = (current - 1) * pageSize;
            render(rows.slice(startIdx, startIdx + pageSize), current);
            drawBar();
        }

        function drawBar() {
            if (!pagerEl) { return; }
            pagerEl.innerHTML = '';
            var tp = totalPages();
            if (rows.length === 0) { return; }

            var from = (current - 1) * pageSize + 1;
            var to = Math.min(current * pageSize, rows.length);
            var info = document.createElement('span');
            info.className = 'acad-pager__info';
            info.textContent = fill(labels.info, { from: from, to: to, total: rows.length });
            pagerEl.appendChild(info);

            if (tp <= 1) { return; }

            pagerEl.appendChild(btn(labels.prev, {
                disabled: current === 1, label: 'Previous page',
                onclick: function () { go(current - 1); }
            }));

            pageTokens(current, tp).forEach(function (tok) {
                if (tok === '...') {
                    var e = document.createElement('span');
                    e.className = 'acad-pager__ellipsis';
                    e.textContent = '…';
                    pagerEl.appendChild(e);
                    return;
                }
                pagerEl.appendChild(btn(String(tok), {
                    cls: tok === current ? 'is-active' : '',
                    onclick: function () { go(tok); }
                }));
            });

            pagerEl.appendChild(btn(labels.next, {
                disabled: current === tp, label: 'Next page',
                onclick: function () { go(current + 1); }
            }));
        }

        go(1);

        return {
            setRows: function (newRows) { rows = newRows || []; go(1); },
            refresh: function () { go(current); },
            get page() { return current; }
        };
    }

    w.AcademyUI = w.AcademyUI || {};
    w.AcademyUI.paginate = paginate;
})(window);
