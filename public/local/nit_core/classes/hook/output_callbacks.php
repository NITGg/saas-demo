<?php
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

namespace local_nit_core\hook;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_nit_core\api\config;
use local_nit_core\output\welcome_panel;

/**
 * Output hook callbacks — the "when/where" of the rendering seam.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {
    /**
     * Inject the NIT welcome panel on the dashboard when enabled.
     *
     * Gated by the local_nit_core/showwelcomepanel setting (off by default) and
     * scoped to the dashboard. The SDK builds the view-model; the theme renders
     * it (via its template override), so no presentation lives here.
     *
     * @param before_standard_top_of_body_html_generation $hook the output hook
     * @return void
     */
    public static function add_welcome_panel(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!config::get_bool('showwelcomepanel')) {
            return;
        }
        if (!isset($PAGE) || $PAGE->pagelayout !== 'mydashboard') {
            return;
        }

        $renderer = $hook->renderer;
        $panel = new welcome_panel();

        // Prefer the theme's NIT renderer when present; degrade to a generic
        // template render otherwise (graceful if the active theme is not NIT).
        if (is_callable([$renderer, 'render_nit'])) {
            $html = $renderer->render_nit($panel);
        } else {
            $html = $renderer->render_from_template($panel->template_name(), $panel->export_for_template($renderer));
        }

        $hook->add_html($html);
    }

    /**
     * Upgrade every short-text course custom field on the course-edit form into a
     * "chips" editor: the teacher types a word/phrase and presses Enter to turn it
     * into a removable pill, repeatedly. No schema change — the hidden real input
     * still submits, carrying every entry joined by the chip separator so the value
     * stays one string in {customfield_data} and can be read back as a list.
     *
     * The separator is the same token the course-detail renderer splits on
     * ({@see \theme_nit\output\format_topics_renderer::CHIP_SEP}), so any field
     * edited this way is consumable as a chip list downstream.
     *
     * @param before_standard_top_of_body_html_generation $hook the output hook
     * @return void
     */
    public static function add_course_edit_chips(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isset($PAGE) || $PAGE->pagetype !== 'course-edit') {
            return;
        }

        // Single source of truth for the delimiter: the theme renderer's CHIP_SEP.
        // Fall back to the literal if the NIT theme is not the active class autoload.
        $sep = class_exists('\theme_nit\output\format_topics_renderer')
            ? \theme_nit\output\format_topics_renderer::CHIP_SEP
            : '@@|@@';

        // Widget styling. Uses the brand palette so it matches the site (with plain
        // fallbacks for a non-NIT theme). The course-edit form paints `.felement
        // input` with !important, so the typing field's colour/border rules are
        // !important with a descendant selector to reliably beat it.
        $css = <<<'CSS'
.nit-chips{display:flex;flex-direction:column;gap:9px;width:100%;padding:9px;border:1px solid color-mix(in srgb, var(--nit-brand-primary,#C0392B) 35%, transparent);border-radius:8px;background:var(--nit-brand-surface,#0D2149) !important;box-sizing:border-box}
.nit-chips .nit-chips__list{display:flex;flex-wrap:wrap;gap:6px}
.nit-chips .nit-chips__list:empty{display:none}
.nit-chips .nit-chips__chip{display:inline-flex;align-items:center;gap:8px;background:var(--nit-brand-primary,#C0392B) !important;color:var(--nit-brand-textprimary,#fff) !important;font-weight:600;font-size:13px;padding:4px 6px 4px 12px;border-radius:40px;line-height:1.5;max-width:100%;cursor:pointer}
.nit-chips .nit-chips__vals{display:inline-flex;align-items:center;gap:7px;overflow:hidden}
.nit-chips .nit-chips__label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;color:inherit !important}
.nit-chips .nit-chips__label:empty{display:none}
.nit-chips .nit-chips__vsep{opacity:.55;font-weight:400;flex:0 0 auto}
.nit-chips .nit-chips__vsep:first-child,.nit-chips .nit-chips__vsep:last-child{display:none}
.nit-chips .nit-chips__x{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border:none !important;border-radius:50%;background:rgba(0,0,0,0.22) !important;color:inherit !important;cursor:pointer;font-size:14px;line-height:1;padding:0;font-family:inherit;flex:0 0 auto}
.nit-chips .nit-chips__x:hover{background:rgba(0,0,0,0.4) !important}
.nit-chips .nit-chips__fields{display:flex;flex-wrap:wrap;gap:7px;align-items:stretch}
.nit-chips .nit-chips__field{display:flex;flex-direction:column;gap:3px;flex:1 1 160px;min-width:130px}
.nit-chips .nit-chips__flabel{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--nit-brand-textsecondary,#8A9AB5) !important}
.nit-chips .nit-chips__input{width:100%;border:1px solid color-mix(in srgb, var(--nit-brand-textprimary,#fff) 16%, transparent) !important;outline:none !important;background:color-mix(in srgb, var(--nit-brand-textprimary,#fff) 5%, transparent) !important;color:var(--nit-brand-textprimary,#fff) !important;font:inherit;border-radius:6px;padding:8px 10px !important;margin:0 !important;height:auto !important;box-shadow:none !important;box-sizing:border-box}
.nit-chips .nit-chips__input:focus{border-color:var(--nit-brand-primary,#C0392B) !important}
.nit-chips .nit-chips__input::placeholder{color:var(--nit-brand-textsecondary,#8A9AB5) !important;opacity:1}
.nit-chips .nit-chips__add{flex:0 0 auto;align-self:flex-end;display:inline-flex;align-items:center;justify-content:center;gap:6px;border:none !important;cursor:pointer;background:var(--nit-brand-primary,#C0392B) !important;color:var(--nit-brand-textprimary,#fff) !important;font:inherit;font-weight:600;border-radius:6px;padding:8px 16px !important;line-height:1.5;height:38px}
.nit-chips .nit-chips__add:hover{background:color-mix(in srgb, var(--nit-brand-primary,#C0392B) 86%, #000) !important}
CSS;

        // Every short-text custom field renders as <input type="text" id="id_customfield_*">.
        // Long text (textarea/editor), select, checkbox and date fields are other
        // elements, so this type+prefix selector picks out exactly the short-text ones.
        //
        // Each chip is one bilingual item: the teacher fills an English and an Arabic
        // field, and we compose the {mlang en}…{mlang}{mlang ar}…{mlang} string for
        // them — they never type mlang syntax by hand. A one-language item is stored
        // as plain text (so numeric fields such as "hours" stay a bare number), which
        // {@see \theme_nit\output\format_topics_renderer::acad_ml()} still reads back.
        $js = <<<'JS'
require([], function() {
    var SEP = {$this_sep_placeholder};
    var L   = {$this_labels_placeholder};

    if (!document.getElementById('nit-chips-css')) {
        var st = document.createElement('style');
        st.id = 'nit-chips-css';
        st.textContent = {$this_css_placeholder};
        document.head.appendChild(st);
    }

    // Split one stored item back into {en, ar}. mlang blocks win; a value with no
    // mlang tags is treated as the English side (plain text / numbers round-trip).
    function parseItem(str) {
        var en = '', ar = '', matched = false;
        var re = /\{mlang\s+([^}]+)\}([\s\S]*?)\{mlang\}/gi;
        var m;
        while ((m = re.exec(str)) !== null) {
            matched = true;
            var langs = m[1].toLowerCase().split(',').map(function(s) { return s.trim(); });
            if (langs.indexOf('en') >= 0) { en = m[2]; }
            if (langs.indexOf('ar') >= 0) { ar = m[2]; }
        }
        if (!matched) { en = str; }
        return { en: en.trim(), ar: ar.trim() };
    }

    // Compose the stored string. Both languages -> mlang; one language -> plain.
    function composeItem(en, ar) {
        en = (en || '').trim();
        ar = (ar || '').trim();
        if (en && ar) { return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}'; }
        if (en) { return en; }
        if (ar) { return ar; }
        return '';
    }

    function build(input) {
        if (!input || input.getAttribute('data-nit-chips')) { return; }
        input.setAttribute('data-nit-chips', '1');

        var chips = []; // Array of { en, ar }.

        var wrap = document.createElement('div');
        wrap.className = 'nit-chips';
        var list = document.createElement('div');
        list.className = 'nit-chips__list';
        var fields = document.createElement('div');
        fields.className = 'nit-chips__fields';

        function makeField(labelText, dir, cls) {
            var f = document.createElement('div');
            f.className = 'nit-chips__field';
            var lab = document.createElement('span');
            lab.className = 'nit-chips__flabel';
            lab.textContent = labelText;
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'nit-chips__input ' + cls;
            inp.setAttribute('autocomplete', 'off');
            inp.setAttribute('dir', dir);
            f.appendChild(lab);
            f.appendChild(inp);
            return { field: f, input: inp };
        }

        var enF = makeField(L.en, 'ltr', 'nit-chips__input--en');
        var arF = makeField(L.ar, 'rtl', 'nit-chips__input--ar');
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-chips__add';
        addBtn.textContent = L.add;

        fields.appendChild(enF.field);
        fields.appendChild(arF.field);
        fields.appendChild(addBtn);
        wrap.appendChild(list);
        wrap.appendChild(fields);

        // Keep the original input in the form (it is what submits) but hide it.
        input.style.display = 'none';
        input.parentNode.insertBefore(wrap, input.nextSibling);

        function serialize() {
            input.value = chips.map(function(c) { return composeItem(c.en, c.ar); })
                               .filter(function(v) { return v !== ''; })
                               .join(SEP);
        }

        function render() {
            list.textContent = '';
            chips.forEach(function(c, i) {
                var chip = document.createElement('span');
                chip.className = 'nit-chips__chip';
                chip.title = L.edit;

                var vals = document.createElement('span');
                vals.className = 'nit-chips__vals';
                var le = document.createElement('span');
                le.className = 'nit-chips__label';
                le.setAttribute('dir', 'ltr');
                le.textContent = c.en;
                var vsep = document.createElement('span');
                vsep.className = 'nit-chips__vsep';
                vsep.textContent = '·';
                if (!c.en || !c.ar) { vsep.style.display = 'none'; }
                var la = document.createElement('span');
                la.className = 'nit-chips__label';
                la.setAttribute('dir', 'rtl');
                la.textContent = c.ar;
                vals.appendChild(le);
                vals.appendChild(vsep);
                vals.appendChild(la);

                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'nit-chips__x';
                x.setAttribute('aria-label', L.remove);
                x.innerHTML = '&times;';
                x.addEventListener('click', function(e) {
                    e.stopPropagation();
                    chips.splice(i, 1);
                    serialize();
                    render();
                    enF.input.focus();
                });

                // Click the chip body -> load it back into the fields to edit, then re-add.
                chip.addEventListener('click', function() {
                    enF.input.value = c.en;
                    arF.input.value = c.ar;
                    chips.splice(i, 1);
                    serialize();
                    render();
                    enF.input.focus();
                });

                chip.appendChild(vals);
                chip.appendChild(x);
                list.appendChild(chip);
            });
        }

        function commit() {
            var e = enF.input.value.trim();
            var a = arF.input.value.trim();
            if (e === '' && a === '') { return; }
            chips.push({ en: e, ar: a });
            enF.input.value = '';
            arF.input.value = '';
            serialize();
            render();
            enF.input.focus();
        }

        // Seed from the existing stored value.
        if (input.value && input.value.trim() !== '') {
            input.value.split(SEP).forEach(function(part) {
                if (part.trim() === '') { return; }
                chips.push(parseItem(part));
            });
            render();
        }

        // Enter in the English field jumps to Arabic (encourage both), unless Arabic
        // is already filled -> commit. Enter in Arabic (or the Add button) commits.
        enF.input.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') { return; }
            e.preventDefault(); // never let Enter submit the whole course form here
            if (enF.input.value.trim() !== '' && arF.input.value.trim() === '') {
                arF.input.focus();
            } else {
                commit();
            }
        });
        arF.input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); commit(); }
        });
        addBtn.addEventListener('click', commit);

        // Don't lose a half-typed entry if the user clicks Save without adding it:
        // when focus leaves the whole widget, commit whatever is typed.
        function flushOnLeave() {
            setTimeout(function() {
                if (!wrap.contains(document.activeElement)) {
                    if (enF.input.value.trim() !== '' || arF.input.value.trim() !== '') {
                        commit();
                    }
                }
            }, 0);
        }
        enF.input.addEventListener('blur', flushOnLeave);
        arF.input.addEventListener('blur', flushOnLeave);
    }

    function init() {
        var form = document.querySelector('form.mform') || document;
        var inputs = form.querySelectorAll('input[type="text"][id^="id_customfield_"]');
        Array.prototype.forEach.call(inputs, build);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
});
JS;

        // UI labels for the bilingual editor. The two language names are shown as-is
        // in either interface language; the actions reuse core strings so they follow
        // the admin's language.
        $labels = [
            'en'     => 'English',
            'ar'     => 'العربية',
            'add'    => get_string('add'),
            'edit'   => get_string('edit'),
            'remove' => get_string('remove'),
        ];

        // Splice the CSS + separator + labels in as JSON literals so quotes/newlines are safe.
        $js = str_replace('{$this_css_placeholder}', json_encode($css), $js);
        $js = str_replace('{$this_sep_placeholder}', json_encode($sep), $js);
        $js = str_replace('{$this_labels_placeholder}', json_encode($labels), $js);

        $PAGE->requires->js_amd_inline($js);
    }
}
