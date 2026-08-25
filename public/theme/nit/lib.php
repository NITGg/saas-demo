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
 * NIT theme SCSS callbacks.
 *
 * Composition (one combined stream): pre_scss -> main -> extra.
 *   pre   : primitives -> mixins -> semantic (Bootstrap var overrides) -> pre.
 *   main  : Boost preset (Bootstrap compiles with NIT values) -> NIT components.
 *   extra : component-tier CSS custom properties -> fonts.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Concatenate the contents of every .scss file in a directory (sorted).
 *
 * @param string $dir absolute directory path
 * @return string combined SCSS
 */
function theme_nit_concat_scss(string $dir): string {
    $files = glob($dir . '/*.scss') ?: [];
    sort($files);
    $scss = '';
    foreach ($files as $file) {
        $scss .= file_get_contents($file) . "\n";
    }
    return $scss;
}

/**
 * The NIT colour palette — the single source of truth for the site's colours.
 *
 * This is the "AppColors" of the theme: a flat set of semantically-named colour
 * tokens the site is built from. It powers three things at once:
 *   1. The colour editor on the gallery page (theme/nit/gallery.php) renders one
 *      picker per token, grouped by the `group` label.
 *   2. theme_nit_get_pre_scss() emits each token as a `$nit-c-<key>` SCSS
 *      variable (config value, else the default here) before Bootstrap compiles.
 *   3. scss/foundation/_root.scss republishes each as a `--nit-<key>` CSS custom
 *      property, so any component — the navbar included — reads its colour from
 *      the palette via `var(--nit-<key>)`.
 *
 * Defaults are the colours the site already uses today (navbar + home), so an
 * untouched install looks identical to before the editor existed. `key` becomes
 * the config name `colour_<key>`, the SCSS var `$nit-c-<key>` and the custom
 * property `--nit-<key>`.
 *
 * @return array<string, array{group:string, label:string, default:string}>
 *         ordered map keyed by token key
 */
function theme_nit_colour_palette(): array {
    return [
        // --- Brand (site) : Bootstrap $primary/$secondary + marketing accents --
        // Aligned to Brand-Colors Group 1 (Slate blue). No red anywhere; the old
        // gold marketing accent is now a soft blue so the whole palette is one
        // calm, cohesive cool family. (Most of these are aliased onto the brand
        // layer in _root.scss; the defaults here keep the legacy vars, the login
        // gradient companion, and the mobile export consistent with the brand.)
        'primary'          => ['group' => 'Brand', 'label' => 'Primary', 'default' => '#5488c4'],
        'secondary'        => ['group' => 'Brand', 'label' => 'Secondary', 'default' => '#33475e'],
        'accentgold'       => ['group' => 'Brand', 'label' => 'Accent (link / underline)', 'default' => '#7fabdb'],
        'accentgolddark'   => ['group' => 'Brand', 'label' => 'Accent (dark / gradient)', 'default' => '#5488c4'],
        'accentteal'       => ['group' => 'Brand', 'label' => 'Accent teal', 'default' => '#2f9e8f'],

        // --- Navbar : the slate top bar ----------------------------------------
        'navbarbg'         => ['group' => 'Navbar', 'label' => 'Navbar background', 'default' => '#0c141f'],
        'navbarsurface'    => ['group' => 'Navbar', 'label' => 'Navbar surface (buttons)', 'default' => '#121e2d'],
        'navbarborder'     => ['group' => 'Navbar', 'label' => 'Navbar border', 'default' => '#223244'],
        'navbaraccent'     => ['group' => 'Navbar', 'label' => 'Navbar accent', 'default' => '#7fabdb'],
        'navbaraccenthover' => ['group' => 'Navbar', 'label' => 'Navbar accent hover', 'default' => '#a9c8e6'],
        'navbartext'       => ['group' => 'Navbar', 'label' => 'Navbar text', 'default' => '#eef3f9'],
        'navbarpanel'      => ['group' => 'Navbar', 'label' => 'Dropdown panel background', 'default' => '#121e2d'],
        'navbarpaneltext'  => ['group' => 'Navbar', 'label' => 'Dropdown item text', 'default' => '#94a3b8'],
        'navbarpanelborder' => ['group' => 'Navbar', 'label' => 'Dropdown divider', 'default' => '#223244'],

        // --- Neutrals : surfaces, text, borders (the dark slate ground) --------
        'background'       => ['group' => 'Neutrals', 'label' => 'Background', 'default' => '#0c141f'],
        'surface'          => ['group' => 'Neutrals', 'label' => 'Surface (subtle fill)', 'default' => '#121e2d'],
        'textprimary'      => ['group' => 'Neutrals', 'label' => 'Text primary', 'default' => '#eef3f9'],
        'textsecondary'    => ['group' => 'Neutrals', 'label' => 'Text secondary', 'default' => '#94a3b8'],
        'border'           => ['group' => 'Neutrals', 'label' => 'Border', 'default' => '#223244'],

        // --- Semantic : status colours (red-free — danger is a warm orange) -----
        'success'          => ['group' => 'Semantic', 'label' => 'Success', 'default' => '#3fa877'],
        'warning'          => ['group' => 'Semantic', 'label' => 'Warning', 'default' => '#d8c24e'],
        'error'            => ['group' => 'Semantic', 'label' => 'Error / danger', 'default' => '#d07f43'],
        'info'             => ['group' => 'Semantic', 'label' => 'Info', 'default' => '#5fb0c9'],

        // --- Dark : the always-dark marketing bands (aligned to Group 1 slate) --
        'darkprimary'         => ['group' => 'Dark', 'label' => 'Dark primary', 'default' => '#5488c4'],
        'darkbackground'      => ['group' => 'Dark', 'label' => 'Dark background', 'default' => '#0c141f'],
        'darksurface'         => ['group' => 'Dark', 'label' => 'Dark surface (card)', 'default' => '#121e2d'],
        'darksurfacevariant'  => ['group' => 'Dark', 'label' => 'Dark surface (raised)', 'default' => '#1c2a3a'],
        'darktextprimary'     => ['group' => 'Dark', 'label' => 'Dark text primary', 'default' => '#eef3f9'],
        'darktextsecondary'   => ['group' => 'Dark', 'label' => 'Dark text secondary', 'default' => '#94a3b8'],
        'darkborder'          => ['group' => 'Dark', 'label' => 'Dark border', 'default' => '#223244'],

        // --- Categories : three interchangeable colour styles for the category
        // details page (local_nit_category). Aligned 1:1 with the three Brand
        // Colors groups so a category page matches its brand group:
        //   Style 1 = Group 1 (Slate blue) · Style 2 = Group 2 (Teal)
        //   Style 3 = Group 3 (Indigo). Eight tokens each: 4 text + 4 background.
        'cat_style1_text1' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 1', 'default' => '#eef3f9'],
        'cat_style1_text2' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 2', 'default' => '#94a3b8'],
        'cat_style1_text3' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 3', 'default' => '#7fabdb'],
        'cat_style1_text4' => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'Text 4', 'default' => '#0c141f'],
        'cat_style1_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 1', 'default' => '#0c141f'],
        'cat_style1_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 2', 'default' => '#121e2d'],
        'cat_style1_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 3', 'default' => '#1c2a3a'],
        'cat_style1_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 1', 'label' => 'BG 4', 'default' => '#5488c4'],

        'cat_style2_text1' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 1', 'default' => '#eef5f4'],
        'cat_style2_text2' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 2', 'default' => '#8aa5a2'],
        'cat_style2_text3' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 3', 'default' => '#58bdad'],
        'cat_style2_text4' => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'Text 4', 'default' => '#06201d'],
        'cat_style2_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 1', 'default' => '#0a1a1a'],
        'cat_style2_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 2', 'default' => '#102727'],
        'cat_style2_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 3', 'default' => '#143231'],
        'cat_style2_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 2', 'label' => 'BG 4', 'default' => '#2f9e8f'],

        'cat_style3_text1' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 1', 'default' => '#efedf7'],
        'cat_style3_text2' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 2', 'default' => '#9691b3'],
        'cat_style3_text3' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 3', 'default' => '#a99ee2'],
        'cat_style3_text4' => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'Text 4', 'default' => '#11101c'],
        'cat_style3_bg1'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 1', 'default' => '#11101c'],
        'cat_style3_bg2'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 2', 'default' => '#1a182d'],
        'cat_style3_bg3'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 3', 'default' => '#201e34'],
        'cat_style3_bg4'   => ['group' => 'Categories', 'subgroup' => 'Style 3', 'label' => 'BG 4', 'default' => '#8478cf'],
    ];
}

/**
 * The resolved value of one palette token: the saved config, else its default.
 *
 * @param string $key palette key (see theme_nit_colour_palette())
 * @return string a `#rrggbb` colour
 */
function theme_nit_colour(string $key): string {
    $palette = theme_nit_colour_palette();
    $default = $palette[$key]['default'] ?? '#000000';
    $value = get_config('theme_nit', 'colour_' . $key);
    return (is_string($value) && $value !== '') ? $value : $default;
}

/**
 * The whole resolved colour palette, for API / export consumption.
 *
 * Each entry carries the token's group, label, the live resolved value (saved
 * config, else default) and its default — so a client (e.g. the mobile app) can
 * both apply the colours and show which were customised. Backs colours.php.
 *
 * @return array<int, array{key:string, group:string, label:string, value:string, default:string, iscustom:bool}>
 */
function theme_nit_colours_all(): array {
    $out = [];
    foreach (theme_nit_colour_palette() as $key => $meta) {
        $value = theme_nit_colour($key);
        $out[] = [
            'key' => $key,
            'group' => $meta['group'],
            'label' => $meta['label'],
            'value' => $value,
            'default' => $meta['default'],
            'iscustom' => (strtolower($value) !== strtolower($meta['default'])),
        ];
    }
    return $out;
}

/**
 * The 16 semantic roles every Brand-Colors group is built from.
 *
 * This is the clean, small semantic layer that replaces the sprawling
 * theme_nit_colour_palette(): a component references a role by name (Primary,
 * Surface, Text primary, …) and never a raw colour. `label` is the display name
 * and `usage` is a list of the concrete UI things that should use the colour —
 * rendered as chips on the gallery's Brand Colors tab. `default` here is only a
 * red-free FALLBACK (the Group 1 / Slate-blue values): every group overrides all
 * 16 roles in theme_nit_brand_group_defaults(), so a role default is used only if
 * a group ever omits a role. The Hover Background / Hover Text roles carry the
 * explicit hover colours (other opacity variants are still derived in SCSS, see
 * scss/foundation/_brand.scss).
 *
 * @return array<string, array{label:string, usage:string[], default:string}>
 */
function theme_nit_brand_roles(): array {
    return [
        'primary'         => ['label' => 'Primary', 'usage' => ['background main button', 'checked toggles', 'progress fill', 'notification dots', 'navbar background'], 'default' => '#5488c4'],
        'secondary'       => ['label' => 'Secondary', 'usage' => ['background secondary button'], 'default' => '#1c2a3a'],
        'accent'          => ['label' => 'Accent', 'usage' => ['none text'], 'default' => '#5488c4'],
        'accenttext'      => ['label' => 'Accent Text', 'usage' => ['text of links', 'important words', 'underlines'], 'default' => '#7fabdb'],
        'background'      => ['label' => 'Background', 'usage' => ['page background'], 'default' => '#0c141f'],
        'surface'         => ['label' => 'Surface', 'usage' => ['Cards background', 'dropdowns background', 'side menu background', 'inputs background', 'tooltips background', 'table background', 'page sections background'], 'default' => '#121e2d'],
        'textprimary'     => ['label' => 'Text primary', 'usage' => ['main normal text', 'text in buttons', 'text in inputs', 'navbar text', 'navbar underline'], 'default' => '#eef3f9'],
        'textsecondary'   => ['label' => 'Text secondary', 'usage' => ['secondary normal text', 'placeholders'], 'default' => '#94a3b8'],
        'borderprimary'   => ['label' => 'Border primary', 'usage' => ['main border color'], 'default' => '#223244'],
        'bordersecondary' => ['label' => 'Border secondary', 'usage' => ['secondary border color'], 'default' => '#33475e'],
        'hoverbackground' => ['label' => 'Hover Background', 'usage' => ['hover background'], 'default' => '#16222f'],
        'hovertext'       => ['label' => 'Hover Text', 'usage' => ['hover text'], 'default' => '#7fabdb'],
        'error'           => ['label' => 'Error', 'usage' => ['Errors', 'danger / destructive actions', 'invalid fields'], 'default' => '#d07f43'],
        'success'         => ['label' => 'Success', 'usage' => ['Success', 'enrolled / active / paid', 'positive states'], 'default' => '#3fa877'],
        'warning'         => ['label' => 'Warning', 'usage' => ['Warnings', 'caution', 'pending / expiring'], 'default' => '#d8c24e'],
        'info'            => ['label' => 'Info', 'usage' => ['Neutral notices', 'tips', 'hints'], 'default' => '#5fb0c9'],
    ];
}

/**
 * The ordered Brand-Colors groups.
 *
 * A "group" is a complete named set of all 13 roles — a swappable palette.
 * Group 1 is the site-wide default; a component can opt into another group via
 * the matching wrapper class (`.nit-brand-2`, `.nit-brand-3`), keeping the same
 * variable names but resolving them from that group's values. Groups 2 and 3
 * seed equal to Group 1 and are tuned later on the gallery page.
 *
 * @return array<string, string> group key (g1/g2/g3) => display label
 */
function theme_nit_brand_groups(): array {
    return ['g1' => 'Group 1', 'g2' => 'Group 2', 'g3' => 'Group 3'];
}

/**
 * The Brand-Colors group assigned to a category (for the category details page).
 *
 * Admins map only the MAIN (top-level) categories to groups on the gallery
 * "Category styles" tab; the map is stored as the theme_nit config
 * `nit_category_groups` (JSON `{topcatid: "g2", …}`). A category page resolves to
 * the group of its top-level ancestor, so every subcategory / filtered view under
 * a main category inherits that main category's group. Unassigned → Group 1.
 *
 * @param int $categoryid the category whose page is being rendered
 * @return string one of the group keys from theme_nit_brand_groups() (g1/g2/g3)
 */
function theme_nit_category_brand_group(int $categoryid): string {
    static $map = null;
    if ($map === null) {
        $raw = get_config('theme_nit', 'nit_category_groups');
        $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    }
    if (empty($map)) {
        return 'g1';
    }
    // Styles are assigned per main category → resolve to the top-level ancestor.
    $topid = $categoryid;
    try {
        $cat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($cat) {
            $parents = $cat->get_parents();      // Ancestor ids, top-most first, excludes self.
            $topid = !empty($parents) ? (int) $parents[0] : (int) $categoryid;
        }
    } catch (\Throwable $e) {
        $topid = $categoryid;
    }
    $group = $map[$topid] ?? 'g1';
    return array_key_exists($group, theme_nit_brand_groups()) ? $group : 'g1';
}

/**
 * The CSS body/wrapper class that switches an element to a brand group.
 *
 * Group 1 is the default layer (no class); groups 2/3 map to the switch classes
 * declared in scss/foundation/_brand.scss.
 *
 * @param string $group a group key (g1/g2/g3)
 * @return string '' | 'nit-brand-2' | 'nit-brand-3'
 */
function theme_nit_brand_group_class(string $group): string {
    $classes = ['g1' => '', 'g2' => 'nit-brand-2', 'g3' => 'nit-brand-3'];
    return $classes[$group] ?? '';
}

/**
 * Per-group default overrides for the Brand-Colors palette.
 *
 * Each of the three groups is a complete, self-contained theme with its own
 * distinct — but deliberately calm and low-strain — mood, so an admin can skin a
 * category with a genuinely different look by switching groups. All three are
 * dark palettes tuned for eye comfort: desaturated accents (no harsh, fully
 * saturated hues), gentle contrast, and semantic colours (error/success/…) kept
 * softened but still readable.
 *
 * No red anywhere — not as a brand accent and not for the semantic "error"
 * role: danger is signalled with a warm orange and caution with yellow, so the
 * two stay distinguishable while keeping the palette entirely red-free.
 *
 *   - Group 1 — Slate blue : calm, cool blue accent on a deep slate ground.
 *   - Group 2 — Teal / Deep sea : cool, restful green-teal on near-black teal.
 *   - Group 3 — Indigo / Lavender : soft violet on a deep indigo ground.
 *
 * A role missing from a group falls back to theme_nit_brand_roles()['default'].
 *
 * @return array<string, array<string, string>> group key (g1/g2/g3) => role => #hex
 */
function theme_nit_brand_group_defaults(): array {
    return [
        // --- Group 1 : Slate blue (calm, cool). -------------------------------
        'g1' => [
            'primary'         => '#5488c4',
            'secondary'       => '#1c2a3a',
            'accent'          => '#5488c4',
            'accenttext'      => '#7fabdb',
            'background'      => '#0c141f',
            'surface'         => '#121e2d',
            'textprimary'     => '#eef3f9',
            'textsecondary'   => '#94a3b8',
            'borderprimary'   => '#223244',
            'bordersecondary' => '#33475e',
            'hoverbackground' => '#16222f',
            'hovertext'       => '#7fabdb',
            'error'           => '#d07f43',
            'success'         => '#3fa877',
            'warning'         => '#d8c24e',
            'info'            => '#5fb0c9',
        ],
        // --- Group 2 : Teal / Deep sea (cool, restful). -----------------------
        'g2' => [
            'primary'         => '#2f9e8f',
            'secondary'       => '#12302e',
            'accent'          => '#2f9e8f',
            'accenttext'      => '#58bdad',
            'background'      => '#0a1a1a',
            'surface'         => '#102727',
            'textprimary'     => '#eef5f4',
            'textsecondary'   => '#8aa5a2',
            'borderprimary'   => '#1f3f3d',
            'bordersecondary' => '#2f5a56',
            'hoverbackground' => '#143231',
            'hovertext'       => '#6ccabb',
            'error'           => '#d07f43',
            'success'         => '#46b085',
            'warning'         => '#d8c24e',
            'info'            => '#6aa6c9',
        ],
        // --- Group 3 : Indigo / Lavender (soft, cool violet). -----------------
        'g3' => [
            'primary'         => '#8478cf',
            'secondary'       => '#26243d',
            'accent'          => '#8478cf',
            'accenttext'      => '#a99ee2',
            'background'      => '#11101c',
            'surface'         => '#1a182d',
            'textprimary'     => '#efedf7',
            'textsecondary'   => '#9691b3',
            'borderprimary'   => '#2d2a45',
            'bordersecondary' => '#433d64',
            'hoverbackground' => '#201e34',
            'hovertext'       => '#b4a9ee',
            'error'           => '#d07f43',
            'success'         => '#57b39a',
            'warning'         => '#d8c24e',
            'info'            => '#7fa6d6',
        ],
    ];
}

/**
 * The full Brand-Colors palette: every group × every role, flattened.
 *
 * Keyed `g<N>_<role>` (e.g. `g1_primary`); the key becomes the config name
 * `brandcolour_<key>`, the SCSS var `$nit-b-<gkey>-<role>` and the per-group
 * custom property `--nit-brand-<gkey>-<role>`. Powers the Brand Colors editor,
 * the pre-SCSS emission and the _brand.scss custom-property layer.
 *
 * Each group's per-role default comes from theme_nit_brand_group_defaults(),
 * falling back to the shared role default (theme_nit_brand_roles()) when a group
 * does not override a role — so the three groups ship as distinct palettes.
 *
 * @return array<string, array{group:string, groupkey:string, role:string,
 *         label:string, usage:string, default:string}> ordered map keyed by token key
 */
function theme_nit_brand_palette(): array {
    $out = [];
    $roles = theme_nit_brand_roles();
    $groupdefaults = theme_nit_brand_group_defaults();
    foreach (theme_nit_brand_groups() as $gkey => $glabel) {
        foreach ($roles as $role => $meta) {
            $out[$gkey . '_' . $role] = [
                'group'    => $glabel,
                'groupkey' => $gkey,
                'role'     => $role,
                'label'    => $meta['label'],
                'usage'    => $meta['usage'],
                'default'  => $groupdefaults[$gkey][$role] ?? $meta['default'],
            ];
        }
    }
    return $out;
}

/**
 * The resolved value of one Brand-Colors token: the saved config, else default.
 *
 * @param string $key brand palette key (see theme_nit_brand_palette())
 * @return string a `#rrggbb` colour
 */
function theme_nit_brandcolour(string $key): string {
    $palette = theme_nit_brand_palette();
    $default = $palette[$key]['default'] ?? '#000000';
    $value = get_config('theme_nit', 'brandcolour_' . $key);
    return (is_string($value) && $value !== '') ? $value : $default;
}

/**
 * The whole resolved Brand-Colors palette, for the editor / export.
 *
 * @return array<int, array{key:string, group:string, groupkey:string, role:string,
 *         label:string, usage:string, value:string, default:string, iscustom:bool}>
 */
function theme_nit_brand_all(): array {
    $out = [];
    foreach (theme_nit_brand_palette() as $key => $meta) {
        $value = theme_nit_brandcolour($key);
        $out[] = [
            'key'      => $key,
            'group'    => $meta['group'],
            'groupkey' => $meta['groupkey'],
            'role'     => $meta['role'],
            'label'    => $meta['label'],
            'usage'    => $meta['usage'],
            'value'    => $value,
            'default'  => $meta['default'],
            'iscustom' => (strtolower($value) !== strtolower($meta['default'])),
        ];
    }
    return $out;
}

// -----------------------------------------------------------------------------
// Design-system export helpers.
//
// These back the public JSON API (design_system.php) that mirrors the four tabs
// of the gallery page for external clients (the Flutter / mobile app):
//   1. Brand Colors    → theme_nit_brand_export()
//   2. Category styles → theme_nit_category_styles_export()
//   3. Fonts           → theme_nit_fonts_export()
//   4. Components      → theme_nit_components_export()
// theme_nit_design_system_export() wraps all four into one payload. Everything
// returned is public branding metadata (already visible in the site CSS / DOM);
// nothing sensitive is exposed.
// -----------------------------------------------------------------------------

/**
 * Brand Colors tab, as data: every group with its resolved roles.
 *
 * One entry per group (Group 1/2/3). Group 1 is the site-wide default (applied
 * with no wrapper class); groups 2/3 are activated by wrapping an element in the
 * matching `class` (`nit-brand-2` / `nit-brand-3`), which re-resolves the same
 * `--nit-brand-<role>` custom properties to that group's values. `roles` lists
 * the site-wide role keys shared by every group, in order.
 *
 * @return array{roles: string[], groups: array<int, array{key:string, name:string,
 *         isdefault:bool, class:string, roles: array}>}
 */
function theme_nit_brand_export(): array {
    $groups = [];
    $gidx = [];
    foreach (theme_nit_brand_all() as $token) {
        $gkey = $token['groupkey'];
        if (!array_key_exists($gkey, $gidx)) {
            $gidx[$gkey] = count($groups);
            $groups[] = [
                'key'       => $gkey,
                'name'      => $token['group'],
                'isdefault' => ($gkey === 'g1'),
                'class'     => theme_nit_brand_group_class($gkey),
                'roles'     => [],
            ];
        }
        $groups[$gidx[$gkey]]['roles'][] = [
            'key'      => $token['key'],
            'role'     => $token['role'],
            'label'    => $token['label'],
            // The semantic custom property a component consumes. Its value is the
            // active group's value: inside a .nit-brand-2/3 wrapper it resolves to
            // that group; at the top level it resolves to Group 1.
            'cssvar'   => '--nit-brand-' . $token['role'],
            'value'    => $token['value'],
            'default'  => $token['default'],
            'iscustom' => $token['iscustom'],
            'usage'    => array_values($token['usage']),
        ];
    }

    // The ordered list of role keys (identical across groups).
    $roles = [];
    foreach (theme_nit_brand_roles() as $role => $unused) {
        $roles[] = $role;
    }

    return ['roles' => $roles, 'groups' => $groups];
}

/**
 * Category styles tab, as data: which brand group each main category uses.
 *
 * Only top-level (main) categories are assignable; subcategories inherit their
 * top ancestor's group. Visibility-aware: an anonymous request sees only the
 * categories a guest may see. `group` is the assigned group key (default `g1`);
 * `class` is the wrapper class that skins the category page from that group.
 *
 * @return array{groups: array<int, array{key:string, name:string}>,
 *         categories: array<int, array{id:int, name:string, group:string,
 *         groupname:string, class:string, isdefault:bool}>}
 */
function theme_nit_category_styles_export(): array {
    $grouplabels = theme_nit_brand_groups();

    $groups = [];
    foreach ($grouplabels as $gkey => $glabel) {
        $groups[] = ['key' => $gkey, 'name' => $glabel];
    }

    $raw = get_config('theme_nit', 'nit_category_groups');
    $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];

    $categories = [];
    foreach (core_course_category::top()->get_children() as $cat) {
        $gkey = $map[$cat->id] ?? 'g1';
        if (!array_key_exists($gkey, $grouplabels)) {
            $gkey = 'g1';
        }
        $categories[] = [
            'id'        => (int) $cat->id,
            'name'      => $cat->get_formatted_name(),
            'group'     => $gkey,
            'groupname' => $grouplabels[$gkey],
            'class'     => theme_nit_brand_group_class($gkey),
            'isdefault' => ($gkey === 'g1'),
        ];
    }

    return ['groups' => $groups, 'categories' => $categories];
}

/**
 * Fonts tab, as data: the per-language font family + downloadable file URL.
 *
 * One entry per language slot (en / ar). `family` is the CSS font-family the
 * compiled stylesheet exposes; `url` is the self-hosted font file (empty when no
 * font has been uploaded, in which case the site falls back to `fallback`).
 *
 * @return array<int, array{lang:string, label:string, family:string, rtl:bool,
 *         fallback:string, hasfont:bool, filename:string, url:string}>
 */
function theme_nit_fonts_export(): array {
    $theme = theme_config::load('nit');
    $out = [];
    foreach (theme_nit_font_slots() as $lang => $slot) {
        $filename = get_config('theme_nit', $slot['setting']);
        $hasfont = is_string($filename) && $filename !== '';
        // setting_file_url() already returns a full, self-hosted pluginfile URL
        // string (the same one theme_nit_font_scss() emits into the @font-face).
        $url = $hasfont ? (string) $theme->setting_file_url($slot['setting'], $slot['filearea']) : '';
        $out[] = [
            'lang'     => $lang,
            'label'    => get_string($slot['strkey'], 'theme_nit'),
            'family'   => $slot['family'],
            'rtl'      => (bool) $slot['rtl'],
            'fallback' => $slot['fallback'],
            'hasfont'  => $hasfont,
            'filename' => $hasfont ? ltrim($filename, '/') : '',
            'url'      => $url,
        ];
    }
    return $out;
}

/**
 * Components tab, as data: the global components showcased on the gallery page.
 *
 * A static inventory mirroring the gallery's Components tab — each component with
 * its named variants and the CSS classes that render them — so the mobile app
 * knows which shared UI elements exist and how they map to markup.
 *
 * @return array<int, array{name:string, variants: array<int, array{label:string, class:string}>}>
 */
function theme_nit_components_export(): array {
    return [
        [
            'name'     => 'Buttons',
            'variants' => [
                ['label' => 'Primary',   'class' => 'btn btn-primary'],
                ['label' => 'Secondary', 'class' => 'btn btn-secondary'],
                ['label' => 'Success',   'class' => 'btn btn-success'],
                ['label' => 'Warning',   'class' => 'btn btn-warning'],
                ['label' => 'Danger',    'class' => 'btn btn-danger'],
                ['label' => 'Outline',   'class' => 'btn btn-outline-primary'],
                ['label' => 'Disabled',  'class' => 'btn btn-primary', 'disabled' => true],
            ],
        ],
        [
            'name'     => 'Alerts',
            'variants' => [
                ['label' => 'Primary', 'class' => 'alert alert-primary'],
                ['label' => 'Success', 'class' => 'alert alert-success'],
                ['label' => 'Warning', 'class' => 'alert alert-warning'],
                ['label' => 'Danger',  'class' => 'alert alert-danger'],
            ],
        ],
    ];
}

/**
 * The whole design system as one payload — the four gallery tabs, as data,
 * plus the white-label `site`, `branding`, and `links` blocks the mobile app reads.
 *
 * Backs design_system.php (the public mobile-facing JSON API).
 *
 * @return array{generated:int, site: array, brandcolors: array,
 *         categorystyles: array, fonts: array, components: array,
 *         branding?: array, links?: array}
 */
function theme_nit_design_system_export(): array {
    $payload = [
        'generated'      => time(),
        'site'           => theme_nit_site_export(),
        'brandcolors'    => theme_nit_brand_export(),
        'categorystyles' => theme_nit_category_styles_export(),
        'fonts'          => theme_nit_fonts_export(),
        'components'     => theme_nit_components_export(),
    ];
    // Only emit branding / links blocks when the tenant has actually published
    // something — the app falls back to bundled assets for any missing key.
    if ($branding = theme_nit_branding_export()) {
        $payload['branding'] = $branding;
    }
    if ($links = theme_nit_links_export()) {
        $payload['links'] = $links;
    }
    return $payload;
}

/**
 * The `site` identity block the white-label mobile app reads before login:
 * name, canonical URL, version handshake, provisioning/expiry status, and the
 * authoritative (licence-driven) feature map.
 *
 * The `features` key is emitted ONLY when local_license enforcement is on, so an
 * unmanaged host reads as "legacy => everything on" per the app's absence rule
 * (a missing object, not an all-true one). Likewise `package`/`status`/`expires`
 * appear only when the licence plugin is present.
 *
 * @return array
 */
function theme_nit_site_export(): array {
    global $SITE, $CFG;

    $site = [
        'name'            => format_string($SITE->fullname ?? ''),
        'shortname'       => format_string($SITE->shortname ?? ''),
        'url'             => $CFG->wwwroot,   // retained for backward compatibility
        'wwwroot'         => $CFG->wwwroot,
        'apiversion'      => 1,               // bump on a breaking payload change
        'supportedapp'    => true,
        'provisioned'     => (bool) (int) (get_config('theme_nit', 'provisioned') ?? 1),
        'status'          => 'active',        // active | expired | suspended
        'defaultlanguage' => $CFG->lang ?? 'en',
        'languages'       => array_values(array_keys(
            get_string_manager()->get_list_of_translations() ?: [($CFG->lang ?? 'en') => 1])),
    ];

    if (class_exists('\local_license\license')) {
        $lic = '\local_license\license';
        $site['package'] = ['code' => $lic::tier(), 'name' => $lic::tiername()];
        if (method_exists($lic, 'is_expired') && $lic::is_expired()) {
            $site['status'] = 'expired';
        }
        if (method_exists($lic, 'expiry') && ($exp = (int) $lic::expiry()) > 0) {
            $site['expires'] = $exp;
        }
        // Absence rule: authoritative map only when enforcement is on.
        if (method_exists($lic, 'is_enforced') && $lic::is_enforced()
                && method_exists($lic, 'mobile_features')) {
            $site['features'] = $lic::mobile_features();
        }
    }

    return $site;
}

/**
 * Remote branding assets (logos, splash, hero) the app downloads and caches.
 * Each value is a URL stored in theme_nit config; missing keys are omitted so the
 * app keeps its bundled fallback. Light + dark variants for every logo.
 *
 * @return array (empty when nothing is published)
 */
function theme_nit_branding_export(): array {
    $url = static function (string $key): string {
        $v = get_config('theme_nit', $key);
        return ($v === false || $v === null) ? '' : (string) $v;
    };
    $pair = static function (string $light, string $dark) use ($url): array {
        return array_filter(['light' => $url($light), 'dark' => $url($dark)]);
    };

    $branding = array_filter([
        'logo'      => $pair('mobile_logo_light', 'mobile_logo_dark'),
        'logomark'  => $pair('mobile_logomark_light', 'mobile_logomark_dark'),
        'loginhero' => $pair('mobile_loginhero_light', 'mobile_loginhero_dark'),
        'splash'    => array_filter([
            'lottie_light'     => $url('mobile_splash_lottie_light'),
            'lottie_dark'      => $url('mobile_splash_lottie_dark'),
            'image_light'      => $url('mobile_splash_image_light'),
            'image_dark'       => $url('mobile_splash_image_dark'),
            'background_light' => $url('mobile_splash_bg_light'),
            'background_dark'  => $url('mobile_splash_bg_dark'),
        ]),
    ]);
    if (($p = $url('mobile_courseplaceholder')) !== '') {
        $branding['courseplaceholder'] = $p;
    }
    if (($e = $url('mobile_emptystate')) !== '') {
        $branding['emptystate'] = $e;
    }
    return $branding;
}

/**
 * Per-tenant legal / social links. About/Privacy/Terms/FAQ are per language and
 * open in a webview (must be chrome-less). All from theme_nit config; omitted
 * when unset.
 *
 * @return array (empty when nothing is published)
 */
function theme_nit_links_export(): array {
    $s = static function (string $key): string {
        $v = get_config('theme_nit', $key);
        return ($v === false || $v === null) ? '' : (string) $v;
    };
    $bilingual = static function (string $base) use ($s): array {
        return array_filter(['en' => $s($base . '_en'), 'ar' => $s($base . '_ar')]);
    };

    return array_filter([
        'about'         => $bilingual('link_about'),
        'privacy'       => $bilingual('link_privacy'),
        'terms'         => $bilingual('link_terms'),
        'faq'           => $bilingual('link_faq'),
        'support_email' => $s('support_email'),
        'support_phone' => $s('support_phone'),
        'facebook'      => $s('social_facebook'),
        'instagram'     => $s('social_instagram'),
        'youtube'       => $s('social_youtube'),
        'tiktok'        => $s('social_tiktok'),
        'website'       => $s('social_website'),
    ]);
}

/**
 * The per-language custom-font slots.
 *
 * The theme hosts one uploadable font file per site language: the English font
 * is applied when the site runs in English (`html[lang="en"]`) and the Arabic
 * font when it runs in Arabic (`html[lang="ar"]`). Each slot is stored exactly
 * like a Boost stored-file setting — the file lives in its own file area
 * (itemid 0, system context) and the config `theme_nit/<setting>` holds the
 * filename — so the standard theme plumbing (setting_file_url / setting_file_serve)
 * serves it (see theme_nit_pluginfile()).
 *
 * `input` is the multipart field name on the gallery font form; `family` is the
 * CSS font-family the compiled stylesheet exposes; `selector` scopes it to the
 * matching site language; `fallback` is the system-font stack used until (and
 * behind) the uploaded file.
 *
 * @return array<string, array{setting:string, filearea:string, input:string,
 *         basename:string, family:string, selector:string, fallback:string,
 *         strkey:string, samplekey:string, rtl:bool}>
 */
function theme_nit_font_slots(): array {
    return [
        'en' => [
            'setting'   => 'fontfileen',
            'filearea'  => 'fontfileen',
            'input'     => 'fontfile_en',
            'basename'  => 'font-en',
            'family'    => 'NIT Site Font EN',
            'selector'  => 'html[lang="en"] body',
            'fallback'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            'strkey'    => 'fonten',
            'samplekey' => 'fontsampleen',
            'rtl'       => false,
        ],
        'ar' => [
            'setting'   => 'fontfilear',
            'filearea'  => 'fontfilear',
            'input'     => 'fontfile_ar',
            'basename'  => 'font-ar',
            'family'    => 'NIT Site Font AR',
            'selector'  => 'html[lang="ar"] body',
            'fallback'  => '"Segoe UI", Tahoma, "Traditional Arabic", "Noto Naskh Arabic", Arial, sans-serif',
            'strkey'    => 'fontar',
            'samplekey' => 'fontsamplear',
            'rtl'       => true,
        ],
    ];
}

/**
 * The @font-face + language-scoped font-family rules for the uploaded fonts.
 *
 * Emitted into the (cached) extra SCSS stream. Only slots that actually have a
 * file uploaded produce output, so an untouched install keeps the default
 * system font. The font URL is a self-hosted pluginfile URL (never external);
 * because it is wrapped in a quoted url("…") the protocol-relative `//` is a
 * string, not a SCSS line comment.
 *
 * @param theme_config $theme the theme config object (carries the settings)
 * @return string CSS (valid SCSS)
 */
function theme_nit_font_scss($theme): string {
    $css = '';
    foreach (theme_nit_font_slots() as $slot) {
        $url = $theme->setting_file_url($slot['setting'], $slot['filearea']);
        if (empty($url)) {
            continue;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $format = ($ext === 'otf') ? 'opentype' : 'truetype';
        $family = $slot['family'];

        // Declare the uploaded file as the *normal* weight only (not 100 900). Most
        // uploads are a single regular-weight file; claiming it covers 100-900 makes
        // the browser use that one file verbatim for bold too (headings look thin).
        // Declaring just `normal` lets the browser synthesize bold for heavier weights
        // (font-weight: 600/700/800 on headings), so a single-weight upload still bolds.
        $css .= '@font-face {'
            . 'font-family: "' . $family . '";'
            . 'src: url("' . $url . '") format("' . $format . '");'
            . 'font-weight: normal;'
            . 'font-style: normal;'
            . 'font-display: swap;'
            . "}\n";
        $css .= $slot['selector'] . ' {'
            . 'font-family: "' . $family . '", ' . $slot['fallback'] . ';'
            . "}\n";
    }
    return $css;
}

/**
 * The login / signup page background image.
 *
 * Boost ships a `loginbackgroundimage` setting whose CSS is emitted by
 * theme_boost_get_extra_scss(). Because theme_nit overrides extra_scss (it does
 * NOT chain Boost's callback), that logic never runs for us — so we re-emit it
 * here off our own `loginbackgroundimage` file area. The file lives in the
 * system context (itemid 0) exactly like the fonts and is served by
 * theme_nit_pluginfile(); the config `theme_nit/loginbackgroundimage` holds the
 * filename. When no file is uploaded the login page keeps its plain background.
 *
 * @param theme_config $theme the theme config object
 * @return string CSS (valid SCSS)
 */
function theme_nit_login_bg_scss($theme): string {
    $url = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (empty($url)) {
        return '';
    }
    // A dark scrim over the photo keeps the white login card readable on any image.
    return 'body.pagelayout-login {'
        . 'background-image: linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.55)), url("' . $url . '");'
        . 'background-size: cover;'
        . 'background-position: center center;'
        . 'background-repeat: no-repeat;'
        . 'background-attachment: fixed;'
        . "}\n";
}

/**
 * How long (seconds) the front-page data helpers cache their result.
 *
 * Read from the theme setting `frontpagecachettl` (edited under Site admin →
 * Appearance → NIT settings). When the setting has never been saved, fall back
 * to 5 minutes; an explicit 0 disables caching (recompute every request).
 *
 * @return int seconds, or 0 to disable caching
 */
function theme_nit_frontpage_cache_ttl(): int {
    $raw = get_config('theme_nit', 'frontpagecachettl');
    if ($raw === false || $raw === null || $raw === '') {
        return 300;
    }
    return max(0, (int) $raw);
}

/**
 * Live site counters for the front-page marketing sections.
 *
 * Exposed to JavaScript as `window.NIT_STATS` by the frontpage layout, so
 * author-written NIT Section blocks can render real numbers dynamically
 * (works for guests — no web service or token needed).
 *
 * @return array{courses:int,categories:int,topcategories:int,subcategories:int,students:int}
 */
function theme_nit_get_site_stats(): array {
    global $DB;

    // Short-lived cache: these whole-table counts change slowly but run on the
    // busiest page (Site home), so serve a cached copy. Lifetime is the
    // admin-configurable theme setting (0 = disabled).
    $ttl = theme_nit_frontpage_cache_ttl();
    $cache = \cache::make('theme_nit', 'frontpage');
    if ($ttl > 0) {
        $cached = $cache->get('sitestats');
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            return $cached['data'];
        }
    }

    $categories = (int) $DB->count_records('course_categories', ['visible' => 1]);
    $topcategories = (int) $DB->count_records('course_categories', ['visible' => 1, 'parent' => 0]);

    $stats = [
        // Real courses (exclude the site "course" id 1) that are visible.
        'courses' => (int) $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]),
        'categories' => $categories,
        'topcategories' => $topcategories,
        'subcategories' => max(0, $categories - $topcategories),
        // Distinct users with at least one enrolment.
        'students' => (int) $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {user_enrolments}'),
    ];

    if ($ttl > 0) {
        $cache->set('sitestats', ['expires' => time() + $ttl, 'data' => $stats]);
    }
    return $stats;
}

/**
 * The fee-enrolment price of a course, or '' when the course is free.
 *
 * @param int $courseid
 * @return string e.g. "250.00 EGP" or '' (free)
 */
function theme_nit_course_price(int $courseid): string {
    global $DB, $USER;

    // Prefer the local_payments plugin: it stores per-country course prices in
    // its own table (local_payments_course_prices), independent of Moodle's core
    // enrol methods. Resolve for the current user's country so an Egyptian user
    // sees the EGP price, etc. (the front-page grid cache is keyed by user +
    // country — see theme_nit_get_courses — so this stays cache-safe).
    if (class_exists('\local_payments\price_resolver')
        && \local_payments\price_resolver::has_pricing($courseid)) {
        try {
            $pricing = \local_payments\price_resolver::resolve(
                $courseid,
                !empty($USER->id) ? (int) $USER->id : null
            );
            if ((float) $pricing->price > 0) {
                return format_float($pricing->price, 2, false) . ' ' . $pricing->currency;
            }
            // Active rule with a zero price — treat as explicitly free.
            return '';
        } catch (\moodle_exception $e) {
            // No matching rule for this country — fall through to free/enrol.
        }
    }

    $recs = $DB->get_records_select(
        'enrol',
        "courseid = :cid AND status = 0 AND enrol IN ('fee', 'paypal')",
        ['cid' => $courseid],
        'sortorder ASC',
        'id, cost, currency'
    );
    foreach ($recs as $r) {
        if ((float) $r->cost > 0) {
            return format_float($r->cost, 2, false) . ' ' . $r->currency;
        }
    }
    return '';
}

/**
 * The name of a course's (editing) teacher, or '' if none.
 *
 * @param int $courseid
 * @return string
 */
function theme_nit_course_teacher(int $courseid): string {
    global $DB;

    $roleids = $DB->get_fieldset_select('role', 'id', "archetype IN ('editingteacher', 'teacher')");
    if (empty($roleids)) {
        return '';
    }
    [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
    $params['ctx'] = context_course::instance($courseid)->id;

    $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename
              FROM {role_assignments} ra
              JOIN {user} u ON u.id = ra.userid
             WHERE ra.contextid = :ctx AND ra.roleid $insql AND u.deleted = 0
          ORDER BY ra.timemodified ASC";
    $teacher = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

    return $teacher ? fullname($teacher) : '';
}

/**
 * Visible courses as view-models for the front-page "courses" section.
 *
 * Exposed to JavaScript as `window.NIT_COURSES`; author-written NIT Section
 * blocks render them via a <template> (see the frontpage renderer).
 *
 * @param int $limit maximum number of courses
 * @return array<int, array{id:int,fullname:string,summary:string,url:string,image:string,price:string,is_free:bool}>
 */
function theme_nit_get_courses(int $limit = 12): array {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/filelib.php');

    // Short-lived cache: assembling each card costs several per-course queries
    // (context, overview image, price, teacher). On the Site home that is an
    // N+1 pattern on the busiest page, so cache the assembled list (keyed by
    // limit). Lifetime is the admin-configurable theme setting (0 = disabled).
    // Purge theme caches to refresh sooner.
    global $USER;

    // Prices are resolved per country and the "enrolled" state is per user, so
    // the cached view-model must be keyed by user + country — otherwise the first
    // visitor's prices/enrolment would be served to everyone.
    $userid = (int) ($USER->id ?? 0);
    $country = '';
    if (class_exists('\local_payments\country_detector')) {
        $country = \local_payments\country_detector::detect($userid > 0 ? $userid : null);
    }

    $ttl = theme_nit_frontpage_cache_ttl();
    $cache = \cache::make('theme_nit', 'frontpage');
    $cachekey = 'courses_' . $limit . '_' . $userid . '_' . $country;
    if ($ttl > 0) {
        $cached = $cache->get($cachekey);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
            return $cached['data'];
        }
    }

    $records = $DB->get_records_select(
        'course',
        'id <> :site AND visible = 1',
        ['site' => SITEID],
        'sortorder ASC',
        '*',
        0,
        $limit
    );

    $fs = get_file_storage();
    $courses = [];
    foreach ($records as $c) {
        $context = context_course::instance($c->id);

        // Course image: overview file, else a generated pattern.
        $image = '';
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $image = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                break;
            }
        }
        if ($image === '') {
            $image = $OUTPUT->get_generated_image_for_id($c->id);
        }

        // Short plain-text summary.
        $summary = '';
        if (!empty($c->summary)) {
            $plain = html_to_text(
                format_text($c->summary, $c->summaryformat, ['context' => $context, 'noclean' => true]),
                0,
                false
            );
            $summary = shorten_text(trim($plain), 120);
        }

        $price = theme_nit_course_price((int) $c->id);
        $isenrolled = false;
        if ($userid > 0 && class_exists('\local_payments\enrollment_handler')) {
            $isenrolled = \local_payments\enrollment_handler::is_enrolled($userid, (int) $c->id);
        }
        $courses[] = [
            'id' => (int) $c->id,
            'fullname' => format_string($c->fullname, true, ['context' => $context]),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'image' => $image,
            'price' => $price,
            'is_free' => ($price === ''),
            'is_enrolled' => $isenrolled,
            'teacher' => theme_nit_course_teacher((int) $c->id),
        ];
    }

    if ($ttl > 0) {
        $cache->set($cachekey, ['expires' => time() + $ttl, 'data' => $courses]);
    }
    return $courses;
}

/**
 * The courses the current user is enrolled in, as view-models for the front-page "My courses" section.
 *
 * Exposed to JavaScript as `window.NIT_MY_COURSES`; a NIT Section block renders them via a
 * <template> keyed on `data-nit-my-courses` / `data-nit-my-course-card`. Per-user, so not cached.
 *
 * @param int $limit maximum number of courses
 * @return array<int, array{id:int,fullname:string,summary:string,url:string,image:string,price:string,is_free:bool,teacher:string}>
 */
function theme_nit_get_enrolled_courses(int $limit = 12): array {
    global $CFG, $OUTPUT, $USER;
    require_once($CFG->libdir . '/filelib.php');
    require_once($CFG->libdir . '/enrollib.php');

    if (empty($USER->id) || isguestuser()) {
        return [];
    }

    // The user's enrolled, visible courses (most recently accessed first).
    $records = enrol_get_my_courses('*', 'visible DESC, sortorder ASC');
    $fs = get_file_storage();
    $courses = [];
    foreach ($records as $c) {
        if ((int) $c->id === (int) SITEID || empty($c->visible)) {
            continue;
        }
        $context = context_course::instance($c->id);

        // Course image: overview file, else a generated pattern.
        $image = '';
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $image = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                break;
            }
        }
        if ($image === '') {
            $image = $OUTPUT->get_generated_image_for_id($c->id);
        }

        // Short plain-text summary.
        $summary = '';
        if (!empty($c->summary)) {
            $plain = html_to_text(
                format_text($c->summary, $c->summaryformat ?? FORMAT_HTML, ['context' => $context, 'noclean' => true]),
                0,
                false
            );
            $summary = shorten_text(trim($plain), 120);
        }

        $courses[] = [
            'id' => (int) $c->id,
            'fullname' => format_string($c->fullname, true, ['context' => $context]),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'image' => $image,
            'price' => '',
            'is_free' => true,
            'teacher' => theme_nit_course_teacher((int) $c->id),
        ];
        if (count($courses) >= $limit) {
            break;
        }
    }
    return $courses;
}

/**
 * Main SCSS: Boost's preset, then the NIT component layer.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_main_scss_content($theme) {
    global $CFG;

    // Inherit Boost's compiled preset. Bootstrap compiles using the NIT
    // variable values set in pre_scss, so components adopt the NIT look.
    require_once($CFG->dirroot . '/theme/boost/lib.php');
    $scss = theme_boost_get_main_scss_content($theme);

    // NIT component refinements (token-driven).
    $scss .= theme_nit_concat_scss(__DIR__ . '/scss/components');

    // Any global post-Bootstrap styles.
    $scss .= file_get_contents(__DIR__ . '/scss/post.scss');

    return $scss;
}

/**
 * Pre-SCSS: primitives, mixins, then the semantic tier that overrides Bootstrap.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_pre_scss($theme) {
    $scss = '';

    // Tier 1: primitive tokens (raw palette + scales).
    $scss .= file_get_contents(__DIR__ . '/scss/tokens/_primitives.scss');
    // Shared functions / mixins.
    $scss .= file_get_contents(__DIR__ . '/scss/_mixins.scss');
    // Tier 2: semantic tokens mapped onto Bootstrap variables (before Bootstrap).
    $scss .= file_get_contents(__DIR__ . '/scss/tokens/_semantic.scss');

    // Brand overrides (M5): the SDK resolver returns the active brand's semantic
    // tokens; the theme maps them onto SCSS variables here, after the M3 defaults
    // and before Bootstrap, so the whole UI recompiles on brand. Guarded so the
    // theme still renders if the SDK is absent (graceful degradation).
    if (class_exists('\local_nit_core\api\branding')) {
        $brand = \local_nit_core\api\branding::tokens();
        if (!empty($brand['primary'])) {
            $scss .= '$primary: ' . $brand['primary'] . ";\n";
            $scss .= '$nit-on-primary: ' . $brand['onprimary'] . ";\n";
        }
        if (!empty($brand['font'])) {
            $scss .= '$font-family-sans-serif: ' . $brand['font'] . ";\n";
        }
    }

    // User-editable colour palette (edited on the gallery page). Always emit
    // every token as a `$nit-c-<key>` SCSS variable — the saved colour, else the
    // palette default — so it is defined for the navbar and for the --nit-*
    // custom properties in _root.scss (extra_scss, same combined stream).
    foreach (theme_nit_colour_palette() as $key => $meta) {
        $scss .= '$nit-c-' . $key . ': ' . theme_nit_colour($key) . ";\n";
    }

    // Map palette tokens onto the Bootstrap/semantic layer, but ONLY for tokens
    // the admin has actually saved — so an untouched install (and any live M5
    // SDK brand set just above) keeps its existing values. `$nit-c-*` above
    // still carries the defaults for the custom-property layer regardless.
    // Config key => the SCSS variables it drives.
    $semanticmap = [
        'primary'     => ['primary', 'link-color'],
        'secondary'   => ['secondary'],
        'success'     => ['success'],
        'warning'     => ['warning'],
        'error'       => ['danger'],
        'info'        => ['info'],
        'background'  => ['body-bg', 'nit-surface'],
        'textprimary' => ['body-color', 'nit-ink'],
        'border'      => ['border-color', 'card-border-color', 'nit-line'],
    ];
    foreach ($semanticmap as $key => $targets) {
        $saved = get_config('theme_nit', 'colour_' . $key);
        if (!is_string($saved) || $saved === '') {
            continue;
        }
        foreach ($targets as $target) {
            $scss .= '$' . $target . ': $nit-c-' . $key . ";\n";
        }
    }

    // -------------------------------------------------------------------------
    // Brand Colors palette (the new semantic layer — gallery "Brand Colors" tab).
    // Emit every group's tokens as `$nit-b-<gkey>-<role>` SCSS variables (saved
    // value, else the seed default), so _brand.scss can publish them as
    // `--nit-brand-*` custom properties in the same combined stream.
    foreach (theme_nit_brand_palette() as $key => $meta) {
        $scss .= '$nit-b-' . str_replace('_', '-', $key) . ': ' . theme_nit_brandcolour($key) . ";\n";
    }

    // Drive the Bootstrap / semantic SCSS layer from Group 1 — the site-wide
    // default group. Unlike the legacy colour map above (applied only when the
    // admin saved a value), the brand always sets these, so buttons, cards,
    // alerts, links and the page body follow the brand out of the box. This is
    // the primary "rewire the whole site" lever; components that read these
    // Bootstrap vars need no edits. Group key => the SCSS variables it drives.
    $brandmap = [
        'g1_primary'         => ['primary'],
        'g1_secondary'       => ['secondary'],
        'g1_accenttext'      => ['link-color'],
        'g1_background'      => ['body-bg'],
        // Surface also drives form controls: Bootstrap's $input-bg is a fixed
        // light gray, so on the dark brand it would leave white text on a light
        // field (invisible). Point it at the brand surface instead.
        'g1_surface'         => ['card-bg', 'dropdown-bg', 'input-bg', 'nit-surface'],
        'g1_textprimary'     => ['body-color', 'dropdown-link-color', 'input-color', 'nit-ink'],
        'g1_textsecondary'   => ['text-muted'],
        'g1_borderprimary'   => ['border-color', 'card-border-color', 'dropdown-border-color', 'input-border-color', 'nit-line'],
        'g1_success'         => ['success'],
        'g1_warning'         => ['warning'],
        'g1_error'           => ['danger'],
        'g1_info'            => ['info'],
    ];
    foreach ($brandmap as $key => $targets) {
        foreach ($targets as $target) {
            $scss .= '$' . $target . ': $nit-b-' . str_replace('_', '-', $key) . ";\n";
        }
    }

    // Reserved pre-Boost overrides.
    $scss .= file_get_contents(__DIR__ . '/scss/pre.scss');

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Extra SCSS: component-tier CSS custom properties (light + dark) and fonts.
 *
 * @param theme_config $theme the theme config object
 * @return string
 */
function theme_nit_get_extra_scss($theme) {
    $scss = '';

    // _brand.scss must come before _root.scss: it declares the --nit-brand-*
    // custom properties (active layer + per-group + switch classes) that
    // _root.scss then aliases the legacy --nit-* properties onto.
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_brand.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_root.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_fonts.scss');

    // Admin-uploaded, per-language custom fonts (edited on the gallery page).
    $scss .= theme_nit_font_scss($theme);

    // Admin/provision-uploaded login + signup page background image.
    $scss .= theme_nit_login_bg_scss($theme);

    if (!empty($theme->settings->scss)) {
        $scss .= $theme->settings->scss;
    }

    return $scss;
}

/**
 * Serve the theme's admin-uploaded font files via pluginfile.php.
 *
 * Mirrors theme_boost_pluginfile(): the uploaded fonts live in a system-context
 * file area per language slot (see theme_nit_font_slots()), and the theme
 * revision — not the itemid — busts the cache. The gallery page (site:config
 * only) is the sole writer; this endpoint is a public, cache-able read of the
 * self-hosted font, exactly like the site logo.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_nit_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $fontareas = array_map(static fn($slot) => $slot['filearea'], theme_nit_font_slots());
    // Image file areas served exactly like the fonts (system context, itemid 0).
    $imageareas = ['loginbackgroundimage'];
    $servable = array_merge($fontareas, $imageareas);

    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $servable, true)) {
        $theme = theme_config::load('nit');
        // Theme files must be cache-able by both browsers and proxies by default.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }

    send_file_not_found();
}

/**
 * Visible categories as view-models for the front-page "categories" section.
 *
 * Exposed to JavaScript as `window.NIT_CATEGORIES`.
 *
 * @param int $limit maximum number of categories
 * @return array<int, array{id:int,name:string,coursecount:int,icon:string}>
 */
function theme_nit_get_categories(int $limit = 4): array {
    global $OUTPUT;
    $icons = ['💻', '📊', '🎨', '🗣️', '🔬', '💡', '📚', '🎯'];

    // Moodle categories have no image field of their own, so use the site logo as
    // the fallback image ("if the category has no image, show the site logo").
    $logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
    $logourl = $logo ? $logo->out(false) : '';

    // Only main (top-level) categories, in display order, visible to this user.
    // core_course_category::top()->get_children() is permission- and visibility-aware.
    $toplevel = core_course_category::top()->get_children(['limit' => $limit]);

    $categories = [];
    $i = 0;
    foreach ($toplevel as $cat) {
        $categories[] = [
            'id' => (int) $cat->id,
            'name' => $cat->get_formatted_name(),
            // Count courses in this category AND all its subcategories, so a main
            // category whose courses live only in subcategories still shows a real total.
            'coursecount' => $cat->get_courses_count(['recursive' => true]),
            'icon' => $icons[$i % count($icons)],
            'image' => $logourl,
            // Build the details-page URL here so the frontend never has to guess wwwroot.
            'url' => (new moodle_url('/local/nit_category/index.php', ['id' => $cat->id]))->out(false),
        ];
        $i++;
    }

    return $categories;
}
