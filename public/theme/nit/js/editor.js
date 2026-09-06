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

    function t(key, fallback) {
        return (S && S[key]) || fallback;
    }

    // ── styles ───────────────────────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('nit-edit-styles')) {
            return;
        }
        var css =
            '.nit-edit-toggle{position:fixed;inset-inline-end:18px;bottom:18px;z-index:99999;' +
            'display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:none;border-radius:999px;' +
            'font:600 14px system-ui,sans-serif;cursor:pointer;background:#0B2923;color:#00FFB2;' +
            'box-shadow:0 6px 20px -6px rgba(0,0,0,.5)}' +
            '.nit-edit-toggle.nit-on{background:#00FFB2;color:#0B2923}' +
            'body.nit-editing [data-nit-section],body.nit-editing [data-nit-edit]{outline:2px dashed rgba(0,180,140,.7);outline-offset:-2px;position:relative}' +
            '.nit-edit-pencil{position:absolute;top:10px;inset-inline-end:10px;z-index:9999;' +
            'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:none;border-radius:8px;' +
            'font:600 13px system-ui,sans-serif;cursor:pointer;background:#0B2923;color:#00FFB2;' +
            'box-shadow:0 2px 8px rgba(0,0,0,.35)}' +
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
            '.nit-edit-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:6px}';
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

    // Hero size presets — keep the image looking good at any upload dimensions
    // (background is center/cover, so the band's aspect + height is the knob).
    var HERO_SIZES = [
        { key: 'banner', label: 'Banner', aspect: '16/4', minheight: 220 },
        { key: 'standard', label: 'Standard', aspect: '16/6', minheight: 340 },
        { key: 'tall', label: 'Tall', aspect: '16/8', minheight: 420 }
    ];
    function heroPanel(marker) {
        var body = document.createElement('div');

        var imgRow = document.createElement('div');
        imgRow.className = 'nit-edit-row';
        var imgBtn = document.createElement('button');
        imgBtn.type = 'button';
        imgBtn.className = 'nit-edit-btn';
        imgBtn.textContent = '🖼 ' + t('replaceimage', 'Replace image');
        imgBtn.addEventListener('click', function () { uploadImage('section_bg_image', { section: marker }, imgBtn); });
        imgRow.appendChild(imgBtn);
        body.appendChild(imgRow);

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
            postFields({ action: 'section_style', section: marker, aspect: s.aspect, minheight: String(s.minheight) }, save);
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
        document.querySelectorAll('[data-nit-section]').forEach(function (sec) {
            var marker = sec.getAttribute('data-nit-section');
            if (marker === 'hero') {
                attachPencil(sec, t('edithero', 'Edit hero'), function () {
                    openPanel(t('edithero', 'Edit hero'), heroPanel(marker));
                });
            } else {
                attachPencil(sec, t('editimage', 'Edit image'), function (pencil) {
                    uploadImage('section_bg_image', { section: marker }, pencil);
                });
            }
        });
        // Logo (navbar brand) → replace the site logo.
        document.querySelectorAll('[data-nit-edit="logo"]').forEach(function (el) {
            attachPencil(el, t('editlogo', 'Edit logo'), function (pencil) {
                uploadImage('logo', {}, pencil);
            });
        });
    }

    function removePencils() {
        document.querySelectorAll('.nit-edit-pencil').forEach(function (p) { p.remove(); });
    }

    function setEditing(on) {
        editing = on;
        document.body.classList.toggle('nit-editing', on);
        if (on) {
            addPencils();
        } else {
            removePencils();
        }
    }

    function init() {
        injectStyles();
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'nit-edit-toggle';
        toggle.textContent = '✏ ' + t('editpage', 'Edit page');
        toggle.addEventListener('click', function () {
            setEditing(!editing);
            toggle.classList.toggle('nit-on', editing);
            toggle.textContent = (editing ? '✓ ' + t('doneediting', 'Done') : '✏ ' + t('editpage', 'Edit page'));
        });
        document.body.appendChild(toggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
