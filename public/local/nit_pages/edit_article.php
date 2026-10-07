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
    $cover_image = optional_param('cover_image', '', PARAM_URL);
    $status = optional_param('status', article_manager::STATUS_PUBLISHED, PARAM_ALPHA);
    $published_at_str = optional_param('published_at', '', PARAM_TEXT);

    $pubtime = null;
    if ($published_at_str !== '') {
        $parsed = strtotime($published_at_str);
        if ($parsed !== false) {
            $pubtime = $parsed;
        }
    }

    $data = [
        'title_ar' => $title_ar,
        'title_en' => $title_en,
        'slug' => $slug,
        'summary_ar' => $summary_ar,
        'summary_en' => $summary_en,
        'body_ar' => $body_ar,
        'body_en' => $body_en,
        'cover_image' => $cover_image,
        'status' => $status,
    ];
    if ($pubtime !== null) {
        $data['published_at'] = $pubtime;
    }
    if ($article) {
        $data['id'] = $article->id;
    }

    article_manager::save_article($data, (int)$USER->id);
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
<div style="max-width:960px;margin:0 auto;padding-bottom:60px;">
  <div style="margin-bottom:20px;">
    <a href="<?php echo (new \moodle_url('/local/nit_pages/manage_articles.php'))->out(); ?>" class="btn btn-sm btn-outline-secondary">
      ← <?php echo get_string('manage_articles', 'local_nit_pages'); ?>
    </a>
  </div>

  <div class="card" style="border-radius:14px;border:1px solid #e5e7eb;background:#fff;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h2 style="font-size:22px;font-weight:700;color:#1e293b;margin:0 0 24px;"><?php echo $title_str; ?></h2>

    <form method="post" action="<?php echo $PAGE->url->out(false); ?>">
      <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
      <?php if ($article): ?>
        <input type="hidden" name="id" value="<?php echo $article->id; ?>">
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold"><?php echo get_string('title_ar', 'local_nit_pages'); ?> *</label>
          <input type="text" name="title_ar" class="form-control" required value="<?php echo $val('title_ar'); ?>" placeholder="عنوان المقال بالعربية" dir="rtl">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold"><?php echo get_string('title_en', 'local_nit_pages'); ?> *</label>
          <input type="text" name="title_en" class="form-control" required value="<?php echo $val('title_en'); ?>" placeholder="Article Title in English" dir="ltr">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold"><?php echo get_string('slug', 'local_nit_pages'); ?></label>
        <input type="text" name="slug" class="form-control" value="<?php echo $val('slug'); ?>" placeholder="unique-article-slug (leaves blank to auto-generate)" dir="ltr">
        <small class="form-text text-muted"><?php echo ($isar ? 'اتركه فارغاً للتوليد التلقائي من العنوان.' : 'Leave empty to auto-generate from title.'); ?></small>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold"><?php echo get_string('summary_ar', 'local_nit_pages'); ?></label>
          <textarea name="summary_ar" rows="3" class="form-control" dir="rtl" placeholder="موجز المقال بالعربية..."><?php echo $val('summary_ar'); ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold"><?php echo get_string('summary_en', 'local_nit_pages'); ?></label>
          <textarea name="summary_en" rows="3" class="form-control" dir="ltr" placeholder="Article summary in English..."><?php echo $val('summary_en'); ?></textarea>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold"><?php echo get_string('body_ar', 'local_nit_pages'); ?> (HTML)</label>
        <textarea name="body_ar" rows="8" class="form-control" dir="rtl" placeholder="محتوى المقال كاملاً بالعربية..."><?php echo s($valraw('body_ar')); ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold"><?php echo get_string('body_en', 'local_nit_pages'); ?> (HTML)</label>
        <textarea name="body_en" rows="8" class="form-control" dir="ltr" placeholder="Full article content in English..."><?php echo s($valraw('body_en')); ?></textarea>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold"><?php echo get_string('cover_image', 'local_nit_pages'); ?> (URL)</label>
          <input type="text" name="cover_image" class="form-control" value="<?php echo $val('cover_image'); ?>" placeholder="https://example.com/cover.jpg" dir="ltr">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold"><?php echo get_string('status', 'local_nit_pages'); ?></label>
          <select name="status" class="form-select custom-select">
            <option value="published" <?php echo ($val('status') === 'published' ? 'selected' : ''); ?>><?php echo get_string('published', 'local_nit_pages'); ?></option>
            <option value="draft" <?php echo ($val('status') === 'draft' ? 'selected' : ''); ?>><?php echo get_string('draft', 'local_nit_pages'); ?></option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold"><?php echo get_string('publish_date', 'local_nit_pages'); ?></label>
          <input type="datetime-local" name="published_at" class="form-control" value="<?php echo s($default_date); ?>">
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4 fw-bold">
          <?php echo ($isar ? 'حفظ المقال' : 'Save Article'); ?>
        </button>
        <a href="<?php echo (new \moodle_url('/local/nit_pages/manage_articles.php'))->out(); ?>" class="btn btn-outline-secondary">
          <?php echo get_string('cancel'); ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php
echo $OUTPUT->footer();
