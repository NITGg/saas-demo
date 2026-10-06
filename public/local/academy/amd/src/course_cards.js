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
 * The script of the ONE course card (local_academy/course_card), on every page that
 * draws it: the home page's "المحاضرات المقترحة" slider, the teacher page and the
 * subject page. "... عرض باقي التفاصيل" shows only where the clamped description is
 * cut off, and opens the rest.
 *
 * No build step: amd/build/course_cards.min.js is a plain copy of this file.
 *
 * @module     local_academy/course_cards
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('local_academy/course_cards', [], function() {

    /**
     * Wire the cards inside root (cards wired before are left alone).
     *
     * @param {HTMLElement|string} root the element holding the cards, or its selector
     */
    var init = function(root) {
        if (typeof root === 'string') {
            root = document.querySelector(root);
        }
        if (!root) {
            return;
        }
        var toggles = Array.prototype.filter.call(root.querySelectorAll('[data-bthtp-more]'), function(btn) {
            return !btn.hasAttribute('data-bthtp-ready');
        });
        if (!toggles.length) {
            return;
        }
        // Measured again once the web fonts are in and on resize — both change the wrap.
        var measure = function() {
            toggles.forEach(function(btn) {
                var desc = btn.previousElementSibling;
                if (!desc.classList.contains('is-open')) {
                    btn.hidden = desc.scrollHeight <= desc.clientHeight + 1;
                }
            });
        };
        toggles.forEach(function(btn) {
            var desc = btn.previousElementSibling;
            var more = btn.textContent;
            btn.setAttribute('data-bthtp-ready', '1');
            btn.addEventListener('click', function() {
                var open = desc.classList.toggle('is-open');
                btn.textContent = open ? btn.getAttribute('data-less') : more;
            });
        });
        measure();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(measure);
        }
        window.addEventListener('resize', measure);
    };

    return {init: init};
});
