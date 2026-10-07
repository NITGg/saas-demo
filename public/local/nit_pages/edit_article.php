<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_nit_pages_articles');
$context = \context_system::instance();
require_capability('local/nit_pages:manage', $context);

use local_nit_pages\article_manager;

$id = optional_param('id', 0, PARAM_INT);
$article = $id ? article_manager::get_article_by_id($id) : null;

if ($id && !$article) {
    throw new \moodle_exception('article_not_found', 'local_nit_pages');
}

$title_str = $article ? get_string('edit_article', 'local_nit_pages') : get_string('add_article', 'local_nit_pages');
$PAGE->set_url(new \moodle_url('/local/nit_pages/edit_article.php', $id ? ['id' => $id] : []));
$PAGE->set_title($title_str);
$PAGE->set_heading($title_str);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $title_ar = required_param('title_ar', PARAM_TEXT);
    $title_en = required_param('title_en', PARAM_TEXT);
    $slug = optional_param('slug', '', PARAM_RAW_TRIMMED);
    $summary_ar = optional_param('summary_ar', '', PARAM_TEXT);
    $summary_en = optional_param('summary_en', '', PARAM_TEXT);
    $body_ar = optional_param('body_ar', '', PARAM_RAW);
    $body_en = optional_param('body_en', '', PARAM_RAW);
    $status = optional_param('status', article_manager::STATUS_PUBLISHED, PARAM_ALPHA);
    $published_at_str = optional_param('published_at', '', PARAM_TEXT);

    $now = time();
    $pubtime = null;
    if ($published_at_str !== '') {
        $parsed = strtotime($published_at_str);
        if ($parsed !== false) {
            $pubtime = $parsed;
        }
    }

    // If published now (or within 24 hours), ensure it's not set in future so it displays immediately.
    if ($status === article_manager::STATUS_PUBLISHED) {
        if ($pubtime === null || abs($pubtime - $now) < 86400) {
            if ($pubtime !== null && $pubtime > $now) {
                $pubtime = $now;
            } else if ($pubtime === null) {
                $pubtime = $now;
            }
        }
    }

    $data = [
        'title_ar'   => $title_ar,
        'title_en'   => $title_en,
        'slug'       => $slug,
        'summary_ar' => $summary_ar,
        'summary_en' => $summary_en,
        'body_ar'    => $body_ar,
        'body_en'    => $body_en,
        'status'     => $status,
    ];
    if ($pubtime !== null) {
        $data['published_at'] = $pubtime;
    }
    if ($article) {
        $data['id'] = $article->id;
        $data['cover_image'] = $article->cover_image ?? '';
    } else {
        $data['cover_image'] = '';
    }

    $saved = article_manager::save_article($data, (int)$USER->id);
    $articleid = (int)$saved->id;

    // Handle image file upload.
    if (!empty($_FILES['cover_file']['name']) && is_uploaded_file($_FILES['cover_file']['tmp_name'])) {
        $imgcheck = @getimagesize($_FILES['cover_file']['tmp_name']);
        if ($imgcheck !== false) {
            $fs = get_file_storage();
            $sysctx = \context_system::instance();
            $filename = clean_param($_FILES['cover_file']['name'], PARAM_FILE);
            if ($filename === '') {
                $filename = 'cover_' . $articleid . '.jpg';
            }
            $filerecord = [
                'contextid' => $sysctx->id,
                'component' => 'local_nit_pages',
                'filearea'  => 'article_cover',
                'itemid'    => $articleid,
                'filepath'  => '/',
                'filename'  => $filename,
            ];
            $fs->delete_area_files($sysctx->id, 'local_nit_pages', 'article_cover', $articleid);
            $storedfile = $fs->create_file_from_pathname($filerecord, $_FILES['cover_file']['tmp_name']);
            if ($storedfile) {
                $coverurl = \moodle_url::make_pluginfile_url(
                    $sysctx->id,
                    'local_nit_pages',
                    'article_cover',
                    $articleid,
                    '/',
                    $filename
                )->out(false);
                $DB->set_field('nit_articles', 'cover_image', $coverurl, ['id' => $articleid]);
            }
        }
    }

    redirect(new \moodle_url('/local/nit_pages/manage_articles.php'), get_string('article_saved', 'local_nit_pages'));
}

echo $OUTPUT->header();

$val = fn($prop, $default = '') => s($article->$prop ?? $default);
$valraw = fn($prop, $default = '') => ($article->$prop ?? $default);
$currlang = current_language();
$isar = (strpos($currlang, 'ar') === 0);

$default_date = '';
if ($article && !empty($article->published_at)) {
    $default_date = date('Y-m-d\TH:i', $article->published_at);
} else {
    $default_date = date('Y-m-d\TH:i');
}
?>
<div style="max-width:920px;margin:24px auto 60px;font-family:'Tajawal','Almarai',sans-serif;">
  <div style="margin-bottom:20px;">
    <a href="<?php echo (new \moodle_url('/local/nit_pages/manage_articles.php'))->out(); ?>" style="color:#64748b;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
      ← <?php echo get_string('manage_articles', 'local_nit_pages'); ?>
    </a>
  </div>

  <div class="card" style="border-radius:20px;border:1px solid #e2e8f0;background:#ffffff;box-shadow:0 6px 20px rgba(0,0,0,0.04);padding:36px;">
    <h2 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 24px;"><?php echo $title_str; ?></h2>

    <form method="post" action="<?php echo $PAGE->url->out(false); ?>" enctype="multipart/form-data">
      <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
      <?php if ($article): ?>
        <input type="hidden" name="id" value="<?php echo $article->id; ?>">
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('title_ar', 'local_nit_pages'); ?> *</label>
          <input type="text" name="title_ar" class="form-control" required value="<?php echo $val('title_ar'); ?>" placeholder="عنوان المقال بالعربية" dir="rtl" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('title_en', 'local_nit_pages'); ?> *</label>
          <input type="text" name="title_en" class="form-control" required value="<?php echo $val('title_en'); ?>" placeholder="Article Title in English" dir="ltr" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('slug', 'local_nit_pages'); ?></label>
        <input type="text" name="slug" class="form-control" value="<?php echo $val('slug'); ?>" placeholder="unique-article-slug" dir="ltr" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        <small class="form-text text-muted" style="font-size:12px;"><?php echo ($isar ? 'اتركه فارغاً للتوليد التلقائي من العنوان.' : 'Leave empty to auto-generate from title.'); ?></small>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('summary_ar', 'local_nit_pages'); ?></label>
          <textarea name="summary_ar" rows="3" class="form-control" dir="rtl" placeholder="موجز المقال بالعربية..." style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;"><?php echo $val('summary_ar'); ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('summary_en', 'local_nit_pages'); ?></label>
          <textarea name="summary_en" rows="3" class="form-control" dir="ltr" placeholder="Article summary in English..." style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;"><?php echo $val('summary_en'); ?></textarea>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('body_ar', 'local_nit_pages'); ?> (HTML)</label>
        <textarea name="body_ar" rows="8" class="form-control" dir="rtl" placeholder="محتوى المقال كاملاً بالعربية..." style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;font-family:inherit;"><?php echo s($valraw('body_ar')); ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('body_en', 'local_nit_pages'); ?> (HTML)</label>
        <textarea name="body_en" rows="8" class="form-control" dir="ltr" placeholder="Full article content in English..." style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;font-family:inherit;"><?php echo s($valraw('body_en')); ?></textarea>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('cover_image', 'local_nit_pages'); ?></label>
          <?php if (!empty($article->cover_image)): ?>
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:14px;">
              <img src="<?php echo s($article->cover_image); ?>" alt="Cover" style="height:70px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0;box-shadow:0 2px 6px rgba(0,0,0,0.06);">
              <span style="font-size:13px;color:#64748b;">(<?php echo ($isar ? 'الصورة الحالية' : 'Current image'); ?>)</span>
            </div>
          <?php endif; ?>
          <input type="file" name="cover_file" id="cover_file" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:9px 14px;">
          <small class="form-text text-muted" style="font-size:12px;"><?php echo ($isar ? 'اختر صورة غلاف المقال من جهازك (JPG, PNG, WEBP, GIF).' : 'Upload article cover image from your device (JPG, PNG, WEBP, GIF).'); ?></small>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('status', 'local_nit_pages'); ?></label>
          <select name="status" class="form-select custom-select" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
            <option value="published" <?php echo ($val('status') === 'published' ? 'selected' : ''); ?>><?php echo get_string('published', 'local_nit_pages'); ?></option>
            <option value="draft" <?php echo ($val('status') === 'draft' ? 'selected' : ''); ?>><?php echo get_string('draft', 'local_nit_pages'); ?></option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold" style="color:#1e293b;"><?php echo get_string('publish_date', 'local_nit_pages'); ?></label>
          <input type="datetime-local" name="published_at" class="form-control" value="<?php echo s($default_date); ?>" style="background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;border-radius:10px;padding:10px 14px;">
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn fw-bold" style="background:#1b75d0;border-color:#1b75d0;color:#ffffff;border-radius:10px;padding:10px 24px;">
          <?php echo ($isar ? 'حفظ المقال' : 'Save Article'); ?>
        </button>
        <a href="<?php echo (new \moodle_url('/local/nit_pages/manage_articles.php'))->out(); ?>" class="btn btn-outline-secondary fw-bold" style="border-radius:10px;padding:10px 20px;border-color:#cbd5e1;color:#334155;">
          <?php echo get_string('cancel'); ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php
echo $OUTPUT->footer();

