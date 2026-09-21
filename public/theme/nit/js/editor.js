/* eslint-env browser */
/*
 * theme_nit — in-academy inline front-page editor (Phase 3.0 framework).
 *
 * Loaded on the Site home only for site admins (see layout/frontpage.php). Adds a
 * floating "Edit page" toggle; while editing, each front-page region (an element
 * carrying data-nit-section="…") gets a pencil. Phase 3.0 wires the shared image
 * pipeline (replace a region's background image, size-enforced, saved via
 * edit.php with sesskey). Per-region panels (text, points, gallery, contact,
 * footer, palette, logo) extend this in 3.1–3.8.
 *
 * Config comes from window.NIT_EDIT = { editUrl, sesskey, maxImageBytes,
 * str:{...} } set inline by the layout. Dependency-free.
 */
(function () {
    'use strict';

    var CFG = window.NIT_EDIT;
    if (!CFG || !CFG.editUrl || !CFG.sesskey) {
        return;
    }
    var S = CFG.str || {};
    var editing = false;
    var coloursBtn = null;
    var panelCleanup = null; // run when the current panel closes (e.g. revert preview)

    function t(key, fallback) {
        return (S && S[key]) || fallback;
    }

    // Build a Moodle multilang2 string from an EN/AR pair — mirrors editor.php
    // mlang_build(): both sides → {mlang en}…{mlang}{mlang ar}…{mlang}; one side →
    // that side as plain text.
    function mlangBuild(en, ar) {
        en = (en || '').trim();
        ar = (ar || '').trim();
        if (en && ar) { return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}'; }
        return en || ar;
    }

    // Blend hex a→b by ratio r (0..1) — mirrors the server's _mix so the live
    // preview matches what Save will compile.
    function mixHex(a, b, r) {
        a = String(a).replace('#', '');
        b = String(b).replace('#', '');
        if (a.length !== 6 || b.length !== 6) { return '#' + a; }
        var ch = function (h, i) { return parseInt(h.substr(i, 2), 16); };
        var to = function (x) { return ('0' + Math.round(x).toString(16)).slice(-2); };
        return '#' + to(ch(a, 0) * (1 - r) + ch(b, 0) * r)
            + to(ch(a, 2) * (1 - r) + ch(b, 2) * r)
            + to(ch(a, 4) * (1 - r) + ch(b, 4) * r);
    }

    var BRAND_VARS = ['primary', 'accent', 'secondary', 'background', 'surface', 'textprimary',
        'accenttext', 'textsecondary', 'borderprimary', 'bordersecondary', 'hoverbackground', 'hovertext'];

    // Live-preview a palette. The active --nit-brand-<role> resolves to
    // --nit-brand-g1-<role>, and every derived shade / legacy alias references
    // those via live color-mix — so overriding the g1 SOURCE vars (on :root)
    // updates the WHOLE page, including the page background (fixes dark↔light).
    function paletteVars(c) {
        return {
            primary: c.primary, accent: c.accent, secondary: c.secondary,
            background: c.background, surface: c.surface, textprimary: c.text,
            // Accent Text = the LINK / navbar-title / footer-link colour. It sits on
            // the page BACKGROUND, so it must read there — a tint of accent toward
            // text (NOT a contrast-with-primary value, which turned the navbar title
            // dark-on-dark). Button labels use --nit-brand-on-primary instead.
            accenttext: mixHex(c.accent, c.text, 0.30),
            textsecondary: mixHex(c.text, c.background, 0.42),
            borderprimary: mixHex(c.surface, c.text, 0.12),
            bordersecondary: mixHex(c.surface, c.text, 0.24),
            hoverbackground: mixHex(c.surface, c.primary, 0.14),
            hovertext: c.text
        };
    }
    function applyPalettePreview(c) {
        var v = paletteVars(c);
        var el = document.documentElement;
        BRAND_VARS.forEach(function (k) {
            el.style.setProperty('--nit-brand-g1-' + k, v[k]);
            el.style.setProperty('--nit-brand-' + k, v[k]);
        });
    }
    function clearPalettePreview() {
        var el = document.documentElement;
        BRAND_VARS.forEach(function (k) {
            el.style.removeProperty('--nit-brand-g1-' + k);
            el.style.removeProperty('--nit-brand-' + k);
        });
    }

    // ── styles ───────────────────────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('nit-edit-styles')) {
            return;
        }
        var css =
            // Colours button — docked in the navbar beside the edit switch (not floating).
            '.nit-colours-navbtn{display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px;' +
            'padding:6px 14px;border:none;border-radius:999px;font:600 13px system-ui,sans-serif;cursor:pointer;' +
            'background:#00FFB2;color:#0B2923;white-space:nowrap}' +
            '.nit-img-pick{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}' +
            '.nit-img-thumb{height:64px;width:96px;border-radius:8px;border:1px solid #ccc;background-size:cover;background-position:center}' +
            'body.editing [data-nit-section],body.editing [data-nit-edit]{outline:2px dashed rgba(0,180,140,.7);outline-offset:-2px;position:relative}' +
            // z-index 1020 is BELOW the fixed navbar (Bootstrap .fixed-top = 1030) but
            // above page content — so a section pencil slides BEHIND the navbar as its
            // section scrolls up under the bar (instead of floating over it), and shows
            // normally while the section is in view.
            '.nit-edit-pencil{position:absolute;top:10px;inset-inline-end:10px;z-index:1020;' +
            'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:none;border-radius:8px;' +
            'font:600 13px system-ui,sans-serif;cursor:pointer;background:#0B2923;color:#00FFB2;' +
            'box-shadow:0 2px 8px rgba(0,0,0,.35)}' +
            // The hero is pulled UP under the 100px navbar, so its pencil at top:10px
            // would always hide behind the bar — drop it to 56px so it clears the bar
            // and stays clickable. The branding pencil lives ON the navbar logo; pin it
            // just BELOW the bar (above it in z so it isn't clipped) instead of over it.
            '[data-nit-section="hero"] > .nit-edit-pencil{top:56px}' +
            '.nit-navbar-brand > .nit-edit-pencil{position:fixed;top:108px;inset-inline-start:12px;' +
            'inset-inline-end:auto;z-index:1031}' +
            '.nit-edit-busy{opacity:.6;pointer-events:none}' +
            '.nit-edit-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);' +
            'display:flex;align-items:flex-start;justify-content:center;padding:40px 16px;overflow:auto}' +
            '.nit-edit-card{background:#fff;color:#0B2923;border-radius:14px;max-width:420px;width:100%;' +
            'padding:20px;box-shadow:0 20px 60px -20px rgba(0,0,0,.6);font:14px system-ui,sans-serif}' +
            '.nit-edit-card h3{margin:0 0 14px;font-size:16px}' +
            '.nit-edit-row{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}' +
            '.nit-edit-card label{font-weight:600;font-size:13px}' +
            '.nit-edit-card select{padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit;width:100%}' +
            '.nit-edit-btn{padding:8px 14px;border:none;border-radius:8px;font:600 13px system-ui;cursor:pointer;background:#0B2923;color:#00FFB2}' +
            '.nit-edit-btn.sec{background:#eee;color:#0B2923}' +
            '.nit-edit-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:6px}' +
            '.nit-add-course{display:inline-flex;align-items:center;justify-content:center;gap:8px;' +
            'min-height:44px;padding:12px 20px;margin:4px;border-radius:12px;text-decoration:none;' +
            'font:700 15px system-ui,sans-serif;background:#0B2923;color:#00FFB2;' +
            'border:2px dashed rgba(0,255,178,.4)}' +
            // ── Design-17 side panel ──
            "#nit-side-panel{position:fixed;top:64px;inset-inline-end:0;width:340px;max-width:88vw;" +
            "height:calc(100vh - 64px);background:#fff;border-inline-start:1px solid #EDEDE9;" +
            "box-shadow:-12px 0 40px rgba(20,24,28,.08);z-index:1029;overflow:auto;" +
            "font:14px 'Manrope',system-ui,sans-serif;color:#16191D;padding:18px 16px 40px}" +
            "#nit-side-panel .nit-side-hd{font:700 11px 'Manrope',system-ui;letter-spacing:.12em;" +
            "text-transform:uppercase;color:#8A8A82;margin:18px 4px 8px}" +
            "#nit-side-panel .nit-side-hint{font-size:12px;color:#8A8A82;margin:0 4px 6px;line-height:1.5}" +
            "#nit-side-panel .nit-side-list{display:flex;flex-direction:column;gap:2px}" +
            "#nit-side-panel .nit-side-secrow{display:flex;align-items:center;gap:11px;width:100%;text-align:start;" +
            "padding:11px 12px;border:1px solid transparent;border-radius:10px;background:none;cursor:pointer;" +
            "font:600 14px 'Manrope',system-ui;color:#16191D}" +
            "#nit-side-panel .nit-side-secrow:hover{background:#FAFAF8}" +
            "#nit-side-panel .nit-side-secrow.on{background:color-mix(in srgb,var(--nit-brand-primary,#0E7C66) 9%,#fff);" +
            "border-color:color-mix(in srgb,var(--nit-brand-primary,#0E7C66) 30%,#DCE9E5);color:var(--nit-brand-primary,#0E7C66)}" +
            "#nit-side-panel .nit-side-secrow .h{color:#C4C4BD}" +
            "#nit-side-panel .nit-side-content{border:1px solid #EDEDE9;border-radius:12px;padding:14px;background:#FAFAF8}" +
            "#nit-side-panel .nit-side-content .nit-edit-row,#nit-side-panel .nit-side-content label{color:#16191D}" +
            "#nit-side-panel .nit-side-btn,#nit-side-panel .nit-edit-btn{padding:10px 14px;border:0;border-radius:9px;" +
            "font:600 13px 'Manrope',system-ui;cursor:pointer;background:#16191D;color:#fff}" +
            "#nit-side-panel .nit-side-field{display:flex;flex-direction:column;gap:6px;margin:0 0 12px}" +
            "#nit-side-panel .nit-side-field label{font:600 12px 'Manrope',system-ui;color:#6E7781}" +
            "#nit-side-panel .nit-side-input{width:100%;box-sizing:border-box;border:1px solid #DCDCD7;border-radius:9px;padding:9px 11px;" +
            "font:14px 'Manrope',system-ui;color:#16191D;background:#fff}" +
            "#nit-side-panel textarea.nit-side-input{min-height:72px;resize:vertical}" +
            "#nit-side-panel .nit-side-img{display:flex;align-items:center;gap:10px}" +
            "#nit-side-panel .nit-side-thumb{width:72px;height:48px;border-radius:8px;border:1px solid #DCDCD7;" +
            "background:repeating-linear-gradient(135deg,#EFEFEC 0 6px,#F7F7F5 6px 12px) center/cover no-repeat;flex:none}" +
            "#nit-side-panel .nit-side-file{font:12px system-ui;max-width:180px}" +
            "#nit-side-panel .nit-side-actions{display:flex;gap:8px;margin-top:8px}" +
            "#nit-side-panel .nit-side-btn.sec{background:#fff;color:#16191D;border:1px solid #DCDCD7}" +
            "#nit-side-panel .nit-side-secrow{padding:6px 8px;gap:6px}" +
            "#nit-side-panel .nit-side-secrow .n{flex:1;text-align:start;background:none;border:0;padding:6px 4px;cursor:pointer;" +
            "font:600 14px 'Manrope',system-ui;color:inherit}" +
            ".nit-side-group{margin:18px 0 6px;padding-top:14px;border-top:1px solid #ECECE8}" +
            ".nit-publishbar{background:#F7F7F5;border:1px solid #ECECE8;border-radius:12px;padding:12px 14px 12px;margin:0 0 14px}" +
            ".nit-publishbar .nit-side-actions{margin-top:6px}" +
            ".nit-side-order{display:flex;flex-direction:column;gap:4px}" +
            ".nit-side-orow{display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:8px;font-size:13px}" +
            ".nit-side-orow.on{background:#F3F6F5}" +
            ".nit-side-orow .n{flex:1}" +
            ".nit-side-orow .h{background:none;border:0;cursor:pointer;color:#A3A39B;font-size:13px;padding:2px 5px}" +
            ".nit-side-orow .h:disabled{opacity:.25;cursor:default}" +
            ".nit-side-subhd{font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:#6E7781;margin:0 0 10px}" +
            ".nit-side-checks{display:flex;flex-direction:column;gap:6px;max-height:260px;overflow:auto;padding:4px 2px}" +
            ".nit-side-check{display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.4;margin:0;cursor:pointer}" +
            ".nit-side-check input{margin-top:3px;flex:none}" +
            ".nit-side-mini{display:flex;gap:6px;margin-bottom:6px}" +
            ".nit-side-mini button{font:600 11px system-ui;padding:3px 9px;border-radius:999px;border:1px solid #DCDCD7;background:#fff;cursor:pointer}" +
            ".nit-side-mini button.on{background:#16191D;color:#fff;border-color:#16191D}" +
            ".nit-side-card{position:relative;display:flex;flex-direction:column;gap:6px;padding:10px 34px 10px 10px;border:1px solid #ECECE8;border-radius:10px;margin-bottom:8px;background:#FAFAF8}" +
            ".nit-side-x{position:absolute;top:6px;inset-inline-end:6px;width:24px;height:24px;border-radius:50%;border:1px solid #DCDCD7;background:#fff;cursor:pointer;font-size:14px;line-height:1}" +
            ".nit-side-preview{border:1px solid #ECECE8;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:12px;aspect-ratio:16/10}" +
            ".nit-side-preview iframe{width:200%;height:200%;border:0;transform:scale(.5);transform-origin:0 0;pointer-events:none}" +
            "body.nit-dirty .nit-side-btn:not(.sec){box-shadow:0 0 0 3px rgba(224,161,0,.35)}" +
            "#nit-side-panel .nit-side-secrow .h,#nit-side-panel .nit-side-secrow .eye{background:none;border:0;cursor:pointer;" +
            "color:#A3A39B;font-size:13px;padding:4px 5px;line-height:1}" +
            "#nit-side-panel .nit-side-secrow .h:disabled{opacity:.25;cursor:default}" +
            "#nit-side-panel .nit-side-secrow.off .n{color:#A3A39B;text-decoration:line-through}" +
            "#nit-side-panel .nit-side-add{display:flex;gap:8px;margin:10px 4px 0;align-items:center}" +
            "#nit-side-panel .nit-side-add select{flex:1}" +
            "body.editing [data-nit-section].nit-sel{outline:2px solid var(--nit-brand-primary,#0E7C66)!important;outline-offset:-2px}" +
            "@media (max-width:820px){#nit-side-panel{width:100%;max-width:100%;top:auto;bottom:0;height:70vh;border-top:1px solid #EDEDE9}}";
        var el = document.createElement('style');
        el.id = 'nit-edit-styles';
        el.textContent = css;
        document.head.appendChild(el);
    }

    // ── save: upload an image for a given action (+ extra form fields) ─────────
    function uploadImage(action, extra, pencil) {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            if (CFG.maxImageBytes && file.size > CFG.maxImageBytes) {
                window.alert(t('imagetoolarge', 'Image is too large.'));
                return;
            }
            var fd = new FormData();
            fd.append('action', action);
            fd.append('sesskey', CFG.sesskey);
            fd.append('image', file);
            Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
            pencil.classList.add('nit-edit-busy');
            fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        window.location.reload();
                    } else {
                        pencil.classList.remove('nit-edit-busy');
                        window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : ''));
                    }
                })
                .catch(function () {
                    pencil.classList.remove('nit-edit-busy');
                    window.alert(t('savefailed', 'Could not save'));
                });
        });
        input.click();
    }

    // ── modal panel ───────────────────────────────────────────────────────────
    function closePanel() {
        if (panelCleanup) {
            var fn = panelCleanup;
            panelCleanup = null;
            try { fn(); } catch (e) { /* ignore */ }
        }
        var ov = document.getElementById('nit-edit-overlay');
        if (ov) { ov.remove(); }
    }
    function openPanel(title, bodyNode) {
        closePanel();
        var ov = document.createElement('div');
        ov.className = 'nit-edit-overlay';
        ov.id = 'nit-edit-overlay';
        var card = document.createElement('div');
        card.className = 'nit-edit-card';
        var h = document.createElement('h3');
        h.textContent = title;
        card.appendChild(h);
        card.appendChild(bodyNode);
        ov.appendChild(card);
        ov.addEventListener('click', function (e) { if (e.target === ov) { closePanel(); } });
        document.body.appendChild(ov);
    }

    // Post a plain (non-file) form to edit.php; reloads on success.
    function postFields(fields, btn) {
        var fd = new FormData();
        fd.append('sesskey', CFG.sesskey);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        if (btn) { btn.disabled = true; }
        fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    window.location.reload();
                } else {
                    if (btn) { btn.disabled = false; }
                    window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : ''));
                }
            })
            .catch(function () {
                if (btn) { btn.disabled = false; }
                window.alert(t('savefailed', 'Could not save'));
            });
    }

    // Submit a panel: FormData with action + sesskey + fields + an optional staged
    // image file, in ONE request. Reloads on success.
    function submitPanel(action, fields, file, btn) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('sesskey', CFG.sesskey);
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
        if (file) {
            fd.append('image', file);
        }
        if (btn) { btn.disabled = true; }
        fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    window.location.reload();
                } else {
                    if (btn) { btn.disabled = false; }
                    window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : ''));
                }
            })
            .catch(function () {
                if (btn) { btn.disabled = false; }
                window.alert(t('savefailed', 'Could not save'));
            });
    }

    // A "Replace image" control that STAGES the pick (preview) without saving.
    // Returns { row, file() } — file() gives the chosen File (or null) at Save time.
    function imagePicker() {
        var staged = null;
        var row = document.createElement('div');
        row.className = 'nit-img-pick';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nit-edit-btn';
        btn.textContent = '🖼 ' + t('replaceimage', 'Replace image');
        var thumb = document.createElement('div');
        thumb.className = 'nit-img-thumb';
        thumb.hidden = true;
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
        input.style.display = 'none';
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { return; }
            if (CFG.maxImageBytes && f.size > CFG.maxImageBytes) {
                window.alert(t('imagetoolarge', 'Image is too large.'));
                input.value = '';
                return;
            }
            staged = f;
            btn.textContent = '✓ ' + (f.name || t('replaceimage', 'Replace image'));
            thumb.hidden = false;
            thumb.style.backgroundImage = "url('" + URL.createObjectURL(f) + "')";
        });
        btn.addEventListener('click', function () { input.click(); });
        row.appendChild(btn);
        row.appendChild(thumb);
        row.appendChild(input);
        return { row: row, file: function () { return staged; } };
    }

    // Hero size presets — keep the image looking good at any upload dimensions
    // (background is center/cover, so the band's aspect + height is the knob).
    var HERO_SIZES = [
        { key: 'banner', label: 'Banner', aspect: '16/4', minheight: 220 },
        { key: 'standard', label: 'Standard', aspect: '16/6', minheight: 340 },
        { key: 'tall', label: 'Tall', aspect: '16/8', minheight: 420 }
    ];
    function heroPanel(marker) {
        var body = document.createElement('div');

        var pick = imagePicker();
        body.appendChild(pick.row);

        var hRow = document.createElement('div');
        hRow.className = 'nit-edit-row';
        var lbl = document.createElement('label');
        lbl.textContent = t('heroheight', 'Height');
        var sel = document.createElement('select');
        HERO_SIZES.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.key;
            o.textContent = s.label;
            sel.appendChild(o);
        });
        sel.value = 'standard';
        hRow.appendChild(lbl);
        hRow.appendChild(sel);
        body.appendChild(hRow);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            var s = HERO_SIZES.filter(function (x) { return x.key === sel.value; })[0] || HERO_SIZES[1];
            submitPanel('hero', { aspect: s.aspect, minheight: String(s.minheight) }, pick.file(), save);
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // About panel: replace the photo + edit the bullet points (seeded from the
    // live DOM, so no extra fetch). Points save as a JSON array to about_points.
    // A text input, optionally tagged EN/AR (bilingual academies get one per
    // language). Returns { wrap, input }.
    function langInput(val, tag) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;align-items:center;gap:6px;flex:1';
        if (tag) {
            var badge = document.createElement('span');
            badge.textContent = tag;
            badge.style.cssText = 'font:700 10px system-ui;color:#888;min-width:22px;text-align:center';
            wrap.appendChild(badge);
        }
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.maxLength = 200;
        inp.value = val || '';
        inp.style.cssText = 'flex:1;padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit';
        if (tag === 'AR') { inp.dir = 'rtl'; }
        wrap.appendChild(inp);
        return { wrap: wrap, input: inp };
    }

    function aboutPanel(sec) {
        var body = document.createElement('div');
        var bi = !!CFG.bilingual;                       // both EN + AR installed?
        var data = CFG.about || { subheader: { en: '', ar: '' }, bullets: [] };
        var sub = data.subheader || { en: '', ar: '' };

        var pick = imagePicker();
        body.appendChild(pick.row);

        // ── Subheader (the <h3> under "About") ──────────────────────────────
        var subLbl = document.createElement('label');
        subLbl.textContent = t('aboutsubheader', 'Subheader');
        subLbl.style.cssText = 'font-weight:600;display:block;margin-bottom:6px';
        body.appendChild(subLbl);
        var subWrap = document.createElement('div');
        subWrap.style.cssText = 'display:flex;flex-direction:column;gap:6px;margin-bottom:16px';
        var subEn, subAr = null;
        if (bi) {
            var se = langInput(sub.en, 'EN'), sa = langInput(sub.ar, 'AR');
            subEn = se.input; subAr = sa.input;
            subWrap.appendChild(se.wrap); subWrap.appendChild(sa.wrap);
        } else {
            var s1 = langInput(sub.en || sub.ar);
            subEn = s1.input;
            subWrap.appendChild(s1.wrap);
        }
        body.appendChild(subWrap);

        // ── Bullet points ───────────────────────────────────────────────────
        var lbl = document.createElement('label');
        lbl.textContent = t('aboutpoints', 'Points');
        lbl.style.cssText = 'font-weight:600;display:block;margin-bottom:6px';
        body.appendChild(lbl);
        var list = document.createElement('div');
        list.style.margin = '6px 0 10px';

        function addRow(pair) {
            if (list.querySelectorAll('.nit-point-row').length >= 8) {
                return;
            }
            pair = pair || { en: '', ar: '' };
            var row = document.createElement('div');
            row.className = 'nit-point-row';
            row.style.cssText = 'display:flex;gap:6px;margin-bottom:8px;align-items:flex-start';
            var col = document.createElement('div');
            col.style.cssText = 'display:flex;flex-direction:column;gap:4px;flex:1';
            if (bi) {
                var e = langInput(pair.en, 'EN'), a = langInput(pair.ar, 'AR');
                row._en = e.input; row._ar = a.input;
                col.appendChild(e.wrap); col.appendChild(a.wrap);
            } else {
                var o = langInput(pair.en || pair.ar);
                row._en = o.input; row._ar = null;
                col.appendChild(o.wrap);
            }
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'nit-edit-btn sec';
            del.textContent = '✕';
            del.addEventListener('click', function () { row.remove(); });
            row.appendChild(col);
            row.appendChild(del);
            list.appendChild(row);
        }

        (data.bullets || []).forEach(function (b) { addRow(b); });
        if (!list.querySelector('.nit-point-row')) { addRow(null); }
        body.appendChild(list);

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-edit-btn sec';
        addBtn.textContent = '+ ' + t('addpoint', 'Add point');
        addBtn.style.marginBottom = '14px';
        addBtn.addEventListener('click', function () { addRow(null); });
        body.appendChild(addBtn);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            var subheader = { en: (subEn.value || '').trim(), ar: subAr ? (subAr.value || '').trim() : '' };
            var bullets = [];
            Array.prototype.slice.call(list.querySelectorAll('.nit-point-row')).forEach(function (row) {
                var en = (row._en.value || '').trim();
                var ar = row._ar ? (row._ar.value || '').trim() : '';
                if (en !== '' || ar !== '') { bullets.push({ en: en, ar: ar }); }
            });
            submitPanel('about', {
                subheader: JSON.stringify(subheader),
                bullets: JSON.stringify(bullets)
            }, pick.file(), save);
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // Gallery panel: thumbnails of current tiles (each with a delete) + Add image.
    // Add/delete apply immediately (reload), so the only footer button is Close.
    function galleryPanel(sec) {
        var body = document.createElement('div');
        var grid = sec.querySelector('[data-nit-gallery-grid]');

        // Working list — existing tiles (by original index) + staged new files.
        // Nothing is saved until Save; add / delete / drag-reorder all just edit it.
        var items = [];
        if (grid) {
            Array.prototype.slice.call(grid.children).forEach(function (tile, i) {
                items.push({ kind: 'existing', origIndex: i, bg: getComputedStyle(tile).backgroundImage });
            });
        }

        var hint = document.createElement('p');
        hint.style.cssText = 'font-size:12px;color:#666;margin:0 0 10px';
        hint.textContent = t('gallerydraghint', 'Drag to reorder. Changes apply when you press Save.');
        body.appendChild(hint);

        var thumbs = document.createElement('div');
        thumbs.style.cssText = 'display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px';
        body.appendChild(thumbs);

        var MAX_GALLERY = 10;
        var addBtn; // created below; render() keeps its label + disabled state in sync.
        function updateAddBtn() {
            if (!addBtn) { return; }
            var full = items.length >= MAX_GALLERY;
            addBtn.disabled = full;
            addBtn.textContent = full
                ? t('gallerymax', 'Maximum {n} images.').replace('{n}', MAX_GALLERY)
                : '➕ ' + t('addimage', 'Add image') + ' (' + items.length + '/' + MAX_GALLERY + ')';
        }

        var dragFrom = null;
        function render() {
            thumbs.innerHTML = '';
            if (!items.length) {
                var empty = document.createElement('p');
                empty.style.cssText = 'color:#888;grid-column:1/-1;margin:0';
                empty.textContent = t('galleryempty', 'No images yet.');
                thumbs.appendChild(empty);
                return;
            }
            items.forEach(function (it, idx) {
                var cell = document.createElement('div');
                cell.draggable = true;
                cell.style.cssText = 'position:relative;aspect-ratio:4/3;border-radius:8px;border:1px solid #ddd;'
                    + 'background-size:cover;background-position:center;cursor:grab';
                cell.style.backgroundImage = it.kind === 'existing' ? it.bg : "url('" + it.url + "')";
                if (it.kind === 'new') {
                    var badge = document.createElement('span');
                    badge.textContent = t('new', 'new');
                    badge.style.cssText = 'position:absolute;bottom:4px;inset-inline-start:4px;background:#0B2923;color:#00FFB2;font:600 10px system-ui;padding:1px 6px;border-radius:6px';
                    cell.appendChild(badge);
                }
                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'nit-edit-btn';
                del.textContent = '✕';
                del.style.cssText = 'position:absolute;top:4px;inset-inline-end:4px;padding:2px 9px';
                del.addEventListener('click', function () { items.splice(idx, 1); render(); });
                cell.appendChild(del);
                cell.addEventListener('dragstart', function () { dragFrom = idx; });
                cell.addEventListener('dragover', function (e) { e.preventDefault(); });
                cell.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (dragFrom === null || dragFrom === idx) { return; }
                    var moved = items.splice(dragFrom, 1)[0];
                    items.splice(idx, 0, moved);
                    dragFrom = null;
                    render();
                });
                thumbs.appendChild(cell);
            });
            updateAddBtn();
        }
        render();

        addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-edit-btn';
        addBtn.addEventListener('click', function () {
            if (items.length >= MAX_GALLERY) { return; }
            var input = document.createElement('input');
            input.type = 'file';
            input.multiple = true; // pick several images at once
            input.accept = 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
            input.addEventListener('change', function () {
                var files = input.files ? Array.prototype.slice.call(input.files) : [];
                var toobig = 0, overflow = 0;
                files.forEach(function (f) {
                    if (items.length >= MAX_GALLERY) { overflow++; return; }
                    if (CFG.maxImageBytes && f.size > CFG.maxImageBytes) { toobig++; return; }
                    items.push({ kind: 'new', file: f, url: URL.createObjectURL(f) });
                });
                render();
                if (toobig) { window.alert(t('imagetoolarge', 'Image is too large.')); }
                if (overflow) { window.alert(t('gallerymax', 'Maximum {n} images.').replace('{n}', MAX_GALLERY)); }
            });
            input.click();
        });
        updateAddBtn();
        body.appendChild(addBtn);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'gallery');
            fd.append('sesskey', CFG.sesskey);
            var order = [];
            var fileIdx = 0;
            items.forEach(function (it) {
                if (it.kind === 'existing') {
                    order.push('e:' + it.origIndex);
                } else {
                    order.push('n:' + fileIdx);
                    fd.append('image' + fileIdx, it.file);
                    fileIdx++;
                }
            });
            fd.append('order', JSON.stringify(order));
            save.disabled = true;
            fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) { window.location.reload(); }
                    else { save.disabled = false; window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : '')); }
                })
                .catch(function () { save.disabled = false; window.alert(t('savefailed', 'Could not save')); });
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // Contact panel: text fields for phone/whatsapp + social URLs, seeded from
    // the stored config (passed in NIT_EDIT.contact). Saves to the contact action.
    var CONTACT_FIELDS = ['phone', 'whatsapp', 'facebook', 'instagram', 'youtube', 'tiktok', 'website'];
    function contactPanel() {
        var c = CFG.contact || {};
        var body = document.createElement('div');
        var inputs = {};
        CONTACT_FIELDS.forEach(function (key) {
            var row = document.createElement('div');
            row.className = 'nit-edit-row';
            var lbl = document.createElement('label');
            lbl.textContent = t('c_' + key, key);
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.value = c[key] || '';
            inp.style.cssText = 'padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit';
            row.appendChild(lbl);
            row.appendChild(inp);
            body.appendChild(row);
            inputs[key] = inp;
        });
        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            var fields = { action: 'contact' };
            CONTACT_FIELDS.forEach(function (key) { fields[key] = inputs[key].value.trim(); });
            postFields(fields, save);
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // Footer panel: academy name + description + show-logo. Bilingual academies
    // get an EN + AR field for the name AND description (stored as {mlang} so the
    // footer renders in the visitor's language — US #5); single-language academies
    // get one field each. Seeded from the RAW block text (NIT_EDIT.footer, with
    // {mlang} intact) so both languages prefill; falls back to the rendered DOM.
    function footerPanel(sec) {
        var body = document.createElement('div');
        var head = sec.querySelector('div[style*="font-weight:800"]');
        var para = sec.querySelector('p');
        var hasLogo = !!sec.querySelector('img');
        var bi = !!CFG.bilingual;
        var f = CFG.footer || {};
        var namePart = f.name || { en: head ? head.textContent.trim() : '', ar: '' };
        var descPart = f.desc || { en: para ? para.textContent.trim() : '', ar: '' };

        // A labelled field that is bilingual (EN+AR) or single, returning value().
        function field(labelKey, labelFallback, part) {
            var lbl = document.createElement('label');
            lbl.textContent = t(labelKey, labelFallback);
            lbl.style.cssText = 'font-weight:600;font-size:13px;display:block;margin-bottom:6px';
            body.appendChild(lbl);
            if (bi) {
                var e = langInput(part.en, 'EN'), a = langInput(part.ar, 'AR');
                e.wrap.style.marginBottom = '6px';
                a.wrap.style.marginBottom = '14px';
                body.appendChild(e.wrap);
                body.appendChild(a.wrap);
                return function () { return mlangBuild(e.input.value, a.input.value); };
            }
            var s = langInput(part.en || part.ar || '');
            s.wrap.style.marginBottom = '14px';
            body.appendChild(s.wrap);
            return function () { return s.input.value.trim(); };
        }
        var nameVal = field('footername', 'Academy name', namePart);
        var descVal = field('footerdesc', 'Description', descPart);

        var logoRow = document.createElement('label');
        logoRow.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:14px;font-weight:600';
        var logoChk = document.createElement('input');
        logoChk.type = 'checkbox';
        logoChk.checked = hasLogo;
        logoRow.appendChild(logoChk);
        logoRow.appendChild(document.createTextNode(t('footershowlogo', 'Show logo')));
        body.appendChild(logoRow);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            postFields({
                action: 'footer',
                name: nameVal(),
                desc: descVal(),
                showlogo: logoChk.checked ? '1' : '0'
            }, save);
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // Download-apps panel: override the Google Play / App Store links shown in the
    // download band. Empty = use the auto per-academy Play link + the platform iOS
    // URL. Saves to the 'apps' action.
    function appsPanel() {
        var a = CFG.apps || {};
        var body = document.createElement('div');
        var hint = document.createElement('p');
        hint.style.cssText = 'font-size:12px;color:#666;margin:0 0 12px';
        hint.textContent = t('appshint', 'Leave empty to use the default NIT Academy app link for this academy.');
        body.appendChild(hint);
        function urlRow(labelKey, labelFallback, value) {
            var row = document.createElement('div');
            row.className = 'nit-edit-row';
            var lbl = document.createElement('label');
            lbl.textContent = t(labelKey, labelFallback);
            var inp = document.createElement('input');
            inp.type = 'url';
            inp.value = value || '';
            inp.placeholder = 'https://…';
            inp.style.cssText = 'padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit';
            inp.dir = 'ltr';
            row.appendChild(lbl);
            row.appendChild(inp);
            body.appendChild(row);
            return inp;
        }
        var androidInp = urlRow('appsandroid', 'Google Play URL', a.android);
        var iosInp = urlRow('appsios', 'App Store URL', a.ios);
        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            postFields({
                action: 'apps',
                android: androidInp.value.trim(),
                ios: iosInp.value.trim()
            }, save);
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // Palette panel: the 6 brand pickers (rest are derived server-side). Seeded
    // from NIT_EDIT.palette; saving recompiles the theme CSS.
    var PALETTE_FIELDS = ['primary', 'accent', 'secondary', 'background', 'surface', 'text'];
    var PALETTE_DEFAULTS = {
        primary: '#5488c4', accent: '#5488c4', secondary: '#1c2a3a',
        background: '#0c141f', surface: '#121e2d', text: '#eef3f9'
    };
    // Ready-made palettes — identical to the create-academy form (5 dark + 5 light).
    var PALETTE_PRESETS = [
        { name: 'Slate', p: PALETTE_DEFAULTS },
        { name: 'Teal', p: { primary: '#2f9e8f', accent: '#3fb8a6', secondary: '#10221f', background: '#0a1a17', surface: '#102a25', text: '#eafaf6' } },
        { name: 'Indigo', p: { primary: '#7c6cd6', accent: '#9b8cf0', secondary: '#1a1730', background: '#0d0b1a', surface: '#171334', text: '#eeeaff' } },
        { name: 'Ruby', p: { primary: '#c2456b', accent: '#e06a8c', secondary: '#2a1420', background: '#170a10', surface: '#241019', text: '#fdeef3' } },
        { name: 'Amber', p: { primary: '#d4933a', accent: '#e8b45c', secondary: '#2a2012', background: '#17120a', surface: '#241c10', text: '#fdf5e8' } },
        { name: 'Royal', p: { primary: '#00126c', accent: '#c9a227', secondary: '#eaeef9', background: '#ffffff', surface: '#f3f5fb', text: '#0b1230' } },
        { name: 'Teal Gold', p: { primary: '#0e504d', accent: '#c7ae72', secondary: '#eaf3f1', background: '#ffffff', surface: '#f2f8f6', text: '#14201f' } },
        { name: 'Emerald', p: { primary: '#167b44', accent: '#1f9e57', secondary: '#eaf5ee', background: '#ffffff', surface: '#f2f9f4', text: '#12241a' } },
        { name: 'Brick', p: { primary: '#92251e', accent: '#b23a2e', secondary: '#fbeeec', background: '#ffffff', surface: '#fbf4f3', text: '#183041' } },
        { name: 'Navy', p: { primary: '#003362', accent: '#1f6fb2', secondary: '#e9eef4', background: '#ffffff', surface: '#f2f6fa', text: '#10233a' } }
    ];
    // Branding panel — academy name, logo, and login-page background in one place.
    function brandingPanel() {
        var body = document.createElement('div');
        var label = function (text, mb) {
            var l = document.createElement('label');
            l.textContent = text;
            l.style.cssText = 'display:block;font-weight:600;margin-bottom:' + (mb || 6) + 'px';
            return l;
        };
        var divider = function () {
            var d = document.createElement('div');
            d.style.cssText = 'height:1px;background:#eee;margin:16px 0';
            return d;
        };
        var pillBtn = function (text) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = text;
            b.style.cssText = 'padding:8px 16px;border:1px solid #ccc;border-radius:8px;background:#fff;color:#0B2923;font-weight:600;cursor:pointer';
            return b;
        };

        // Academy name + Save. Bilingual academies get an EN + AR field (the name
        // is stored as a {mlang} string so the navbar / login / footer each show it
        // in the visitor's language); single-language academies get one field.
        body.appendChild(label(t('academyname', 'Academy name')));
        var bi = !!CFG.bilingual;
        var parts = CFG.sitenameParts || { en: CFG.sitename || '', ar: CFG.sitename || '' };
        var nameEn, nameAr = null;
        if (bi) {
            var ne = langInput(parts.en, 'EN'), na = langInput(parts.ar, 'AR');
            nameEn = ne.input; nameAr = na.input;
            ne.wrap.style.marginBottom = '6px';
            body.appendChild(ne.wrap);
            body.appendChild(na.wrap);
        } else {
            var single = langInput(parts.en || CFG.sitename || '');
            nameEn = single.input;
            body.appendChild(single.wrap);
        }
        var nameBtn = document.createElement('button');
        nameBtn.type = 'button';
        nameBtn.textContent = t('save', 'Save');
        nameBtn.style.cssText = 'margin-top:8px;padding:8px 18px;border:0;border-radius:8px;background:#0B2923;color:#00FFB2;font-weight:700;cursor:pointer';
        nameBtn.addEventListener('click', function () {
            var v = bi ? mlangBuild(nameEn.value, nameAr.value) : nameEn.value.trim();
            if (v === '') { return; }
            submitPanel('sitename', { name: v }, null, nameBtn);
        });
        body.appendChild(nameBtn);

        // Logo
        body.appendChild(divider());
        body.appendChild(label(t('editlogo', 'Edit logo')));
        var logoBtn = pillBtn('🖼 ' + t('replacelogo', 'Replace logo'));
        logoBtn.addEventListener('click', function () { uploadImage('logo', {}, logoBtn); });
        body.appendChild(logoBtn);

        // Favicon (browser-tab icon). Small square image / .ico.
        body.appendChild(divider());
        body.appendChild(label(t('favicon', 'Favicon'), 4));
        var favHint = document.createElement('p');
        favHint.textContent = t('faviconhint', 'The small icon shown in the browser tab. A square PNG or .ico works best.');
        favHint.style.cssText = 'margin:0 0 8px;font-size:12px;color:#666';
        body.appendChild(favHint);
        var favBtn = pillBtn('🖼 ' + t('replacefavicon', 'Replace favicon'));
        favBtn.addEventListener('click', function () { uploadImage('favicon', {}, favBtn); });
        body.appendChild(favBtn);

        return body;
    }

    function palettePanel() {
        var pal = CFG.palette || {};
        var body = document.createElement('div');
        var inputs = {};
        var saved = false;

        var current = function () {
            var c = {};
            PALETTE_FIELDS.forEach(function (k) { c[k] = inputs[k].value; });
            return c;
        };
        var preview = function () { applyPalettePreview(current()); };
        // Closing the panel by any means reverts the preview unless we saved.
        panelCleanup = function () { if (!saved) { clearPalettePreview(); } };

        // Preset swatches (one click fills all six pickers) — same as create form.
        var presets = document.createElement('div');
        presets.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px';
        PALETTE_PRESETS.forEach(function (ps) {
            var b = document.createElement('button');
            b.type = 'button';
            b.title = ps.name;
            b.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border:1px solid #ddd;'
                + 'border-radius:999px;background:#fff;color:#0B2923;cursor:pointer;font:600 12px system-ui';
            var sw = document.createElement('span');
            sw.style.cssText = 'width:14px;height:14px;border-radius:50%;border:1px solid rgba(0,0,0,.15);'
                + 'background:linear-gradient(135deg,' + ps.p.primary + ' 50%,' + ps.p.background + ' 50%)';
            b.appendChild(sw);
            b.appendChild(document.createTextNode(ps.name));
            b.addEventListener('click', function () {
                PALETTE_FIELDS.forEach(function (k) { inputs[k].value = ps.p[k]; });
                preview();
            });
            presets.appendChild(b);
        });
        body.appendChild(presets);

        PALETTE_FIELDS.forEach(function (key) {
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px';
            var lbl = document.createElement('label');
            lbl.textContent = t('col_' + key, key);
            lbl.style.fontWeight = '600';
            var inp = document.createElement('input');
            inp.type = 'color';
            var v = (pal[key] || '').trim();
            inp.value = /^#[0-9A-Fa-f]{6}$/.test(v) ? v : PALETTE_DEFAULTS[key];
            inp.style.cssText = 'width:54px;height:34px;border:1px solid #ccc;border-radius:8px;background:none;cursor:pointer;padding:2px';
            inp.addEventListener('input', preview);
            row.appendChild(lbl);
            row.appendChild(inp);
            body.appendChild(row);
            inputs[key] = inp;
        });
        // Text ON buttons (labels on the primary fill): automatic contrast, or pinned.
        var onRow = document.createElement('div');
        onRow.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px';
        var onLbl = document.createElement('label'); onLbl.style.fontWeight = '600';
        onLbl.textContent = t('col_onprimary', 'Text on buttons');
        var onWrap = document.createElement('div'); onWrap.style.cssText = 'display:flex;align-items:center;gap:8px';
        var onAuto = document.createElement('input'); onAuto.type = 'checkbox'; onAuto.id = 'nit-onprimary-auto';
        var onAutoL = document.createElement('label'); onAutoL.htmlFor = onAuto.id; onAutoL.textContent = t('auto', 'Auto'); onAutoL.style.cssText = 'font-size:12px;margin:0';
        var onInp = document.createElement('input'); onInp.type = 'color';
        onInp.style.cssText = 'width:54px;height:34px;border:1px solid #ccc;border-radius:8px;background:none;cursor:pointer;padding:2px';
        var pinned = /^#[0-9A-Fa-f]{6}$/.test((pal.onprimary || '').trim());
        onAuto.checked = !pinned; onInp.value = pinned ? pal.onprimary.trim() : '#ffffff'; onInp.disabled = !pinned;
        var onPreview = function () {
            document.documentElement.style.setProperty('--nit-brand-on-primary', onAuto.checked ? '' : onInp.value);
        };
        onAuto.addEventListener('change', function () { onInp.disabled = onAuto.checked; onPreview(); });
        onInp.addEventListener('input', onPreview);
        onWrap.appendChild(onAuto); onWrap.appendChild(onAutoL); onWrap.appendChild(onInp);
        onRow.appendChild(onLbl); onRow.appendChild(onWrap);
        body.appendChild(onRow);
        var note = document.createElement('p');
        note.style.cssText = 'font-size:12px;color:#666;margin:2px 0 12px';
        note.textContent = t('palettenote', 'Text, borders and hover shades are derived automatically.');
        body.appendChild(note);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'nit-edit-btn sec';
        cancel.textContent = t('cancel', 'Cancel');
        cancel.addEventListener('click', closePanel);
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'nit-edit-btn';
        save.textContent = t('save', 'Save');
        save.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'palette');
            fd.append('sesskey', CFG.sesskey);
            PALETTE_FIELDS.forEach(function (k) { fd.append(k, inputs[k].value); });
            fd.append('onprimary', onAuto.checked ? '' : onInp.value);
            var label = save.textContent;
            save.disabled = true;
            save.textContent = t('saving', 'Saving…');
            fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        // Persisted. The live preview already shows the final palette —
                        // every front-page surface (body, region-main, cards, buttons,
                        // text) follows the --nit-brand-* custom properties — so just
                        // keep it and close: no page reload, no flash. The server
                        // recompiles the theme CSS in the background (returned before
                        // the compile) for later loads and non-editing visitors.
                        saved = true;
                        var chosen = current();
                        if (!CFG.palette) { CFG.palette = {}; }
                        PALETTE_FIELDS.forEach(function (k) { CFG.palette[k] = chosen[k]; });
                        closePanel();
                    } else {
                        save.disabled = false;
                        save.textContent = label;
                        window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : ''));
                    }
                })
                .catch(function () {
                    save.disabled = false;
                    save.textContent = label;
                    window.alert(t('savefailed', 'Could not save'));
                });
        });
        act.appendChild(cancel);
        act.appendChild(save);
        body.appendChild(act);
        return body;
    }

    // ── pencils ───────────────────────────────────────────────────────────────
    function attachPencil(el, label, onClick) {
        if (el.querySelector(':scope > .nit-edit-pencil')) {
            return;
        }
        if (getComputedStyle(el).position === 'static') {
            el.style.position = 'relative';
        }
        var pencil = document.createElement('button');
        pencil.type = 'button';
        pencil.className = 'nit-edit-pencil';
        pencil.textContent = '✏ ' + label;
        pencil.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            onClick(pencil);
        });
        el.appendChild(pencil);
    }

    function addPencils() {
        // Front-page regions. Hero opens a panel (image + height); the rest do a
        // direct background-image replace for now (their own panels land later).
        // Every section pencil selects that section in the design panel, which
        // lists its real text / image / link hooks (Advanced… keeps the structural
        // editors reachable). A section with no hooks offers a background swap.
        document.querySelectorAll('[data-nit-section]').forEach(function (sec) {
            var marker = sec.getAttribute('data-nit-section');
            attachPencil(sec, '✏ ' + secLabel(marker), function () {
                if (!sidePanel) { buildSidePanel(); }
                selectSection(marker);
            });
        });
    }

    function removePencils() {
        document.querySelectorAll('.nit-edit-pencil').forEach(function (p) { p.remove(); });
    }

    // Add-course button INSIDE the courses grid (replaces core's floating one,
    // which the theme hides on the Site home). Edit-mode only.
    function addCourseButton() {
        if (!CFG.addCourseUrl) {
            return;
        }
        var grid = document.querySelector('[data-nit-courses]');
        if (!grid || grid.querySelector(':scope > .nit-add-course')) {
            return;
        }
        var a = document.createElement('a');
        a.className = 'nit-add-course';
        a.href = CFG.addCourseUrl;
        a.textContent = '➕ ' + t('addcourse', 'Add course');
        grid.insertBefore(a, grid.firstChild);
    }
    function removeCourseButton() {
        document.querySelectorAll('.nit-add-course').forEach(function (e) { e.remove(); });
    }

    // ── Design panel: edits the REAL homepage content ─────────────────────────
    // ── The design's right-hand editor panel ─────────────────────────────────
    // Every edit made anywhere in the panel goes into ONE draft and previews live
    // on the page; nothing is stored until "Publish" (which writes the whole
    // draft: content hooks through edit.php?action=content → homepage_content::
    // apply(), then cards / picks / nav / footer / auth) — "Discard" reloads.
    // Switching sections keeps the draft. PAGE SECTIONS (reorder, show/hide,
    // reset, add) act immediately: they are structure, not content.
    var sidePanel = null;
    var panelContent = null;
    var publishBar = null;
    var leaving = false;
    var draft = { text: {}, href: {}, images: {}, imageUrls: {}, cards: null, picks: {}, nav: null, footer: null, auth: null };
    function draftCount() {
        var n = Object.keys(draft.text).length + Object.keys(draft.href).length + Object.keys(draft.images).length
            + Object.keys(draft.picks).length + (draft.cards ? 1 : 0) + (draft.nav ? 1 : 0) + (draft.footer ? 1 : 0) + (draft.auth ? 1 : 0);
        return n;
    }
    var SECTION_LABELS = {
        hero: ['Hero', 'الغلاف'], categories: ['Categories', 'التصنيفات'],
        courses: ['Courses', 'الدورات'], about: ['About', 'من نحن'],
        subscriptions: ['Subscriptions', 'الاشتراكات'], coupons: ['Coupons', 'الكوبونات'],
        gallery: ['Gallery', 'المعرض'], testimonials: ['Testimonials', 'آراء المتعلمين'], faq: ['FAQ', 'الأسئلة الشائعة'],
        contact: ['Contact', 'تواصل'], footer: ['Footer', 'التذييل'],
        appband: ['App band', 'التطبيق'], brand: ['Brand & navbar', 'الهوية والقائمة'], auth: ['Auth pages', 'صفحات الدخول']
    };
    var SECTION_SIG = {
        categories: '[data-nit-categories]', courses: '[data-nit-courses],[data-nit-my-courses]',
        subscriptions: '[data-nit-subs]', coupons: '[data-nit-coupons]', testimonials: '[data-nit-testimonials]'
    };
    var PICK_SECTIONS = ['courses', 'categories', 'subscriptions', 'coupons', 'testimonials'];
    var NAV_MIN = 3, NAV_MAX = 5;
    var isAr = (document.documentElement.getAttribute('lang') || '').indexOf('ar') === 0;
    function secLabel(marker) {
        var l = SECTION_LABELS[marker];
        return l ? (isAr ? l[1] : l[0]) : marker;
    }
    function sectionRoot(marker) {
        var r = document.querySelector('[data-nit-section="' + marker + '"]');
        if (r) { return r; }
        var sel = SECTION_SIG[marker];
        if (!sel) { return null; }
        var el = document.querySelector(sel);
        if (!el) { return null; }
        return el.closest('[data-nit-section]') || el.closest('.block_nit_section, [data-block]') || el;
    }
    function schemaFor(key) {
        var fields = CFG.contentFields || [];
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].key === key) { return fields[i]; }
        }
        return null;
    }
    function fieldLabel(key) {
        var f = schemaFor(key);
        return f ? f.label : key.replace(/_/g, ' ');
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function plainOf(html) {
        var d = document.createElement('div');
        d.innerHTML = String(html || '').replace(/<br\s*\/?>/gi, '\n');
        return (d.textContent || '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }
    // The hero title carries an accent: "Learn a skill<br><span …>stays with you</span>".
    var ACCENT_SPAN = '<span style="font-weight: 600; color: var(--t-accent);">';
    function splitAccent(html) {
        var s = String(html || '');
        var m = s.match(/^([\s\S]*?)<span[^>]*>([\s\S]*?)<\/span>\s*$/i);
        if (m) { return { main: plainOf(m[1]).replace(/[\s\n]+$/, ''), accent: plainOf(m[2]) }; }
        return { main: plainOf(s), accent: '' };
    }
    function joinAccent(main, accent) {
        var out = escapeHtml(main).replace(/\n/g, '<br>');
        if (accent.trim()) { out += (main.trim() ? ' ' : '') + ACCENT_SPAN + escapeHtml(accent.trim()) + '</span>'; }
        return out;
    }
    function storedText(key, el) {
        if (draft.text[key]) { return draft.text[key]; }
        var v = (CFG.contentValues || {})[key];
        if (v && typeof v === 'object') { return { en: v.en || '', ar: v.ar || '' }; }
        return { en: el ? el.innerHTML : '', ar: '' };
    }
    function mkInput(multiline, value, placeholder) {
        var i = document.createElement(multiline ? 'textarea' : 'input');
        if (!multiline) { i.type = 'text'; }
        i.className = 'nit-side-input';
        i.value = value || '';
        if (placeholder) { i.placeholder = placeholder; }
        return i;
    }
    function fieldRow(label) {
        var row = document.createElement('div');
        row.className = 'nit-side-field';
        var lab = document.createElement('label');
        lab.textContent = label;
        row.appendChild(lab);
        return row;
    }
    function touch() {
        document.body.classList.add('nit-dirty');
        updatePublishBar();
    }
    function post(fd) {
        fd.append('sesskey', CFG.sesskey);
        return fetch(CFG.editUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function previewHtml(key, html) {
        document.querySelectorAll('[data-nit-edit="' + key + '"]').forEach(function (el) { el.innerHTML = html; });
    }
    function previewImage(el, url) {
        el.style.backgroundImage = 'url(' + url + ')';
        el.style.backgroundSize = 'cover';
        el.style.backgroundPosition = 'center';
        [].slice.call(el.children).forEach(function (c) {
            if (!c.querySelector('[data-nit-edit],[data-nit-edit-img],[data-nit-stat]')) { c.style.visibility = 'hidden'; }
        });
    }

    // ── Publish / Discard bar (top of the panel) ─────────────────────────────
    function updatePublishBar() {
        if (!publishBar) { return; }
        var n = draftCount();
        var btn = publishBar.querySelector('.nit-publish');
        btn.disabled = n === 0;
        btn.textContent = t('publish', 'Publish') + (n ? ' (' + n + ')' : '');
        publishBar.querySelector('.nit-side-hint').textContent = n
            ? t('unpublished', 'Changes preview on the page only. Publish to make them live, or Discard.')
            : t('nochanges', 'No unpublished changes.');
        // Navbar pick must be 0 or NAV_MIN..NAV_MAX.
        var nav = draft.nav;
        var navOk = !nav || nav.length === 0 || (nav.length >= NAV_MIN && nav.length <= NAV_MAX);
        if (!navOk) { btn.disabled = true; }
    }
    function publishAll() {
        var steps = [];
        if (Object.keys(draft.text).length || Object.keys(draft.href).length || Object.keys(draft.images).length) {
            steps.push(function () {
                var fd = new FormData();
                fd.append('action', 'content');
                var text = {};
                Object.keys(draft.text).forEach(function (k) { text[k] = CFG.bilingual ? draft.text[k] : draft.text[k].en; });
                fd.append('text', JSON.stringify(text));
                fd.append('href', JSON.stringify(draft.href));
                Object.keys(draft.images).forEach(function (k) { fd.append('image_' + k, draft.images[k]); });
                return post(fd);
            });
        }
        if (draft.cards) {
            steps.push(function () { var fd = new FormData(); fd.append('action', 'about_cards'); fd.append('cards', JSON.stringify(draft.cards)); return post(fd); });
        }
        Object.keys(draft.picks).forEach(function (section) {
            steps.push(function () { var fd = new FormData(); fd.append('action', 'picks'); fd.append('section', section); fd.append('ids', JSON.stringify(draft.picks[section])); return post(fd); });
        });
        if (draft.nav) {
            steps.push(function () { var fd = new FormData(); fd.append('action', 'nav_pages'); fd.append('keys', JSON.stringify(draft.nav)); return post(fd); });
        }
        if (draft.footer) {
            steps.push(function () { var fd = new FormData(); fd.append('action', 'footer_links'); fd.append('keys', JSON.stringify(draft.footer)); return post(fd); });
        }
        if (draft.auth) {
            steps.push(function () {
                var fd = new FormData(); fd.append('action', 'auth');
                fd.append('welcome', JSON.stringify(draft.auth.welcome)); fd.append('tagline', JSON.stringify(draft.auth.tagline));
                fd.append('signup_welcome', JSON.stringify(draft.auth.signup_welcome || { en: '', ar: '' }));
                fd.append('signup_tagline', JSON.stringify(draft.auth.signup_tagline || { en: '', ar: '' }));
                if (draft.auth.image) { fd.append('image', draft.auth.image); }
                return post(fd);
            });
        }
        var chain = Promise.resolve({ ok: true });
        steps.forEach(function (fn) { chain = chain.then(function (d) { return (d && d.ok) ? fn() : d; }); });
        return chain;
    }
    function buildPublishBar() {
        publishBar = document.createElement('div');
        publishBar.className = 'nit-publishbar';
        var hint = document.createElement('p'); hint.className = 'nit-side-hint'; publishBar.appendChild(hint);
        var row = document.createElement('div'); row.className = 'nit-side-actions';
        var pub = document.createElement('button'); pub.type = 'button'; pub.className = 'nit-side-btn nit-publish';
        pub.addEventListener('click', function () {
            var lbl = pub.textContent;
            pub.disabled = true; pub.textContent = t('saving', 'Saving…');
            publishAll().then(function (d) {
                if (d && d.ok) { leaving = true; document.body.classList.remove('nit-dirty'); window.location.reload(); return; }
                pub.disabled = false; pub.textContent = lbl;
                window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : ''));
            }).catch(function () { pub.disabled = false; pub.textContent = lbl; window.alert(t('savefailed', 'Could not save')); });
        });
        var dis = document.createElement('button'); dis.type = 'button'; dis.className = 'nit-side-btn sec';
        dis.textContent = t('discard', 'Discard');
        dis.addEventListener('click', function () {
            if (draftCount() && !window.confirm(t('unsaved', 'You have unsaved changes. Discard them?'))) { return; }
            leaving = true; document.body.classList.remove('nit-dirty'); window.location.reload();
        });
        row.appendChild(pub); row.appendChild(dis);
        publishBar.appendChild(row);
        updatePublishBar();
        return publishBar;
    }
    function checkList(options, checked, onChange) {
        var wrap = document.createElement('div');
        wrap.className = 'nit-side-checks';
        var boxes = [];
        options.forEach(function (o) {
            var lab = document.createElement('label');
            lab.className = 'nit-side-check';
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = String(o.id != null ? o.id : o.key);
            cb.checked = checked(cb.value);
            cb.addEventListener('change', function () { if (onChange) { onChange(); } });
            var sp = document.createElement('span'); sp.textContent = o.label;
            lab.appendChild(cb); lab.appendChild(sp);
            wrap.appendChild(lab);
            boxes.push(cb);
        });
        return { node: wrap, values: function () { return boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; }); }, boxes: boxes };
    }

    // The editor for one section root (or the brand pseudo-section).
    function contentEditorFor(marker, root) {
        var body = document.createElement('div');
        var scope = root || null;
        var textEls = [], imgEls = [], hrefEls = [];
        if (marker === 'brand') {
            textEls = [].slice.call(document.querySelectorAll('[data-nit-edit="brand_name"]'));
            imgEls  = [].slice.call(document.querySelectorAll('[data-nit-logo]')).slice(0, 1);
        } else if (scope) {
            textEls = [].slice.call(scope.querySelectorAll('[data-nit-edit]'))
                .filter(function (el) { var k = el.getAttribute('data-nit-edit'); return k !== 'logo' && k !== 'apps' && k !== 'brand_name'; });
            imgEls  = [].slice.call(scope.querySelectorAll('[data-nit-edit-img]'));
            hrefEls = [].slice.call(scope.querySelectorAll('[data-nit-edit-href]'));
        }
        var seen = {};

        textEls.forEach(function (el) {
            var key = el.getAttribute('data-nit-edit');
            if (!key || seen['t:' + key]) { return; }
            seen['t:' + key] = true;
            var sch = schemaFor(key);
            var multiline = sch ? !!sch.multiline : (el.textContent || '').length > 80;
            var cur = storedText(key, el);
            var langs = CFG.bilingual ? ['en', 'ar'] : ['en'];
            if (key === 'hero_title') {
                // Title + highlighted words (the accent-coloured part) as two fields.
                var parts = { en: splitAccent(cur.en), ar: splitAccent(cur.ar) };
                var inputs = {};
                var rowT = fieldRow(fieldLabel(key));
                var rowA = fieldRow(t('herohighlight', 'Highlighted words (accent colour)'));
                langs.forEach(function (lg) {
                    inputs[lg] = { main: mkInput(true, parts[lg].main, lg === 'ar' ? 'العربية' : (CFG.bilingual ? 'English' : '')),
                                   accent: mkInput(false, parts[lg].accent, lg === 'ar' ? 'العربية' : (CFG.bilingual ? 'English' : '')) };
                    if (lg === 'ar') { inputs[lg].main.dir = 'rtl'; inputs[lg].accent.dir = 'rtl'; }
                    rowT.appendChild(inputs[lg].main); rowA.appendChild(inputs[lg].accent);
                });
                var apply = function () {
                    var v = {};
                    langs.forEach(function (lg) { v[lg] = joinAccent(inputs[lg].main.value, inputs[lg].accent.value); });
                    if (!CFG.bilingual) { v.ar = ''; }
                    draft.text[key] = v;
                    previewHtml(key, isAr ? (v.ar || v.en) : (v.en || v.ar));
                    touch();
                };
                langs.forEach(function (lg) { inputs[lg].main.addEventListener('input', apply); inputs[lg].accent.addEventListener('input', apply); });
                body.appendChild(rowT); body.appendChild(rowA);
                return;
            }
            var row = fieldRow(fieldLabel(key));
            var en = mkInput(multiline, plainOf(cur.en), CFG.bilingual ? 'English' : '');
            row.appendChild(en);
            var ar = null;
            if (CFG.bilingual) { ar = mkInput(multiline, plainOf(cur.ar), 'العربية'); ar.dir = 'rtl'; row.appendChild(ar); }
            var onInput = function () {
                var v = { en: escapeHtml(en.value).replace(/\n/g, '<br>'), ar: ar ? escapeHtml(ar.value).replace(/\n/g, '<br>') : '' };
                draft.text[key] = v;
                previewHtml(key, isAr ? (v.ar || v.en) : (v.en || v.ar));
                touch();
            };
            en.addEventListener('input', onInput);
            if (ar) { ar.addEventListener('input', onInput); }
            body.appendChild(row);
        });

        hrefEls.forEach(function (el) {
            var key = el.getAttribute('data-nit-edit-href');
            if (!key || seen['h:' + key] || seen['t:' + key]) { return; }
            seen['h:' + key] = true;
            var row = fieldRow(fieldLabel(key));
            var cur = draft.href[key];
            if (cur == null) { cur = (CFG.contentValues || {})[key + '__href']; }
            if (cur == null) { cur = el.getAttribute('href') || ''; }
            if (cur === '#') { cur = ''; }
            var i = mkInput(false, cur, 'https://');
            i.type = 'url'; i.dir = 'ltr';
            i.addEventListener('input', function () {
                draft.href[key] = i.value.trim();
                document.querySelectorAll('[data-nit-edit-href="' + key + '"]').forEach(function (a) {
                    a.setAttribute('href', i.value.trim());
                    if (key.indexOf('social_') === 0) { a.style.display = i.value.trim() ? '' : 'none'; }
                });
                touch();
            });
            row.appendChild(i);
            body.appendChild(row);
        });

        imgEls.forEach(function (el) {
            var key = el.getAttribute('data-nit-edit-img') || 'logo';
            if (seen['i:' + key]) { return; }
            seen['i:' + key] = true;
            var row = fieldRow(fieldLabel(key));
            var pick = document.createElement('div'); pick.className = 'nit-side-img';
            var thumb = document.createElement('div'); thumb.className = 'nit-side-thumb';
            var bg = draft.imageUrls[key] ? 'url(' + draft.imageUrls[key] + ')' : (getComputedStyle(el).backgroundImage || '');
            if (bg && bg !== 'none') { thumb.style.backgroundImage = bg; }
            var file = document.createElement('input');
            file.type = 'file'; file.accept = 'image/*'; file.className = 'nit-side-file';
            file.addEventListener('change', function () {
                if (!(file.files && file.files[0])) { return; }
                if (file.files[0].size > (CFG.maxImageBytes || 1572864)) {
                    window.alert(t('toolarge', 'Image too large')); file.value = ''; return;
                }
                var url = URL.createObjectURL(file.files[0]);
                thumb.style.backgroundImage = 'url(' + url + ')';
                draft.images[key] = file.files[0]; draft.imageUrls[key] = url;
                document.querySelectorAll('[data-nit-edit-img="' + key + '"]').forEach(function (x) { previewImage(x, url); });
                if (key === 'logo') {
                    document.querySelectorAll('[data-nit-logo]').forEach(function (x) { previewImage(x, url); });
                    document.querySelectorAll('.nit-navbar-logo').forEach(function (img) { img.src = url; });
                    document.querySelectorAll('.nit-navbar-mark').forEach(function (m) { previewImage(m, url); });
                }
                touch();
            });
            pick.appendChild(thumb); pick.appendChild(file);
            row.appendChild(pick);
            body.appendChild(row);
        });

        var hasContent = !!(textEls.length || imgEls.length || hrefEls.length);
        var hasExtra = false;
        if (marker === 'about' && root) { body.appendChild(aboutCardsEditor(root)); hasExtra = true; }
        if (PICK_SECTIONS.indexOf(marker) !== -1) { body.appendChild(picksEditor(marker)); hasExtra = true; }
        if (marker === 'brand') {
            body.appendChild(pagesEditor(t('navlinks', 'Navbar links'), 'nav',
                t('navlinkshint', 'Pick 3 to 5 pages for the top navigation, in order. None = Moodle\'s default menu.'), NAV_MIN, NAV_MAX));
            hasExtra = true;
        }
        if (marker === 'footer') {
            body.appendChild(pagesEditor(t('footerlinks', 'Footer links'), 'footer',
                t('footerlinkshint', 'Pages listed in the footer link column, in order. None = the template defaults.'), 0, 8));
            hasExtra = true;
        }
        if (!hasContent && !hasExtra) {
            var none = document.createElement('p');
            none.className = 'nit-side-hint';
            none.textContent = t('noeditable', 'This section has no editable text or images; it renders live Moodle data.');
            body.appendChild(none);
        }
        if (root && (!hasContent || marker === 'hero')) {
            var bgb = document.createElement('button');
            bgb.type = 'button'; bgb.className = 'nit-side-btn sec';
            bgb.textContent = t('editimage', 'Background image');
            bgb.addEventListener('click', function () { uploadImage('section_bg_image', { section: marker }, null); });
            body.appendChild(bgb);
        }
        return body;
    }

    // About: the feature cards (title + text, max 5) — add / remove / edit.
    function aboutCardsEditor(root) {
        var wrap = document.createElement('div');
        wrap.className = 'nit-side-group';
        var hd = document.createElement('div'); hd.className = 'nit-side-subhd'; hd.textContent = t('aboutcards', 'Feature cards (max 5)');
        wrap.appendChild(hd);
        var list = document.createElement('div');
        var cards = [].slice.call(root.querySelectorAll('[data-nit-about-card]')).filter(function (c) { return c.style.display !== 'none'; });
        if (!cards.length) { return document.createDocumentFragment(); }
        var rows = [];
        var leavesOf = function (card) {
            var ls = [].slice.call(card.querySelectorAll('*')).filter(function (n) {
                return n.children.length === 0 && (n.textContent || '').trim() !== '';
            });
            if (ls.length && (ls[0].textContent || '').trim().length <= 2) { ls.shift(); }
            return ls;
        };
        var sync = function () {
            draft.cards = rows.map(function (r) {
                var title = r.title.value.trim(), text = r.text.value.trim();
                return CFG.bilingual
                    ? { title: isAr ? { en: '', ar: title } : { en: title, ar: '' }, text: isAr ? { en: '', ar: text } : { en: text, ar: '' } }
                    : { title: title, text: text };
            });
            touch();
        };
        var addBtn = document.createElement('button');
        addBtn.type = 'button'; addBtn.className = 'nit-side-btn sec';
        addBtn.textContent = '+ ' + t('addcard', 'Add a card');
        var addRow = function (title, text, cardEl) {
            if (rows.length >= 5) { return; }
            var r = document.createElement('div');
            r.className = 'nit-side-card';
            var ti = mkInput(false, title, t('cardtitle', 'Title'));
            var tx = mkInput(true, text, t('cardtext', 'Text'));
            var del = document.createElement('button'); del.type = 'button'; del.className = 'nit-side-x'; del.textContent = '×'; del.title = t('remove', 'Remove');
            var entry = { title: ti, text: tx };
            var live = function () {
                var ls = leavesOf(cardEl);
                if (ls[0]) { ls[0].textContent = ti.value; }
                if (ls[1]) { ls[1].textContent = tx.value; }
                sync();
            };
            ti.addEventListener('input', live); tx.addEventListener('input', live);
            del.addEventListener('click', function () {
                cardEl.style.display = 'none';
                rows.splice(rows.indexOf(entry), 1);
                r.remove();
                addBtn.disabled = rows.length >= 5;
                sync();
            });
            r.appendChild(ti); r.appendChild(tx); r.appendChild(del);
            list.appendChild(r);
            rows.push(entry);
            addBtn.disabled = rows.length >= 5;
        };
        addBtn.addEventListener('click', function () {
            var proto = cards[0];
            var clone = proto.cloneNode(true);
            clone.style.display = '';
            var ls = leavesOf(clone);
            if (ls[0]) { ls[0].textContent = ''; }
            if (ls[1]) { ls[1].textContent = ''; }
            proto.parentNode.appendChild(clone);
            addRow('', '', clone);
            sync();
        });
        cards.forEach(function (c) {
            var ls = leavesOf(c);
            addRow(ls[0] ? ls[0].textContent.trim() : '', ls[1] ? ls[1].textContent.trim() : '', c);
        });
        wrap.appendChild(list);
        wrap.appendChild(addBtn);
        return wrap;
    }

    // Data sections: pick which real items show (none picked = all).
    function picksEditor(section) {
        var wrap = document.createElement('div');
        wrap.className = 'nit-side-group';
        var picks = CFG.picks || {};
        var opts = (picks.options || {})[section] || [];
        var cur = draft.picks[section] || (picks.current || {})[section];
        var titles = { courses: ['Courses to show', 'الدورات المعروضة'], categories: ['Categories to show', 'التصنيفات المعروضة'],
            subscriptions: ['Plans to show', 'الخطط المعروضة'], coupons: ['Coupons to show', 'الكوبونات المعروضة'],
            testimonials: ['Reviews to show', 'الآراء المعروضة'] };
        var hd = document.createElement('div'); hd.className = 'nit-side-subhd';
        hd.textContent = isAr ? titles[section][1] : titles[section][0];
        wrap.appendChild(hd);
        if (picks.licence && picks.licence[section] === false) {
            var lic = document.createElement('p'); lic.className = 'nit-side-hint';
            lic.textContent = t('notlicensed', 'Not included in your plan.');
            wrap.appendChild(lic);
            return wrap;
        }
        if (!opts.length) {
            var none = document.createElement('p'); none.className = 'nit-side-hint';
            none.textContent = section === 'testimonials'
                ? t('noreviews', 'No learner reviews with text yet — they appear here once learners rate a course.')
                : t('noitems', 'Nothing to pick yet.');
            wrap.appendChild(none);
            return wrap;
        }
        var all = !cur || !cur.length;
        var cl;
        var sync = function () {
            var v = cl.values();
            draft.picks[section] = (v.length === opts.length) ? [] : v;
            // Live preview for the sections the front page renders itself.
            if (window.NIT_PICKS) { window.NIT_PICKS[section] = draft.picks[section]; }
            var sel = { courses: '[data-nit-courses] [data-course-id],[data-nit-courses] > *:not([data-nit-course-card]):not(template)',
                        categories: '[data-nit-categories] > a', subscriptions: '[data-sub-id]', coupons: '[data-nit-coupons-card]' }[section];
            if (sel) {
                document.querySelectorAll(sel).forEach(function (card) {
                    var id = card.getAttribute('data-course-id') || card.getAttribute('data-sub-id') || card.getAttribute('data-category-id')
                        || (card.querySelector('[data-code]') && card.querySelector('[data-code]').getAttribute('data-code'))
                        || (card.href && (card.href.match(/[?&]id=(\d+)/) || [])[1]);
                    if (!id) { return; }
                    var show = !draft.picks[section].length || draft.picks[section].map(String).indexOf(String(id)) !== -1;
                    card.style.display = show ? '' : 'none';
                });
            }
            touch();
        };
        cl = checkList(opts, function (v) { return all || cur.map(String).indexOf(v) !== -1; }, sync);
        var hint = document.createElement('p'); hint.className = 'nit-side-hint';
        hint.textContent = t('pickshint', 'Untick to hide an item from the homepage. All ticked = show everything.');
        var tools = document.createElement('div'); tools.className = 'nit-side-mini';
        var selAll = document.createElement('button'); selAll.type = 'button'; selAll.textContent = t('selectall', 'All');
        var selNone = document.createElement('button'); selNone.type = 'button'; selNone.textContent = t('selectnone', 'None');
        selAll.addEventListener('click', function () { cl.boxes.forEach(function (b) { b.checked = true; }); sync(); });
        selNone.addEventListener('click', function () { cl.boxes.forEach(function (b) { b.checked = false; }); sync(); });
        tools.appendChild(selAll); tools.appendChild(selNone);
        wrap.appendChild(tools);
        wrap.appendChild(cl.node);
        wrap.appendChild(hint);
        return wrap;
    }

    // Navbar / footer: an ORDERED pick of the academy's pages (↑↓ to sort).
    function pagesEditor(title, which, hint, min, max) {
        var wrap = document.createElement('div');
        wrap.className = 'nit-side-group';
        var hd = document.createElement('div'); hd.className = 'nit-side-subhd'; hd.textContent = title;
        wrap.appendChild(hd);
        var pages = (CFG.pages || []).map(function (p) { return { key: p.key, label: isAr ? p.label.ar : p.label.en }; });
        var saved = draft[which] || (which === 'nav' ? CFG.navPages : CFG.footerLinks) || [];
        if (which === 'nav' && !saved.length) { saved = ['home', 'dashboard']; } // what Moodle shows by default
        var order = saved.slice().filter(function (k) { return pages.some(function (p) { return p.key === k; }); });
        var list = document.createElement('div'); list.className = 'nit-side-order';
        var count = document.createElement('p'); count.className = 'nit-side-hint';
        var render = function () {
            list.innerHTML = '';
            var selected = order.map(function (k) { return pages.filter(function (p) { return p.key === k; })[0]; });
            var rest = pages.filter(function (p) { return order.indexOf(p.key) === -1; });
            selected.concat(rest).forEach(function (p) {
                var idx = order.indexOf(p.key);
                var row = document.createElement('div'); row.className = 'nit-side-orow' + (idx !== -1 ? ' on' : '');
                var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = idx !== -1;
                cb.disabled = idx === -1 && order.length >= max;
                cb.addEventListener('change', function () {
                    if (cb.checked) { order.push(p.key); } else { order.splice(order.indexOf(p.key), 1); }
                    commit();
                });
                var name = document.createElement('span'); name.className = 'n'; name.textContent = p.label;
                row.appendChild(cb); row.appendChild(name);
                if (idx !== -1) {
                    var up = document.createElement('button'); up.type = 'button'; up.className = 'h'; up.textContent = '↑'; up.disabled = idx === 0;
                    var dn = document.createElement('button'); dn.type = 'button'; dn.className = 'h'; dn.textContent = '↓'; dn.disabled = idx === order.length - 1;
                    up.addEventListener('click', function () { order.splice(idx - 1, 0, order.splice(idx, 1)[0]); commit(); });
                    dn.addEventListener('click', function () { order.splice(idx + 1, 0, order.splice(idx, 1)[0]); commit(); });
                    row.appendChild(up); row.appendChild(dn);
                }
                list.appendChild(row);
            });
            var n = order.length;
            count.textContent = n + ' ' + t('selected', 'selected') + (min && n && n < min ? ' — ' + t('pickatleast', 'pick at least') + ' ' + min : '') + (n >= max ? ' — ' + t('max', 'max') + ' ' + max : '');
            count.style.color = (min && n && n < min) ? '#B42318' : '';
        };
        var commit = function () {
            draft[which] = order.slice();
            render();
            // Live preview: navbar links / footer column.
            var byKey = {}; (CFG.pages || []).forEach(function (p) { byKey[p.key] = p; });
            if (which === 'nav') {
                var ul = document.querySelector('.nit-navbar-links');
                if (ul) {
                    var proto = ul.querySelector('li');
                    ul.querySelectorAll('li').forEach(function (li) { li.style.display = order.length ? 'none' : ''; });
                    ul.querySelectorAll('li.nit-preview').forEach(function (li) { li.remove(); });
                    if (proto) {
                        order.forEach(function (k) {
                            var li = proto.cloneNode(true); li.className += ' nit-preview'; li.style.display = '';
                            var a = li.querySelector('a'); if (a) { a.textContent = isAr ? byKey[k].label.ar : byKey[k].label.en; a.href = byKey[k].url; a.classList.remove('active'); }
                            ul.appendChild(li);
                        });
                    }
                }
            } else {
                window.NIT_FOOTER_LINKS = order.length ? order.map(function (k) { return { label: isAr ? byKey[k].label.ar : byKey[k].label.en, url: byKey[k].url }; }) : null;
                var footer = document.querySelector('[data-nit-section="footer"]');
                if (footer && order.length && window.nitFillFooterLinks) { window.nitFillFooterLinks(footer, window.NIT_FOOTER_LINKS); }
            }
            touch();
        };
        render();
        wrap.appendChild(list);
        wrap.appendChild(count);
        var h = document.createElement('p'); h.className = 'nit-side-hint'; h.textContent = hint; wrap.appendChild(h);
        return wrap;
    }

    // Auth pages: welcome + tagline + background, with a live login preview.
    function authEditor() {
        var body = document.createElement('div');
        var a = draft.auth || CFG.auth || {};
        var base = (M.cfg && M.cfg.wwwroot ? M.cfg.wwwroot : '') + '/theme/nit/authpreview.php';
        var mode = 'login';
        var tabs = document.createElement('div'); tabs.className = 'nit-side-mini';
        var tabL = document.createElement('button'); tabL.type = 'button'; tabL.textContent = t('loginpage', 'Login page'); tabL.className = 'on';
        var tabS = document.createElement('button'); tabS.type = 'button'; tabS.textContent = t('signuppage', 'Signup page');
        tabs.appendChild(tabL); tabs.appendChild(tabS);
        body.appendChild(tabs);
        var frameWrap = document.createElement('div'); frameWrap.className = 'nit-side-preview';
        var frame = document.createElement('iframe');
        frame.src = base;
        frame.title = t('loginpreview', 'Login preview');
        frameWrap.appendChild(frame);
        body.appendChild(frameWrap);
        var inDoc = function (fn) { try { var d = frame.contentDocument; if (d && d.body) { fn(d); } } catch (e) { /* ignore */ } };
        var pick = function (o, k) { return { en: (o[k] || {}).en || '', ar: (o[k] || {}).ar || '' }; };
        var state = { welcome: pick(a, 'welcome'), tagline: pick(a, 'tagline'),
            signup_welcome: pick(a, 'signup_welcome'), signup_tagline: pick(a, 'signup_tagline'),
            image: (draft.auth && draft.auth.image) || null };
        var groups = { login: document.createElement('div'), signup: document.createElement('div') };
        var setMode = function (m) {
            mode = m;
            tabL.className = m === 'login' ? 'on' : ''; tabS.className = m === 'signup' ? 'on' : '';
            groups.login.style.display = m === 'login' ? '' : 'none';
            groups.signup.style.display = m === 'signup' ? '' : 'none';
            frame.src = base + (m === 'signup' ? '?signup=1' : '');
        };
        tabL.addEventListener('click', function () { setMode('login'); });
        tabS.addEventListener('click', function () { setMode('signup'); });
        var current = body;
        function field(label, key, multiline, sel) {
            var row = fieldRow(label);
            var en = mkInput(multiline, state[key].en, CFG.bilingual ? 'English' : ''); row.appendChild(en);
            var ar = null;
            if (CFG.bilingual) { ar = mkInput(multiline, state[key].ar, 'العربية'); ar.dir = 'rtl'; row.appendChild(ar); }
            var live = function () {
                state[key] = { en: en.value, ar: ar ? ar.value : '' };
                draft.auth = state;
                var v = isAr ? (state[key].ar || state[key].en) : (state[key].en || state[key].ar);
                inDoc(function (d) { var el = d.querySelector(sel); if (el) { el.textContent = v; } });
                touch();
            };
            en.addEventListener('input', live); if (ar) { ar.addEventListener('input', live); }
            current.appendChild(row);
        }
        current = groups.login;
        field(t('authwelcome', 'Welcome title'), 'welcome', false, '.nit-auth-side .quote h2');
        field(t('authtagline', 'Tagline'), 'tagline', true, '.nit-auth-side .quote p');
        current = groups.signup;
        var sh = document.createElement('p'); sh.className = 'nit-side-hint'; sh.textContent = t('signuphint', 'Leave empty to reuse the login texts.'); groups.signup.appendChild(sh);
        field(t('authwelcome', 'Welcome title'), 'signup_welcome', false, '.nit-auth-side .quote h2');
        field(t('authtagline', 'Tagline'), 'signup_tagline', true, '.nit-auth-side .quote p');
        body.appendChild(groups.login); body.appendChild(groups.signup);
        groups.signup.style.display = 'none';
        var imgrow = fieldRow(t('loginbg', 'Login / signup background image'));
        var file = document.createElement('input'); file.type = 'file'; file.accept = 'image/*'; file.className = 'nit-side-file';
        file.addEventListener('change', function () {
            if (!(file.files && file.files[0])) { return; }
            state.image = file.files[0]; draft.auth = state;
            var url = URL.createObjectURL(file.files[0]);
            inDoc(function (d) { var art = d.getElementById('nit-auth-art'); if (art) { art.style.backgroundImage = 'url(' + url + ')'; } });
            touch();
        });
        imgrow.appendChild(file); body.appendChild(imgrow);
        return body;
    }

    function postAction(fields) {
        if (draftCount() && !window.confirm(t('structurewarn', 'This changes the page structure now and discards your unpublished edits. Continue?'))) { return Promise.resolve(); }
        var fd = new FormData();
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return post(fd)
            .then(function (d) { if (d && d.ok) { leaving = true; document.body.classList.remove('nit-dirty'); window.location.reload(); } else { window.alert(t('savefailed', 'Could not save') + (d && d.error ? ' (' + d.error + ')' : '')); } })
            .catch(function () { window.alert(t('savefailed', 'Could not save')); });
    }

    function showInPanel(title, node) {
        if (!panelContent) { return; }
        panelContent.innerHTML = '';
        var st = document.createElement('div');
        st.className = 'nit-side-hd';
        st.textContent = title;
        panelContent.appendChild(st);
        panelContent.appendChild(node);
        panelContent.scrollTop = 0;
    }
    function selectSection(marker) {
        var root = marker === 'brand' ? null : sectionRoot(marker);
        document.querySelectorAll('[data-nit-section].nit-sel').forEach(function (s) { s.classList.remove('nit-sel'); });
        if (root) {
            root.classList.add('nit-sel');
            if (root.scrollIntoView) { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }
        if (sidePanel) {
            sidePanel.querySelectorAll('.nit-side-secrow').forEach(function (r) {
                r.classList.toggle('on', r.getAttribute('data-marker') === marker);
            });
        }
        showInPanel(secLabel(marker) + ' ' + t('settings', 'settings'), contentEditorFor(marker, root));
        if (sidePanel) { sidePanel.scrollTop = 0; }
    }

    function buildSidePanel() {
        if (sidePanel) { return; }
        var state = (CFG.sections || []).slice();
        var domIndex = function (s) {
            var r = sectionRoot(s.key);
            if (!r) { return 1e9; }
            var all = [].slice.call(document.querySelectorAll('[data-nit-section], .block_nit_section, [data-block]'));
            var i = all.indexOf(r);
            return i === -1 ? 1e8 : i;
        };
        var present = state.filter(function (s) { return s.present; }).sort(function (a, b) { return domIndex(a) - domIndex(b); });
        var missing = state.filter(function (s) { return !s.present && s.licensed !== false; });
        sidePanel = document.createElement('aside');
        sidePanel.id = 'nit-side-panel';
        var dock = function () {
            var nb = document.querySelector('.nit-navbar, .navbar.fixed-top, nav.navbar');
            var top = nb ? Math.max(0, Math.round(nb.getBoundingClientRect().bottom)) : 64;
            sidePanel.style.top = top + 'px';
            sidePanel.style.height = 'calc(100vh - ' + top + 'px)';
        };
        dock();
        window.addEventListener('resize', dock);

        sidePanel.appendChild(buildPublishBar());

        panelContent = document.createElement('div');
        panelContent.className = 'nit-side-content';
        var hint = document.createElement('p');
        hint.className = 'nit-side-hint';
        hint.textContent = t('sidehint', 'Select a section to edit its text, images and links. Changes preview live; Publish to make them live.');
        panelContent.appendChild(hint);
        sidePanel.appendChild(panelContent);

        var listHd = document.createElement('div');
        listHd.className = 'nit-side-hd';
        listHd.textContent = t('pagesections', 'PAGE SECTIONS');
        sidePanel.appendChild(listHd);
        var list = document.createElement('div');
        list.className = 'nit-side-list';

        function rowFor(marker, label, onSelect) {
            var row = document.createElement('div');
            row.className = 'nit-side-secrow';
            row.setAttribute('data-marker', marker);
            var name = document.createElement('button'); name.type = 'button'; name.className = 'n';
            name.textContent = label;
            name.addEventListener('click', onSelect);
            return { row: row, name: name };
        }

        var brand = rowFor('brand', secLabel('brand'), function () { selectSection('brand'); });
        var bh = document.createElement('span'); bh.className = 'h'; bh.textContent = '✦';
        brand.row.appendChild(bh); brand.row.appendChild(brand.name);
        list.appendChild(brand.row);

        present.forEach(function (s, i) {
            var r = rowFor(s.key, secLabel(s.key), function () { selectSection(s.key); });
            if (!s.visible) { r.row.classList.add('off'); }
            if (s.licensed === false) { r.row.classList.add('off'); r.row.title = t('notlicensed', 'Not included in your plan.'); }
            var up = document.createElement('button'); up.type = 'button'; up.className = 'h'; up.textContent = '↑'; up.title = t('moveup', 'Move up');
            var down = document.createElement('button'); down.type = 'button'; down.className = 'h'; down.textContent = '↓'; down.title = t('movedown', 'Move down');
            up.disabled = i === 0; down.disabled = i === present.length - 1;
            up.addEventListener('click', function () { postAction({ action: 'section_move', blockid: s.blockid, dir: 'up' }); });
            down.addEventListener('click', function () { postAction({ action: 'section_move', blockid: s.blockid, dir: 'down' }); });
            var eye = document.createElement('button'); eye.type = 'button'; eye.className = 'eye';
            eye.textContent = s.visible ? '👁' : '◌';
            eye.title = s.visible ? t('hidesection', 'Hide section') : t('showsection', 'Show section');
            eye.addEventListener('click', function () { postAction({ action: 'section_toggle', blockid: s.blockid, visible: s.visible ? 0 : 1 }); });
            var rst = document.createElement('button'); rst.type = 'button'; rst.className = 'eye'; rst.textContent = '↺';
            rst.title = t('resetsection', 'Reset to template (drops this section\'s edits)');
            rst.addEventListener('click', function () {
                if (window.confirm(t('resetconfirm', 'Reset this section to the template? Its edits will be lost.'))) {
                    postAction({ action: 'section_reset', blockid: s.blockid });
                }
            });
            r.row.appendChild(up); r.row.appendChild(down); r.row.appendChild(r.name); r.row.appendChild(rst); r.row.appendChild(eye);
            list.appendChild(r.row);
        });
        sidePanel.appendChild(list);

        if (missing.length) {
            var addWrap = document.createElement('div');
            addWrap.className = 'nit-side-add';
            var sel = document.createElement('select');
            sel.className = 'nit-side-input';
            missing.forEach(function (s) {
                var o = document.createElement('option'); o.value = s.key; o.textContent = secLabel(s.key); sel.appendChild(o);
            });
            var addBtn = document.createElement('button'); addBtn.type = 'button'; addBtn.className = 'nit-side-btn sec';
            addBtn.textContent = '+ ' + t('addsection', 'Add a section');
            addBtn.addEventListener('click', function () { postAction({ action: 'section_add', section: sel.value }); });
            addWrap.appendChild(sel); addWrap.appendChild(addBtn);
            sidePanel.appendChild(addWrap);
        }

        var designHd = document.createElement('div');
        designHd.className = 'nit-side-hd';
        designHd.textContent = t('design', 'DESIGN');
        sidePanel.appendChild(designHd);
        var designWrap = document.createElement('div');
        designWrap.className = 'nit-side-list';
        [['colours', t('colours', 'Colours'), palettePanel],
         ['branding', t('editbrand', 'Branding'), brandingPanel],
         ['auth', secLabel('auth'), authEditor]].forEach(function (d) {
            var r = rowFor(d[0], d[1], function () {
                sidePanel.querySelectorAll('.nit-side-secrow').forEach(function (x) { x.classList.toggle('on', x === r.row); });
                document.querySelectorAll('[data-nit-section].nit-sel').forEach(function (s) { s.classList.remove('nit-sel'); });
                showInPanel(d[1], d[2]());
            });
            var h = document.createElement('span'); h.className = 'h'; h.textContent = '✦';
            r.row.appendChild(h); r.row.appendChild(r.name);
            designWrap.appendChild(r.row);
        });
        sidePanel.appendChild(designWrap);

        document.body.appendChild(sidePanel);
        document.body.classList.add('nit-editing-panel');
        window.addEventListener('beforeunload', function (e) { if (!leaving && draftCount()) { e.preventDefault(); e.returnValue = ''; } });
    }
    function removeSidePanel() {
        if (sidePanel) { sidePanel.remove(); sidePanel = null; panelContent = null; publishBar = null; }
        document.body.classList.remove('nit-editing-panel');
        document.querySelectorAll('[data-nit-section].nit-sel').forEach(function (s) { s.classList.remove('nit-sel'); });
    }

    // Editing is driven by Moodle's NATIVE edit mode (body.editing) — one toggle,
    // and our pencils only touch our own regions.
    function setEditing(on) {
        if (on === editing) {
            if (on) { addPencils(); addCourseButton(); buildSidePanel(); } // re-ensure after DOM changes
            return;
        }
        editing = on;
        if (coloursBtn) {
            coloursBtn.style.display = on ? '' : 'none';
        }
        if (on) {
            addPencils();
            addCourseButton();
            buildSidePanel();
        } else {
            removePencils();
            removeCourseButton();
            removeSidePanel();
        }
    }

    function init() {
        injectStyles();

        var navtools = document.querySelector('.nit-navbar-tools') || document.querySelector('.nit-navbar-editswitch');

        var isEditing = function () { return document.body.classList.contains('editing'); };

        // The front page has no Moodle edit-mode switch (it uses a chrome-free
        // layout), and it ignores the ?edit GET toggle — so an owner can't turn
        // editing ON from here. Give them the design's "Edit page" button, which
        // POSTs to Moodle's real editmode endpoint (setmode=on) and returns to the
        // page in edit mode → the pencils below activate. Shown only when NOT
        // already editing (in edit mode, the top "Editing homepage" toolbar has the
        // "Done editing" control).
        var hasSwitch = !!document.querySelector('.nit-navbar-editswitch input, .editmode-switch-form, [data-region="editmode-switch"]');
        if (!isEditing() && !hasSwitch) {
            var mcfg = (window.M && M.cfg) || {};
            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'nit-editpage-btn';
            editBtn.setAttribute('style',
                'display:inline-flex;align-items:center;gap:8px;cursor:pointer;font:inherit;'
                + 'font-size:13px;font-weight:600;background:#FFF7E6;border:1px solid #F0DDB0;'
                + 'color:#8A6A12;padding:9px 14px;border-radius:9px;');
            editBtn.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#E0A100;display:block;"></span>'
                + t('editpage', 'Edit page');
            editBtn.addEventListener('click', function () {
                var f = document.createElement('form');
                f.method = 'post';
                f.action = (mcfg.wwwroot || '') + '/editmode.php';
                var add = function (n, v) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i);
                };
                add('setmode', 'on');
                add('sesskey', mcfg.sesskey || (CFG && CFG.sesskey) || '');
                add('pageurl', window.location.href);
                add('context', mcfg.contextid || '');
                document.body.appendChild(f);
                f.submit();
            });
            (navtools || document.body).appendChild(editBtn);
        }

        // Activate with Moodle's native "Edit mode" toggle (body.editing). React
        // live if the user flips it without a reload.
        setEditing(isEditing());
        try {
            new MutationObserver(function () { setEditing(isEditing()); })
                .observe(document.body, { attributes: true, attributeFilter: ['class'] });
        } catch (e) { /* older browsers: initial state is enough */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
