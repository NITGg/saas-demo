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
 * Library functions for local_nit_category.
 *
 * Core Moodle gives courses a picture (the `course/overviewfiles` filearea, added by
 * course/edit_form.php) but gives course CATEGORIES nothing: the `course_categories`
 * table has no image column, categories are not a custom-field area, and the category
 * edit form (course/classes/editcategory_form.php) fires no hook a plugin could use to
 * inject a filemanager. So rather than touch core, this plugin owns the image itself:
 * one file per category, stored in our own filearea inside that category's context.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** The filearea holding the (single) image of a course category. */
define('LOCAL_NIT_CATEGORY_IMAGE_FILEAREA', 'categoryimage');

/** The filearea holding the (single) icon of a course category. */
define('LOCAL_NIT_CATEGORY_ICON_FILEAREA', 'categoryicon');

/** Plugin config key holding the {categoryid: emoji} map of text icons. */
define('LOCAL_NIT_CATEGORY_ICON_CONFIG', 'categoryicons');

/**
 * Filemanager / draft-area options for the category image.
 *
 * One web-displayable image, no subdirectories — the category hero is a single picture.
 *
 * @return array options for filemanager elements and the file_*_draft_area helpers
 */
function local_nit_category_image_options(): array {
    global $CFG;
    return [
        'maxfiles'       => 1,
        'maxbytes'       => $CFG->maxbytes,
        'subdirs'        => 0,
        'accepted_types' => ['web_image'],
    ];
}

/**
 * The image file uploaded for a category, if there is one.
 *
 * @param int $categoryid a course category id
 * @return stored_file|null the image, or null when the category has none
 */
function local_nit_category_get_image_file(int $categoryid): ?stored_file {
    // Memoised: a category listing resolves the same category more than once (hero,
    // section header, card), and every miss is a file_storage query.
    static $cache = [];
    if (array_key_exists($categoryid, $cache)) {
        return $cache[$categoryid];
    }
    $cache[$categoryid] = null;

    $context = context_coursecat::instance($categoryid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $files = get_file_storage()->get_area_files(
        $context->id,
        'local_nit_category',
        LOCAL_NIT_CATEGORY_IMAGE_FILEAREA,
        0,
        'filepath, filename',
        false                       // Exclude directories.
    );
    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            $cache[$categoryid] = $file;
            return $file;
        }
    }
    return null;
}

/**
 * The first image found inside a category's description, as a usable URL.
 *
 * This is the zero-configuration path: an admin who just pastes/uploads a picture into
 * the category Description (the core category form already has a full editor with file
 * support, filearea `coursecat/description`) gets a category image without touching the
 * upload page at all. External `https://…` images in the description work too, which is
 * why this matches on the rendered HTML rather than scanning the filearea.
 *
 * @param int $categoryid a course category id
 * @return string image URL, or '' when the description has no image
 */
function local_nit_category_description_image_url(int $categoryid): string {
    $category = core_course_category::get($categoryid, IGNORE_MISSING, true);
    if (!$category) {
        return '';
    }
    $record = $category->get_db_record();
    if (empty($record->description)) {
        return '';
    }
    $context = context_coursecat::instance($categoryid, IGNORE_MISSING);
    if (!$context) {
        return '';
    }
    // Turn the stored @@PLUGINFILE@@ placeholders into real URLs first. Category
    // description files live at itemid 0 and are served without an itemid segment,
    // hence the null itemid here (the same call core's backup UI renderer makes).
    $html = file_rewrite_pluginfile_urls(
        $record->description,
        'pluginfile.php',
        $context->id,
        'coursecat',
        'description',
        null
    );
    if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * The image to show for a category, resolved through the whole fallback chain.
 *
 * Order: the uploaded image -> the first image in the category description -> nothing
 * (callers pick their own last resort, e.g. the site logo). When $inherit is on and a
 * category has neither, its ancestors are tried nearest-first, so every subcategory of a
 * branded main category inherits that branding — the same way theme_nit resolves a
 * category's Brand-Colors group to its top-level ancestor.
 *
 * @param int $categoryid a course category id
 * @param bool $inherit whether to fall back to ancestor categories
 * @return string image URL, or '' when nothing is available anywhere up the tree
 */
function local_nit_category_get_image_url(int $categoryid, bool $inherit = true): string {
    $candidates = [$categoryid];
    if ($inherit) {
        $category = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($category) {
            // get_parents() is top-most first and excludes self; reverse for nearest-first.
            $candidates = array_merge($candidates, array_reverse($category->get_parents()));
        }
    }

    foreach ($candidates as $id) {
        if ($file = local_nit_category_get_image_file((int) $id)) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,                   // No itemid segment; see local_nit_category_pluginfile().
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
        if ($url = local_nit_category_description_image_url((int) $id)) {
            return $url;
        }
    }
    return '';
}

/**
 * Filemanager / draft-area options for the category icon.
 *
 * Same shape as the image options, but the icon is the small glyph that sits next to a
 * category's NAME (badge, filter pill, section heading), where the full-size image
 * would be far too big.
 *
 * @return array options for filemanager elements and the file_*_draft_area helpers
 */
function local_nit_category_icon_options(): array {
    global $CFG;
    return [
        'maxfiles'       => 1,
        'maxbytes'       => $CFG->maxbytes,
        'subdirs'        => 0,
        'accepted_types' => ['web_image'],
    ];
}

/**
 * The icon file uploaded for a category, if there is one.
 *
 * @param int $categoryid a course category id
 * @return stored_file|null the icon, or null when the category has none
 */
function local_nit_category_get_icon_file(int $categoryid): ?stored_file {
    static $cache = [];
    if (array_key_exists($categoryid, $cache)) {
        return $cache[$categoryid];
    }
    $cache[$categoryid] = null;

    $context = context_coursecat::instance($categoryid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $files = get_file_storage()->get_area_files(
        $context->id,
        'local_nit_category',
        LOCAL_NIT_CATEGORY_ICON_FILEAREA,
        0,
        'filepath, filename',
        false
    );
    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            $cache[$categoryid] = $file;
            return $file;
        }
    }
    return null;
}

/**
 * The URL of a category's uploaded icon.
 *
 * @param int $categoryid a course category id
 * @return string icon URL, or '' when the category has no icon file
 */
function local_nit_category_get_icon_url(int $categoryid): string {
    $file = local_nit_category_get_icon_file($categoryid);
    if (!$file) {
        return '';
    }
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        null,
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

/**
 * The whole {categoryid: emoji} icon map.
 *
 * Emoji icons live in one plugin config row rather than a table — the same shape
 * theme_nit uses for its per-category brand groups (`nit_category_groups`), and one
 * read serves a whole page of categories.
 *
 * @param bool $reload read the config again (after local_nit_category_set_icon_emoji())
 * @return array<int, string> category id => emoji
 */
function local_nit_category_get_icon_map(bool $reload = false): array {
    static $map = null;
    if ($map === null || $reload) {
        $raw = get_config('local_nit_category', LOCAL_NIT_CATEGORY_ICON_CONFIG);
        $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    }
    return $map;
}

/**
 * The emoji / short text icon set for a category.
 *
 * @param int $categoryid a course category id
 * @return string the emoji, or '' when none is set
 */
function local_nit_category_get_icon_emoji(int $categoryid): string {
    $map = local_nit_category_get_icon_map();
    $value = $map[$categoryid] ?? ($map[(string) $categoryid] ?? '');
    return is_string($value) ? $value : '';
}

/**
 * Stores (or clears, when given '') a category's emoji icon.
 *
 * @param int $categoryid a course category id
 * @param string $emoji the emoji / short text, or '' to remove it
 */
function local_nit_category_set_icon_emoji(int $categoryid, string $emoji): void {
    $map = local_nit_category_get_icon_map();
    $emoji = trim($emoji);

    if ($emoji === '') {
        unset($map[$categoryid], $map[(string) $categoryid]);
    } else {
        // A few characters is all an icon slot can show; a flag or a family emoji is
        // several code points, hence 8 rather than 1.
        $map[(string) $categoryid] = core_text::substr($emoji, 0, 8);
    }

    set_config(
        LOCAL_NIT_CATEGORY_ICON_CONFIG,
        json_encode($map, JSON_UNESCAPED_UNICODE),
        'local_nit_category'
    );
    local_nit_category_get_icon_map(true); // The same request then sees the new icon.
}

/**
 * A category's icon as ready-to-print HTML.
 *
 * An uploaded icon file wins over the emoji, so an admin can start with an emoji and
 * upload real artwork later without clearing anything. No inheritance from ancestors:
 * an icon identifies one category, and inheriting would make every sibling identical.
 *
 * @param int $categoryid a course category id
 * @param string $class CSS class(es) for the produced element
 * @param string $alt alt text for the image variant (already format_string()'d)
 * @return string HTML for the icon, or '' when the category has neither icon
 */
function local_nit_category_render_icon(int $categoryid, string $class = '', string $alt = ''): string {
    $classattr = $class !== '' ? ' class="' . s($class) . '"' : '';

    if ($url = local_nit_category_get_icon_url($categoryid)) {
        return '<img' . $classattr . ' src="' . s($url) . '" alt="' . $alt . '">';
    }
    if ($emoji = local_nit_category_get_icon_emoji($categoryid)) {
        // Decorative next to the name it accompanies, so hidden from screen readers.
        return '<span' . $classattr . ' aria-hidden="true">' . s($emoji) . '</span>';
    }
    return '';
}

/**
 * Adds the "Category image & icon" tab to a category's settings navigation.
 *
 * This is the supported extension point for category admin pages — the same one
 * tool_uploadcourse uses for its "Upload courses" tab — so the link appears next to the
 * stock tabs without editing any core file.
 *
 * @param navigation_node $navigation the category settings node to extend
 * @param context $coursecategorycontext the category's context
 */
function local_nit_category_extend_navigation_category_settings(
    navigation_node $navigation,
    context $coursecategorycontext
): void {
    if (!has_capability('moodle/category:manage', $coursecategorycontext)) {
        return;
    }
    $navigation->add_node(navigation_node::create(
        get_string('categorymedia', 'local_nit_category'),
        new moodle_url('/local/nit_category/image.php', ['id' => $coursecategorycontext->instanceid]),
        navigation_node::TYPE_SETTING,
        null,
        'nitcategoryimage',
        new pix_icon('i/files', '')
    ));
}

/**
 * Serves files from the category-image filearea.
 *
 * Visibility follows the category itself: a user who cannot see the category cannot
 * fetch its picture.
 *
 * @param stdClass|null $course unused (category context)
 * @param stdClass|null $cm unused (category context)
 * @param context $context the file's context, must be a category context
 * @param string $filearea the requested filearea
 * @param array $args remaining URL path segments (filepath + filename)
 * @param bool $forcedownload whether to send the file as an attachment
 * @param array $options send_stored_file options
 * @return void never returns — either sends the file or a "not found"
 */
function local_nit_category_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    $allowedareas = [LOCAL_NIT_CATEGORY_IMAGE_FILEAREA, LOCAL_NIT_CATEGORY_ICON_FILEAREA];
    if ($context->contextlevel != CONTEXT_COURSECAT || !in_array($filearea, $allowedareas, true)) {
        send_file_not_found();
    }
    if (!empty($CFG->forcelogin)) {
        require_login();
    }
    // get() honours visibility, so hidden categories stay hidden.
    if (!core_course_category::get($context->instanceid, IGNORE_MISSING)) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file(
        $context->id,
        'local_nit_category',
        $filearea,
        0,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    \core\session\manager::write_close(); // Unlock the session while the file streams.
    send_stored_file($file, 60 * 60, 0, $forcedownload, $options);
}

