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
require_capability('local/nit_pages:manage', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW);
$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$PAGE->set_url(new moodle_url('/local/nit_pages/manage_articles.php', [
    'search' => $search,
    'status' => $status,
    'page' => $page,
]));
$PAGE->set_title(get_string('manage_articles', 'local_nit_pages'));
$PAGE->set_heading(get_string('manage_articles', 'local_nit_pages'));

// Handle actions.
if ($action === 'toggle' && $id && confirm_sesskey()) {
    $article = \local_nit_pages\article_manager::get_by_id($id);
    if ($article) {
        $newstatus = ($article->status === 'published') ? 'draft' : 'published';
        \local_nit_pages\article_manager::save((object)[
            'id' => $article->id,
            'status' => $newstatus,
        ]);
        \core\notification::success(get_string('changessaved'));
    }
    redirect($PAGE->url);
}

if ($action === 'delete' && $id && confirm_sesskey()) {
    \local_nit_pages\article_manager::delete($id);
    \core\notification::success(get_string('article_deleted', 'local_nit_pages'));
    redirect($PAGE->url);
}

echo $OUTPUT->header();
?>
<style>
.nit-art-table-wrapper {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    margin-top: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.nit-art-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.nit-art-search-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.nit-art-table {
    width: 100%;
    border-collapse: collapse;
}
.nit-art-table th {
    background: #f8fafc;
    padding: 12px 16px;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    text-align: inherit;
}
.nit-art-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.nit-art-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.82rem;
    font-weight: 600;
}
.nit-art-badge-published { background: #dcfce7; color: #15803d; }
.nit-art-badge-draft { background: #fef3c7; color: #b45309; }
.nit-art-thumb {
    width: 60px;
    height: 42px;
    border-radius: 6px;
    object-fit: cover;
    background: #f1f5f9;
}
.nit-art-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}
</style>

<div class="nit-art-table-wrapper">
    <div class="nit-art-header">
        <form method="get" action="<?php echo $PAGE->url->out_omit_querystring(); ?>" class="nit-art-search-form">
            <input type="text" name="search" value="<?php echo s($search); ?>" placeholder="<?php echo s(get_string('search')); ?>..." class="form-control" style="width: 240px;">
            <select name="status" class="custom-select form-control">
                <option value=""><?php echo get_string('all'); ?></option>
                <option value="published" <?php echo ($status === 'published' ? 'selected' : ''); ?>><?php echo get_string('published', 'local_nit_pages'); ?></option>
                <option value="draft" <?php echo ($status === 'draft' ? 'selected' : ''); ?>><?php echo get_string('draft', 'local_nit_pages'); ?></option>
            </select>
            <button type="submit" class="btn btn-secondary"><?php echo get_string('search'); ?></button>
            <?php if ($search !== '' || $status !== ''): ?>
                <a href="<?php echo new moodle_url('/local/nit_pages/manage_articles.php'); ?>" class="btn btn-outline-secondary"><?php echo get_string('clear'); ?></a>
            <?php endif; ?>
        </form>

        <div>
            <a href="<?php echo new moodle_url('/local/nit_pages/edit_article.php'); ?>" class="btn btn-primary">
                + <?php echo get_string('add_article', 'local_nit_pages'); ?>
            </a>
        </div>
    </div>

    <?php
    $result = \local_nit_pages\article_manager::list_articles([
        'status' => $status,
        'search' => $search,
        'page' => $page,
        'perpage' => $perpage,
    ]);

    if (empty($result['articles'])):
    ?>
        <div class="alert alert-info"><?php echo get_string('no_articles_found', 'local_nit_pages'); ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="nit-art-table">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php echo get_string('cover_image', 'local_nit_pages'); ?></th>
                        <th><?php echo get_string('article_title', 'local_nit_pages'); ?></th>
                        <th><?php echo get_string('author', 'local_nit_pages'); ?></th>
                        <th><?php echo get_string('status', 'local_nit_pages'); ?></th>
                        <th><?php echo get_string('publish_date', 'local_nit_pages'); ?></th>
                        <th style="text-align: end;"><?php echo get_string('actions', 'local_nit_pages'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sesskey = sesskey();
                    foreach ($result['articles'] as $art):
                        $viewurl = \local_nit_pages\article_manager::url($art->slug ?: (string)$art->id);
                        $editurl = new moodle_url('/local/nit_pages/edit_article.php', ['id' => $art->id]);
                        $toggleurl = new moodle_url('/local/nit_pages/manage_articles.php', [
                            'action' => 'toggle',
                            'id' => $art->id,
                            'sesskey' => $sesskey,
                        ]);
                        $deleteurl = new moodle_url('/local/nit_pages/manage_articles.php', [
                            'action' => 'delete',
                            'id' => $art->id,
                            'sesskey' => $sesskey,
                        ]);

                        $coverurl = \local_nit_pages\article_manager::cover_url($art->id);
                        $displaytitle = $art->title_ar ? $art->title_ar : $art->title_en;
                        $displaytitle2 = ($art->title_ar && $art->title_en) ? $art->title_en : '';
                    ?>
                    <tr>
                        <td>
                            <?php if ($coverurl): ?>
                                <img src="<?php echo s($coverurl->out()); ?>" class="nit-art-thumb" alt="">
                            <?php else: ?>
                                <div class="nit-art-thumb" style="display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.2rem;">📰</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo s($viewurl->out()); ?>" target="_blank" style="font-weight: 600; color: #1e293b; text-decoration: none;">
                                <?php echo s($displaytitle); ?>
                            </a>
                            <?php if ($displaytitle2 && $displaytitle2 !== $displaytitle): ?>
                                <div style="font-size: 0.85rem; color: #64748b;"><?php echo s($displaytitle2); ?></div>
                            <?php endif; ?>
                            <div style="font-size: 0.78rem; color: #94a3b8; font-family: monospace;">
                                /<?php echo s($art->slug); ?>
                            </div>
                        </td>
                        <td>
                            <?php echo s($art->author_name ?? '-'); ?>
                        </td>
                        <td>
                            <span class="nit-art-badge nit-art-badge-<?php echo s($art->status); ?>">
                                <?php echo get_string($art->status, 'local_nit_pages'); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.88rem; color: #475569;">
                            <?php echo userdate($art->published_at, get_string('strftimedatetime', 'langconfig')); ?>
                        </td>
                        <td style="text-align: end;">
                            <div class="nit-art-actions" style="justify-content: flex-end;">
                                <a href="<?php echo s($viewurl->out()); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="<?php echo s(get_string('view')); ?>">
                                    👁️
                                </a>
                                <a href="<?php echo s($editurl->out()); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo s(get_string('edit')); ?>">
                                    ✏️
                                </a>
                                <a href="<?php echo s($toggleurl->out()); ?>" class="btn btn-sm btn-outline-info" title="<?php echo s(get_string('toggle_publish', 'local_nit_pages')); ?>">
                                    <?php echo ($art->status === 'published' ? '⏸️' : '▶️'); ?>
                                </a>
                                <a href="<?php echo s($deleteurl->out()); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo s(get_string('confirm_delete', 'local_nit_pages')); ?>');" title="<?php echo s(get_string('delete')); ?>">
                                    🗑️
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $PAGE->url);
    endif;
    ?>
</div>

<?php
echo $OUTPUT->footer();
