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

    // Where a Brand Colors save lands the admin back: the same group's editor AND
    // the same section of it, not the first of each. Every editor here saves by
    // POST-and-redirect, so without carrying the place forward an admin tuning
    // the navbar was thrown back to Group 1 / Brand on every save — the deeper
    // they were working, the more of it they lost. The group pills and the
    // section strip both read these off the URL (see the handler below); the
    // section travels in a hidden field the strip keeps up to date.
    $brandsection = optional_param('brandsection', '', PARAM_ALPHANUMEXT);
    if ($brandsection !== '' && !array_key_exists($brandsection, theme_nit_brand_role_sections())) {
        $brandsection = '';
    }
    $brandparams = [];
    if ($brandgroup !== '') {
        $brandparams['brandgroup'] = $brandgroup;
    }
    if ($brandsection !== '') {
        $brandparams['brandsection'] = $brandsection;
    }
    $brandurl = new moodle_url('/theme/nit/gallery.php', $brandparams ?: null, 'nit-tab-brand');

    // The navbar's non-colour style settings — the shapes, and the size / weight
    // of each of the three subjects — ride in the same form as the colours, saved
    // and reset by the same buttons. They are part of "what this group looks
    // like", and splitting them out would mean two saves for one decision (a
    // shape and the colour it is drawn in).
    $shapestates = array_keys(theme_nit_navbar_shape_states());
    $shapevalues = array_keys(theme_nit_navbar_shape_treatments());
    $typesubjects = theme_nit_navbar_type_subjects();
    $typeweights = theme_nit_navbar_weights();

    if (!empty($data->resetbrand)) {
        foreach ($brand as $key => $meta) {
            if ($brandgroup !== '' && $meta['groupkey'] !== $brandgroup) {
                continue;
            }
            unset_config('brandcolour_' . $key, 'theme_nit');
        }
        foreach ($groupkeys as $gkey) {
            if ($brandgroup !== '' && $gkey !== $brandgroup) {
                continue;
            }
            foreach ($shapestates as $state) {
                unset_config('navbarshape_' . $gkey . '_' . $state, 'theme_nit');
            }
            foreach (array_keys($typesubjects) as $subject) {
                unset_config('navbarsize_' . $gkey . '_' . $subject, 'theme_nit');
                unset_config('navbarweight_' . $gkey . '_' . $subject, 'theme_nit');
            }
            unset_config('navbarglass_' . $gkey, 'theme_nit');
            unset_config('navbartransparency_' . $gkey, 'theme_nit');
            unset_config('navbarscrollgroup_' . $gkey, 'theme_nit');
            foreach (array_keys(theme_nit_button_outline_variants()) as $variant) {
                unset_config($variant . 'fill_' . $gkey, 'theme_nit');
            }
        }
        theme_reset_all_caches();
        redirect($brandurl, get_string('brandcoloursreset', 'theme_nit'), null,
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
        foreach ($groupkeys as $gkey) {
            if ($brandgroup !== '' && $gkey !== $brandgroup) {
                continue;
            }
            // A shape is a SET of treatments now, posted as a tick per box. Any
            // subset is a valid answer, INCLUDING none — so the row is written
            // even when the array comes back empty, and "" is stored rather than
            // the row being removed. Removing it would mean "never set", which is
            // what re-applies the default, and an admin who deliberately unticked
            // everything would watch the shape come straight back.
            //
            // Only ever for the group whose form was posted: an empty
            // `brandgroup` is a legacy single-form post that carried no shape
            // fields at all, and treating its silence as "none" would wipe all
            // five groups at once.
            if ($brandgroup !== '') {
                foreach ($shapestates as $state) {
                    $field = 'navbarshape_' . $gkey . '_' . $state;
                    $ticked = optional_param_array($field, [], PARAM_ALPHANUMEXT);
                    // Against the catalogue, so a post naming a treatment that
                    // does not exist (a retired `bold`) is dropped rather than
                    // stored.
                    $ticked = array_values(array_intersect($shapevalues, $ticked));
                    set_config($field, implode(',', $ticked), 'theme_nit');
                }
            }
            foreach ($typesubjects as $subject => $meta) {
                // Size: a number in px, clamped to the subject's own range. The
                // browser enforces min/max on the spinner, so anything outside it
                // arrived some other way and is pulled back into range rather
                // than rejected — the range is what the bar can actually draw.
                $field = 'navbarsize_' . $gkey . '_' . $subject;
                $size = optional_param($field, 0, PARAM_INT);
                if ($size > 0) {
                    set_config($field, min($meta['max'], max($meta['min'], $size)), 'theme_nit');
                }

                // Weight: one of the fixed ladder, or nothing is written.
                $field = 'navbarweight_' . $gkey . '_' . $subject;
                $weight = optional_param($field, 0, PARAM_INT);
                if (array_key_exists($weight, $typeweights)) {
                    set_config($field, $weight, 'theme_nit');
                }
            }

            // The glass switch, the transparency degree and the scrolled group.
            // Same gate as the shapes: an unticked switch posts nothing, so the
            // absence has to be read as "off" — which is only safe for the group
            // whose form was actually submitted.
            if ($brandgroup !== '') {
                set_config('navbarglass_' . $gkey,
                    optional_param('navbarglass_' . $gkey, 0, PARAM_INT) ? '1' : '0', 'theme_nit');

                // Whether each outline button paints its Background role. Same
                // gate and the same reason as the glass switch above: an
                // unticked box posts nothing, so its absence can only be read as
                // "off" for the group whose form was actually submitted.
                foreach (array_keys(theme_nit_button_outline_variants()) as $variant) {
                    $field = $variant . 'fill_' . $gkey;
                    set_config($field, optional_param($field, 0, PARAM_INT) ? '1' : '0', 'theme_nit');
                }

                // 0 is a solid bar and a real answer, so the field is read with a
                // sentinel default rather than 0 — a browser that sent nothing
                // must not be taken for an admin asking for zero.
                $field = 'navbartransparency_' . $gkey;
                $transparency = optional_param($field, -1, PARAM_INT);
                if ($transparency >= 0) {
                    set_config($field, min(90, $transparency), 'theme_nit');
                }

                $field = 'navbarscrollgroup_' . $gkey;
                $scroll = optional_param($field, '', PARAM_ALPHANUMEXT);
                if (in_array($scroll, $groupkeys, true)) {
                    set_config($field, $scroll, 'theme_nit');
                }
            }
        }
        theme_reset_all_caches();
        redirect($brandurl, get_string('brandcolourssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Category branding — the "Category styles" tab. One row per MAIN category,
    // and four decisions on it:
    //
    //   * a Brand-Colors group for light mode and one for dark mode. A category
    //     is branded twice because the site is: pages under it render in the
    //     group chosen for the mode the visitor is in, and the navbar light/dark
    //     button moves between the two. Stored as two JSON maps,
    //     `nit_category_groups` (light, the original key — so every assignment
    //     made before the second column existed is still the light one) and
    //     `nit_category_groups_dark`, each { "<categoryid>": "g2", … }. "Site
    //     default" is stored as absence, which is what leaves those pages on the
    //     site's own group for that mode.
    //   * a navbar logo for light mode and one for dark mode, uploaded into the
    //     CATEGORY's own file area (theme_nit_category_logo_slots()) so the file
    //     belongs to the category and goes when it does. An empty slot means
    //     "use the site logo", never "no logo".
    //
    // The group maps change no CSS — the switch classes are already compiled —
    // but a replaced logo file keeps its name, so only a theme-revision bump
    // makes browsers drop the picture they have. Hence the purge, and only when
    // a file actually changed.
    // -------------------------------------------------------------------------
    if (!empty($data->savecatgroups)) {
        // The two group maps, read per mode. Posted category ids are cast and
        // checked; an unknown group key (or the empty "site default") simply is
        // not written, so the map only ever holds assignments we can honour.
        foreach (array_keys(theme_nit_modes()) as $mode) {
            $selected = optional_param_array('catgroup' . $mode, [], PARAM_ALPHANUMEXT);
            $map = [];
            foreach ($selected as $cid => $gk) {
                $cid = (int) $cid;
                if ($cid > 0 && in_array($gk, $groupkeys, true)) {
                    $map[$cid] = $gk;
                }
            }
            set_config(theme_nit_category_groups_config($mode), json_encode($map), 'theme_nit');
        }

        // The per-category logos. Which categories exist is read from the
        // catalogue, not from the post: these are file operations in somebody
        // else's context, and the only ids worth acting on are the ones the form
        // was built from.
        $errors = [];
        $filechanged = false;
        $fs = get_file_storage();
        $catlogoslots = theme_nit_category_logo_slots();

        $removals = [];
        foreach ($catlogoslots as $mode => $slot) {
            $removals[$mode] = optional_param_array($slot['remove'], [], PARAM_BOOL);
        }

        // Every file field the browser said it was sending has to have arrived.
        // One that did not was dropped by PHP's `max_file_uploads` cap, which it
        // does silently — and an admin who picked a logo and got a green "saved"
        // would have no way of knowing. The page prunes its empty inputs before
        // submitting (see the script below) so this list names only real uploads.
        $announced = array_filter(array_map(
            'trim',
            explode(',', optional_param('catlogofields', '', PARAM_RAW_TRIMMED))
        ));
        $dropped = 0;
        foreach ($announced as $field) {
            if (!array_key_exists($field, $_FILES)) {
                $dropped++;
            }
        }
        if ($dropped > 0) {
            $errors[] = get_string('categorylogotoomany', 'theme_nit', $dropped);
        }

        foreach (core_course_category::top()->get_children() as $cat) {
            $catid = (int) $cat->id;
            $catcontext = context_coursecat::instance($catid, IGNORE_MISSING);
            if (!$catcontext) {
                continue;
            }

            foreach ($catlogoslots as $mode => $slot) {
                $label = $cat->get_formatted_name() . ' — ' . get_string($slot['strkey'], 'theme_nit');

                // "Remove" wins over an upload in the same post: an admin who
                // ticked the box and also chose a file meant the box, or they
                // would not have had to tick it.
                if (!empty($removals[$mode][$catid])) {
                    $fs->delete_area_files($catcontext->id, 'theme_nit', $slot['filearea']);
                    $filechanged = true;
                    continue;
                }

                $field = $slot['input'] . '_' . $catid;
                if (empty($_FILES[$field]['name']) ||
                        ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $upload = $_FILES[$field];

                // Guard against upload failures and non-uploaded (spoofed) paths.
                if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                    $errors[] = get_string('categorylogouploaderror', 'theme_nit', $label);
                    continue;
                }

                $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                if (!in_array($ext, ['png', 'jpg', 'webp', 'gif', 'svg'], true)) {
                    $errors[] = get_string('categorylogoinvalidtype', 'theme_nit', $label);
                    continue;
                }

                // Verify the file really is the picture its name claims rather
                // than trusting the extension. SVG is accepted here — a logo is
                // the one image that genuinely wants to be vector, and the core
                // Logos page this sits beside accepts it for the same reason —
                // so it is checked for an SVG root instead of being parsed as a
                // raster image.
                if ($ext === 'svg') {
                    $head = (string) file_get_contents($upload['tmp_name'], false, null, 0, 1024);
                    if (stripos($head, '<svg') === false) {
                        $errors[] = get_string('categorylogoinvalidtype', 'theme_nit', $label);
                        continue;
                    }
                } else {
                    $info = @getimagesize($upload['tmp_name']);
                    $allowedtypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
                    if ($info === false || !in_array($info[2], $allowedtypes, true)) {
                        $errors[] = get_string('categorylogoinvalidtype', 'theme_nit', $label);
                        continue;
                    }
                }

                // Fixed, predictable filename per slot (only the extension
                // varies), replacing any previous file in the area.
                $filename = clean_param($slot['basename'] . '.' . $ext, PARAM_FILE);
                $fs->delete_area_files($catcontext->id, 'theme_nit', $slot['filearea']);
                $fs->create_file_from_pathname((object) [
                    'contextid' => $catcontext->id,
                    'component' => 'theme_nit',
                    'filearea'  => $slot['filearea'],
                    'itemid'    => 0,
                    'filepath'  => '/',
                    'filename'  => $filename,
                ], $upload['tmp_name']);
                $filechanged = true;
            }
        }

        if ($filechanged) {
            // A replaced logo keeps its filename, so only a new theme revision
            // moves the URL and makes browsers fetch the new picture.
            theme_reset_all_caches();
        }

        // Back to the tab the form was posted from (see the hash handler below).
        $caturl = new moodle_url('/theme/nit/gallery.php', null, 'nit-tab-catstyles');
        if ($errors) {
            redirect($caturl, implode(' ', $errors), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        redirect($caturl, get_string('categorygroupssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Display mode → Brand-group mapping ("Site styles", same "Change style"
    // tab). The light/dark button in the navbar carries no palette of its own:
    // it selects one of the three brand groups, and this is where an admin says
    // which one each mode gets. Stored as one JSON config `nit_mode_groups`
    // = { "light": "g1", "dark": "g2" }.
    //
    // Unlike the category map, BOTH modes are stored even when one of them is
    // Group 1 — "light is deliberately Group 1" and "light has never been set"
    // have to stay distinguishable, or an admin who moves light to Group 2 and
    // back could not get the default back by saving. No SCSS changes (the switch
    // classes are already compiled), so no cache purge.
    // -------------------------------------------------------------------------
    if (!empty($data->savemodegroups)) {
        $selected = optional_param_array('modegroup', [], PARAM_ALPHANUMEXT);
        $map = [];
        foreach (array_keys(theme_nit_mode_groups()) as $mode) {
            $gk = $selected[$mode] ?? '';
            if (in_array($gk, $groupkeys, true)) {
                $map[$mode] = $gk;
            }
        }
        set_config('nit_mode_groups', json_encode($map), 'theme_nit');
        // Which of the two a first-time visitor (no `nit_mode` cookie yet) opens
        // in — the "Default" radio in the same table. Read back by
        // theme_nit_default_mode(); an unknown value leaves the stored one alone.
        $default = optional_param('modedefault', '', PARAM_ALPHA);
        if (array_key_exists($default, theme_nit_modes())) {
            set_config('nit_mode_default', $default, 'theme_nit');
        }
        // Back to the tab the form was posted from (see the hash handler below).
        redirect(new moodle_url('/theme/nit/gallery.php', null, 'nit-tab-catstyles'),
            get_string('sitestylessaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Home page chrome (same "Change style" tab): whether the navigation bar and
    // the site footer are drawn on the Site home. One checkbox each, ticked =
    // shown; an unticked box is simply absent from the POST, so both are written
    // on every save. Stored as `homechrome_navbar` / `homechrome_footer` ('1' /
    // '0') and read by theme_nit_home_chrome(). The bar is left out of the page
    // and the footer band is not rendered — no SCSS changes, so no cache purge.
    // -------------------------------------------------------------------------
    if (!empty($data->savehomechrome)) {
        $ticked = optional_param_array('homechrome', [], PARAM_INT);
        foreach (array_keys(theme_nit_home_chrome()) as $part) {
            set_config('homechrome_' . $part, empty($ticked[$part]) ? '0' : '1', 'theme_nit');
        }
        redirect(new moodle_url('/theme/nit/gallery.php', null, 'nit-tab-catstyles'),
            get_string('homechromesaved', 'theme_nit'), null,
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

    // Back to the Fonts tab, not to the first one. Every other editor on this
    // page already carried its tab through the redirect; this was the one that
    // did not, so uploading a font looked like it had gone nowhere.
    $fonturl = new moodle_url('/theme/nit/gallery.php', null, 'nit-tab-fonts');

    if (!empty($data->resetfonts)) {
        foreach ($slots as $slot) {
            $fs->delete_area_files($syscontext->id, 'theme_nit', $slot['filearea']);
            unset_config($slot['setting'], 'theme_nit');
        }
        theme_reset_all_caches();
        redirect($fonturl, get_string('fontsreset', 'theme_nit'), null,
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
            redirect($fonturl, implode(' ', $errors), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        redirect($fonturl, get_string('fontssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // -------------------------------------------------------------------------
    // Account screens: the picture beside the log-in and sign-up cards, and the
    // quote drawn over it.
    //
    // The pictures are stored exactly like the fonts above — system context,
    // itemid 0, config `theme_nit/<setting>` = the filename — so the standard
    // theme plumbing (theme_nit_pluginfile) serves them and the extra SCSS
    // (theme_nit_auth_background_scss) paints them. The quote is plain config,
    // read at render time by theme_nit_auth_panel_content(); it changes no CSS,
    // but the pictures do, so the cache purge below covers both.
    //
    // This lives on the gallery page rather than in settings.php because it is a
    // branding decision, and every other branding decision — the palette, the
    // fonts, the category styles — is made here, next to what it changes.
    // -------------------------------------------------------------------------
    if (!empty($data->saveauth)) {
        $errors = [];
        $remove = optional_param_array('removeauthimage', [], PARAM_BOOL);

        foreach (theme_nit_auth_image_slots() as $key => $slot) {
            $label = get_string($slot['strkey'], 'theme_nit');

            // "Remove" wins over an upload in the same post: an admin who ticked
            // the box and also chose a file meant the box, or they would not have
            // had to tick it.
            if (!empty($remove[$key])) {
                $fs->delete_area_files($syscontext->id, 'theme_nit', $slot['filearea']);
                unset_config($slot['setting'], 'theme_nit');
                continue;
            }

            $field = $slot['input'];

            // No file chosen for this slot → keep whatever is already stored.
            if (empty($_FILES[$field]['name']) ||
                    ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $upload = $_FILES[$field];

            // Guard against upload failures and non-uploaded (spoofed) paths.
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                $errors[] = get_string('authimageuploaderror', 'theme_nit', $label);
                continue;
            }

            $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            if (!in_array($ext, ['jpg', 'png', 'webp'], true)) {
                $errors[] = get_string('authimageinvalidtype', 'theme_nit', $label);
                continue;
            }

            // Verify the file really is the raster image its name claims, rather
            // than trusting the extension: getimagesize() parses the header and
            // returns false for anything it cannot read as an image. SVG is
            // deliberately not offered — it is a document that can carry script,
            // and a background photograph is never one.
            $info = @getimagesize($upload['tmp_name']);
            $allowedtypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
            if ($info === false || !in_array($info[2], $allowedtypes, true)) {
                $errors[] = get_string('authimageinvalidtype', 'theme_nit', $label);
                continue;
            }

            // Fixed, predictable filename per slot (only the extension varies),
            // replacing any previous file in the area.
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

        // The quote and its attribution, one pair per language. PARAM_TEXT, not
        // PARAM_RAW: this is a sentence, and it is printed through format_string()
        // — which would strip any markup anyway.
        foreach (theme_nit_auth_text_langs() as $lang => $meta) {
            foreach (['quote', 'author'] as $part) {
                $value = trim(optional_param('auth' . $part . '_' . $lang, '', PARAM_TEXT));
                if ($value === '') {
                    unset_config('authpanel' . $part . '_' . $lang, 'theme_nit');
                } else {
                    set_config('authpanel' . $part . '_' . $lang, $value, 'theme_nit');
                }
            }
        }

        theme_reset_all_caches();

        // Back to the tab the form was submitted from, rather than to the first
        // tab on the page (see the hash handler below).
        $authurl = new moodle_url('/theme/nit/gallery.php', null, 'nit-tab-authscreens');
        if ($errors) {
            redirect($authurl, implode(' ', $errors), null,
                \core\output\notification::NOTIFY_ERROR);
        }
        redirect($authurl, get_string('authscreenssaved', 'theme_nit'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

$gallery = new \theme_nit\output\gallery();

// Keep the Category styles form's empty file inputs out of the submission.
//
// That form carries two file inputs per category, and PHP counts EVERY file part
// a browser sends against `max_file_uploads` (20 by default) — the empty ones
// included. On a site with more than ten main categories the uploads past that
// limit are dropped without a word. An empty input carries nothing, so it is
// disabled just before the form goes (a disabled control is not submitted), and
// the hidden field is rewritten to name only what is still on its way — which is
// what lets the server tell "the admin left this one empty" apart from "PHP threw
// this one away" (see the catlogofields check in the save handler above).
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var form = document.querySelector('[data-nit-catstyles-form]');
    if (!form) {
        return;
    }
    form.addEventListener('submit', function() {
        var kept = [];
        form.querySelectorAll('input[type="file"]').forEach(function(input) {
            if (input.files && input.files.length) {
                kept.push(input.name);
            } else {
                input.disabled = true;
            }
        });
        var hidden = form.querySelector('[data-nit-catlogo-fields]');
        if (hidden) {
            hidden.value = kept.join(',');
        }
    });
});
JS);

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

// Brand Colors: show one group's editor, and one section of it, at a time.
//
// Five groups of 59 roles is 295 colour wells. Printed one under another - which
// is what this tab did - finding the navbar roles of Group 4 meant scrolling past
// a hundred and forty other pickers, and the Save button for the group you were
// editing was somewhere off the bottom of the screen. Nothing here changes what
// is submitted: every section stays inside its group's form whether it is on
// screen or not, so Save still saves the whole group in one post.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var brandtab = document.getElementById('nit-tab-brand');
    if (!brandtab) {
        return;
    }

    var pills = brandtab.querySelectorAll('[data-nit-brand-group]');
    var panes = brandtab.querySelectorAll('[data-nit-brand-pane]');

    var showGroup = function(groupkey) {
        var found = false;
        panes.forEach(function(pane) {
            var mine = pane.getAttribute('data-nit-brand-pane') === groupkey;
            pane.classList.toggle('d-none', !mine);
            found = found || mine;
        });
        if (!found) {
            return false;
        }
        pills.forEach(function(pill) {
            var mine = pill.getAttribute('data-nit-brand-group') === groupkey;
            pill.classList.toggle('active', mine);
            pill.setAttribute('aria-selected', mine ? 'true' : 'false');
        });
        return true;
    };

    pills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            showGroup(pill.getAttribute('data-nit-brand-group'));
        });
    });

    // Section strip, one per group's editor. Scoped to the form it lives in so
    // the five strips never reach into each other.
    //
    // Every pane records the open section in a hidden field of its own form, so
    // the POST carries it and the redirect can hand it back (see $brandurl in
    // the save handler above). Showing a section is what writes it, which means
    // the field is right on arrival too, not only after a click.
    var showSection = function(pane, key) {
        var tabs = pane.querySelectorAll('[data-nit-brand-section]');
        var sections = pane.querySelectorAll('[data-nit-brand-sectionpane]');
        var match = null;
        tabs.forEach(function(tab) {
            if (tab.getAttribute('data-nit-brand-sectionkey') === key) {
                match = tab;
            }
        });
        if (!match) {
            return false;
        }
        var target = match.getAttribute('data-nit-brand-section');
        sections.forEach(function(section) {
            section.classList.toggle('d-none', section.id !== target);
        });
        tabs.forEach(function(other) {
            var mine = other === match;
            other.classList.toggle('active', mine);
            other.setAttribute('aria-selected', mine ? 'true' : 'false');
        });
        var field = pane.querySelector('[data-nit-brand-sectionfield]');
        if (field) {
            field.value = key;
        }
        return true;
    };

    panes.forEach(function(pane) {
        pane.querySelectorAll('[data-nit-brand-section]').forEach(function(tab) {
            tab.addEventListener('click', function() {
                showSection(pane, tab.getAttribute('data-nit-brand-sectionkey'));
            });
        });
        // Seed the hidden field with the section that is open on arrival, so a
        // save made without ever touching the strip still comes back here.
        var first = pane.querySelector('[data-nit-brand-sectionkey]');
        if (first) {
            showSection(pane, first.getAttribute('data-nit-brand-sectionkey'));
        }
    });

    // Land back exactly where the save was made — the same group AND the same
    // section of it. Every editor here saves by POST-and-redirect, and the
    // redirect carries ?brandgroup= and ?brandsection= for this (see $brandurl in
    // the save handler). The section is applied to whichever pane is showing, so
    // it works whether the group came from the URL or was already the default.
    var params = new URLSearchParams(window.location.search);
    var wantgroup = params.get('brandgroup');
    if (wantgroup) {
        showGroup(wantgroup);
    }
    var wantsection = params.get('brandsection');
    if (wantsection) {
        panes.forEach(function(pane) {
            if (!pane.classList.contains('d-none')) {
                showSection(pane, wantsection);
            }
        });
    }
});
JS);

// Open the tab named in the URL fragment. Every editor on this page saves by
// POST-and-redirect, which without this always lands the admin back on the first
// tab - so a font upload, or a change to the account screens, appeared to have
// gone nowhere. Clicking the button rather than driving Bootstrap directly keeps
// this working through a Bootstrap major version.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var hash = window.location.hash;
    if (!hash || !/^#[\w-]+$/.test(hash)) {
        return;
    }
    var button = document.querySelector('#nit-gallery-tabs [data-bs-target="' + hash + '"]');
    if (button && !button.classList.contains('active')) {
        button.click();
    }
});
JS);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_nit/gallery/showcase', $gallery->export_for_template($OUTPUT));
echo $OUTPUT->footer();
