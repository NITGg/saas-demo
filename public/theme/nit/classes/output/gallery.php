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

namespace theme_nit\output;

use renderable;
use renderer_base;
use templatable;

/**
 * View-model for the design-system component gallery.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gallery implements renderable, templatable {
    /**
     * Export sample data for the gallery template.
     *
     * @param renderer_base $output the renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Brand Colors palette: the semantic layer, one editor per group, each
        // listing the same roles. The palette is ordered so each group's roles
        // are contiguous, and within a group so that each SECTION's roles are
        // contiguous too — the template renders them in that order and does no
        // sorting of its own. `usage` is a list of UI targets, wrapped as
        // {label} objects so the template renders one chip each.
        //
        // Only ONE group's editor is shown at a time (the pills at the top of
        // the tab switch between them) and only one section within it, because
        // seventeen groups × 69 roles is 1,173 colour wells and finding anything in
        // that by scrolling is the thing this page was worst at.
        // Built keyed (group → section → block) and flattened into lists at the
        // end, because everything below wants to reach a named block — the shape
        // and typography cards belong beside the colours of the same subject, not
        // in a pile at the foot of the section.
        $sectionlabels = \theme_nit_brand_role_sections();
        $blocklabels = \theme_nit_brand_role_subsections();
        $keyed = [];      // gkey => [gname, sections: skey => [label, blocks: sub => card lists]].
        foreach (\theme_nit_brand_palette() as $key => $meta) {
            $gkey = $meta['groupkey'];
            $skey = $meta['section'];
            $sub = $meta['sub'] ?? '';
            $keyed[$gkey]['name'] ??= $meta['group'];
            $keyed[$gkey]['sections'][$skey]['label'] ??= $sectionlabels[$skey] ?? $skey;
            $block = &$keyed[$gkey]['sections'][$skey]['blocks'][$sub];
            $block['roles'] ??= [];

            $value = \theme_nit_brandcolour($key);
            // `short` is the name used HERE. On a page already headed "Navbar"
            // and under a block already headed "Titles", the full role name says
            // its own address three times; the export keeps the long one, where
            // there is no surrounding page to supply the context.
            $block['roles'][] = [
                'key' => $key,
                'label' => $meta['short'] ?? $meta['label'],
                'usage' => array_map(static fn($u) => ['label' => $u], $meta['usage']),
                'cssvar' => '--nit-brand-' . $meta['role'],
                'value' => $value,
                'default' => $meta['default'],
                'isdefault' => (strtolower($value) === strtolower($meta['default'])),
            ];
            unset($block);
        }

        // The navbar's non-colour cards, dropped into the block of the subject
        // they describe rather than collected at the end of the section:
        //
        //   * TYPOGRAPHY — how big and how heavy the subject is set. One card
        //     holding both controls, because "16px" and "Semi-bold" are one
        //     decision about one thing, not two.
        //   * SHAPES — which of the three treatments mark hover and active. A
        //     card of tick boxes, not a picker: any combination is a valid answer
        //     and so is none, which a list of named looks could not say.
        $weightoptions = \theme_nit_navbar_weights();
        $treatments = \theme_nit_navbar_shape_treatments();
        foreach (array_keys($keyed) as $gkey) {
            foreach (\theme_nit_navbar_type_subjects() as $subject => $meta) {
                $weight = \theme_nit_navbar_type_weight($gkey, $subject);
                $keyed[$gkey]['sections']['navbar']['blocks'][$meta['sub']]['typography'][] = [
                    'label' => $meta['short'] ?? $meta['label'],
                    'usage' => array_map(static fn($u) => ['label' => $u], $meta['usage']),
                    'sizeinput' => 'navbarsize_' . $gkey . '_' . $subject,
                    'sizevalue' => \theme_nit_navbar_type_size($gkey, $subject),
                    'sizemin' => $meta['min'],
                    'sizemax' => $meta['max'],
                    'sizedefault' => $meta['size'],
                    'weightinput' => 'navbarweight_' . $gkey . '_' . $subject,
                    'weightoptions' => array_map(static fn($w, $wlabel) => [
                        'value' => $w,
                        'label' => $wlabel,
                        'selected' => ($w === $weight),
                    ], array_keys($weightoptions), $weightoptions),
                ];
            }
            // The glass: whether the bar is see-through at all, and by how much.
            // One card, because they are one decision — the degree is meaningless
            // with the switch off, and the switch alone leaves nothing to tune.
            $glass = \theme_nit_navbar_glass($gkey);
            $keyed[$gkey]['sections']['navbar']['blocks']['background']['glass'][] = [
                'oninput' => 'navbarglass_' . $gkey,
                'onid' => 'nit-navbarglass-' . $gkey,
                'on' => $glass['on'],
                'valueinput' => 'navbartransparency_' . $gkey,
                'valueid' => 'nit-navbartransparency-' . $gkey,
                'value' => $glass['transparency'],
            ];

            // The group the bar switches to once the page moves. "Same as this
            // group" is stored as the group itself and means "do not change";
            // it is first in the list because it is the answer for almost every
            // site.
            $scroll = \theme_nit_navbar_scroll_group($gkey);
            $keyed[$gkey]['sections']['navbar']['blocks']['scroll']['scrollgroup'][] = [
                'input' => 'navbarscrollgroup_' . $gkey,
                'id' => 'nit-navbarscrollgroup-' . $gkey,
                'options' => array_map(static fn($okey, $olabel) => [
                    'value' => $okey,
                    'label' => ($okey === $gkey)
                        ? get_string('navbarscroll_same', 'theme_nit')
                        : $olabel,
                    'selected' => ($okey === $scroll),
                ], array_keys(\theme_nit_brand_groups()), \theme_nit_brand_groups()),
            ];

            foreach (\theme_nit_navbar_shape_states() as $state => $meta) {
                $ticked = \theme_nit_navbar_shape($gkey, $state);
                $input = 'navbarshape_' . $gkey . '_' . $state;
                $options = [];
                foreach (array_keys($treatments) as $tkey) {
                    $options[] = [
                        'id' => 'nit-' . $input . '-' . $tkey,
                        'value' => $tkey,
                        'label' => $treatments[$tkey]['label'],
                        'checked' => in_array($tkey, $ticked, true),
                    ];
                }
                $keyed[$gkey]['sections']['navbar']['blocks'][$meta['sub']]['shapes'][] = [
                    'input' => $input,
                    'label' => $meta['short'] ?? $meta['label'],
                    'options' => $options,
                ];
            }

            // Whether each outline button paints its Background role at all. A
            // switch and not just the colour card above it, because an outline
            // button's resting fill is transparent by design — it takes the
            // colour of the page or the card it sits on, and no single hex is
            // right in both places. Off until an admin says otherwise, at which
            // point the colour beside it starts being drawn.
            //
            // One per outline block, landing in the block beside the six colours
            // it governs rather than in a settings list somewhere else.
            foreach (array_keys(\theme_nit_button_outline_variants()) as $variant) {
                $keyed[$gkey]['sections']['brand']['blocks'][$variant]['switches'][] = [
                    'input' => $variant . 'fill_' . $gkey,
                    'id' => 'nit-' . $variant . 'fill-' . $gkey,
                    'label' => get_string('btnoutlinefill', 'theme_nit'),
                    'usage' => get_string('btnoutlinefill_usage', 'theme_nit'),
                    'on' => \theme_nit_button_outline_fill($gkey, $variant),
                ];
            }
        }

        // Flatten: keyed maps become the ordered lists the template walks.
        $brandgroups = [];
        foreach ($keyed as $gkey => $group) {
            $sections = [];
            foreach ($group['sections'] as $skey => $section) {
                $blocks = [];
                foreach ($section['blocks'] as $sub => $block) {
                    // The heading is looked up from the block KEY here rather
                    // than stored on the block, so the three places that fill a
                    // block (roles, typography, shapes) cannot each remember to
                    // set it — and two of them would have forgotten.
                    $blocklabel = $blocklabels[$sub] ?? '';
                    $blocks[] = [
                        // A section that was never broken into blocks has one
                        // unnamed block, and renders exactly as it did before.
                        'label' => $blocklabel,
                        'hasheading' => ($blocklabel !== ''),
                        'roles' => $block['roles'] ?? [],
                        'glass' => $block['glass'] ?? [],
                        'typography' => $block['typography'] ?? [],
                        'shapes' => $block['shapes'] ?? [],
                        'switches' => $block['switches'] ?? [],
                        'scrollgroup' => $block['scrollgroup'] ?? [],
                    ];
                }
                $sections[] = [
                    'key' => $skey,
                    // Unique per group: the same section appears in all five.
                    'paneid' => 'nit-brand-' . $gkey . '-' . $skey,
                    'label' => $section['label'],
                    'isfirst' => empty($sections),
                    'blocks' => $blocks,
                ];
            }
            $brandgroups[] = [
                'name' => $group['name'],
                'groupkey' => $gkey,
                // The first group's editor is the one shown on arrival.
                'isfirst' => empty($brandgroups),
                'sections' => $sections,
            ];
        }

        // Four dots on each group's pill, so the pills say which palette they
        // switch to instead of only saying "Group 3". These are the roles that
        // actually tell two groups apart at a glance — the bar, the page, the
        // main button and the link colour.
        $pills = [];
        foreach ($brandgroups as $group) {
            $swatches = [];
            foreach (['navbarbackground1', 'background', 'primary', 'accenttext'] as $role) {
                $swatches[] = ['value' => \theme_nit_brandcolour($group['groupkey'] . '_' . $role)];
            }
            $pills[] = [
                'groupkey' => $group['groupkey'],
                'name' => $group['name'],
                'swatches' => $swatches,
            ];
        }

        // The group switcher rides inside each group's own editor rather than
        // standing above all five, so the whole header — both choices and both
        // buttons — is ONE sticky element instead of two stacked ones needing a
        // hard-coded offset between them, and the Save button stays inside the
        // form it submits. Only one editor is ever on screen, so only one copy
        // of the switcher is ever visible. `iscurrent` is the pill for the group
        // whose editor this copy belongs to.
        foreach ($brandgroups as $gi => $group) {
            $brandgroups[$gi]['groupswitch'] = array_map(
                static fn($pill) => $pill + ['iscurrent' => ($pill['groupkey'] === $group['groupkey'])],
                $pills
            );
        }

        // Category branding for the "Category styles" tab. Only the MAIN
        // (top-level) categories are assignable; every page under a main
        // category (its subcategories, its courses, filtered views) inherits it
        // via theme_nit_category_brand_group() / theme_nit_category_logo_url().
        //
        // Each row carries the same decision twice — once for light mode, once
        // for dark — because that is what a category's style now is: a pair the
        // navbar light/dark button moves between. `modes` is that pair, in
        // switch order, so the template prints the columns from the data rather
        // than naming "light" and "dark" itself.
        global $CFG;
        // Category pictures come from local_nit_category; load it so the per-row
        // thumbnail below can resolve, but keep the tab working without it.
        if (file_exists($CFG->dirroot . '/local/nit_category/lib.php')) {
            require_once($CFG->dirroot . '/local/nit_category/lib.php');
        }

        $grouplabels = \theme_nit_brand_groups();
        $modelabels = [
            'light' => \get_string('modelight', 'theme_nit'),
            'dark' => \get_string('modedark', 'theme_nit'),
        ];
        $logoslots = \theme_nit_category_logo_slots();

        // One read of each map, rather than one per category.
        $catmaps = [];
        foreach (array_keys(\theme_nit_modes()) as $mode) {
            $catmaps[$mode] = \theme_nit_category_group_map($mode);
        }

        // The column headings, in the same order every row's cells come out in.
        // Built here rather than in the template because each one names a mode:
        // "Style — light mode", "Logo — dark mode".
        $catmodecolumns = [];
        foreach (array_keys($catmaps) as $mode) {
            $label = $modelabels[$mode] ?? $mode;
            $catmodecolumns[] = [
                'mode' => $mode,
                'label' => $label,
                'stylelabel' => \get_string('categorystyles_col_stylefor', 'theme_nit', $label),
                'logolabel' => \get_string('categorystyles_col_logofor', 'theme_nit', $label),
            ];
        }

        // Every file field this form renders, so the page can tell the server
        // which uploads were actually on their way — see the max_file_uploads
        // note on the pruning script in gallery.php.
        $catlogofields = [];

        $categorygroups = [];
        foreach (\core_course_category::top()->get_children() as $cat) {
            $modes = [];
            foreach ($catmaps as $mode => $map) {
                $current = $map[$cat->id] ?? '';
                if (!array_key_exists($current, $grouplabels)) {
                    $current = '';
                }
                // "Site default" first, and selected when nothing is assigned:
                // an unassigned category must leave its pages on the site's own
                // group for that mode, which is not the same answer as Group 1.
                $options = [[
                    'value' => '',
                    'label' => \get_string('categorystyles_sitedefault', 'theme_nit'),
                    'selected' => ($current === ''),
                ]];
                foreach ($grouplabels as $gkey => $glabel) {
                    $options[] = ['value' => $gkey, 'label' => $glabel, 'selected' => ($gkey === $current)];
                }

                $slot = $logoslots[$mode];
                $logo = \theme_nit_category_logo_url((int) $cat->id, $mode);
                $catlogofields[] = $slot['input'] . '_' . (int) $cat->id;
                $modes[] = [
                    'mode' => $mode,
                    'label' => $modelabels[$mode] ?? $mode,
                    'options' => $options,
                    'isdefault' => ($current === ''),
                    'select' => 'catgroup' . $mode . '[' . (int) $cat->id . ']',
                    'logoinput' => $slot['input'] . '_' . (int) $cat->id,
                    'logoremove' => $slot['remove'] . '[' . (int) $cat->id . ']',
                    'haslogo' => (bool) $logo,
                    'logourl' => $logo ? $logo->out(false) : '',
                ];
            }

            // The category's picture (local_nit_category), so this one screen shows
            // both halves of a category's branding — its palette and its image — and
            // an admin can see at a glance which categories are still missing one.
            $image = '';
            if (function_exists('local_nit_category_get_image_url')) {
                $image = \local_nit_category_get_image_url((int) $cat->id, false);
            }

            $categorygroups[] = [
                'id' => $cat->id,
                'name' => $cat->get_formatted_name(),
                'modes' => $modes,
                'image' => $image,
                'hasimage' => ($image !== ''),
                'imageurl' => (new \moodle_url('/local/nit_category/image.php', ['id' => $cat->id]))->out(false),
            ];
        }

        // Display mode → brand-group mapping for the "Site styles" section of the
        // same tab. This is what the navbar light/dark button switches between
        // OUTSIDE a styled category: the button holds no palette, it just puts
        // the chosen group's switch class on <html>. Same shape as the category
        // rows above (one selector per row, pre-set to the stored assignment)
        // because it is the same decision made about a different subject.
        // `isdefault` marks the mode a visitor with no `nit_mode` cookie opens in
        // (theme_nit_default_mode()) — one radio per row, saved with the same button.
        $modegroups = [];
        $defaultmode = \theme_nit_default_mode();
        foreach (\theme_nit_mode_groups() as $mode => $current) {
            $options = [];
            foreach ($grouplabels as $gkey => $glabel) {
                $options[] = ['value' => $gkey, 'label' => $glabel, 'selected' => ($gkey === $current)];
            }
            $modegroups[] = [
                'mode' => $mode,
                'label' => $modelabels[$mode] ?? $mode,
                'options' => $options,
                'isdefault' => ($mode === $defaultmode),
            ];
        }

        // Home page chrome, on the same tab: one checkbox per part of the page
        // frame the Site home may go without (theme_nit_home_chrome()). Ticked
        // means shown, which is also what an unsaved site gets.
        $homechrome = [];
        foreach (\theme_nit_home_chrome() as $part => $shown) {
            $homechrome[] = [
                'key' => $part,
                'label' => \get_string('homechrome_' . $part, 'theme_nit'),
                'description' => \get_string('homechrome_' . $part . '_desc', 'theme_nit'),
                'checked' => $shown,
            ];
        }

        // Per-language font slots: current filename (if any) + a live preview
        // that renders in the uploaded family the compiled CSS already exposes.
        $fonts = [];
        foreach (\theme_nit_font_slots() as $slot) {
            $filename = \get_config('theme_nit', $slot['setting']);
            $hasfont = is_string($filename) && $filename !== '';
            $fonts[] = [
                'input' => $slot['input'],
                'label' => \get_string($slot['strkey'], 'theme_nit'),
                'family' => $slot['family'],
                'sample' => \get_string($slot['samplekey'], 'theme_nit'),
                'rtl' => $slot['rtl'],
                'hasfont' => $hasfont,
                'filename' => $hasfont ? ltrim($filename, '/') : '',
            ];
        }

        // Account screens: the picture beside each of the two cards, plus the
        // quote drawn over it. The picture URL is the live one the compiled CSS
        // uses (theme_nit_auth_background_scss reads the same slot), so the
        // thumbnail on this tab is the thing itself and not a re-derivation of it.
        $theme = \theme_config::load('nit');
        $authimages = [];
        foreach (\theme_nit_auth_image_slots() as $key => $slot) {
            $filename = \get_config('theme_nit', $slot['setting']);
            $hasimage = is_string($filename) && $filename !== '';
            $authimages[] = [
                'key' => $key,
                'input' => $slot['input'],
                'label' => \get_string($slot['strkey'], 'theme_nit'),
                'description' => \get_string($slot['deskey'], 'theme_nit'),
                'hasimage' => $hasimage,
                'filename' => $hasimage ? ltrim($filename, '/') : '',
                'url' => $hasimage
                    ? (string) $theme->setting_file_url($slot['setting'], $slot['filearea'])
                    : '',
            ];
        }

        // One quote + attribution pair per language. `rtl` lets the template turn
        // the Arabic boxes round, so what an admin types looks like what a learner
        // will read.
        $authtexts = [];
        foreach (\theme_nit_auth_text_langs() as $lang => $meta) {
            $stored = \theme_nit_auth_text($lang);
            $authtexts[] = [
                'lang' => $lang,
                'label' => \get_string($meta['strkey'], 'theme_nit'),
                'rtl' => $meta['rtl'],
                'quote' => $stored['quote'],
                'author' => $stored['author'],
            ];
        }

        return [
            'sesskey' => sesskey(),
            'actionurl' => (new \moodle_url('/theme/nit/gallery.php'))->out(false),
            'brandgroups' => $brandgroups,
            'categorygroups' => $categorygroups,
            'catmodecolumns' => $catmodecolumns,
            'catlogofields' => implode(',', $catlogofields),
            'hascategorygroups' => !empty($categorygroups),
            'modegroups' => $modegroups,
            'homechrome' => $homechrome,
            'fonts' => $fonts,
            'authimages' => $authimages,
            'authtexts' => $authtexts,
            'stats' => [
                ['label' => 'Active learners', 'value' => '1,284', 'trend' => '+12%', 'up' => true],
                ['label' => 'Course completions', 'value' => '842', 'trend' => '+5%', 'up' => true],
                ['label' => 'Overdue tasks', 'value' => '37', 'trend' => '-8%', 'up' => false],
            ],
        ];
    }
}
