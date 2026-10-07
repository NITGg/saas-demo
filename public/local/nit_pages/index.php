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
 * Static pages management screen (Site admins only).
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_pages\page_manager;

admin_externalpage_setup('local_nit_pages_manage');

$context = \context_system::instance();
require_capability('local/nit_pages:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$pageid = optional_param('id', 0, PARAM_INT);

// Handle actions.
if ($action === 'toggle' && $pageid > 0 && confirm_sesskey()) {
    page_manager::toggle_status($pageid);
    redirect(new \moodle_url('/local/nit_pages/index.php'), get_string('page_saved', 'local_nit_pages'));
}
if ($action === 'delete' && $pageid > 0 && confirm_sesskey()) {
    try {
        page_manager::delete_page($pageid);
        redirect(new \moodle_url('/local/nit_pages/index.php'), get_string('page_deleted', 'local_nit_pages'));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

$pages = page_manager::get_all_pages();

$currlang = current_language();
$isar = (strpos($currlang, 'ar') === 0);

$PAGE->set_title(get_string('pages_dashboard', 'local_nit_pages'));
$PAGE->set_heading(get_string('pages_dashboard', 'local_nit_pages'));

echo $OUTPUT->header();

// Tabs / Navigation header.
echo '
<div style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
  <ul class="nav nav-tabs" style="border-bottom:2px solid var(--nit-brand-border, #223244);">
    <li class="nav-item">
      <a class="nav-link active fw-bold" href="' . (new \moodle_url('/local/nit_pages/index.php'))->out() . '">
        ' . get_string('manage_pages', 'local_nit_pages') . '
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="' . (new \moodle_url('/local/nit_pages/manage_articles.php'))->out() . '">
        ' . get_string('manage_articles', 'local_nit_pages') . '
      </a>
    </li>
  </ul>
  <div>
    <a href="' . (new \moodle_url('/local/nit_pages/edit_page.php'))->out() . '" class="btn btn-primary" style="border-radius:8px;font-weight:700;">
      + ' . get_string('add_page', 'local_nit_pages') . '
    </a>
  </div>
</div>';

echo '<div class="card" style="border-radius:14px;overflow:hidden;border:1px solid var(--nit-brand-border, #223244);background:var(--nit-brand-surface, #121e2d);">';
echo '<div class="table-responsive">';
echo '<table class="table table-hover align-middle mb-0" style="color:var(--nit-brand-textprimary, #eef3f9);">';
echo '<thead style="background:rgba(0,0,0,0.15);font-size:13px;text-transform:uppercase;letter-spacing:0.04em;">';
echo '<tr>';
echo '<th style="padding:16px 20px;">' . ($isar ? 'عنوان الصفحة' : 'Page Title') . '</th>';
echo '<th>' . ($isar ? 'الرابط (Slug)' : 'Link (Slug)') . '</th>';
echo '<th>' . get_string('status', 'local_nit_pages') . '</th>';
echo '<th>' . get_string('last_updated', 'local_nit_pages') . '</th>';
echo '<th style="text-align:end;padding-inline-end:20px;">' . get_string('actions', 'local_nit_pages') . '</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($pages as $p) {
    $title = $isar ? $p->title_ar : $p->title_en;
    $subtitle = $isar ? $p->title_en : $p->title_ar;
    $slug = $isar ? $p->slug_ar : $p->slug_en;
    $fullurl = (new \moodle_url('/local/nit_pages/page.php', ['p' => $slug]))->out(false);
    $viewediturl = (new \moodle_url('/local/nit_pages/page.php', ['p' => $slug, 'edit' => 1]))->out();
    $editsettingsurl = (new \moodle_url('/local/nit_pages/edit_page.php', ['id' => $p->id]))->out();
    $toggleurl = (new \moodle_url('/local/nit_pages/index.php', ['action' => 'toggle', 'id' => $p->id, 'sesskey' => sesskey()]))->out();
    $deleteurl = (new \moodle_url('/local/nit_pages/index.php', ['action' => 'delete', 'id' => $p->id, 'sesskey' => sesskey()]))->out();

    $statusbadge = ($p->status === page_manager::STATUS_PUBLISHED)
        ? '<a href="' . $toggleurl . '" class="badge bg-success text-decoration-none" style="font-size:12px;padding:6px 12px;border-radius:20px;">' . get_string('published', 'local_nit_pages') . '</a>'
        : '<a href="' . $toggleurl . '" class="badge bg-warning text-dark text-decoration-none" style="font-size:12px;padding:6px 12px;border-radius:20px;">' . get_string('draft', 'local_nit_pages') . '</a>';

    $defaultbadge = !empty($p->is_default)
        ? '<span class="badge bg-info text-dark ms-1 me-1" style="font-size:11px;">' . get_string('is_default', 'local_nit_pages') . '</span>'
        : '';

    $accessbadge = !empty($p->loggedin_only)
        ? '<span class="badge bg-secondary ms-1 me-1" style="font-size:11px;">' . get_string('loggedin_only', 'local_nit_pages') . '</span>'
        : '';

    $updatedstr = $p->timemodified ? userdate($p->timemodified, get_string('strftimedatetimeshort', 'langconfig')) : '-';

    echo '<tr>';
    echo '<td style="padding:16px 20px;">';
    echo '<div class="fw-bold" style="font-size:15px;color:var(--nit-brand-textprimary, #eef3f9);">' . s($title) . ' ' . $defaultbadge . ' ' . $accessbadge . '</div>';
    echo '<div style="font-size:12px;color:var(--nit-brand-textsecondary, #94a3b8);">' . s($subtitle) . '</div>';
    echo '</td>';

    echo '<td>';
    echo '<div style="display:flex;align-items:center;gap:8px;">';
    echo '<code style="background:rgba(0,0,0,0.2);padding:4px 8px;border-radius:6px;font-size:13px;color:#7fabdb;">/local/nit_pages/page.php?p=' . s($slug) . '</code>';
    echo '<button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-url="' . s($fullurl) . '" title="' . s(get_string('copy_link', 'local_nit_pages')) . '" style="padding:2px 8px;font-size:12px;border-radius:6px;">📋</button>';
    echo '</div>';
    echo '</td>';

    echo '<td>' . $statusbadge . '</td>';
    echo '<td style="font-size:13px;color:var(--nit-brand-textsecondary, #94a3b8);">' . $updatedstr . '</td>';

    echo '<td style="text-align:end;padding-inline-end:20px;">';
    echo '<div class="btn-group btn-group-sm" role="group">';
    echo '<a href="' . $viewediturl . '" class="btn btn-outline-primary" style="font-weight:700;" title="' . s(get_string('view_edit_content', 'local_nit_pages')) . '">👁️ ' . get_string('view_edit_content', 'local_nit_pages') . '</a>';
    echo '<a href="' . $editsettingsurl . '" class="btn btn-outline-secondary" title="' . s(get_string('edit_settings', 'local_nit_pages')) . '">⚙️ ' . get_string('edit_settings', 'local_nit_pages') . '</a>';
    if (empty($p->is_default)) {
        echo '<a href="' . $deleteurl . '" class="btn btn-outline-danger" onclick="return confirm(\'' . s(get_string('delete_confirm_page', 'local_nit_pages')) . '\');" title="' . s(get_string('delete', 'local_nit_pages')) . '">🗑️</a>';
    } else {
        echo '<button class="btn btn-outline-secondary" disabled title="' . s(get_string('err_cannot_delete_default', 'local_nit_pages')) . '">🔒</button>';
    }
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';

// Copy link JS.
echo '
<script>
document.querySelectorAll(".copy-btn").forEach(function(btn) {
  btn.addEventListener("click", function() {
    var url = this.getAttribute("data-url");
    navigator.clipboard.writeText(url).then(function() {
      var orig = btn.innerText;
      btn.innerText = "✓";
      setTimeout(function() { btn.innerText = orig; }, 1500);
    });
  });
});
</script>';

echo $OUTPUT->footer();
