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
 * NIT design-system component gallery (admin/dev only).
 *
 * Living documentation and the accessibility test surface for the design
 * system. Reached from Site administration → Appearance → NIT Design System.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// Palette helpers (theme_nit_colour_palette / theme_nit_colour) live here.
require_once(__DIR__ . '/lib.php');

// Handles login, the moodle/site:config capability, admin page layout and
// breadcrumb, using the external page registered in settings.php.
admin_externalpage_setup('theme_nit_gallery');

$pageurl = new moodle_url('/theme/nit/gallery.php');

// -----------------------------------------------------------------------------
// Colour palette editor — save / reset. The palette (theme_nit_colour_palette())
// is the site's single source of colour truth; values are stored as theme_nit
// config (`colour_<key>`) and compiled into SCSS + --nit-* custom properties by
// the theme's SCSS callbacks. After a change we purge theme caches so the CSS
// rebuilds on the next request.
// -----------------------------------------------------------------------------
if (($data = data_submitted()) && confirm_sesskey()) {
    // -------------------------------------------------------------------------
    // Brand Colors palette (the semantic layer — 3 groups × 13 roles). Stored as
    // config `brandcolour_<key>`; caches are purged so the SCSS rebuilds. Values
    // are validated to #rgb / #rrggbb. (The legacy "Colours" editor was retired;
    // its tokens still exist in lib.php for the dark bands / category styles that
    // read them, but they are no longer edited here.)
    // -------------------------------------------------------------------------
    $brand = theme_nit_brand_palette();
    // Save / reset act on ONE group at a time: each group's form posts its own
    // group key (g1/g2/g3). An empty group (legacy single-form post) means "all".
    $brandgroup = optional_param('brandgroup', '', PARAM_ALPHANUMEXT);
    $groupkeys = array_keys(theme_nit_brand_groups());
    if ($brandgroup !== '' && !in_array($brandgroup, $groupkeys, true)) {
        $brandgroup = '';
    }

    if (!empty($data->resetbrand)) {
        foreach ($brand as $key => $meta) {
            if ($brandgroup !== '' && $meta['groupkey'] !== $brandgroup) {
                continue;
            }
            unset_config('brandcolour_' . $key, 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($pageurl, get_string('brandcoloursreset', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if (!empty($data->savebrand)) {
        foreach ($brand as $key => $meta) {
            if ($brandgroup !== '' && $meta['groupkey'] !== $brandgroup) {
                continue;
            }
            $field = 'brandcolour_' . $key;
            $value = optional_param($field, '', PARAM_RAW_TRIMMED);
            if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                $value = $meta['default'];
            }
            set_config($field, $value, 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($pageurl, get_string('brandcolourssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Category → Brand-group mapping. The "Category styles" tab lets an admin
    // assign a brand group (g1/g2/g3) to each category; the category details page
    // (local_nit_category) then wraps itself in .nit-brand-2 / .nit-brand-3 so it
    // re-skins from that group. Stored as one JSON config `nit_category_groups`
    // = { "<categoryid>": "g2", … }; Group 1 (the default) is stored as absence.
    // No SCSS change, so no cache rebuild is needed — the class is applied per
    // request and the group switch classes already exist in the compiled CSS.
    // -------------------------------------------------------------------------
    if (!empty($data->savecatgroups)) {
        $selected = optional_param_array('catgroup', [], PARAM_ALPHANUMEXT);
        $map = [];
        foreach ($selected as $cid => $gk) {
            $cid = (int) $cid;
            // Only persist real, non-default assignments to keep the map small.
            if ($cid > 0 && in_array($gk, $groupkeys, true) && $gk !== 'g1') {
                $map[$cid] = $gk;
            }
        }
        set_config('nit_category_groups', json_encode($map), 'theme_nit');
        redirect($pageurl, get_string('categorygroupssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Per-language font upload / removal. Each slot (theme_nit_font_slots())
    // stores its file exactly like a Boost stored-file setting — system context,
    // itemid 0, config `theme_nit/<setting>` = the filename — so the standard
    // theme plumbing serves it (theme_nit_pluginfile) and the extra SCSS emits
    // the @font-face. Caches are purged so the CSS rebuilds with the new URL.
    // -------------------------------------------------------------------------
    $slots = theme_nit_font_slots();
    $syscontext = context_system::instance();
    $fs = get_file_storage();

    if (!empty($data->resetfonts)) {
        foreach ($slots as $slot) {
            $fs->delete_area_files($syscontext->id, 'theme_nit', $slot['filearea']);
            unset_config($slot['setting'], 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($pageurl, get_string('fontsreset', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if (!empty($data->savefonts)) {
        $errors = [];
        foreach ($slots as $slot) {
            $field = $slot['input'];

            // No file chosen for this slot → keep whatever is already stored.
            if (empty($_FILES[$field]['name']) ||
                    ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $upload = $_FILES[$field];
            $label = get_string($slot['strkey'], 'theme_nit');

            // Guard against upload failures and non-uploaded (spoofed) paths.
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                $errors[] = get_string('fontuploaderror', 'theme_nit', $label);
                continue;
            }

            // Font files only — .ttf (truetype) or .otf (opentype).
            $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['ttf', 'otf'], true)) {
                $errors[] = get_string('fontinvalidtype', 'theme_nit', $label);
                continue;
            }

            // Verify the file's actual content, not just its extension: check the
            // 4-byte signature so a renamed non-font file is rejected. Valid
            // sfnt/font signatures: 0x00010000 (TrueType), "OTTO" (CFF/OpenType),
            // "true"/"typ1" (Apple TrueType), "ttcf" (TrueType Collection).
            $magic = (string) file_get_contents($upload['tmp_name'], false, null, 0, 4);
            $validsignatures = ["\x00\x01\x00\x00", 'OTTO', 'true', 'typ1', 'ttcf'];
            if (!in_array($magic, $validsignatures, true)) {
                $errors[] = get_string('fontinvalidtype', 'theme_nit', $label);
                continue;
            }

            // Store under a fixed, predictable filename per slot (only the
            // extension varies), replacing any previous file in the area.
            $filename = clean_param($slot['basename'] . '.' . $ext, PARAM_FILE);
            $fs->delete_area_files($syscontext->id, 'theme_nit', $slot['filearea']);
            $fs->create_file_from_pathname((object) [
                'contextid' => $syscontext->id,
                'component' => 'theme_nit',
                'filearea'  => $slot['filearea'],
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $filename,
            ], $upload['tmp_name']);
            set_config($slot['setting'], '/' . $filename, 'theme_nit');
        }

        theme_reset_all_caches();

        if ($errors) {
            redirect($pageurl, implode(' ', $errors), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        redirect($pageurl, get_string('fontssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$gallery = new \theme_nit\output\gallery();

// Two-way sync between each colour picker and its hex text field.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    document.querySelectorAll('[data-nit-colour]').forEach(function(row) {
        var picker = row.querySelector('input[type="color"]');
        var text = row.querySelector('input[type="text"]');
        if (!picker || !text) {
            return;
        }
        picker.addEventListener('input', function() {
            text.value = picker.value.toUpperCase();
        });
        text.addEventListener('input', function() {
            if (/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text.value)) {
                picker.value = text.value;
            }
        });
    });
});
JS);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_nit/gallery/showcase', $gallery->export_for_template($OUTPUT));
echo $OUTPUT->footer();
