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

/**
 * Admin UI to manage automatic offers. Drives /local/nit_commerce/api.php from vanilla JS.
 * Mirrors manage_coupons.php (offers carry no code / max / usage limit). Labels match the reference.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

admin_externalpage_setup('local_nit_commerce_manageoffers');
require_capability('local/nit_commerce:manageoffers', context_system::instance());
local_nit_commerce_require_feature('offers');

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('manageoffers', 'local_nit_commerce'));
$PAGE->set_heading(get_string('manageoffers', 'local_nit_commerce'));
$PAGE->requires->js(new moodle_url('/local/nit_commerce/ui.js'), true);

echo $OUTPUT->header();

$STR = local_nit_commerce_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active', 'ui_activate', 'ui_deactivate',
    'ui_edit', 'ui_delete', 'ui_never', 'ui_optional', 'ui_pager_info',
    'pkg_col_status', 'pkg_col_actions', 'sub_inactive',
    'cpn_col_type', 'cpn_col_value', 'cpn_col_scope', 'cpn_col_dates', 'cpn_field_dtype',
    'cpn_field_value', 'cpn_field_start', 'cpn_field_end', 'cpn_field_scope', 'cpn_type_percent',
    'cpn_type_fixed', 'cpn_scope_courses', 'cpn_scope_packages', 'cpn_scope_subscriptions', 'cpn_scope_programs',
    'cpn_scope_all', 'cpn_scope_specific', 'cpn_scope_required',
    'ofr_new', 'ofr_none', 'ofr_col_name', 'ofr_field_name', 'ofr_created', 'ofr_updated',
    'ofr_activated', 'ofr_deactivated', 'ofr_deleted', 'ofr_confirm_delete', 'ofr_edit_titled',
    'ofr_delete_title',
    'pkg_field_name_en', 'pkg_field_name_ar',
    'err_sessionexpired', 'err_requestfailed',
));
echo html_writer::script('window.ACADEMY_CFG = ' . json_encode(array(
    'endpoint' => (new moodle_url('/local/nit_commerce/api.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
    .acad-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
    .acad-pager__info { margin-inline-end:auto; color:#6a6f73; font-size:.9rem; }
    .acad-pager button { border:1px solid #dee2e6; background:#fff; border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
    .acad-pager button.is-active { background:#0f6cbf; border-color:#0f6cbf; color:#fff; }
    .acad-pager button:disabled { opacity:.5; cursor:default; }
</style>
<div id="academy-offer-app">
    <div id="ofr-message" class="alert" style="display:none"></div>

    <div class="mb-3">
        <button id="ofr-new" class="btn btn-primary"><?php echo $STR['ofr_new']; ?></button>
        <button id="ofr-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <table class="table table-striped" id="ofr-table">
        <thead>
            <tr>
                <th><?php echo $STR['ofr_col_name']; ?></th>
                <th><?php echo $STR['cpn_col_type']; ?></th>
                <th><?php echo $STR['cpn_col_value']; ?></th>
                <th><?php echo $STR['cpn_col_scope']; ?></th>
                <th><?php echo $STR['cpn_col_dates']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="ofr-table-pager" class="acad-pager"></div>

    <div id="ofr-form-card" class="card" style="display:none; max-width:640px;">
        <div class="card-body">
            <h4 id="ofr-form-title" class="card-title"><?php echo $STR['ofr_new']; ?></h4>
            <input type="hidden" id="o-id">
            <div class="form-group">
                <label for="o-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="o-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="o-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="o-name-ar" dir="rtl">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="o-dtype"><?php echo $STR['cpn_field_dtype']; ?></label>
                    <select class="form-control" id="o-dtype">
                        <option value="percent"><?php echo $STR['cpn_type_percent']; ?></option>
                        <option value="fixed"><?php echo $STR['cpn_type_fixed']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="o-value"><?php echo $STR['cpn_field_value']; ?></label>
                    <input type="number" class="form-control" id="o-value" min="0" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="o-start"><?php echo $STR['cpn_field_start']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="o-start">
                </div>
                <div class="form-group col-md-6">
                    <label for="o-end"><?php echo $STR['cpn_field_end']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="o-end">
                </div>
            </div>

            <label class="d-block"><strong><?php echo $STR['cpn_field_scope']; ?></strong></label>
            <div id="o-scope" class="mb-3 p-2" style="border:1px solid #dee2e6; border-radius:6px;">
                <?php
                $scopetypes = array(
                    'course'       => $STR['cpn_scope_courses'],
                    'package'      => $STR['cpn_scope_packages'],
                    'subscription' => $STR['cpn_scope_subscriptions'],
                    'program'      => $STR['cpn_scope_programs'],
                );
                foreach ($scopetypes as $t => $label) {
                    echo '<div class="scope-block mb-2" data-type="' . $t . '">';
                    echo '<div class="form-check">';
                    echo '<input type="checkbox" class="form-check-input scope-on" id="oscope-' . $t . '-on">';
                    echo '<label class="form-check-label" for="oscope-' . $t . '-on"><strong>' . $label . '</strong></label>';
                    echo '</div>';
                    echo '<div class="scope-detail" style="display:none; margin:.25rem 0 .25rem 1.5rem;">';
                    echo '<select class="form-control form-control-sm scope-mode mb-1" style="max-width:220px">';
                    echo '<option value="all">' . $STR['cpn_scope_all'] . '</option>';
                    echo '<option value="specific">' . $STR['cpn_scope_specific'] . '</option>';
                    echo '</select>';
                    echo '<select class="form-control scope-list" multiple size="5" style="display:none"></select>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="o-active" checked>
                <label class="form-check-label" for="o-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="ofr-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="ofr-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

    <!-- ── Delete confirmation modal ── -->
    <div id="ofr-confirm-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <div class="academy-modal-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2zM4 6h16v1H4V6z"/></svg>
            </div>
            <h5 class="academy-modal-title" id="ofr-confirm-title"></h5>
            <p id="ofr-confirm-text" class="academy-modal-text"></p>
            <div class="academy-modal-actions">
                <button id="ofr-confirm-cancel" class="btn btn-light"><?php echo $STR['ui_cancel']; ?></button>
                <button id="ofr-confirm-ok" class="btn btn-danger"><?php echo $STR['ui_delete']; ?></button>
            </div>
        </div>
    </div>
</div>

<style>
    .academy-modal-backdrop { position: fixed; inset: 0; background: rgba(28,29,36,.55); display: flex;
        align-items: center; justify-content: center; z-index: 1055; padding: 1rem; }
    .academy-modal { background: #fff; border-radius: 12px; padding: 1.75rem 1.5rem 1.4rem; max-width: 420px;
        width: 100%; box-shadow: 0 24px 60px rgba(0,0,0,.28); text-align: center; }
    .academy-modal-icon { width: 54px; height: 54px; margin: 0 auto .9rem; border-radius: 50%;
        background: #fdecef; display: flex; align-items: center; justify-content: center; }
    .academy-modal-icon svg { width: 28px; height: 28px; fill: #e8153b; }
    .academy-modal-title { font-weight: 700; font-size: 1.15rem; margin: 0 0 .4rem; color: #1c1d1f; }
    .academy-modal-text { color: #5a5f66; font-size: .95rem; margin: 0 0 .5rem; line-height: 1.5; }
    .academy-modal-actions { display: flex; justify-content: center; gap: .6rem; margin-top: 1.35rem; }
    .academy-modal-actions .btn { min-width: 108px; }
</style>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_CFG;
    var STR = window.ACADEMY_STR || {};
    function str(k){return (k in STR)?STR[k]:k;}
    function strf(k,a){var s=str(k);return s.replace(/\{\$a\}/g,a);}
    function $(id){return document.getElementById(id);}

    var PAGE_SIZE = 10, pager = null, TARGETS = null;

    function msg(text, type){
        var el = $('ofr-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success'){ setTimeout(function(){ el.style.display = 'none'; }, 3000); }
    }
    function esc(s){
        return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    function api(func, params, method){
        params = params || {}; method = method || 'GET';
        var data = new URLSearchParams({ function: func, sesskey: CFG.sesskey });
        if (CFG.lang){ data.append('alang', CFG.lang); }
        Object.keys(params).forEach(function(k){ data.append(k, params[k]); });
        var opts, url = CFG.endpoint;
        if (method === 'POST'){
            opts = { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:data.toString() };
        } else { url = CFG.endpoint + '?' + data.toString(); opts = {}; }
        return fetch(url, opts).then(function(r){ return r.text(); }).then(function(text){
            var json;
            try { json = JSON.parse(text); } catch(e){ throw new Error(str('err_sessionexpired')); }
            if (json.status !== 'success'){ throw new Error(json.error || str('err_requestfailed')); }
            return json.data;
        });
    }

    // ── Multilang helpers (same one-field {mlang} approach as manage_subscriptions.php) ──
    function parseMultilang(value){
        var out = { en:'', ar:'' }, raw = String(value == null ? '' : value), m, found = false;
        var re2 = /\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}([\s\S]*?)\{\s*mlang\s*\}/g;
        while ((m = re2.exec(raw)) !== null){
            found = true;
            var c = m[1].toLowerCase();
            if (c.indexOf('ar') === 0){ out.ar = m[2].trim(); } else if (c.indexOf('en') === 0){ out.en = m[2].trim(); }
        }
        if (found){ return out; }
        var re1 = /<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>([\s\S]*?)<\/span>/g;
        while ((m = re1.exec(raw)) !== null){
            found = true;
            var c1 = m[1].toLowerCase();
            if (c1.indexOf('ar') === 0){ out.ar = m[2].trim(); } else if (c1.indexOf('en') === 0){ out.en = m[2].trim(); }
        }
        if (!found){ out.en = raw; }
        return out;
    }
    function buildMultilang(en, ar){
        en = String(en == null ? '' : en).trim();
        ar = String(ar == null ? '' : ar).trim();
        if (en && ar){ return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}'; }
        return en || ar;
    }
    function displayName(value){
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function(x){ return x; }).join(' / ') || value || '';
    }

    function toInput(ts){
        if (!ts){ return ''; }
        var d = new Date(ts * 1000), p = function(n){ return (n<10?'0':'')+n; };
        return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());
    }
    function fromInput(v){ return v ? Math.floor(new Date(v).getTime()/1000) : 0; }
    function fmtDate(ts){ return ts ? new Date(ts*1000).toLocaleDateString() : str('ui_never'); }
    function dtype(t){ return t === 'fixed' ? str('cpn_type_fixed') : str('cpn_type_percent'); }
    function valueLabel(o){ return o.discount_type === 'percent' ? (o.discount_value + '%') : o.discount_value; }
    function scopeLabel(o){
        if (!o.applies_to || !o.applies_to.length){ return '<span class="text-muted">—</span>'; }
        return o.applies_to.map(function(a){ return esc(a.label); }).join(', ');
    }

    function renderRows(items){
        var tbody = $('ofr-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(o){
            var tr = document.createElement('tr');
            var toggle = o.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="'+o.id+'">'+esc(str('ui_deactivate'))+'</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="'+o.id+'">'+esc(str('ui_activate'))+'</button>';
            tr.innerHTML =
                '<td>'+esc(displayName(o.name_raw || o.name))+'</td>'+
                '<td>'+esc(dtype(o.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(o))+'</td>'+
                '<td>'+scopeLabel(o)+'</td>'+
                '<td>'+esc(fmtDate(o.startdate))+' → '+esc(fmtDate(o.enddate))+'</td>'+
                '<td>'+esc(o.status === 'active' ? str('ui_active') : str('sub_inactive'))+'</td>'+
                '<td>'+
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="'+o.id+'">'+esc(str('ui_edit'))+'</button> '+
                    toggle+' '+
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="'+o.id+'">'+esc(str('ui_delete'))+'</button>'+
                '</td>';
            tr._o = o;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('ofr-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">'+esc(str('ui_loading'))+'</td></tr>';
        api('get_offers').then(function(rows){
            if (!rows.length){
                tbody.innerHTML = '<tr><td colspan="7">'+esc(str('ofr_none'))+'</td></tr>';
                $('ofr-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(rows); }
            else {
                pager = AcademyUI.paginate({ rows:rows, pageSize:PAGE_SIZE, pagerEl:$('ofr-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Scope editor (identical to manage_coupons.php) ──
    function targetsFor(type){
        if (!TARGETS){ return []; }
        if (type === 'package'){ return TARGETS.packages || []; }
        if (type === 'subscription'){ return TARGETS.subscriptions || []; }
        if (type === 'program'){ return TARGETS.programs || []; }
        var courses = [];
        (TARGETS.categories || []).forEach(function(cat){
            (cat.courses || []).forEach(function(co){ courses.push({ id:co.id, name:co.fullname }); });
        });
        return courses;
    }
    function fillList(block){
        var type = block.getAttribute('data-type');
        var sel = block.querySelector('.scope-list');
        sel.innerHTML = '';
        targetsFor(type).forEach(function(it){
            var opt = document.createElement('option');
            opt.value = it.id; opt.textContent = it.name;
            sel.appendChild(opt);
        });
    }
    function wireScopeBlock(block){
        var on = block.querySelector('.scope-on');
        var detail = block.querySelector('.scope-detail');
        var mode = block.querySelector('.scope-mode');
        var list = block.querySelector('.scope-list');
        on.addEventListener('change', function(){ detail.style.display = on.checked ? 'block' : 'none'; });
        mode.addEventListener('change', function(){ list.style.display = mode.value === 'specific' ? 'block' : 'none'; });
    }
    function scopeBlocks(){ return Array.prototype.slice.call(document.querySelectorAll('#o-scope .scope-block')); }
    function resetScope(){
        scopeBlocks().forEach(function(block){
            block.querySelector('.scope-on').checked = false;
            block.querySelector('.scope-detail').style.display = 'none';
            block.querySelector('.scope-mode').value = 'all';
            var list = block.querySelector('.scope-list');
            list.style.display = 'none';
            fillList(block);
            Array.prototype.forEach.call(list.options, function(o){ o.selected = false; });
        });
    }
    function applyScope(appliesTo){
        resetScope();
        var byType = {};
        (appliesTo || []).forEach(function(a){ (byType[a.item_type] = byType[a.item_type] || []).push(a.item_id); });
        scopeBlocks().forEach(function(block){
            var type = block.getAttribute('data-type');
            var ids = byType[type];
            if (!ids){ return; }
            block.querySelector('.scope-on').checked = true;
            block.querySelector('.scope-detail').style.display = 'block';
            if (ids.indexOf(0) !== -1){
                block.querySelector('.scope-mode').value = 'all';
            } else {
                block.querySelector('.scope-mode').value = 'specific';
                var list = block.querySelector('.scope-list');
                list.style.display = 'block';
                Array.prototype.forEach.call(list.options, function(o){
                    if (ids.indexOf(parseInt(o.value, 10)) !== -1){ o.selected = true; }
                });
            }
        });
    }
    function collectItems(){
        var items = [];
        scopeBlocks().forEach(function(block){
            if (!block.querySelector('.scope-on').checked){ return; }
            var type = block.getAttribute('data-type');
            if (block.querySelector('.scope-mode').value === 'all'){
                items.push({ item_type:type, item_id:0 });
            } else {
                Array.prototype.forEach.call(block.querySelector('.scope-list').selectedOptions, function(o){
                    items.push({ item_type:type, item_id:parseInt(o.value, 10) });
                });
            }
        });
        return items;
    }

    // ── Reusable confirmation modal ──
    var confirmCb = null;
    function openConfirm(title, text, onOk){
        confirmCb = onOk;
        $('ofr-confirm-title').textContent = title;
        $('ofr-confirm-text').textContent = text;
        $('ofr-confirm-backdrop').style.display = 'flex';
    }
    function closeConfirm(){ confirmCb = null; $('ofr-confirm-backdrop').style.display = 'none'; }
    $('ofr-confirm-cancel').addEventListener('click', closeConfirm);
    $('ofr-confirm-backdrop').addEventListener('click', function(ev){ if (ev.target === this){ closeConfirm(); } });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && $('ofr-confirm-backdrop').style.display !== 'none'){ closeConfirm(); }
    });
    $('ofr-confirm-ok').addEventListener('click', function(){
        var cb = confirmCb;
        closeConfirm();
        if (cb){ cb(); }
    });

    function showForm(o){
        var nm = parseMultilang(o ? (o.name_raw || o.name) : '');
        $('ofr-form-title').textContent = o ? strf('ofr_edit_titled', displayName(o.name_raw || o.name)) : str('ofr_new');
        $('o-id').value    = o ? o.id : '';
        $('o-name-en').value = nm.en;
        $('o-name-ar').value = nm.ar;
        $('o-dtype').value = o ? o.discount_type : 'percent';
        $('o-value').value = o ? o.discount_value : '';
        $('o-start').value = toInput(o ? o.startdate : 0);
        $('o-end').value   = toInput(o ? o.enddate : 0);
        $('o-active').checked = o ? (o.status === 'active') : true;
        applyScope(o ? o.applies_to : []);
        $('ofr-form-card').style.display = 'block';
    }
    function hideForm(){ $('ofr-form-card').style.display = 'none'; }

    function save(){
        var items = collectItems();
        if (!items.length){ msg(str('cpn_scope_required'), 'danger'); return; }
        var id = $('o-id').value;
        var params = {
            name: buildMultilang($('o-name-en').value, $('o-name-ar').value),
            discount_type: $('o-dtype').value,
            discount_value: $('o-value').value || 0,
            startdate: fromInput($('o-start').value),
            enddate: fromInput($('o-end').value),
            items: JSON.stringify(items)
        };
        var p;
        if (id){
            params.id = id;
            params.status = $('o-active').checked ? 'active' : 'inactive';
            p = api('update_offer', params, 'POST');
        } else {
            params.active = $('o-active').checked ? 1 : 0;
            p = api('create_offer', params, 'POST');
        }
        p.then(function(){
            msg(id ? str('ofr_updated') : str('ofr_created'), 'success');
            hideForm(); load();
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    $('ofr-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-act]');
        if (!btn){ return; }
        var id = btn.getAttribute('data-id'), act = btn.getAttribute('data-act');
        if (act === 'edit'){ showForm(btn.closest('tr')._o); return; }
        if (act === 'activate'){
            api('activate_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_activated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'deactivate'){
            api('deactivate_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_deactivated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'delete'){
            openConfirm(str('ofr_delete_title'), str('ofr_confirm_delete'), function(){
                api('delete_offer', { id:id }, 'POST').then(function(){ msg(str('ofr_deleted'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
            });
        }
    });

    $('ofr-new').addEventListener('click', function(){ showForm(null); });
    $('ofr-refresh').addEventListener('click', load);
    $('ofr-save').addEventListener('click', save);
    $('ofr-cancel').addEventListener('click', hideForm);

    scopeBlocks().forEach(wireScopeBlock);

    api('get_discount_targets').then(function(t){
        TARGETS = t;
        scopeBlocks().forEach(fillList);
    }).catch(function(e){ msg(e.message, 'danger'); }).then(load);
})();
JS
);

echo $OUTPUT->footer();
