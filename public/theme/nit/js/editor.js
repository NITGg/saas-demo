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
            '.nit-colours-btn{bottom:18px}' +
            '.nit-img-pick{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}' +
            '.nit-img-thumb{height:64px;width:96px;border-radius:8px;border:1px solid #ccc;background-size:cover;background-position:center}' +
            'body.editing [data-nit-section],body.editing [data-nit-edit]{outline:2px dashed rgba(0,180,140,.7);outline-offset:-2px;position:relative}' +
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
    function aboutPanel(sec) {
        var body = document.createElement('div');

        var pick = imagePicker();
        body.appendChild(pick.row);

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
            submitPanel('about', { bullets: JSON.stringify(bullets) }, pick.file(), save);
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
        }
        render();

        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'nit-edit-btn';
        addBtn.textContent = '➕ ' + t('addimage', 'Add image');
        addBtn.addEventListener('click', function () {
            var input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml';
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) { return; }
                if (CFG.maxImageBytes && f.size > CFG.maxImageBytes) {
                    window.alert(t('imagetoolarge', 'Image is too large.'));
                    return;
                }
                items.push({ kind: 'new', file: f, url: URL.createObjectURL(f) });
                render();
            });
            input.click();
        });
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

    // Palette panel: the 6 brand pickers (rest are derived server-side). Seeded
    // from NIT_EDIT.palette; saving recompiles the theme CSS.
    var PALETTE_FIELDS = ['primary', 'accent', 'secondary', 'background', 'surface', 'text'];
    var PALETTE_DEFAULTS = {
        primary: '#5488c4', accent: '#5488c4', secondary: '#1c2a3a',
        background: '#0c141f', surface: '#121e2d', text: '#eef3f9'
    };
    function palettePanel() {
        var pal = CFG.palette || {};
        var body = document.createElement('div');
        var inputs = {};
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
            row.appendChild(lbl);
            row.appendChild(inp);
            body.appendChild(row);
            inputs[key] = inp;
        });
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
            var fields = { action: 'palette' };
            PALETTE_FIELDS.forEach(function (k) { fields[k] = inputs[k].value; });
            postFields(fields, save);
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

    // Editing is driven by Moodle's NATIVE edit mode (body.editing) — one toggle,
    // and our pencils only touch our own regions.
    function setEditing(on) {
        if (on === editing) {
            if (on) { addPencils(); addCourseButton(); } // re-ensure after DOM changes
            return;
        }
        editing = on;
        if (coloursBtn) {
            coloursBtn.style.display = on ? '' : 'none';
        }
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

        coloursBtn = document.createElement('button');
        coloursBtn.type = 'button';
        coloursBtn.className = 'nit-edit-toggle nit-colours-btn';
        coloursBtn.textContent = '🎨 ' + t('colours', 'Colours');
        coloursBtn.style.display = 'none';
        coloursBtn.addEventListener('click', function () {
            openPanel(t('colours', 'Colours'), palettePanel());
        });
        document.body.appendChild(coloursBtn);

        // Activate with Moodle's native "Edit mode" toggle (body.editing) — no
        // separate button. React live if the user flips it without a reload.
        var isEditing = function () { return document.body.classList.contains('editing'); };
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
