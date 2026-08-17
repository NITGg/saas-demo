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
 * Admin UI to manage discount coupons. Drives /local/nit_commerce/api.php from vanilla JS.
 * UI labels match the reference local_academy plugin.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

admin_externalpage_setup('local_nit_commerce_managecoupons');
require_capability('local/nit_commerce:managecoupons', context_system::instance());

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('managecoupons', 'local_nit_commerce'));
$PAGE->set_heading(get_string('managecoupons', 'local_nit_commerce'));
$PAGE->requires->js(new moodle_url('/local/nit_commerce/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecoupons', 'local_nit_commerce'));

$STR = local_nit_commerce_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_save', 'ui_cancel', 'ui_active', 'ui_activate', 'ui_deactivate',
    'ui_edit', 'ui_delete', 'ui_never', 'ui_optional', 'ui_pager_info',
    'pkg_col_status', 'pkg_col_actions', 'sub_inactive',
    'cpn_new', 'cpn_none', 'cpn_col_code', 'cpn_col_type', 'cpn_col_value', 'cpn_col_scope',
    'cpn_col_usage', 'cpn_col_dates', 'cpn_field_code', 'cpn_field_dtype', 'cpn_field_value',
    'cpn_field_max', 'cpn_field_utype', 'cpn_field_limit', 'cpn_field_start', 'cpn_field_end',
    'cpn_field_scope', 'cpn_type_percent', 'cpn_type_fixed', 'cpn_usage_once', 'cpn_usage_multiple',
    'cpn_scope_courses', 'cpn_scope_packages', 'cpn_scope_subscriptions', 'cpn_scope_programs', 'cpn_scope_all',
    'cpn_scope_specific', 'cpn_created', 'cpn_updated', 'cpn_activated', 'cpn_deactivated',
    'cpn_deleted', 'cpn_confirm_delete', 'cpn_edit_titled', 'cpn_scope_required', 'cpn_unlimited',
    'cpn_used_count',
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
<div id="academy-coupon-app">
    <div id="cpn-message" class="alert" style="display:none"></div>

    <div class="mb-3">
        <button id="cpn-new" class="btn btn-primary"><?php echo $STR['cpn_new']; ?></button>
        <button id="cpn-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <table class="table table-striped" id="cpn-table">
        <thead>
            <tr>
                <th><?php echo $STR['cpn_col_code']; ?></th>
                <th><?php echo $STR['cpn_col_type']; ?></th>
                <th><?php echo $STR['cpn_col_value']; ?></th>
                <th><?php echo $STR['cpn_col_scope']; ?></th>
                <th><?php echo $STR['cpn_col_usage']; ?></th>
                <th><?php echo $STR['cpn_col_dates']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="8"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="cpn-table-pager" class="acad-pager"></div>

    <div id="cpn-form-card" class="card" style="display:none; max-width:640px;">
        <div class="card-body">
            <h4 id="cpn-form-title" class="card-title"><?php echo $STR['cpn_new']; ?></h4>
            <input type="hidden" id="c-id">
            <div class="form-group">
                <label for="c-code"><?php echo $STR['cpn_field_code']; ?></label>
                <input type="text" class="form-control" id="c-code" dir="ltr">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-dtype"><?php echo $STR['cpn_field_dtype']; ?></label>
                    <select class="form-control" id="c-dtype">
                        <option value="percent"><?php echo $STR['cpn_type_percent']; ?></option>
                        <option value="fixed"><?php echo $STR['cpn_type_fixed']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="c-value"><?php echo $STR['cpn_field_value']; ?></label>
                    <input type="number" class="form-control" id="c-value" min="0" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label for="c-max"><?php echo $STR['cpn_field_max']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                <input type="number" class="form-control" id="c-max" min="0" step="0.01">
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-utype"><?php echo $STR['cpn_field_utype']; ?></label>
                    <select class="form-control" id="c-utype">
                        <option value="multiple"><?php echo $STR['cpn_usage_multiple']; ?></option>
                        <option value="once"><?php echo $STR['cpn_usage_once']; ?></option>
                    </select>
                </div>
                <div class="form-group col-md-6" id="c-limit-wrap">
                    <label for="c-limit"><?php echo $STR['cpn_field_limit']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="number" class="form-control" id="c-limit" min="0" step="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="c-start"><?php echo $STR['cpn_field_start']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="c-start">
                </div>
                <div class="form-group col-md-6">
                    <label for="c-end"><?php echo $STR['cpn_field_end']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span></label>
                    <input type="datetime-local" class="form-control" id="c-end">
                </div>
            </div>

            <label class="d-block"><strong><?php echo $STR['cpn_field_scope']; ?></strong></label>
            <div id="c-scope" class="mb-3 p-2" style="border:1px solid #dee2e6; border-radius:6px;">
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
                    echo '<input type="checkbox" class="form-check-input scope-on" id="scope-' . $t . '-on">';
                    echo '<label class="form-check-label" for="scope-' . $t . '-on"><strong>' . $label . '</strong></label>';
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
                <input type="checkbox" class="form-check-input" id="c-active" checked>
                <label class="form-check-label" for="c-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="cpn-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="cpn-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>
</div>
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
        var el = $('cpn-message');
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

    function toInput(ts){
        if (!ts){ return ''; }
        var d = new Date(ts * 1000), p = function(n){ return (n<10?'0':'')+n; };
        return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());
    }
    function fromInput(v){ return v ? Math.floor(new Date(v).getTime()/1000) : 0; }

    function fmtDate(ts){ return ts ? new Date(ts*1000).toLocaleDateString() : str('ui_never'); }
    function dtype(t){ return t === 'fixed' ? str('cpn_type_fixed') : str('cpn_type_percent'); }
    function valueLabel(c){ return c.discount_type === 'percent' ? (c.discount_value + '%') : c.discount_value; }
    function scopeLabel(c){
        if (!c.applies_to || !c.applies_to.length){ return '<span class="text-muted">—</span>'; }
        return c.applies_to.map(function(a){ return esc(a.label); }).join(', ');
    }
    function usageLabel(c){
        if (c.usage_type === 'once'){ return str('cpn_usage_once') + ' (' + c.usage_count + ')'; }
        var cap = c.usage_limit > 0 ? c.usage_limit : str('cpn_unlimited');
        return strf('cpn_used_count', c.usage_count) + ' / ' + cap;
    }

    function renderRows(items){
        var tbody = $('cpn-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(c){
            var tr = document.createElement('tr');
            var toggle = c.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="'+c.id+'">'+esc(str('ui_deactivate'))+'</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="'+c.id+'">'+esc(str('ui_activate'))+'</button>';
            tr.innerHTML =
                '<td><code>'+esc(c.code)+'</code></td>'+
                '<td>'+esc(dtype(c.discount_type))+'</td>'+
                '<td>'+esc(valueLabel(c))+(c.max_discount!=null?' <small class="text-muted">(max '+esc(c.max_discount)+')</small>':'')+'</td>'+
                '<td>'+scopeLabel(c)+'</td>'+
                '<td>'+esc(usageLabel(c))+'</td>'+
                '<td>'+esc(fmtDate(c.startdate))+' → '+esc(fmtDate(c.enddate))+'</td>'+
                '<td>'+esc(c.status === 'active' ? str('ui_active') : str('sub_inactive'))+'</td>'+
                '<td>'+
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="'+c.id+'">'+esc(str('ui_edit'))+'</button> '+
                    toggle+' '+
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="'+c.id+'">'+esc(str('ui_delete'))+'</button>'+
                '</td>';
            tr._c = c;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('cpn-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="8">'+esc(str('ui_loading'))+'</td></tr>';
        api('get_coupons').then(function(rows){
            if (!rows.length){
                tbody.innerHTML = '<tr><td colspan="8">'+esc(str('cpn_none'))+'</td></tr>';
                $('cpn-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(rows); }
            else {
                pager = AcademyUI.paginate({ rows:rows, pageSize:PAGE_SIZE, pagerEl:$('cpn-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Scope editor ──
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
            var o = document.createElement('option');
            o.value = it.id; o.textContent = it.name;
            sel.appendChild(o);
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
    function scopeBlocks(){ return Array.prototype.slice.call(document.querySelectorAll('#c-scope .scope-block')); }

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

    function showForm(c){
        $('cpn-form-title').textContent = c ? strf('cpn_edit_titled', c.code) : str('cpn_new');
        $('c-id').value    = c ? c.id : '';
        $('c-code').value  = c ? c.code : '';
        $('c-dtype').value = c ? c.discount_type : 'percent';
        $('c-value').value = c ? c.discount_value : '';
        $('c-max').value   = (c && c.max_discount != null) ? c.max_discount : '';
        $('c-utype').value = c ? c.usage_type : 'multiple';
        $('c-limit').value = (c && c.usage_limit) ? c.usage_limit : '';
        $('c-start').value = toInput(c ? c.startdate : 0);
        $('c-end').value   = toInput(c ? c.enddate : 0);
        $('c-active').checked = c ? (c.status === 'active') : true;
        applyScope(c ? c.applies_to : []);
        $('cpn-form-card').style.display = 'block';
    }
    function hideForm(){ $('cpn-form-card').style.display = 'none'; }

    function save(){
        var items = collectItems();
        if (!items.length){ msg(str('cpn_scope_required'), 'danger'); return; }
        var id = $('c-id').value;
        var params = {
            code: $('c-code').value,
            discount_type: $('c-dtype').value,
            discount_value: $('c-value').value || 0,
            max_discount: $('c-max').value === '' ? '' : $('c-max').value,
            usage_type: $('c-utype').value,
            usage_limit: $('c-limit').value || 0,
            startdate: fromInput($('c-start').value),
            enddate: fromInput($('c-end').value),
            items: JSON.stringify(items)
        };
        var p;
        if (id){
            params.id = id;
            params.status = $('c-active').checked ? 'active' : 'inactive';
            p = api('update_coupon', params, 'POST');
        } else {
            params.active = $('c-active').checked ? 1 : 0;
            p = api('create_coupon', params, 'POST');
        }
        p.then(function(){
            msg(id ? str('cpn_updated') : str('cpn_created'), 'success');
            hideForm(); load();
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    $('cpn-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('button[data-act]');
        if (!btn){ return; }
        var id = btn.getAttribute('data-id'), act = btn.getAttribute('data-act');
        if (act === 'edit'){ showForm(btn.closest('tr')._c); return; }
        if (act === 'activate'){
            api('activate_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_activated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'deactivate'){
            api('deactivate_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_deactivated'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        } else if (act === 'delete'){
            if (!confirm(str('cpn_confirm_delete'))){ return; }
            api('delete_coupon', { id:id }, 'POST').then(function(){ msg(str('cpn_deleted'),'success'); load(); }).catch(function(e){ msg(e.message,'danger'); });
        }
    });

    $('cpn-new').addEventListener('click', function(){ showForm(null); });
    $('cpn-refresh').addEventListener('click', load);
    $('cpn-save').addEventListener('click', save);
    $('cpn-cancel').addEventListener('click', hideForm);

    scopeBlocks().forEach(wireScopeBlock);

    // Load the selectable courses/packages/subscriptions, then the coupon list.
    api('get_discount_targets').then(function(t){
        TARGETS = t;
        scopeBlocks().forEach(fillList);
    }).catch(function(e){ msg(e.message, 'danger'); }).then(load);
})();
JS
);

echo $OUTPUT->footer();
