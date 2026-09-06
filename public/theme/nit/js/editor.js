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
            '.nit-edit-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:6px}' +
            '.nit-add-course{display:inline-flex;align-items:center;justify-content:center;gap:8px;' +
            'min-height:44px;padding:12px 20px;margin:4px;border-radius:12px;text-decoration:none;' +
            'font:700 15px system-ui,sans-serif;background:#0B2923;color:#00FFB2;' +
            'border:2px dashed rgba(0,255,178,.4)}';
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

    // About panel: replace the photo + edit the bullet points (seeded from the
    // live DOM, so no extra fetch). Points save as a JSON array to about_points.
    function aboutPanel(sec) {
        var body = document.createElement('div');

        var imgRow = document.createElement('div');
        imgRow.className = 'nit-edit-row';
        var imgBtn = document.createElement('button');
        imgBtn.type = 'button';
        imgBtn.className = 'nit-edit-btn';
        imgBtn.textContent = '🖼 ' + t('replaceimage', 'Replace image');
        imgBtn.addEventListener('click', function () { uploadImage('about_image', {}, imgBtn); });
        imgRow.appendChild(imgBtn);
        body.appendChild(imgRow);

        var lbl = document.createElement('label');
        lbl.textContent = t('aboutpoints', 'Points');
        body.appendChild(lbl);
        var list = document.createElement('div');
        list.style.margin = '6px 0 10px';

        function addRow(val) {
            if (list.querySelectorAll('.nit-point-input').length >= 8) {
                return;
            }
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:6px;margin-bottom:6px';
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'nit-point-input';
            inp.maxLength = 200;
            inp.value = val || '';
            inp.style.cssText = 'flex:1;padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit';
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'nit-edit-btn sec';
            del.textContent = '✕';
            del.addEventListener('click', function () { row.remove(); });
            row.appendChild(inp);
            row.appendChild(del);
            list.appendChild(row);
        }

        Array.prototype.slice.call(sec.querySelectorAll('ul li')).forEach(function (li) {
            addRow(li.textContent.replace(/^[\s◆]+/, '').trim());
        });
        body.appendChild(list);

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-edit-btn sec';
        addBtn.textContent = '+ ' + t('addpoint', 'Add point');
        addBtn.style.marginBottom = '14px';
        addBtn.addEventListener('click', function () { addRow(''); });
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
            var bullets = Array.prototype.slice.call(list.querySelectorAll('.nit-point-input'))
                .map(function (i) { return i.value.trim(); })
                .filter(function (v) { return v !== ''; });
            postFields({ action: 'about_points', bullets: JSON.stringify(bullets) }, save);
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
        var thumbs = document.createElement('div');
        thumbs.style.cssText = 'display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px';
        var tiles = grid ? Array.prototype.slice.call(grid.children) : [];
        if (!tiles.length) {
            var empty = document.createElement('p');
            empty.style.cssText = 'color:#666;margin:0 0 12px';
            empty.textContent = t('galleryempty', 'No images yet.');
            body.appendChild(empty);
        }
        tiles.forEach(function (tile, idx) {
            var cell = document.createElement('div');
            cell.style.cssText = 'position:relative;aspect-ratio:4/3;border-radius:8px;border:1px solid #ddd;'
                + 'background-size:cover;background-position:center';
            cell.style.backgroundImage = getComputedStyle(tile).backgroundImage;
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'nit-edit-btn';
            del.textContent = '✕';
            del.style.cssText = 'position:absolute;top:4px;inset-inline-end:4px;padding:2px 9px';
            del.addEventListener('click', function () {
                if (window.confirm(t('deleteconfirm', 'Delete this image?'))) {
                    postFields({ action: 'gallery_delete', index: String(idx) }, del);
                }
            });
            cell.appendChild(del);
            thumbs.appendChild(cell);
        });
        body.appendChild(thumbs);

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-edit-btn';
        addBtn.textContent = '➕ ' + t('addimage', 'Add image');
        addBtn.addEventListener('click', function () { uploadImage('gallery_add', {}, addBtn); });
        body.appendChild(addBtn);

        var act = document.createElement('div');
        act.className = 'nit-edit-actions';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'nit-edit-btn sec';
        close.textContent = t('close', 'Close');
        close.addEventListener('click', closePanel);
        act.appendChild(close);
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

    // Footer panel: academy name + description + show-logo, seeded from the DOM.
    function footerPanel(sec) {
        var body = document.createElement('div');
        var head = sec.querySelector('div[style*="font-weight:800"]');
        var para = sec.querySelector('p');
        var hasLogo = !!sec.querySelector('img');

        function textRow(labelKey, labelFallback, value) {
            var row = document.createElement('div');
            row.className = 'nit-edit-row';
            var lbl = document.createElement('label');
            lbl.textContent = t(labelKey, labelFallback);
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.value = value || '';
            inp.style.cssText = 'padding:8px;border:1px solid #ccc;border-radius:8px;font:inherit';
            row.appendChild(lbl);
            row.appendChild(inp);
            body.appendChild(row);
            return inp;
        }
        var nameInp = textRow('footername', 'Academy name', head ? head.textContent.trim() : '');
        var descInp = textRow('footerdesc', 'Description', para ? para.textContent.trim() : '');

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
                name: nameInp.value.trim(),
                desc: descInp.value.trim(),
                showlogo: logoChk.checked ? '1' : '0'
            }, save);
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
            } else if (marker === 'about') {
                attachPencil(sec, t('editabout', 'Edit about'), function () {
                    openPanel(t('editabout', 'Edit about'), aboutPanel(sec));
                });
            } else if (marker === 'gallery') {
                attachPencil(sec, t('editgallery', 'Edit gallery'), function () {
                    openPanel(t('editgallery', 'Edit gallery'), galleryPanel(sec));
                });
            } else if (marker === 'contact') {
                attachPencil(sec, t('editcontact', 'Edit contact'), function () {
                    openPanel(t('editcontact', 'Edit contact'), contactPanel());
                });
            } else if (marker === 'footer') {
                attachPencil(sec, t('editfooter', 'Edit footer'), function () {
                    openPanel(t('editfooter', 'Edit footer'), footerPanel(sec));
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

    function setEditing(on) {
        editing = on;
        document.body.classList.toggle('nit-editing', on);
        if (on) {
            addPencils();
            addCourseButton();
        } else {
            removePencils();
            removeCourseButton();
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
