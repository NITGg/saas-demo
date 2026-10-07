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
 * Add / Edit static page settings (Site admins only).
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

$pageid = optional_param('id', 0, PARAM_INT);
$page = $pageid ? page_manager::get_page_by_id($pageid) : null;

if ($pageid && !$page) {
    throw new \moodle_exception('page_not_found', 'local_nit_pages');
}

$title_str = $page ? get_string('edit_page', 'local_nit_pages') : get_string('add_page', 'local_nit_pages');
$PAGE->set_title($title_str);
$PAGE->set_heading($title_str);

// Handle form submit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $title_ar = required_param('title_ar', PARAM_TEXT);
    $title_en = required_param('title_en', PARAM_TEXT);
    $slug_ar = optional_param('slug_ar', '', PARAM_RAW_TRIMMED);
    $slug_en = optional_param('slug_en', '', PARAM_RAW_TRIMMED);
    $seo_desc_ar = optional_param('seo_desc_ar', '', PARAM_TEXT);
    $seo_desc_en = optional_param('seo_desc_en', '', PARAM_TEXT);

    if ($slug_ar === '') {
        $slug_ar = page_manager::clean_slug($title_ar);
    }
    if ($slug_en === '') {
        $slug_en = page_manager::clean_slug($title_en);
    }

    $data = [
        'title_ar'      => $title_ar,
        'title_en'      => $title_en,
        'slug_ar'       => $slug_ar,
        'slug_en'       => $slug_en,
        'seo_desc_ar'   => $seo_desc_ar,
        'seo_desc_en'   => $seo_desc_en,
    ];
    if ($page) {
        $data['id'] = $page->id;
        $data['share_image'] = $page->share_image ?? '';
        $data['status'] = $page->status ?? page_manager::STATUS_PUBLISHED;
        $data['loggedin_only'] = $page->loggedin_only ?? 0;
    } else {
        $data['share_image'] = '';
        $data['status'] = page_manager::STATUS_PUBLISHED;
        $data['loggedin_only'] = 0;
    }

    $saved = page_manager::save_page($data);
    redirect(new \moodle_url('/local/nit_pages/index.php'), get_string('page_saved', 'local_nit_pages'));
}

echo $OUTPUT->header();

$val = fn($prop, $default = '') => s($page->$prop ?? $default);
$currlang = current_language();
$isar = (strpos($currlang, 'ar') === 0);

echo '
<div style="max-width:840px;margin:24px auto 60px;font-family:\'Tajawal\',\'Almarai\',sans-serif;">
  <div style="margin-bottom:20px;">
    <a href="' . (new \moodle_url('/local/nit_pages/index.php'))->out() . '" style="color:#64748b;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
      ← ' . get_string('back_to_pages', 'local_nit_pages') . '
    </a>
  </div>

  <div class="card" style="border-radius:20px;border:1px solid #e2e8f0;background:#ffffff;box-shadow:0 6px 20px rgba(0,0,0,0.04);padding:36px;">
    <h2 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 24px;">' . $title_str . '</h2>

    <form method="post" action="' . $PAGE->url->out(false) . '">
      <input type="hidden" name="sesskey" value="' . sesskey() . '">
      ' . ($page ? '<input type="hidden" name="id" value="' . $page->id . '">' : '') . '

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('title_ar', 'local_nit_pages') . ' *</label>
          <input type="text" name="title_ar" class="form-control" required value="' . $val('title_ar') . '" placeholder="عن المنصة" dir="rtl" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('title_en', 'local_nit_pages') . ' *</label>
          <input type="text" name="title_en" class="form-control" required value="' . $val('title_en') . '" placeholder="About Us" dir="ltr" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('slug_ar', 'local_nit_pages') . '</label>
          <input type="text" name="slug_ar" class="form-control" value="' . $val('slug_ar') . '" placeholder="عن-المنصة" dir="rtl" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('slug_en', 'local_nit_pages') . '</label>
          <input type="text" name="slug_en" class="form-control" value="' . $val('slug_en') . '" placeholder="about" dir="ltr" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('seo_desc_ar', 'local_nit_pages') . '</label>
          <textarea name="seo_desc_ar" rows="3" class="form-control" dir="rtl" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">' . $val('seo_desc_ar') . '</textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;">' . get_string('seo_desc_en', 'local_nit_pages') . '</label>
          <textarea name="seo_desc_en" rows="3" class="form-control" dir="ltr" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">' . $val('seo_desc_en') . '</textarea>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn fw-bold" style="background:#1b75d0;border-color:#1b75d0;color:#ffffff;border-radius:10px;padding:10px 24px;">' . ($isar ? 'حفظ الصفحة' : 'Save Page') . '</button>
        <a href="' . (new \moodle_url('/local/nit_pages/index.php'))->out() . '" class="btn btn-outline-secondary fw-bold" style="border-radius:10px;padding:10px 20px;border-color:#cbd5e1;color:#334155;">' . ($isar ? 'إلغاء' : 'Cancel') . '</a>
      </div>
    </form>
  </div>
</div>';

echo $OUTPUT->footer();
