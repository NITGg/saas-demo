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
 * Admin UI to manage single-course purchases: see who bought which course and "unbuy" (revoke) a
 * purchase, unenrolling the buyer. Sibling of manage_subscriptions.php (sesskey + inline JS +
 * AcademyUI.paginate). Reuses the subscriptions capability so no new capability/DB migration is
 * required.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

admin_externalpage_setup('local_nit_subscriptions_managecourses');
require_capability('local/nit_subscriptions:managesubscriptions', context_system::instance());
local_nit_subscriptions_require_feature();

global $OUTPUT, $CFG, $PAGE;

$PAGE->set_title(get_string('managecourses', 'local_nit_subscriptions'));
$PAGE->set_heading(get_string('managecourses', 'local_nit_subscriptions'));
$PAGE->requires->js(new moodle_url('/local/nit_subscriptions/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecourses', 'local_nit_subscriptions'));

// Localised strings: server-rendered HTML reads $STR['key']; the JS reads window.ACADEMY_STR.
$STR = local_nit_subscriptions_string_map(array(
    'ui_refresh', 'ui_loading', 'ui_cancel', 'ui_optional', 'ui_pager_info',
    'pkg_col_user', 'pkg_col_pricepaid', 'pkg_col_status', 'pkg_col_actions',
    'mc_desc', 'mc_col_course', 'mc_col_purchased', 'mc_none',
    'mc_status_enrolled', 'mc_status_norole', 'mc_unbuy', 'mc_unbuy_title', 'mc_unbuy_confirm',
    'mc_unbuy_refund', 'mc_unbuy_success',
    'err_sessionexpired', 'err_requestfailed',
));

echo html_writer::script('window.ACADEMY_MC = ' . json_encode(array(
    'endpoint' => (new moodle_url('/local/nit_subscriptions/api.php'))->out(false),
    'sesskey'  => sesskey(),
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
?>
<style>
    .academy-modal-backdrop { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5);
        display:flex; align-items:center; justify-content:center; z-index:1050; }
    .academy-modal { background:#fff; border-radius:10px; padding:1.5rem; max-width:440px; width:90%;
        box-shadow:0 12px 30px rgba(0,0,0,0.25); }
    .academy-modal-title { margin-bottom:.75rem; font-weight:600; }
    .academy-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.25rem; }
    .acad-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
    .acad-pager__info { margin-inline-end:auto; color:#6a6f73; font-size:.9rem; }
    .acad-pager button { border:1px solid #dee2e6; background:#fff; border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
    .acad-pager button.is-active { background:#0f6cbf; border-color:#0f6cbf; color:#fff; }
    .acad-pager button:disabled { opacity:.5; cursor:default; }
</style>
<div id="academy-mc-app">
    <div id="mc-message" class="alert" style="display:none"></div>

    <p class="text-muted" style="background:#fff3cd;padding:8px 12px;border-radius:6px;"><?php echo $STR['mc_desc']; ?></p>
    <button id="mc-refresh" class="btn btn-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
    <table class="table table-striped" id="mc-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_user']; ?></th>
                <th><?php echo $STR['mc_col_course']; ?></th>
                <th><?php echo $STR['pkg_col_pricepaid']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['mc_col_purchased']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="mc-table-pager" class="acad-pager"></div>

    <!-- ── Unbuy confirmation modal ── -->
    <div id="mc-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title"><?php echo $STR['mc_unbuy_title']; ?></h5>
            <p id="mc-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="mc-refund-checkbox">
                <label class="form-check-label" for="mc-refund-checkbox">
                    <?php echo $STR['mc_unbuy_refund']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="mc-modal-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
                <button id="mc-modal-confirm" class="btn btn-danger"><?php echo $STR['mc_unbuy']; ?></button>
            </div>
        </div>
    </div>
</div>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_MC;
    var STR = window.ACADEMY_STR || {};
    function str(k){ return (k in STR) ? STR[k] : k; }
    function strf(k, params){
        var s = str(k);
        if (params == null){ return s; }
        if (typeof params !== 'object'){ return s.replace(/\{\$a\}/g, params); }
        return s.replace(/\{\$a->(\w+)\}/g, function(m, name){ return (name in params) ? params[name] : m; });
    }
    function $(id){ return document.getElementById(id); }

    var PAGE_SIZE = 10, pager = null;

    function msg(text, type){
        var el = $('mc-message');
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
    // GET for reads, POST for mutations (the API requires POST + sesskey for state changes).
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
    function money(n){ return Number(n || 0).toFixed(2); }
    function fmtDate(ts){ return ts ? new Date(ts * 1000).toLocaleString() : '—'; }

    function renderRows(items){
        var tbody = $('mc-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(r){
            var tr = document.createElement('tr');
            var statusLabel = r.enrolled ? str('mc_status_enrolled') : str('mc_status_norole');
            var statusClass = r.enrolled ? 'badge-success' : 'badge-secondary';
            // Only an enrolled buyer can be unbought.
            var action = r.enrolled
                ? '<button class="btn btn-sm btn-danger btn-unbuy" data-id="' + r.id + '">' + esc(str('mc_unbuy')) + '</button>'
                : '';
            tr.innerHTML =
                '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                '<td>' + esc(r.course_fullname) + '</td>' +
                '<td>' + esc(money(r.amount)) + ' ' + esc(r.currency || '') + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + esc(statusLabel) + '</span></td>' +
                '<td>' + esc(fmtDate(r.timecreated)) + '</td>' +
                '<td>' + action + '</td>';
            tr._row = r;
            tbody.appendChild(tr);
        });
    }

    function load(){
        var tbody = $('mc-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="6">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_all_course_purchases').then(function(rows){
            if (!rows.length){
                tbody.innerHTML = '<tr><td colspan="6">' + esc(str('mc_none')) + '</td></tr>';
                $('mc-table-pager').innerHTML = '';
                return;
            }
            if (pager){ pager.setRows(rows); }
            else {
                pager = AcademyUI.paginate({ rows:rows, pageSize:PAGE_SIZE, pagerEl:$('mc-table-pager'),
                    labels:{ info:str('ui_pager_info') }, render:renderRows });
            }
        }).catch(function(e){ msg(e.message, 'danger'); });
    }

    // ── Unbuy confirmation modal ──
    var pending = null;
    function openModal(row){
        pending = row;
        $('mc-modal-text').innerHTML = strf('mc_unbuy_confirm', {
            user: esc(row.user_fullname), course: esc(row.course_fullname)
        });
        $('mc-refund-checkbox').checked = false;
        $('mc-modal-backdrop').style.display = 'flex';
    }
    function closeModal(){ pending = null; $('mc-modal-backdrop').style.display = 'none'; }

    $('mc-table').addEventListener('click', function(ev){
        var btn = ev.target.closest('.btn-unbuy');
        if (!btn){ return; }
        openModal(btn.closest('tr')._row);
    });
    $('mc-modal-cancel').addEventListener('click', closeModal);
    $('mc-modal-backdrop').addEventListener('click', function(ev){ if (ev.target === this){ closeModal(); } });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && $('mc-modal-backdrop').style.display !== 'none'){ closeModal(); }
    });
    $('mc-modal-confirm').addEventListener('click', function(){
        var row = pending;
        if (!row){ return; }
        var refund = $('mc-refund-checkbox').checked;
        api('revoke_course_purchase', { transactionid: row.id, refund: refund ? 1 : 0 }, 'POST').then(function(){
            msg(str('mc_unbuy_success'), 'success');
            closeModal();
            load();
        }).catch(function(e){ msg(e.message, 'danger'); });
    });

    $('mc-refresh').addEventListener('click', load);
    load();
})();
JS
);

echo $OUTPUT->footer();
