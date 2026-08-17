// Injects country-resolved price badges onto course cards in any listing
// (frontpage, category grid, course search) by keying off each card's
// /course/view.php?id=N link — theme-agnostic. Loaded (deferred) on every
// page by the before_standard_head_html_generation hook; it self-guards and
// does nothing when a page has no course links.
(function () {
    "use strict";

    function esc(s) {
        return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;" }[c];
        });
    }

    function buildBadge(ctx, labels) {
        // Nothing to sell.
        if (ctx.is_enrolled || ctx.is_free) {
            return "";
        }
        if (ctx.is_purchased) {
            return '<div class="lp-card-badge">' +
                '<span class="lp-badge lp-badge--purchased">' + esc(labels.purchased) + '</span></div>';
        }
        var cur = esc(ctx.currency || "");
        if (ctx.is_sale_active) {
            return '<div class="lp-card-badge">' +
                '<span class="lp-badge lp-badge--sale">-' + esc(ctx.discount_pct) + '%</span> ' +
                '<span class="lp-price lp-price--original">' + esc(ctx.original_price) + " " + cur + "</span> " +
                '<span class="lp-price lp-price--sale">' + esc(ctx.sale_price) + " " + cur + "</span>" +
                "</div>";
        }
        return '<div class="lp-card-badge">' +
            '<span class="lp-price lp-price--current">' + esc(ctx.price) + " " + cur + "</span></div>";
    }

    function init() {
        var root = (window.M && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : "";
        var re = /\/course\/view\.php\?id=(\d+)/;
        var links = document.querySelectorAll('a[href*="/course/view.php?id="]');
        if (!links.length) {
            return;
        }

        // First anchor per course id — that's where we hang the badge.
        var first = {};
        Array.prototype.forEach.call(links, function (a) {
            var m = re.exec(a.getAttribute("href") || "");
            if (!m) {
                return;
            }
            if (!first[m[1]]) {
                first[m[1]] = a;
            }
        });

        var ids = Object.keys(first);
        if (!ids.length) {
            return;
        }

        fetch(root + "/local/payments/ajax_prices.php?ids=" + ids.join(","), { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || j.status !== "success" || !j.data) {
                    return;
                }
                ids.forEach(function (id) {
                    var ctx = j.data[id];
                    var a = first[id];
                    if (!ctx || !a) {
                        return;
                    }
                    var html = buildBadge(ctx, j.labels || {});
                    if (!html) {
                        return;
                    }
                    // Prefer the card container so the badge sits cleanly at the bottom
                    // of the card; fall back to just after the course link.
                    var card = a.closest("[data-courseid]") || a.closest(".coursebox") || a.closest(".card");
                    var target = card || a;
                    if (target.dataset.lpBadged) {
                        return;
                    }
                    target.dataset.lpBadged = "1";
                    target.insertAdjacentHTML(card ? "beforeend" : "afterend", html);
                });
            })
            .catch(function () { /* listing still works without badges */ });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
