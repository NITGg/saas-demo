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
 * The 59 semantic roles every Brand-Colors group is built from.
 *
 * This is the clean, small semantic layer that replaces the sprawling
 * theme_nit_colour_palette(): a component references a role by name (Primary,
 * Surface, Text primary, …) and never a raw colour. `label` is the display name,
 * `section` is which block of the editor it belongs to (see
 * theme_nit_brand_role_sections()) and `usage` is a list of the concrete UI
 * things that should use the colour — rendered as chips on the gallery's Brand
 * Colors tab. `default` here is only a red-free FALLBACK (the Group 1 /
 * Slate-blue values): every group overrides all of them in
 * theme_nit_brand_group_defaults(), so a role default is used only if a group
 * ever omits a role. The Hover Background / Hover Text roles carry the explicit
 * hover colours (other opacity variants are still derived in SCSS, see
 * scss/foundation/_brand.scss).
 *
 * @return array<string, array{section:string, label:string, usage:string[], default:string}>
 */
function theme_nit_brand_roles(): array {
    return [
        // --- Brand -----------------------------------------------------------
        // Broken into blocks the way the Navbar section is: the three BUTTON
        // kinds each own a complete set — background, text, border, and the same
        // three again for hover — so "what does a secondary button look like" is
        // one heading and six cards instead of a hunt through a flat grid.
        //
        // The button blocks do NOT mint private background/text roles.
        // `primary` / `secondary` already ARE the two fills and `onprimary` /
        // `onsecondary` already ARE the two labels, so they appear here as
        // "Background" and "Text" under the button they paint. Copies would have
        // bought the tidier heading at the price of the worse trap: an admin
        // moving Primary and watching the main button stay exactly where it was.
        // `usage` here used to read `['none text']`, which is true and says
        // nothing: it names what the role is NOT and leaves an admin to guess
        // what it paints. The list below is the audited answer — every one of
        // the sixty-one places this role reaches, collapsed to six lines, most
        // used first. Borders lead because thirty-six of the sixty-one are
        // borders, and the line says WHICH borders, because the neutral hairline
        // next door in Surfaces & text is the card people reach for first.
        //
        // Keep the key. It is the setting name (`g1_accent`), the custom
        // property (`--nit-brand-accent`, 61 uses in 22 files) and the SCSS
        // variable (`$nit-b-g1-accent`) all at once — see theme_nit_brand_palette().
        'accent'            => ['section' => 'brand', 'sub' => 'core', 'label' => 'Accent (highlight)', 'short' => 'Accent', 'usage' => ['the border of a card that is selected, active or featured', 'icons and decorative glyphs', 'the keyboard focus ring', 'progress bar fill', 'tinted highlight fills behind chips and badges', 'never body text — the site\'s one saturated non-text colour'], 'default' => '#5488c4'],

        // --- Brand > Main button (.btn-primary) ------------------------------
        'primary'           => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Primary', 'short' => 'Background', 'usage' => ['background main button', 'checked toggles', 'progress fill', 'notification dots'], 'default' => '#5488c4'],
        // Text drawn ON a filled button, one role per button colour. These are
        // roles rather than "whatever the body ink happens to be" because the
        // right answer depends on the fill, not on the page: a light group fills
        // its main button with a dark blue and needs white on it, a dark group
        // fills it with a light blue and needs near-black. Getting that from
        // "Text primary" was wrong by construction in half the groups.
        'onprimary'         => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Text on main button', 'short' => 'Text', 'usage' => ['label inside a filled main button', 'text on any primary fill'], 'default' => '#eef3f9'],
        // The four that were not sayable before. The ring and the three hover
        // colours used to be derived in CSS — the border was the fill, and the
        // hover was the fill mixed 85% with white — which is a reasonable guess
        // and not a decision anybody could make. Each group seeds to exactly the
        // colour that derivation produced, so no site moves until it is edited.
        'btnprimaryborder'  => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Main button border', 'short' => 'Border', 'usage' => ['the ring around a filled main button'], 'default' => '#5488c4'],
        'btnprimaryhoverbg' => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Main button hover background', 'short' => 'Hover background', 'usage' => ['a main button under the cursor, or being pressed'], 'default' => '#6e9acd'],
        'btnprimaryhovertext' => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Main button hover text', 'short' => 'Hover text', 'usage' => ['the label of a main button under the cursor'], 'default' => '#eef3f9'],
        'btnprimaryhoverborder' => ['section' => 'brand', 'sub' => 'btnprimary', 'label' => 'Main button hover border', 'short' => 'Hover border', 'usage' => ['the ring around a main button under the cursor'], 'default' => '#6594ca'],

        // --- Brand > Secondary button (.btn-secondary) -----------------------
        // This block is also a fix. `$secondary` is baked from GROUP 1 in
        // pre_scss, so until now every group's secondary button was Group 1's
        // dark navy — the same freeze the Bootstrap bridge at the foot of
        // scss/foundation/_brand.scss was written to undo for .btn-primary.
        'secondary'         => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Secondary', 'short' => 'Background', 'usage' => ['background secondary button'], 'default' => '#1c2a3a'],
        'onsecondary'       => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Text on secondary button', 'short' => 'Text', 'usage' => ['label inside a secondary button'], 'default' => '#eef3f9'],
        'btnsecondaryborder' => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Secondary button border', 'short' => 'Border', 'usage' => ['the ring around a secondary button'], 'default' => '#1c2a3a'],
        'btnsecondaryhoverbg' => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Secondary button hover background', 'short' => 'Hover background', 'usage' => ['a secondary button under the cursor, or being pressed'], 'default' => '#182431'],
        'btnsecondaryhovertext' => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Secondary button hover text', 'short' => 'Hover text', 'usage' => ['the label of a secondary button under the cursor'], 'default' => '#eef3f9'],
        'btnsecondaryhoverborder' => ['section' => 'brand', 'sub' => 'btnsecondary', 'label' => 'Secondary button hover border', 'short' => 'Hover border', 'usage' => ['the ring around a secondary button under the cursor'], 'default' => '#16222e'],

        // --- Brand > Outline button (brand) (.btn-outline-primary) -----------
        // The outline button the PUBLIC pages are built from: the category hero
        // CTAs, the filter pills across the top of a catalogue, "Course details"
        // on every course card, the catalogue and search actions. This is the
        // one an administrator means by "the outline button", which is why it
        // leads the pair.
        //
        // Its colours were derived from the main button before they were roles —
        // ring and label in Primary, hovering onto a solid Primary fill with the
        // main button's label colour on it — so each seeds to exactly that. It
        // is a separate block from the neutral outline button below rather than
        // a second name for it: they have always looked different, and merging
        // them would have had to repaint one of the two.
        'btnoutlineprimarybg' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button background', 'short' => 'Background', 'usage' => ['the fill behind a brand outline button — drawn only while "Fill the background" is ticked'], 'default' => '#0c141f'],
        'btnoutlineprimarytext' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button text', 'short' => 'Text', 'usage' => ['the label of a brand outline button'], 'default' => '#5488c4'],
        'btnoutlineprimaryborder' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button border', 'short' => 'Border', 'usage' => ['the ring that IS the brand outline button'], 'default' => '#5488c4'],
        'btnoutlineprimaryhoverbg' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button hover background', 'short' => 'Hover background', 'usage' => ['a brand outline button under the cursor, or being pressed'], 'default' => '#5488c4'],
        'btnoutlineprimaryhovertext' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button hover text', 'short' => 'Hover text', 'usage' => ['the label of a brand outline button under the cursor'], 'default' => '#eef3f9'],
        'btnoutlineprimaryhoverborder' => ['section' => 'brand', 'sub' => 'btnoutlineprimary', 'label' => 'Brand outline button hover border', 'short' => 'Hover border', 'usage' => ['the ring around a brand outline button under the cursor'], 'default' => '#5488c4'],

        // --- Brand > Outline button (neutral) (.btn-outline-secondary) -------
        // The NEUTRAL outline button: the Cancels and Resets on the payments,
        // refunds, subscriptions and email screens, and "Log in as guest". It is
        // back-office chrome, and it stays visually apart from the brand outline
        // above on purpose — a Cancel that carries the same ring as the Save
        // beside it has stopped saying which one is which.
        //
        // The one block whose background is not painted by default. An outline
        // button IS its ring: the resting fill is transparent, so the button
        // takes the colour of whatever it happens to sit on — the page here, a
        // card two screens later — and one flat colour cannot be both. So the
        // colour is offered with a switch beside it
        // (theme_nit_button_outline_fill()), and the switch is off until an
        // admin actually asks for a filled one.
        'btnoutlinebg'      => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button background', 'short' => 'Background', 'usage' => ['the fill behind an outline button — drawn only while "Fill the background" is ticked'], 'default' => '#0c141f'],
        'btnoutlinetext'    => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button text', 'short' => 'Text', 'usage' => ['the label of an outline button'], 'default' => '#eef3f9'],
        'btnoutlineborder'  => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button border', 'short' => 'Border', 'usage' => ['the ring that IS the outline button'], 'default' => '#33475e'],
        'btnoutlinehoverbg' => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button hover background', 'short' => 'Hover background', 'usage' => ['an outline button under the cursor, or being pressed'], 'default' => '#16222f'],
        'btnoutlinehovertext' => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button hover text', 'short' => 'Hover text', 'usage' => ['the label of an outline button under the cursor'], 'default' => '#7fabdb'],
        'btnoutlinehoverborder' => ['section' => 'brand', 'sub' => 'btnoutline', 'label' => 'Outline button hover border', 'short' => 'Hover border', 'usage' => ['the ring around an outline button under the cursor'], 'default' => '#33475e'],

        // --- Brand > Checkbox & switch (.form-check-input) -------------------
        // The tick box, the radio and the toggle switch are ONE element in
        // Bootstrap (`.form-check-input`; `.form-switch` only swaps the picture
        // drawn on it), so they share one block. Until these were roles the
        // box was assembled from four different places and two of them were
        // not colours an admin could reach at all: the resting fill came from
        // Surface — baked from GROUP 1 in pre_scss via `$input-bg`, so a page
        // in any other group kept Group 1's box; the checked fill was Primary;
        // and the tick and the switch knob were Bootstrap's own SVG data-URIs,
        // fixed white and black-at-25% — which on a dark Surface is an OFF
        // switch with no visible knob.
        //
        // Each seeds to what the box already resolved to in that group (Surface,
        // Border primary, Primary, Text on main button), so nothing moves on a
        // site until a card is edited — except the OFF knob, which had no hex
        // to seed from (`rgba(0,0,0,.25)`) and seeds to Text secondary so it can
        // be seen on the dark groups at all.
        //
        // The tick and the two knobs are drawn as SVG, which cannot read a CSS
        // variable; scss/foundation/_brand.scss compiles one picture per group
        // from the same `$nit-b-*` value instead, scoped to the group's switch
        // class, so they follow a category style like every other role.
        'checkbg'           => ['section' => 'brand', 'sub' => 'check', 'label' => 'Checkbox background', 'short' => 'Background', 'usage' => ['an unticked box', 'an unselected radio', 'the track of a switch that is off'], 'default' => '#121e2d'],
        'checkborder'       => ['section' => 'brand', 'sub' => 'check', 'label' => 'Checkbox border', 'short' => 'Border', 'usage' => ['the ring around an unticked box, radio or switch'], 'default' => '#223244'],
        'checkknob'         => ['section' => 'brand', 'sub' => 'check', 'label' => 'Switch knob (off)', 'short' => 'Switch knob', 'usage' => ['the round knob of a switch that is off'], 'default' => '#94a3b8'],
        'checkcheckedbg'    => ['section' => 'brand', 'sub' => 'check', 'label' => 'Checkbox checked background', 'short' => 'Checked background', 'usage' => ['a ticked box', 'a selected radio', 'the track of a switch that is on', 'the focus ring, faded'], 'default' => '#5488c4'],
        'checkcheckedborder' => ['section' => 'brand', 'sub' => 'check', 'label' => 'Checkbox checked border', 'short' => 'Checked border', 'usage' => ['the ring around a ticked box, selected radio or switch that is on', 'the ring while the box has keyboard focus'], 'default' => '#5488c4'],
        'checkcheckedmark'  => ['section' => 'brand', 'sub' => 'check', 'label' => 'Checkbox tick', 'short' => 'Tick / knob (on)', 'usage' => ['the tick inside a ticked box', 'the dot inside a selected radio', 'the dash of a half-ticked box', 'the knob of a switch that is on'], 'default' => '#eef3f9'],

        // --- Brand > Links and words -----------------------------------------
        // The three text-facing accents. They used to be ONE role ("Accent
        // Text") whose card listed three usages, which made them unsayable
        // apart: an admin who wanted a quieter underline had to move every link
        // on the site with it. One card each now, and each is consumed by
        // exactly the thing it is named after.
        //
        // `accenttext` keeps its KEY (and so its saved value and its custom
        // property) because it is the one that stayed pointed at links, and
        // forty-odd component rules plus five front-page blocks already read
        // `--nit-brand-accenttext`. The two new keys ship seeded to the same hex
        // in every group, so nothing moves on any site until an admin actually
        // pulls them apart.
        //
        // `accentunderline` seeds to each group's HOVER TEXT rather than to its
        // link ink, because the only underline the site actually draws is the
        // one under a hovered link (Boost ships `$link-decoration: none`) and it
        // was `currentColor` before — i.e. the hover ink. Seeding it there is
        // what makes the split invisible until someone uses it.
        'accenttext'        => ['section' => 'brand', 'sub' => 'link', 'label' => 'Link Text', 'short' => 'Link text', 'usage' => ['text of links'], 'default' => '#7fabdb'],
        'accentwords'       => ['section' => 'brand', 'sub' => 'link', 'label' => 'Important Words', 'short' => 'Important words', 'usage' => ['a word highlighted inside a heading', 'a sale price', 'inline code', 'a status word that is not a link'], 'default' => '#7fabdb'],
        'accentunderline'   => ['section' => 'brand', 'sub' => 'link', 'label' => 'Underlines', 'short' => 'Underlines', 'usage' => ['the underline drawn under a link', 'accent rules under a heading'], 'default' => '#7fabdb'],

        // --- Navbar ----------------------------------------------------------
        // The bar owns its whole palette rather than borrowing the page's. Every
        // thing drawn on it — the site titles, the icon cluster, the log-in link
        // — has its own rest / hover / active colour, because the bar is the one
        // surface where "the same blue as a body link" is almost never the right
        // answer: it sits on its own background, at its own size, over content
        // that scrolls under it.
        //
        // `sub` groups the cards on the editor into the four things the bar is
        // made of, and `short` is the name shown there — inside a section already
        // headed "Navbar", under a heading already reading "Titles", a card
        // called "Navbar title hover color" says its own address three times.
        // `label` keeps the long form because that is what the design-system
        // export hands the app, where there is no surrounding page to supply it.
        'navbarbackground1' => ['section' => 'navbar', 'sub' => 'background', 'label' => 'Navbar background 1', 'short' => 'Background 1', 'usage' => ['navbar background'], 'default' => '#0c141f'],
        'navbarbackground2' => ['section' => 'navbar', 'sub' => 'background', 'label' => 'Navbar background 2', 'short' => 'Background 2', 'usage' => ['navbar glass — the translucent pane the bar is painted with'], 'default' => '#121e2d'],
        'navbartitlecolor'  => ['section' => 'navbar', 'sub' => 'title', 'label' => 'Navbar title color', 'short' => 'Title color', 'usage' => ['the site links across the bar (Home, Courses, …)'], 'default' => '#eef3f9'],
        'navbartitlehovercolor' => ['section' => 'navbar', 'sub' => 'title', 'label' => 'Navbar title hover color', 'short' => 'Title hover color', 'usage' => ['a site link under the cursor'], 'default' => '#7fabdb'],
        'navbartitleactivecolor' => ['section' => 'navbar', 'sub' => 'title', 'label' => 'Navbar title active color', 'short' => 'Title active color', 'usage' => ['the site link for the page being viewed'], 'default' => '#7fabdb'],
        // The two SHAPE colours. Which shapes they draw (any of underline / bold
        // / square background, or none) is not a colour and so is not a role: it
        // is a per-group choice stored beside them, see
        // theme_nit_navbar_shape_treatments().
        'navbartitlehoverstylecolor' => ['section' => 'navbar', 'sub' => 'title', 'label' => 'Navbar title hover style color', 'short' => 'Title hover style color', 'usage' => ['the hover shape — its underline, or its square background'], 'default' => '#16222f'],
        'navbartitleactivestylecolor' => ['section' => 'navbar', 'sub' => 'title', 'label' => 'Navbar title active style color', 'short' => 'Title active style color', 'usage' => ['the active shape — its underline, or its square background'], 'default' => '#7fabdb'],
        'navbariconcolor'   => ['section' => 'navbar', 'sub' => 'icon', 'label' => 'Navbar icon color', 'short' => 'Icon color', 'usage' => ['navbar icons — search, language, messages, notifications, gear', 'notification panel action icons'], 'default' => '#eef3f9'],
        'navbariconhovercolor' => ['section' => 'navbar', 'sub' => 'icon', 'label' => 'Navbar icon hover color', 'short' => 'Icon hover color', 'usage' => ['a navbar icon under the cursor', 'the shape drawn with it — its underline, or its soft pad'], 'default' => '#7fabdb'],
        'navbariconactivecolor' => ['section' => 'navbar', 'sub' => 'icon', 'label' => 'Navbar icon active color', 'short' => 'Icon active color', 'usage' => ['a navbar icon whose panel is open, or being pressed', 'the shape drawn with it'], 'default' => '#7fabdb'],
        'navbarlogincolor'  => ['section' => 'navbar', 'sub' => 'login', 'label' => 'Navbar login color', 'short' => 'Login color', 'usage' => ['the "Log in" link on the bar (signed-out visitors)'], 'default' => '#eef3f9'],
        'navbarloginhovercolor' => ['section' => 'navbar', 'sub' => 'login', 'label' => 'Navbar login hover color', 'short' => 'Login hover color', 'usage' => ['the "Log in" link under the cursor'], 'default' => '#7fabdb'],
        'navbarloginactivecolor' => ['section' => 'navbar', 'sub' => 'login', 'label' => 'Navbar login active color', 'short' => 'Login active color', 'usage' => ['the "Log in" link being pressed, or on the log-in page itself'], 'default' => '#7fabdb'],
        // The log-in link's two SHAPE colours, the same pair a title has. It is
        // the one call to action a signed-out visitor gets, so it answers the
        // cursor the way a title does — and an admin who wants a pill behind it
        // needs a colour for that pill that is not the ink.
        'navbarloginhoverstylecolor' => ['section' => 'navbar', 'sub' => 'login', 'label' => 'Navbar login hover style color', 'short' => 'Login hover style color', 'usage' => ['the hover shape — its underline, or its square background'], 'default' => '#16222f'],
        'navbarloginactivestylecolor' => ['section' => 'navbar', 'sub' => 'login', 'label' => 'Navbar login active style color', 'short' => 'Login active style color', 'usage' => ['the active shape — its underline, or its square background'], 'default' => '#7fabdb'],

        // --- Footer ----------------------------------------------------------
        'footerbackground1' => ['section' => 'footer', 'label' => 'Footer background 1', 'usage' => ['footer background'], 'default' => '#0c141f'],
        'footerbackground2' => ['section' => 'footer', 'label' => 'Footer background 2', 'usage' => ['footer background — second colour (reserved, not consumed yet)'], 'default' => '#121e2d'],
        // Footer-only roles. The band used to borrow Accent Text / Primary from
        // the page, which meant an admin could not recolour a footer heading
        // without moving every link on the site. Three roles, one per thing the
        // footer actually draws, so the band is tunable on its own.
        'footerheading'     => ['section' => 'footer', 'label' => 'Footer heading', 'usage' => ['footer column headings'], 'default' => '#7fabdb'],
        'footerlink'        => ['section' => 'footer', 'label' => 'Footer link', 'usage' => ['footer column links'], 'default' => '#5488c4'],
        'footericon'        => ['section' => 'footer', 'label' => 'Footer icon', 'usage' => ['footer social icons and their ring'], 'default' => '#5488c4'],

        // --- Surfaces & text --------------------------------------------------
        // The three background layers, deepest first. They are one ladder, not a
        // primary/secondary pair plus a stray: Background primary is the floor,
        // Background secondary a band lifted off it, and Surface the card that
        // sits on top of both. The usage text below says which is which, because
        // the NAMES alone read as "primary and secondary are the pair" — and an
        // admin who assumes Background secondary is the card recolours bands and
        // wonders why every card stayed put.
        'background'        => ['section' => 'surface', 'label' => 'Background primary', 'usage' => ['the page background — the deepest layer, behind everything else'], 'default' => '#0c141f'],
        'background2'       => ['section' => 'surface', 'label' => 'Background secondary', 'usage' => ['a band or section lifted off the page ground', 'a recessed control inside an input — e.g. the Browse button of a file field', 'Bootstrap\'s secondary background'], 'default' => '#101a27'],
        'surface'           => ['section' => 'surface', 'label' => 'Surface', 'usage' => ['the top layer — anything with an edge sits here', 'cards background', 'dropdowns background', 'side menu background', 'inputs background', 'tooltips background', 'table background', 'navbar buttons and panels'], 'default' => '#121e2d'],
        // "text in buttons" is deliberately NOT one of these any more. Every
        // button label has a card of its own in the Brand section — "Text on
        // main button", "Text on secondary button", "Outline button text" and
        // their hover twins — because a label's colour is decided by the FILL
        // under it, not by the page: the same near-white that reads on a dark
        // page is invisible on Group 4's light one. Leaving the usage here sent
        // an admin to the wrong card, where moving Text primary to fix one
        // button repainted every paragraph on the site and still left the
        // button wrong in half the groups.
        //
        // What stays is the ink of the controls that only LOOK like buttons —
        // the eye toggle inside a password box, the drawer chevron, the ghost
        // links under the log-in card. Those are body / field text that happens
        // to sit in a `.btn`, and the two usages below already name them.
        'textprimary'       => ['section' => 'surface', 'label' => 'Text primary', 'usage' => ['main normal text', 'text in inputs'], 'default' => '#eef3f9'],
        'textsecondary'     => ['section' => 'surface', 'label' => 'Text secondary', 'usage' => ['secondary normal text', 'placeholders'], 'default' => '#94a3b8'],
        // Both borders here are NEUTRAL — the quiet hairline that separates one
        // thing from the next. The coloured border that means "this card is the
        // selected one" is not either of these: it is Accent, over in Brand.
        // Saying so here is the whole point of the usage lines — thirty-six of
        // Accent's sixty-one uses are borders, so "which border card do I move"
        // is the question this section gets asked most.
        'borderprimary'     => ['section' => 'surface', 'label' => 'Border primary', 'usage' => ['the default hairline around cards, inputs, tables and dropdowns', 'the divider between rows', 'NOT the border of a selected card — that is Accent'], 'default' => '#223244'],
        'bordersecondary'   => ['section' => 'surface', 'label' => 'Border secondary', 'usage' => ['a stronger neutral edge where the hairline is too faint to read', 'the outer edge of a raised panel'], 'default' => '#33475e'],

        // --- Surfaces & text > Hover ------------------------------------------
        // Two pairs. The primaries are what the site draws today; the
        // secondaries are declared for the quieter second hover a nested row or
        // a muted label wants, and NOTHING reads them yet. That last fact is in
        // their usage text on purpose: a card an admin can move with no visible
        // effect reads as a broken panel, and the honest fix while they wait for
        // a consumer is to say so on the card rather than to leave them looking
        // like the eleven cards around them that do work.
        'hoverbackground'   => ['section' => 'surface', 'label' => 'Hover background primary', 'usage' => ['the fill behind a row, menu item or card under the cursor', 'table row hover', 'dropdown item hover'], 'default' => '#16222f'],
        'hoverbackgroundsecondary' => ['section' => 'surface', 'label' => 'Hover background secondary', 'usage' => ['reserved — a second, stronger hover fill for a row nested inside an already-hovered container', 'no component reads this yet'], 'default' => '#1b2937'],
        'hovertext'         => ['section' => 'surface', 'label' => 'Hover text primary', 'usage' => ['the colour a link or label turns under the cursor', 'keyboard-focused link text', 'the underline under a hovered link'], 'default' => '#7fabdb'],
        'hovertextsecondary' => ['section' => 'surface', 'label' => 'Hover text secondary', 'usage' => ['reserved — what Text secondary turns into under the cursor, for muted labels that should not brighten all the way', 'no component reads this yet'], 'default' => '#b6c4d3'],

        // --- Status -----------------------------------------------------------
        'error'             => ['section' => 'status', 'label' => 'Error', 'usage' => ['Errors', 'danger / destructive actions', 'invalid fields'], 'default' => '#d07f43'],
        'success'           => ['section' => 'status', 'label' => 'Success', 'usage' => ['Success', 'enrolled / active / paid', 'positive states'], 'default' => '#3fa877'],
        'warning'           => ['section' => 'status', 'label' => 'Warning', 'usage' => ['Warnings', 'caution', 'pending / expiring'], 'default' => '#d8c24e'],
        'info'              => ['section' => 'status', 'label' => 'Info', 'usage' => ['Neutral notices', 'tips', 'hints'], 'default' => '#5fb0c9'],

        // --- Bassthalk --------------------------------------------------------
        // The colours the Bassthalk-style screens draw that no role above
        // covers: the visitor sign-up button and search pill on the navbar, the
        // scroll progress bar inside it, the two illustration panels, and the
        // registration wizard's own accent set. Every group carries them (a role
        // is global); only the Bassthalk group (g18) is tuned for them, and the
        // screens that use them opt into that group with `.nit-brand-18`.
        'bthsignupbg'       => ['section' => 'bassthalk', 'sub' => 'bthnavbar', 'label' => 'Sign-up button background', 'short' => 'Sign-up button background', 'usage' => ['the "حساب جديد" button on the navbar (visitors)', 'the notification count badge'], 'default' => '#4bf7a1'],
        'bthsignuptext'     => ['section' => 'bassthalk', 'sub' => 'bthnavbar', 'label' => 'Sign-up button text', 'short' => 'Sign-up button text', 'usage' => ['the label of the "حساب جديد" button', 'the number on the notification badge'], 'default' => '#0e335d'],
        'bthsearchbg'       => ['section' => 'bassthalk', 'sub' => 'bthnavbar', 'label' => 'Search pill background', 'short' => 'Search pill background', 'usage' => ['the "ابحث في الموقع" pill next to the logo (its hover is derived)'], 'default' => '#d1d5db'],
        'bthprogresstrack'  => ['section' => 'bassthalk', 'sub' => 'bthnavbar', 'label' => 'Scroll progress track', 'short' => 'Progress track', 'usage' => ['the thin bar along the bottom of the navbar while the page is scrolled'], 'default' => '#38bdf8'],
        'bthprogressfill'   => ['section' => 'bassthalk', 'sub' => 'bthnavbar', 'label' => 'Scroll progress fill', 'short' => 'Progress fill', 'usage' => ['the part of the scroll bar already read'], 'default' => '#0369a1'],
        'bthloginimagebg'   => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Log-in illustration background', 'short' => 'Log-in illustration', 'usage' => ['the panel behind the log-in illustration — match the picture\'s own blue'], 'default' => '#0080ff'],
        'bthregisterimagebg' => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Registration illustration background', 'short' => 'Registration illustration', 'usage' => ['the panel behind the registration illustration — match the picture\'s own teal'], 'default' => '#01b4b8'],
        'bthregisteraccent' => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Registration accent', 'short' => 'Registration accent', 'usage' => ['the "التالي" and "طلب انشاء حساب" buttons', 'the step name above the progress bar', 'the terms box, its link and the terms dialog header', 'focused fields on the registration form'], 'default' => '#0ea5e9'],
        'bthregisterprogress' => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Registration progress', 'short' => 'Registration progress', 'usage' => ['the filled part of the registration step bar'], 'default' => '#38bdf8'],
        'bthfieldicon'      => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Field icon & floating label', 'short' => 'Field icon', 'usage' => ['the icons inside the registration fields', 'a field label once it floats up', 'the search dialog\'s label, icon and focus line'], 'default' => '#06b6d4'],
        'bthprevbg'         => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Back button background', 'short' => 'Back button background', 'usage' => ['the yellow "السابق" button on the registration steps'], 'default' => '#f4b30c'],
        'bthprevtext'       => ['section' => 'bassthalk', 'sub' => 'bthauth', 'label' => 'Back button text', 'short' => 'Back button text', 'usage' => ['the label of the "السابق" button', 'text on the registration accent (buttons, dialog header)'], 'default' => '#ffffff'],
        'bthherobgtop'      => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero background (top)', 'short' => 'Hero top', 'usage' => ['the top colour of the home-page hero gradient'], 'default' => '#ffffff'],
        'bthherobgbottom'   => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero background (bottom)', 'short' => 'Hero bottom', 'usage' => ['the bottom colour of the home-page hero gradient'], 'default' => '#bfdfff'],
        'bthherotext'       => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero text', 'short' => 'Hero text', 'usage' => ['the hero title and paragraph on the home page'], 'default' => '#111827'],
        'bthherohighlight'  => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero highlighted words', 'short' => 'Hero highlight', 'usage' => ['the bold highlighted words in the hero title ("الطالب ليتفوق")'], 'default' => '#0d549b'],
        'bthherobtn'        => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero button', 'short' => 'Hero button', 'usage' => ['the "ابدأ رحلتك" button fill and border (drawn at half saturation)', 'its label colour on hover, when the fill turns transparent'], 'default' => '#14d80a'],
        'bthherobtntext'    => ['section' => 'bassthalk', 'sub' => 'bthhome', 'label' => 'Hero button text', 'short' => 'Hero button text', 'usage' => ['the label of the "ابدأ رحلتك" button'], 'default' => '#ffffff'],
        'bthhowbg1'         => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 1', 'short' => 'Card 1', 'usage' => ['the 1st card of "إزاي بسطتهالك بتشتغل؟" (and the 6th, 11th… when there are more)'], 'default' => '#0080ff'],
        'bthhowbg2'         => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 2', 'short' => 'Card 2', 'usage' => ['the 2nd card (and the 7th, 12th…)'], 'default' => '#b5ecff'],
        'bthhowbg3'         => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 3', 'short' => 'Card 3', 'usage' => ['the 3rd card (and the 8th…); drawn at half saturation like the original'], 'default' => '#adffa4'],
        'bthhowbg4'         => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 4', 'short' => 'Card 4', 'usage' => ['the 4th card (and the 9th…)'], 'default' => '#60feff'],
        'bthhowbg5'         => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 5', 'short' => 'Card 5', 'usage' => ['the 5th card (and the 10th…); drawn at half saturation like the original'], 'default' => '#bbf7d0'],
        'bthhowtext1'       => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — card 1 text', 'short' => 'Card 1 text', 'usage' => ['the number, title and text on card 1'], 'default' => '#ffffff'],
        'bthhowtext'        => ['section' => 'bassthalk', 'sub' => 'bthhow', 'label' => 'How it works — text', 'short' => 'Text', 'usage' => ['the section title and paragraph', 'the number, title and text on cards 2–5'], 'default' => '#111827'],
        'bthselbgtop'       => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Selected courses background (top)', 'short' => 'Background top', 'usage' => ['the top colour of the section gradient'], 'default' => '#ffffff'],
        'bthselbgbottom'    => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Selected courses background (bottom)', 'short' => 'Background bottom', 'usage' => ['the bottom colour of the section gradient'], 'default' => '#bfdfff'],
        'bthseltext'        => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Selected courses text', 'short' => 'Text', 'usage' => ['the section title and paragraph', 'the year list items'], 'default' => '#111827'],
        'bthselframebg'     => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Illustration frame', 'short' => 'Frame', 'usage' => ['the white card around the illustration'], 'default' => '#ffffff'],
        'bthselframe'       => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Illustration frame border', 'short' => 'Frame border', 'usage' => ['the thin border of the illustration card'], 'default' => '#d6f3ff'],
        'bthselfilter'      => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Year filter', 'short' => 'Year filter', 'usage' => ['the border, text and arrow of "اختر الصف الدراسي"', 'the border of its open list'], 'default' => '#0861c5'],
        'bthselmenubg'      => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Year list background', 'short' => 'Year list', 'usage' => ['the open list of years'], 'default' => '#edfaff'],
        'bthselmenuhover'   => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Year list hover', 'short' => 'Year list hover', 'usage' => ['a year under the cursor'], 'default' => '#83e2ff'],
        'bthselarrowbg'     => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Carousel arrow', 'short' => 'Arrow', 'usage' => ['the next / previous buttons when they can move'], 'default' => '#0694ff'],
        'bthselarrowhover'  => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Carousel arrow hover', 'short' => 'Arrow hover', 'usage' => ['an arrow under the cursor'], 'default' => '#0080ff'],
        'bthselarrowicon'   => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Carousel arrow icon', 'short' => 'Arrow icon', 'usage' => ['the chevron on an active arrow'], 'default' => '#ffffff'],
        'bthselarrowoff'    => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Carousel arrow disabled', 'short' => 'Arrow disabled', 'usage' => ['the ring of an arrow at the end of the list'], 'default' => '#d1d5db'],
        'bthselarrowofficon' => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Carousel arrow disabled icon', 'short' => 'Arrow disabled icon', 'usage' => ['the chevron of an arrow at the end of the list'], 'default' => '#9ca3af'],
        'bthselcardbg'      => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Course card', 'short' => 'Card', 'usage' => ['the course cards'], 'default' => '#0694ff'],
        'bthselcardhover'   => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Course card hover', 'short' => 'Card hover', 'usage' => ['a course card under the cursor'], 'default' => '#0d549b'],
        'bthselcardtext'    => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Course card text', 'short' => 'Card text', 'usage' => ['the text on the cards (the top box and the counts are this colour mixed down)'], 'default' => '#ffffff'],
        'bthselyear'        => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Course card year', 'short' => 'Year', 'usage' => ['the year next to the course name'], 'default' => '#91ff85'],
        'bthselbtnbg'       => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Card button', 'short' => 'Button', 'usage' => ['the "اعرف اكثر" button'], 'default' => '#ffffff'],
        'bthselbtntext'     => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Card button text', 'short' => 'Button text', 'usage' => ['the label of "اعرف اكثر"'], 'default' => '#0084ff'],
        'bthselbtnborder'   => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Card button border', 'short' => 'Button border', 'usage' => ['the ring of "اعرف اكثر"'], 'default' => '#e5e7eb'],
        'bthselbtnhover'    => ['section' => 'bassthalk', 'sub' => 'bthselected', 'label' => 'Card button hover', 'short' => 'Button hover', 'usage' => ['"اعرف اكثر" under the cursor'], 'default' => '#f3f4f6'],
        'bthteachbg'        => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — background', 'short' => 'Background', 'usage' => ['the blue band behind "المدرسين عندنا"'], 'default' => '#0080ff'],
        'bthteachtitle'     => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — title text', 'short' => 'Title text', 'usage' => ['the big title and the paragraph on the blue band'], 'default' => '#ffffff'],
        'bthteachpanel'     => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — filter panel', 'short' => 'Panel', 'usage' => ['the green panel behind the filters and the teacher cards'], 'default' => '#4bf7a1'],
        'bthteachlabel'     => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — panel text', 'short' => 'Panel text', 'usage' => ['"مرحلتك و دراستك:" and the items of the open lists'], 'default' => '#111827'],
        'bthteachfilter'    => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — filters', 'short' => 'Filters', 'usage' => ['the border, text and arrow of the year / division dropdowns'], 'default' => '#000000'],
        'bthteachmenubg'    => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — open list', 'short' => 'Open list', 'usage' => ['the background of an open dropdown list'], 'default' => '#ffffff'],
        'bthteachmenuhover' => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — list hover', 'short' => 'List hover', 'usage' => ['an item under the cursor in an open list', 'the study-system headings in the division list are this colour mixed down'], 'default' => '#83e2ff'],
        'bthteacharrowbg'   => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — arrow', 'short' => 'Arrow', 'usage' => ['the next / previous buttons when they can move'], 'default' => '#ffffff'],
        'bthteacharrowicon' => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — arrow icon', 'short' => 'Arrow icon', 'usage' => ['the chevron on an active arrow'], 'default' => '#0080ff'],
        'bthteacharrowhover' => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — arrow hover', 'short' => 'Arrow hover', 'usage' => ['an arrow under the cursor'], 'default' => '#edfaff'],
        'bthteacharrowoff'  => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — arrow disabled', 'short' => 'Arrow disabled', 'usage' => ['the ring of an arrow that cannot move'], 'default' => '#d1d5db'],
        'bthteacharrowofficon' => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — arrow disabled icon', 'short' => 'Arrow disabled icon', 'usage' => ['the chevron of an arrow that cannot move'], 'default' => '#9ca3af'],
        'bthteachcardbg'    => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — card', 'short' => 'Card', 'usage' => ['the teacher cards'], 'default' => '#ffffff'],
        'bthteachname'      => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — name', 'short' => 'Name', 'usage' => ['the teacher name on a card'], 'default' => '#111827'],
        'bthteachsub'       => ['section' => 'bassthalk', 'sub' => 'bthteach', 'label' => 'Teachers — subject', 'short' => 'Subject', 'usage' => ['the line under the name (the teacher title)'], 'default' => '#6b7280'],
        'bthlessonsaccent'  => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — accent', 'short' => 'Accent', 'usage' => ['"المقترحة", the course names, the "الكل" / subscribe / enter buttons'], 'default' => '#0861c5'],
        'bthlessonsaccenttext' => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — on accent', 'short' => 'On accent', 'usage' => ['text on the filled buttons'], 'default' => '#ffffff'],
        'bthlessonstext'    => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — text', 'short' => 'Text', 'usage' => ['the section titles and the year on a card'], 'default' => '#111827'],
        'bthlessonsmuted'   => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — muted text', 'short' => 'Muted text', 'usage' => ['the course description and dates'], 'default' => '#6b7280'],
        'bthlessonscard'    => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — card', 'short' => 'Card', 'usage' => ['the course cards (and their border)'], 'default' => '#f3f4f6'],
        'bthlessonsprice'   => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — price', 'short' => 'Price', 'usage' => ['the course price'], 'default' => '#06b6d4'],
        'bthlessonsnav'     => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — arrows', 'short' => 'Arrows', 'usage' => ['the ring and chevron of the slider arrows', 'their fill on hover'], 'default' => '#0694ff'],
        'bthlessonsnavring' => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — arrow ring', 'short' => 'Arrow ring', 'usage' => ['the ring of the slider arrows'], 'default' => '#1eb1ff'],
        'bthlessonsnavbg'   => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — arrow fill', 'short' => 'Arrow fill', 'usage' => ['the slider arrows at rest'], 'default' => '#ffffff'],
        'bthlessonsdot'     => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — dots', 'short' => 'Dots', 'usage' => ['the slider dots (drawn faint; the current one is the active colour)'], 'default' => '#000000'],
        'bthlessonsdotactive' => ['section' => 'bassthalk', 'sub' => 'bthlessons', 'label' => 'Suggested lessons — active dot', 'short' => 'Active dot', 'usage' => ['the dot of the current slide'], 'default' => '#00d5dd'],
        // Course details page (course/view.php for learners) — the white cards,
        // borders and main ink reuse Surface / Border primary / Text primary /
        // Text secondary; only the colours that page owns are listed here.
        'bthcoursepagebg'   => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — background', 'short' => 'Page background', 'usage' => ['the grey ground behind the course cards'], 'default' => '#f3f4f6'],
        'bthcourseaccent'   => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — icons & active tab', 'short' => 'Icons', 'usage' => ['the tick icons in the lessons list', 'the lesson-type icons (book, play, homework)', 'the active tab "عن الكورس" and its underline', 'the icons of the facts list'], 'default' => '#06b6d4'],
        'bthcourseprice'    => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — price', 'short' => 'Price', 'usage' => ['the big price', 'the lesson names in the price card'], 'default' => '#0e7490'],
        'bthcourseoldprice' => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — old price', 'short' => 'Old price', 'usage' => ['the struck-through price before the discount'], 'default' => '#94a3b8'],
        'bthcoursediscount' => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — discount badge', 'short' => 'Discount', 'usage' => ['the border and text of the "خصم %" badge'], 'default' => '#ef4444'],
        'bthcoursecta'      => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — subscribe button', 'short' => 'Subscribe button', 'usage' => ['the "اشترك الآن !" button fill and border', 'the ring and text of the secondary "go to course" button'], 'default' => '#3b82f6'],
        'bthcoursectatext'  => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — subscribe button text', 'short' => 'Subscribe text', 'usage' => ['the label of the "اشترك الآن !" button'], 'default' => '#ffffff'],
        'bthcourseopen'     => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — open lesson header', 'short' => 'Open lesson', 'usage' => ['the blue header of the lesson that is open', 'the title of a lesson part ("كبسولة الشرح")'], 'default' => '#0861c5'],
        'bthcourseopentext' => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — open lesson text', 'short' => 'Open lesson text', 'usage' => ['the title, description and arrow on the open lesson header'], 'default' => '#edfaff'],
        'bthcoursechevron'  => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — lesson arrow', 'short' => 'Arrow', 'usage' => ['the round blue arrow button on every lesson header'], 'default' => '#0080ff'],
        'bthcoursepart'     => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — lesson part header', 'short' => 'Part header', 'usage' => ['the light-green bar of a lesson part ("كبسولة الشرح", "كتاب التفوق")'], 'default' => '#e3ffe1'],
        'bthcourserow'      => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — item row', 'short' => 'Item row', 'usage' => ['the grey rows of the files / videos inside a lesson', 'the closed lesson headers'], 'default' => '#f3f4f6'],
        'bthcoursefaint'    => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — faint text', 'short' => 'Faint text', 'usage' => ['the teacher title under the name', 'the "إخفاء" link on a lesson part'], 'default' => '#9ca3af'],
        'bthcourseteacher'  => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — teacher name', 'short' => 'Teacher name', 'usage' => ['the teacher name under the photo'], 'default' => '#4b5563'],
        'bthcourselabel'    => ['section' => 'bassthalk', 'sub' => 'bthcourse', 'label' => 'Course page — card label', 'short' => 'Card label', 'usage' => ['"الدروس:" above the lessons list in the price card'], 'default' => '#334155'],
        // Teacher page (local/academy/teacher.php). The photo card reuses Surface /
        // Border primary / Text primary / Text secondary, the course cards reuse
        // the "Home — suggested lessons" roles; only the hero and chips are its own.
        'bthtpherobg'       => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — hero background', 'short' => 'Hero background', 'usage' => ['the blue band behind the teacher name (its triangle pattern is the hero text, faint)'], 'default' => '#38bdf8'],
        'bthtpherotext'     => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — hero text', 'short' => 'Hero text', 'usage' => ['the teacher name', 'the year pills text'], 'default' => '#ffffff'],
        'bthtpherosub'      => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — hero title line', 'short' => 'Title line', 'usage' => ['the teacher title under the name'], 'default' => '#e0f2fe'],
        'bthtpchipbg'       => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — count chips', 'short' => 'Chips', 'usage' => ['the dark "+ N" chips above the name'], 'default' => '#1e3a8a'],
        'bthtpchiptext'     => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — count chips text', 'short' => 'Chips text', 'usage' => ['the number on a chip'], 'default' => '#ffffff'],
        'bthtptagyears'     => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — "years" tag', 'short' => 'Years tag', 'usage' => ['the "الصفوف" label on the first chip'], 'default' => '#22d3ee'],
        'bthtptagcourses'   => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — "courses" tag', 'short' => 'Courses tag', 'usage' => ['the "الكورسات" label on the second chip'], 'default' => '#f43f5e'],
        'bthtptagtext'      => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — tag text', 'short' => 'Tag text', 'usage' => ['the words on the two chip tags'], 'default' => '#ffffff'],
        'bthtppillbg'       => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — year pills', 'short' => 'Year pills', 'usage' => ['the pills listing the years taught, under the name'], 'default' => '#0ea5e9'],
        'bthtppillborder'   => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — year pill border', 'short' => 'Pill border', 'usage' => ['the ring of the year pills'], 'default' => '#7dd3fc'],
        'bthtpaccent'       => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — accent', 'short' => 'Accent', 'usage' => ['"المدرس" in the courses heading and the lines beside it', 'the chosen year filter'], 'default' => '#0ea5e9'],
        'bthtpstaticon'     => ['section' => 'bassthalk', 'sub' => 'bthteacherpage', 'label' => 'Teacher page — stat icons', 'short' => 'Stat icons', 'usage' => ['the icons beside the counts on the photo card'], 'default' => '#4f46e5'],
        // Parent dashboard (local/parent/index.php, scss/components/_bthparent.scss).
        'bthparentcard'     => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — grey card', 'short' => 'Grey card', 'usage' => ['the card behind "لوحة تحكم ولي الأمر"', 'the lecture rows', 'the "no content yet" box'], 'default' => '#f3f4f6'],
        'bthparenthighlight' => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — highlight', 'short' => 'Highlight', 'usage' => ['"ولي الأمر" in the title', 'the hand icon above the course list'], 'default' => '#11baf0'],
        'bthparentpara'     => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — paragraph', 'short' => 'Paragraph', 'usage' => ['the paragraph under the title'], 'default' => '#4b5563'],
        'bthparentaccent'   => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — accent', 'short' => 'Accent', 'usage' => ['a phone field\'s line and label while typing', '"نجلك !" in the stats title', 'the "عرض المحتوى" button'], 'default' => '#06b6d4'],
        'bthparentbtn'      => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — start button', 'short' => 'Start button', 'usage' => ['the "ابدأ المتابعة" button fill and border', 'its label on hover, when the fill turns transparent'], 'default' => '#22d3ee'],
        'bthparentbtntext'  => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — start button text', 'short' => 'Start button text', 'usage' => ['the label of "ابدأ المتابعة"'], 'default' => '#ffffff'],
        'bthparentpanel'    => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — panels', 'short' => 'Panels', 'usage' => ['the course list panel and the stats panel'], 'default' => '#f8f8f8'],
        'bthparentpanelborder' => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — panel border', 'short' => 'Panel border', 'usage' => ['the border of the two panels', 'the outline of the yellow shapes and of the "لم يتم رفع محتوى" badge'], 'default' => '#000000'],
        'bthparentcourse'   => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — course item', 'short' => 'Course item', 'usage' => ['each course in the course list'], 'default' => '#a5f3fc'],
        'bthparentcoursehover' => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — course item hover', 'short' => 'Course hover', 'usage' => ['a course under the cursor, and the chosen one'], 'default' => '#67e8f9'],
        'bthparentprompt'   => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — prompt', 'short' => 'Prompt', 'usage' => ['"أختر الكورس أولا…" and "لا يوجد كورسات…"'], 'default' => '#155e75'],
        'bthparenttable'    => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — stats table', 'short' => 'Stats table', 'usage' => ['the box behind a lecture\'s videos / homework / exams table'], 'default' => '#f1f5f9'],
        'bthparentblob'     => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — yellow shapes', 'short' => 'Yellow shapes', 'usage' => ['the yellow shapes behind the course name and the lecture names', 'the hand icon on hover'], 'default' => '#ffe866'],
        'bthparentmissed'   => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — missed', 'short' => 'Missed', 'usage' => ['"لم يتم مشاهدة الفيديو" / "لم يتم حضور الاختبار"', 'wrong phone numbers on the form'], 'default' => '#f43f5e'],
        'bthparentempty'    => ['section' => 'bassthalk', 'sub' => 'bthparent', 'label' => 'Parent dashboard — no content badge', 'short' => 'No content badge', 'usage' => ['the "لم يتم رفع محتوى" badge on an empty lecture'], 'default' => '#fef08a'],
    ];
}

/**
 * The ordered Brand-Colors groups.
 *
 * A "group" is a complete named set of all the roles — a swappable palette.
 * Group 1 is the site-wide default; a component can opt into another group via
 * the matching wrapper class (`.nit-brand-2`, `.nit-brand-3`, …), keeping the
 * same variable names but resolving them from that group's values. Groups 2 and
 * 3 seed equal to Group 1 and are tuned later on the gallery page.
 *
 * Groups 6 onwards are the category pairs — see theme_nit_brand_hues() — and are
 * listed from that table rather than written out here, so a hue is added in one
 * place.
 *
 * @return array<string, string> group key (g1..g17) => display label
 */
function theme_nit_brand_groups(): array {
    $groups = [
        'g1' => 'Group 1',
        'g2' => 'Group 2',
        'g3' => 'Group 3',
        // 4 and 5 are a matched pair, built for the navbar light/dark switch:
        // one palette at two light levels, so toggling changes how bright the
        // site is and not which site it is. Their labels say so, because the two
        // selects on the "Change style" tab are where that choice gets made.
        'g4' => 'Group 4 (Daylight — light)',
        'g5' => 'Group 5 (Graphite — dark)',
    ];
    // The same pair again, once per category hue, labelled the same way.
    foreach (theme_nit_brand_hues() as $hue) {
        $groups[$hue['light']] = 'Group ' . substr($hue['light'], 1) . ' (' . $hue['name'] . ' — light)';
        $groups[$hue['dark']] = 'Group ' . substr($hue['dark'], 1) . ' (' . $hue['name'] . ' — dark)';
    }
    // The Bassthalk palette: the log-in and registration screens, the navbar
    // and the site footer opt into it with `.nit-brand-18`.
    $groups['g18'] = 'Bassthalk';
    return $groups;
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
 * Group 1 is the default layer (no class); every other group maps to the switch
 * class of the same number, `nit-brand-<N>`, which scss/foundation/_brand.scss
 * declares for every group in `$nit-b-groups` (theme_nit_get_pre_scss()).
 *
 * @param string $group a group key (g1..g17)
 * @return string '' | 'nit-brand-2' … 'nit-brand-17'
 */
function theme_nit_brand_group_class(string $group): string {
    if ($group === 'g1' || !array_key_exists($group, theme_nit_brand_groups())) {
        return '';
    }
    return 'nit-brand-' . substr($group, 1);
}

/**
 * Per-group default overrides for the Brand-Colors palette.
 *
 * Groups 1-3 are each a complete, self-contained theme with its own
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
 * Groups 4 and 5 are the exception to "all three are dark", and to "each group is
 * its own mood": they are ONE palette at two light levels, built for the navbar
 * light/dark switch. A switch between modes should change how bright the site is,
 * not which site it is, so they share an accent and differ only in ground.
 *
 *   - Group 4 — Daylight (LIGHT) : the only light group. Near-white page, white
 *     cards, deep slate ink, azure accent — under a DARK navigation bar and
 *     footer (the site logo is white-on-transparent and vanishes on a light bar).
 *   - Group 5 — Graphite (DARK) : the same palette turned down. Neutral graphite
 *     ground — which is also what keeps it apart from groups 1-3, all of which
 *     are coloured darks.
 *
 * Being light, Group 4 is the one group whose text-on-primary cannot be the body
 * ink — see the `--nit-brand-on-primary` note on `.nit-brand-4` in
 * scss/foundation/_brand.scss. It is also why `--nit-navbartext` reads the navbar
 * icon role rather than the body ink (scss/foundation/_root.scss).
 *
 * Groups 6 onward are the category pairs: Daylight and Graphite again, once per
 * hue in theme_nit_brand_hues(), with the azure rungs swapped for that hue's.
 * They are generated from the two templates below rather than written out, so
 * the twelve of them cannot drift apart from one another or from the pair they
 * copy.
 *
 * A role missing from a group falls back to theme_nit_brand_roles()['default'].
 *
 * @return array<string, array<string, string>> group key (g1..g17) => role => #hex
 */
function theme_nit_brand_group_defaults(): array {
    $defaults = [
        // --- Group 1 : Slate blue (calm, cool). -------------------------------
        'g1' => [
            'primary'           => '#5488c4',
            'secondary'         => '#1c2a3a',
            'onprimary'         => '#eef3f9',
            'onsecondary'       => '#eef3f9',
            'accent'            => '#5488c4',
            'accenttext'        => '#7fabdb',
            'accentwords'       => '#7fabdb',
            'accentunderline'   => '#7fabdb',
            // --- Buttons. The border and the three hover colours were derived
            //     in CSS before they were roles (border = the fill; hover = the
            //     fill mixed 85% with white). Each seeds to exactly what that
            //     derivation produced, so no group moves until one is edited.
            'btnprimaryborder'  => '#5488c4',
            'btnprimaryhoverbg' => '#6e9acd',
            'btnprimaryhovertext' => '#eef3f9',
            'btnprimaryhoverborder' => '#6594ca',
            'btnsecondaryborder' => '#1c2a3a',
            'btnsecondaryhoverbg' => '#182431',
            'btnsecondaryhovertext' => '#eef3f9',
            'btnsecondaryhoverborder' => '#16222e',
            // The outline button seeds to what .btn-outline-secondary already
            // resolved to: Text primary on Border secondary, hovering onto the
            // two Hover roles. Its Background is the page ground, and is painted
            // only once the Fill switch is on — theme_nit_button_outline_fill().
            // The brand outline button seeds to what .btn-outline-primary was
            // already drawn from: the ring and the label in Primary, hovering
            // onto a solid Primary fill wearing the main button's label colour.
            'btnoutlineprimarybg' => '#0c141f',
            'btnoutlineprimarytext' => '#5488c4',
            'btnoutlineprimaryborder' => '#5488c4',
            'btnoutlineprimaryhoverbg' => '#5488c4',
            'btnoutlineprimaryhovertext' => '#eef3f9',
            'btnoutlineprimaryhoverborder' => '#5488c4',
            'btnoutlinebg'      => '#0c141f',
            'btnoutlinetext'    => '#eef3f9',
            'btnoutlineborder'  => '#33475e',
            'btnoutlinehoverbg' => '#16222f',
            'btnoutlinehovertext' => '#7fabdb',
            'btnoutlinehoverborder' => '#33475e',
            // The tick box and the switch seed to what the box already drew in
            // this group — Surface under it, Border primary around it, Primary
            // when ticked with the main button's label colour for the tick —
            // except the OFF knob, which had no hex to seed from and takes Text
            // secondary so it shows on a dark track.
            'checkbg'           => '#121e2d',
            'checkborder'       => '#223244',
            'checkknob'         => '#94a3b8',
            'checkcheckedbg'    => '#5488c4',
            'checkcheckedborder' => '#5488c4',
            'checkcheckedmark'  => '#eef3f9',
            'background'        => '#0c141f',
            'background2'       => '#101a27',
            'navbarbackground1' => '#0c141f',
            'navbarbackground2' => '#121e2d',
            'navbartitlecolor'  => '#eef3f9',
            'navbartitlehovercolor' => '#7fabdb',
            'navbartitleactivecolor' => '#7fabdb',
            'navbartitlehoverstylecolor' => '#16222f',
            'navbartitleactivestylecolor' => '#7fabdb',
            'navbariconcolor'   => '#eef3f9',
            'navbariconhovercolor' => '#7fabdb',
            'navbariconactivecolor' => '#7fabdb',
            'navbarlogincolor'  => '#eef3f9',
            'navbarloginhovercolor' => '#7fabdb',
            'navbarloginactivecolor' => '#7fabdb',
            'navbarloginhoverstylecolor' => '#16222f',
            'navbarloginactivestylecolor' => '#7fabdb',
            'footerbackground1' => '#0c141f',
            'footerbackground2' => '#121e2d',
            'footerheading'     => '#7fabdb',
            'footerlink'        => '#5488c4',
            'footericon'        => '#5488c4',
            'surface'           => '#121e2d',
            'textprimary'       => '#eef3f9',
            'textsecondary'     => '#94a3b8',
            'borderprimary'     => '#223244',
            'bordersecondary'   => '#33475e',
            'hoverbackground'   => '#16222f',
            'hoverbackgroundsecondary' => '#1c2937',
            'hovertext'         => '#7fabdb',
            'hovertextsecondary' => '#b6c4d3',
            'error'             => '#d07f43',
            'success'           => '#3fa877',
            'warning'           => '#d8c24e',
            'info'              => '#5fb0c9',
        ],
        // --- Group 2 : Teal / Deep sea (cool, restful). -----------------------
        'g2' => [
            'primary'           => '#2f9e8f',
            'secondary'         => '#12302e',
            'onprimary'         => '#eef5f4',
            'onsecondary'       => '#eef5f4',
            'accent'            => '#2f9e8f',
            'accenttext'        => '#58bdad',
            'accentwords'       => '#58bdad',
            'accentunderline'   => '#6ccabb',
            'btnprimaryborder'  => '#2f9e8f',
            'btnprimaryhoverbg' => '#4eada0',
            'btnprimaryhovertext' => '#eef5f4',
            'btnprimaryhoverborder' => '#44a89a',
            'btnsecondaryborder' => '#12302e',
            'btnsecondaryhoverbg' => '#0f2927',
            'btnsecondaryhovertext' => '#eef5f4',
            'btnsecondaryhoverborder' => '#0e2625',
            'btnoutlineprimarybg' => '#0a1a1a',
            'btnoutlineprimarytext' => '#2f9e8f',
            'btnoutlineprimaryborder' => '#2f9e8f',
            'btnoutlineprimaryhoverbg' => '#2f9e8f',
            'btnoutlineprimaryhovertext' => '#eef5f4',
            'btnoutlineprimaryhoverborder' => '#2f9e8f',
            'btnoutlinebg'      => '#0a1a1a',
            'btnoutlinetext'    => '#eef5f4',
            'btnoutlineborder'  => '#2f5a56',
            'btnoutlinehoverbg' => '#143231',
            'btnoutlinehovertext' => '#6ccabb',
            'btnoutlinehoverborder' => '#2f5a56',
            'checkbg'           => '#102727',
            'checkborder'       => '#1f3f3d',
            'checkknob'         => '#8aa5a2',
            'checkcheckedbg'    => '#2f9e8f',
            'checkcheckedborder' => '#2f9e8f',
            'checkcheckedmark'  => '#eef5f4',
            'background'        => '#0a1a1a',
            'background2'       => '#0d2020',
            'navbarbackground1' => '#0a1a1a',
            'navbarbackground2' => '#102727',
            'navbartitlecolor'  => '#eef5f4',
            'navbartitlehovercolor' => '#6ccabb',
            'navbartitleactivecolor' => '#58bdad',
            'navbartitlehoverstylecolor' => '#143231',
            'navbartitleactivestylecolor' => '#58bdad',
            'navbariconcolor'   => '#eef5f4',
            'navbariconhovercolor' => '#6ccabb',
            'navbariconactivecolor' => '#58bdad',
            'navbarlogincolor'  => '#eef5f4',
            'navbarloginhovercolor' => '#58bdad',
            'navbarloginactivecolor' => '#6ccabb',
            'navbarloginhoverstylecolor' => '#143231',
            'navbarloginactivestylecolor' => '#58bdad',
            'footerbackground1' => '#0a1a1a',
            'footerbackground2' => '#102727',
            'footerheading'     => '#58bdad',
            'footerlink'        => '#2f9e8f',
            'footericon'        => '#2f9e8f',
            'surface'           => '#102727',
            'textprimary'       => '#eef5f4',
            'textsecondary'     => '#8aa5a2',
            'borderprimary'     => '#1f3f3d',
            'bordersecondary'   => '#2f5a56',
            'hoverbackground'   => '#143231',
            'hoverbackgroundsecondary' => '#193937',
            'hovertext'         => '#6ccabb',
            'hovertextsecondary' => '#aec5c2',
            'error'             => '#d07f43',
            'success'           => '#46b085',
            'warning'           => '#d8c24e',
            'info'              => '#6aa6c9',
        ],
        // --- Group 3 : Indigo / Lavender (soft, cool violet). -----------------
        'g3' => [
            'primary'           => '#8478cf',
            'secondary'         => '#26243d',
            'onprimary'         => '#efedf7',
            'onsecondary'       => '#efedf7',
            'accent'            => '#8478cf',
            'accenttext'        => '#a99ee2',
            'accentwords'       => '#a99ee2',
            'accentunderline'   => '#b4a9ee',
            'btnprimaryborder'  => '#8478cf',
            'btnprimaryhoverbg' => '#968cd6',
            'btnprimaryhovertext' => '#efedf7',
            'btnprimaryhoverborder' => '#9086d4',
            'btnsecondaryborder' => '#26243d',
            'btnsecondaryhoverbg' => '#201f34',
            'btnsecondaryhovertext' => '#efedf7',
            'btnsecondaryhoverborder' => '#1e1d31',
            'btnoutlineprimarybg' => '#11101c',
            'btnoutlineprimarytext' => '#8478cf',
            'btnoutlineprimaryborder' => '#8478cf',
            'btnoutlineprimaryhoverbg' => '#8478cf',
            'btnoutlineprimaryhovertext' => '#efedf7',
            'btnoutlineprimaryhoverborder' => '#8478cf',
            'btnoutlinebg'      => '#11101c',
            'btnoutlinetext'    => '#efedf7',
            'btnoutlineborder'  => '#433d64',
            'btnoutlinehoverbg' => '#201e34',
            'btnoutlinehovertext' => '#b4a9ee',
            'btnoutlinehoverborder' => '#433d64',
            'checkbg'           => '#1a182d',
            'checkborder'       => '#2d2a45',
            'checkknob'         => '#9691b3',
            'checkcheckedbg'    => '#8478cf',
            'checkcheckedborder' => '#8478cf',
            'checkcheckedmark'  => '#efedf7',
            'background'        => '#11101c',
            'background2'       => '#151425',
            'navbarbackground1' => '#11101c',
            'navbarbackground2' => '#1a182d',
            'navbartitlecolor'  => '#efedf7',
            'navbartitlehovercolor' => '#b4a9ee',
            'navbartitleactivecolor' => '#a99ee2',
            'navbartitlehoverstylecolor' => '#201e34',
            'navbartitleactivestylecolor' => '#a99ee2',
            'navbariconcolor'   => '#efedf7',
            'navbariconhovercolor' => '#b4a9ee',
            'navbariconactivecolor' => '#a99ee2',
            'navbarlogincolor'  => '#efedf7',
            'navbarloginhovercolor' => '#a99ee2',
            'navbarloginactivecolor' => '#b4a9ee',
            'navbarloginhoverstylecolor' => '#201e34',
            'navbarloginactivestylecolor' => '#a99ee2',
            'footerbackground1' => '#11101c',
            'footerbackground2' => '#1a182d',
            'footerheading'     => '#a99ee2',
            'footerlink'        => '#8478cf',
            'footericon'        => '#8478cf',
            'surface'           => '#1a182d',
            'textprimary'       => '#efedf7',
            'textsecondary'     => '#9691b3',
            'borderprimary'     => '#2d2a45',
            'bordersecondary'   => '#433d64',
            'hoverbackground'   => '#201e34',
            'hoverbackgroundsecondary' => '#26243c',
            'hovertext'         => '#b4a9ee',
            'hovertextsecondary' => '#b8b4d0',
            'error'             => '#d07f43',
            'success'           => '#57b39a',
            'warning'           => '#d8c24e',
            'info'              => '#7fa6d6',
        ],
        // --- Groups 4 and 5 : Daylight / Graphite. ----------------------------
        // Unlike groups 1-3 these were not picked by eye. They are two readings
        // of ONE system, built in OKLCH so the steps are perceptually even, then
        // checked pair by pair for contrast. The generator is checked in beside
        // them — `node theme/nit/docs/palette-check.js` reprints these hexes and
        // the whole contrast table, so a change here can be re-verified rather
        // than argued about.
        //
        // Two ramps, sampled at fixed OKLCH lightnesses:
        //   neutral  hue 258, chroma 0.005-0.014  (barely cool, never tinted)
        //     N0  #fbfdff   N50 #f6f8fb   N100 #f1f3f6  N200 #e6e8eb  N300 #d5d9df
        //     N350 #c7cbd0  N400 #a7abb1  N500 #7b8189  N600 #5e646b  N700 #43484f
        //     N800 #2a2e35  N850 #1f232a  N900 #14191f  N950 #0d1117
        //   accent   hue 256 (azure)
        //     A200 #c0dafc  A300 #98c0f7  A400 #71a7ef  A500 #4687db
        //     A600 #2368bd  A700 #0e509d  A800 #073b78
        //
        // Light reads the ramps from one end, dark from the other, so switching
        // mode changes how bright the site is and not which site it is. The
        // neutrals carry almost no chroma on purpose: at these lightnesses a
        // tinted ground reads as a colour cast, which is what made the warm
        // palette these replaced look muddy.
        //
        // Every text/background pair in both groups is WCAG AA or better; the
        // numbers are in the block comment above each group.

        // --- Group 4 : Daylight (LIGHT). --------------------------------------
        // Light CONTENT under DARK CHROME. The dark navigation bar and footer are
        // not a leftover: the site logo is a white-on-transparent PNG (an admin
        // setting, not a theme asset), so a light bar erases the wordmark. A dark
        // header band is how most light interfaces are built anyway.
        //
        // Contrast: ink on page 16.6 · ink on card 17.7 · muted on page 5.6 ·
        // link on card 7.9 · white on the primary fill 5.6 · primary fill on the
        // page 5.2 · navbar text on the bar 17.8. All AA or AAA.
        'g4' => [
            'primary'           => '#2368bd',   // A600
            'secondary'         => '#e6e8eb',   // N200
            // Buttons: white on the dark-blue fill (5.6:1), and the body ink on
            // the pale grey secondary fill (16.6:1).
            'onprimary'         => '#ffffff',
            'onsecondary'       => '#14191f',   // N900
            'accent'            => '#2368bd',
            // A step darker than primary: on a light ground a link has to beat
            // the paper, not the ink.
            'accenttext'        => '#0e509d',   // A700
            'accentwords'       => '#0e509d',
            'accentunderline'   => '#073b78',
            'btnprimaryborder'  => '#2368bd',
            'btnprimaryhoverbg' => '#447fc7',
            'btnprimaryhovertext' => '#ffffff',
            'btnprimaryhoverborder' => '#3977c4',
            'btnsecondaryborder' => '#e6e8eb',   // N200
            // The one seed that is NOT what the site drew before, and it could
            // not be: this group's secondary button was frozen on Group 1's navy
            // (see the Bootstrap bridge note in scss/foundation/_brand.scss), so
            // there was no correct-looking value to preserve. It steps one rung
            // DOWN the ramp (N300) rather than up, because this is the only
            // group whose secondary fill is light and Bootstrap's tint-on-hover
            // would have made a light button fainter instead of firmer.
            'btnsecondaryhoverbg' => '#d5d9df',   // N300
            'btnsecondaryhovertext' => '#14191f',   // N900
            'btnsecondaryhoverborder' => '#d5d9df',   // N300
            'btnoutlineprimarybg' => '#f6f8fb',   // N50
            'btnoutlineprimarytext' => '#2368bd',   // A600
            'btnoutlineprimaryborder' => '#2368bd',   // A600
            'btnoutlineprimaryhoverbg' => '#2368bd',   // A600
            'btnoutlineprimaryhovertext' => '#ffffff',
            'btnoutlineprimaryhoverborder' => '#2368bd',   // A600
            'btnoutlinebg'      => '#f6f8fb',   // N50
            'btnoutlinetext'    => '#14191f',   // N900
            'btnoutlineborder'  => '#a7abb1',   // N400
            'btnoutlinehoverbg' => '#f1f3f6',   // N100
            'btnoutlinehovertext' => '#073b78',   // A800
            'btnoutlinehoverborder' => '#a7abb1',   // N400
            'checkbg'           => '#ffffff',
            'checkborder'       => '#d5d9df',   // N300
            'checkknob'         => '#5e646b',   // N600
            'checkcheckedbg'    => '#2368bd',   // A600
            'checkcheckedborder' => '#2368bd',  // A600
            'checkcheckedmark'  => '#ffffff',
            'background'        => '#f6f8fb',   // N50
            'background2'       => '#f1f3f6',   // N100
            // Light chrome. This group is light THROUGHOUT — bar, page and band.
            // The bar is one step whiter than the page so it still reads as a
            // bar; the footer is one step greyer, which is what lets the curve
            // across its top be seen at all.
            'navbarbackground1' => '#ffffff',
            'navbarbackground2' => '#f6f8fb',   // N50
            // The bar is light here, so everything drawn on it — the titles, the
            // glyphs, the log-in link — is the body ink, not near-white. Their
            // hover / active states go DARKER (A700 / A800), because on a light
            // ground a state has to beat the paper, not the ink.
            'navbartitlecolor'  => '#14191f',   // N900
            'navbartitlehovercolor' => '#073b78',   // A800
            'navbartitleactivecolor' => '#0e509d',  // A700
            'navbartitlehoverstylecolor' => '#f1f3f6',  // N100 — the hover pad
            'navbartitleactivestylecolor' => '#0e509d', // A700 — the underline
            'navbariconcolor'   => '#14191f',   // N900
            'navbariconhovercolor' => '#073b78',    // A800
            'navbariconactivecolor' => '#0e509d',   // A700
            'navbarlogincolor'  => '#14191f',   // N900
            'navbarloginhovercolor' => '#0e509d',   // A700
            'navbarloginactivecolor' => '#073b78',  // A800
            'navbarloginhoverstylecolor' => '#f1f3f6',
            'navbarloginactivestylecolor' => '#0e509d',
            'footerbackground1' => '#f1f3f6',   // N100
            'footerbackground2' => '#e6e8eb',   // N200
            'footerheading'     => '#0e509d',
            'footerlink'        => '#2368bd',
            'footericon'        => '#2368bd',
            'surface'           => '#ffffff',
            'textprimary'       => '#14191f',   // N900
            'textsecondary'     => '#5e646b',   // N600
            'borderprimary'     => '#d5d9df',   // N300
            'bordersecondary'   => '#a7abb1',   // N400
            'hoverbackground'   => '#f1f3f6',   // N100
            'hoverbackgroundsecondary' => '#e6e8eb',   // N200
            'hovertext'         => '#073b78',   // A800
            // On a LIGHT group a muted label darkens on hover instead of
            // brightening, and it stays neutral — the azure ramp is the link
            // ink, and a secondary label is not a link.
            'hovertextsecondary' => '#43484f',   // N700
            // Semantics sampled at OKLCH L48 — dark enough to read as text on
            // white. Still no red: danger is a deep burnt orange.
            'error'             => '#9a3c16',
            'success'           => '#00703e',
            'warning'           => '#775800',
            'info'              => '#006789',
        ],
        // --- Group 5 : Graphite (DARK). ---------------------------------------
        // Group 4 turned down — the same two ramps read from the dark end. The
        // ground is neutral graphite rather than a coloured dark, which is also
        // what keeps it apart from groups 1-3 (navy, teal, indigo).
        //
        // The primary is LIGHT here and its label is DARK, the way dark themes
        // are built: a fill dark enough to hold white text would be too dim to
        // see against the page. White on the mid azure was 3.65 — short of AA;
        // the page ground on the light azure is 7.6. See the
        // `--nit-brand-on-primary` line on `.nit-brand-5` in _brand.scss.
        //
        // Contrast: ink on page 17.8 · ink on card 14.8 · muted on card 6.8 ·
        // link on card 8.4 · dark label on the primary fill 7.6. All AA or AAA.
        'g5' => [
            'primary'           => '#71a7ef',   // A400
            'secondary'         => '#2a2e35',   // N800
            // Buttons: the page ground on the light-azure fill (7.6:1), and the
            // body ink on the dark grey secondary fill.
            'onprimary'         => '#0d1117',
            'onsecondary'       => '#f6f8fb',
            'accent'            => '#71a7ef',
            'accenttext'        => '#98c0f7',   // A300
            'accentwords'       => '#98c0f7',
            'accentunderline'   => '#c0dafc',
            'btnprimaryborder'  => '#71a7ef',   // A400
            'btnprimaryhoverbg' => '#86b4f1',
            'btnprimaryhovertext' => '#0d1117',   // N950
            'btnprimaryhoverborder' => '#7fb0f1',
            'btnsecondaryborder' => '#2a2e35',   // N800
            'btnsecondaryhoverbg' => '#24272d',
            'btnsecondaryhovertext' => '#f6f8fb',   // N50
            'btnsecondaryhoverborder' => '#22252a',
            'btnoutlineprimarybg' => '#0d1117',   // N950
            'btnoutlineprimarytext' => '#71a7ef',   // A400
            'btnoutlineprimaryborder' => '#71a7ef',   // A400
            'btnoutlineprimaryhoverbg' => '#71a7ef',   // A400
            'btnoutlineprimaryhovertext' => '#0d1117',   // N950
            'btnoutlineprimaryhoverborder' => '#71a7ef',   // A400
            'btnoutlinebg'      => '#0d1117',   // N950
            'btnoutlinetext'    => '#f6f8fb',   // N50
            'btnoutlineborder'  => '#43484f',   // N700
            'btnoutlinehoverbg' => '#14191f',   // N900
            'btnoutlinehovertext' => '#c0dafc',   // A200
            'btnoutlinehoverborder' => '#43484f',   // N700
            'checkbg'           => '#1f232a',   // N850
            'checkborder'       => '#2a2e35',   // N800
            'checkknob'         => '#a7abb1',   // N400
            'checkcheckedbg'    => '#71a7ef',   // A400
            'checkcheckedborder' => '#71a7ef',  // A400
            'checkcheckedmark'  => '#0d1117',   // N950
            'background'        => '#0d1117',   // N950
            'background2'       => '#14191f',   // N900
            'navbarbackground1' => '#0d1117',
            'navbarbackground2' => '#14191f',
            'navbartitlecolor'  => '#f6f8fb',
            'navbartitlehovercolor' => '#c0dafc',   // A200
            'navbartitleactivecolor' => '#98c0f7',  // A300
            'navbartitlehoverstylecolor' => '#14191f',  // N900 — the hover pad
            'navbartitleactivestylecolor' => '#98c0f7', // A300 — the underline
            'navbariconcolor'   => '#f6f8fb',
            'navbariconhovercolor' => '#c0dafc',    // A200
            'navbariconactivecolor' => '#98c0f7',   // A300
            'navbarlogincolor'  => '#f6f8fb',
            'navbarloginhovercolor' => '#98c0f7',   // A300
            'navbarloginactivecolor' => '#c0dafc',  // A200
            'navbarloginhoverstylecolor' => '#14191f',
            'navbarloginactivestylecolor' => '#98c0f7',
            'footerbackground1' => '#0d1117',
            'footerbackground2' => '#14191f',
            'footerheading'     => '#98c0f7',
            'footerlink'        => '#71a7ef',
            'footericon'        => '#71a7ef',
            'surface'           => '#1f232a',   // N850
            'textprimary'       => '#f6f8fb',   // N50
            'textsecondary'     => '#a7abb1',   // N400
            'borderprimary'     => '#2a2e35',   // N800
            'bordersecondary'   => '#43484f',   // N700
            'hoverbackground'   => '#14191f',   // N900
            // Graphite's neutral ramp is tighter than the other groups', so the
            // stronger hover lands on N850 — the same tone as this group's
            // Surface. That is the ramp being honest, not a mistake: a nested
            // row hovered inside a card reads as the card's own tone here.
            'hoverbackgroundsecondary' => '#1f232a',   // N850
            'hovertext'         => '#c0dafc',   // A200
            'hovertextsecondary' => '#d5d9df',   // N300
            // The same four hues as Group 4, sampled at L72 instead of L48.
            'error'             => '#e68867',
            'success'           => '#5cbc82',
            'warning'           => '#c89e3a',
            'info'              => '#3bb2e3',
        ],
    ];

    // --- Groups 6-17 : the category pairs. ---------------------------------
    // Each hue is Daylight and Graphite again with the azure rungs replaced —
    // see theme_nit_brand_hue_templates() for which role reads which rung.
    $templates = theme_nit_brand_hue_templates();
    foreach (theme_nit_brand_hues() as $hue) {
        $defaults[$hue['light']] = theme_nit_brand_rehue($templates['light'], $hue['rungs']);
        $defaults[$hue['dark']] = theme_nit_brand_rehue($templates['dark'], $hue['rungs']);
    }

    // --- Group 18 : Bassthalk (LIGHT). ---------------------------------------
    // Seeded from Daylight (g4, the light palette) so every role it does not
    // name below still has a sane light value, then set to the colours measured
    // on bassthalk.com for the screens that use it.
    $defaults['g18'] = array_merge($defaults['g4'], [
        // Brand — the main button is the log-in / search submit blue; the
        // secondary button is the navbar "تسجيل الدخول" navy.
        'accent'            => '#0861c5',
        'accenttext'        => '#0861c5',
        'accentwords'       => '#013399',
        'accentunderline'   => '#6b7280',
        'primary'           => '#0861c5',
        'onprimary'         => '#ffffff',
        'btnprimaryborder'  => '#0861c5',
        'btnprimaryhoverbg' => '#074ea0',
        'btnprimaryhovertext' => '#ffffff',
        'btnprimaryhoverborder' => '#074ea0',
        'secondary'         => '#0e335d',
        'onsecondary'       => '#ffffff',
        'btnsecondaryborder' => '#0e335d',
        'btnsecondaryhoverbg' => '#0b2a4d',
        'btnsecondaryhovertext' => '#ffffff',
        'btnsecondaryhoverborder' => '#0b2a4d',
        // Neutral outline button — "إنشاء حساب ولي أمر" on the log-in card.
        'btnoutlinebg'      => '#ffffff',
        'btnoutlinetext'    => '#111827',
        'btnoutlineborder'  => '#d1d5db',
        'btnoutlinehoverbg' => '#f9fafb',
        'btnoutlinehovertext' => '#111827',
        'btnoutlinehoverborder' => '#0861c5',
        // Checkbox & switch — the navbar edit-mode switch.
        'checkbg'           => '#ffffff',
        'checkborder'       => '#d1d5db',
        'checkknob'         => '#9ca3af',
        'checkcheckedbg'    => '#4bf7a1',
        'checkcheckedborder' => '#4bf7a1',
        'checkcheckedmark'  => '#ffffff',
        // Navbar — white ink on the blue pill; hover keeps the ink (no colour
        // change on hover), the soft pads are these whites mixed down in CSS.
        'navbarbackground1' => '#0080ff',
        'navbarbackground2' => '#0080ff',
        'navbartitlecolor'  => '#ffffff',
        'navbartitlehovercolor' => '#ffffff',
        'navbartitleactivecolor' => '#ffffff',
        'navbartitlehoverstylecolor' => '#ffffff',
        'navbartitleactivestylecolor' => '#ffffff',
        'navbariconcolor'   => '#ffffff',
        'navbariconhovercolor' => '#ffffff',
        'navbariconactivecolor' => '#ffffff',
        'navbarlogincolor'  => '#ffffff',
        'navbarloginhovercolor' => '#ffffff',
        'navbarloginactivecolor' => '#ffffff',
        'navbarloginhoverstylecolor' => '#ffffff',
        'navbarloginactivestylecolor' => '#ffffff',
        // Footer.
        'footerbackground1' => '#ffffff',
        'footerbackground2' => '#ffffff',
        'footerheading'     => '#013399',
        'footerlink'        => '#111827',
        'footericon'        => '#0861c5',
        // Surfaces & text.
        'background'        => '#ffffff',
        'background2'       => '#f3f4f6',
        'surface'           => '#ffffff',
        'textprimary'       => '#111827',
        'textsecondary'     => '#6b7280',
        'borderprimary'     => '#d1d5db',
        'bordersecondary'   => '#e5e7eb',
        'hoverbackground'   => '#f9fafb',
        'hoverbackgroundsecondary' => '#f3f4f6',
        'hovertext'         => '#0861c5',
        'hovertextsecondary' => '#374151',
        // Status.
        'error'             => '#e03131',
        'success'           => '#16a34a',
        'warning'           => '#f4b30c',
        'info'              => '#0ea5e9',
        // Bassthalk extras.
        'bthsignupbg'       => '#4bf7a1',
        'bthsignuptext'     => '#0e335d',
        'bthsearchbg'       => '#d1d5db',
        'bthprogresstrack'  => '#38bdf8',
        'bthprogressfill'   => '#0369a1',
        'bthloginimagebg'   => '#0080ff',
        'bthregisterimagebg' => '#01b4b8',
        'bthregisteraccent' => '#0ea5e9',
        'bthregisterprogress' => '#38bdf8',
        'bthfieldicon'      => '#06b6d4',
        'bthprevbg'         => '#f4b30c',
        'bthprevtext'       => '#ffffff',
        'bthherobgtop'      => '#ffffff',
        'bthherobgbottom'   => '#bfdfff',
        'bthherotext'       => '#111827',
        'bthherohighlight'  => '#0d549b',
        'bthherobtn'        => '#14d80a',
        'bthherobtntext'    => '#ffffff',
        'bthhowbg1'         => '#0080ff',
        'bthhowbg2'         => '#b5ecff',
        'bthhowbg3'         => '#adffa4',
        'bthhowbg4'         => '#60feff',
        'bthhowbg5'         => '#bbf7d0',
        'bthhowtext1'       => '#ffffff',
        'bthhowtext'        => '#111827',
        'bthselbgtop'       => '#ffffff',
        'bthselbgbottom'    => '#bfdfff',
        'bthseltext'        => '#111827',
        'bthselframebg'     => '#ffffff',
        'bthselframe'       => '#d6f3ff',
        'bthselfilter'      => '#0861c5',
        'bthselmenubg'      => '#edfaff',
        'bthselmenuhover'   => '#83e2ff',
        'bthselarrowbg'     => '#0694ff',
        'bthselarrowhover'  => '#0080ff',
        'bthselarrowicon'   => '#ffffff',
        'bthselarrowoff'    => '#d1d5db',
        'bthselarrowofficon' => '#9ca3af',
        'bthselcardbg'      => '#0694ff',
        'bthselcardhover'   => '#0d549b',
        'bthselcardtext'    => '#ffffff',
        'bthselyear'        => '#91ff85',
        'bthselbtnbg'       => '#ffffff',
        'bthselbtntext'     => '#0084ff',
        'bthselbtnborder'   => '#e5e7eb',
        'bthselbtnhover'    => '#f3f4f6',
        'bthteachbg'        => '#0080ff',
        'bthteachtitle'     => '#ffffff',
        'bthteachpanel'     => '#4bf7a1',
        'bthteachlabel'     => '#111827',
        'bthteachfilter'    => '#000000',
        'bthteachmenubg'    => '#ffffff',
        'bthteachmenuhover' => '#83e2ff',
        'bthteacharrowbg'   => '#ffffff',
        'bthteacharrowicon' => '#0080ff',
        'bthteacharrowhover' => '#edfaff',
        'bthteacharrowoff'  => '#d1d5db',
        'bthteacharrowofficon' => '#9ca3af',
        'bthteachcardbg'    => '#ffffff',
        'bthteachname'      => '#111827',
        'bthteachsub'       => '#6b7280',
        'bthlessonsaccent'  => '#0861c5',
        'bthlessonsaccenttext' => '#ffffff',
        'bthlessonstext'    => '#111827',
        'bthlessonsmuted'   => '#6b7280',
        'bthlessonscard'    => '#f3f4f6',
        'bthlessonsprice'   => '#06b6d4',
        'bthlessonsnav'     => '#0694ff',
        'bthlessonsnavring' => '#1eb1ff',
        'bthlessonsnavbg'   => '#ffffff',
        'bthlessonsdot'     => '#000000',
        'bthlessonsdotactive' => '#00d5dd',
        'bthcoursepagebg'   => '#f3f4f6',
        'bthcourseaccent'   => '#06b6d4',
        'bthcourseprice'    => '#0e7490',
        'bthcourseoldprice' => '#94a3b8',
        'bthcoursediscount' => '#ef4444',
        'bthcoursecta'      => '#3b82f6',
        'bthcoursectatext'  => '#ffffff',
        'bthcourseopen'     => '#0861c5',
        'bthcourseopentext' => '#edfaff',
        'bthcoursechevron'  => '#0080ff',
        'bthcoursepart'     => '#e3ffe1',
        'bthcourserow'      => '#f3f4f6',
        'bthcoursefaint'    => '#9ca3af',
        'bthcourseteacher'  => '#4b5563',
        'bthcourselabel'    => '#334155',
        'bthtpherobg'       => '#38bdf8',
        'bthtpherotext'     => '#ffffff',
        'bthtpherosub'      => '#e0f2fe',
        'bthtpchipbg'       => '#1e3a8a',
        'bthtpchiptext'     => '#ffffff',
        'bthtptagyears'     => '#22d3ee',
        'bthtptagcourses'   => '#f43f5e',
        'bthtptagtext'      => '#ffffff',
        'bthtppillbg'       => '#0ea5e9',
        'bthtppillborder'   => '#7dd3fc',
        'bthtpaccent'       => '#0ea5e9',
        'bthtpstaticon'     => '#4f46e5',
        'bthparentcard'     => '#f3f4f6',
        'bthparenthighlight' => '#11baf0',
        'bthparentpara'     => '#4b5563',
        'bthparentaccent'   => '#06b6d4',
        'bthparentbtn'      => '#22d3ee',
        'bthparentbtntext'  => '#ffffff',
        'bthparentpanel'    => '#f8f8f8',
        'bthparentpanelborder' => '#000000',
        'bthparentcourse'   => '#a5f3fc',
        'bthparentcoursehover' => '#67e8f9',
        'bthparentprompt'   => '#155e75',
        'bthparenttable'    => '#f1f5f9',
        'bthparentblob'     => '#ffe866',
        'bthparentmissed'   => '#f43f5e',
        'bthparentempty'    => '#fef08a',
    ]);
    return $defaults;
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
 * does not override a role — so every group ships as a distinct palette.
 *
 * Built once per request: it is pure (code constants only), and
 * theme_nit_brandcolour() asks for it once per role it resolves — which, with
 * seventeen groups, the SCSS build does well over a thousand times.
 *
 * @return array<string, array{group:string, groupkey:string, role:string,
 *         label:string, usage:string, default:string}> ordered map keyed by token key
 */
function theme_nit_brand_palette(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = [];
    $roles = theme_nit_brand_roles();
    $groupdefaults = theme_nit_brand_group_defaults();
    foreach (theme_nit_brand_groups() as $gkey => $glabel) {
        foreach ($roles as $role => $meta) {
            $out[$gkey . '_' . $role] = [
                'group'    => $glabel,
                'groupkey' => $gkey,
                'role'     => $role,
                'section'  => $meta['section'],
                // Both optional: only the roles the editor groups and renames
                // carry them, and everything downstream falls back to `label`
                // and to the section's single unnamed block.
                'sub'      => $meta['sub'] ?? '',
                'short'    => $meta['short'] ?? $meta['label'],
                'label'    => $meta['label'],
                'usage'    => $meta['usage'],
                'default'  => $groupdefaults[$gkey][$role] ?? $meta['default'],
            ];
        }
    }
    $cache = $out;
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
            'section'  => $meta['section'],
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
 * Two keys exist for clients that theme themselves from this payload and must
 * not hard-code group keys to do it:
 *   * `scheme` on every group — 'light' or 'dark', how that group's own roles
 *     are authored (theme_nit_brand_group_scheme()). It says what the group IS,
 *     not where the site uses it.
 *   * `schemes` beside `groups` — { "light": "g4", "dark": "g5" }, the pair the
 *     site's own light/dark switch moves between (theme_nit_mode_groups()). A
 *     client that follows it survives the groups being renumbered or renamed.
 *
 * @return array{roles: string[], schemes: array<string, string>,
 *         groups: array<int, array{key:string, name:string, scheme:string,
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
                // How this group is authored — measured from its own roles, so
                // it stays true when an admin retunes the group. The name is
                // admin-editable prose and can say anything; this cannot.
                'scheme'    => theme_nit_brand_group_scheme($gkey),
                'isdefault' => ($gkey === 'g1'),
                'class'     => theme_nit_brand_group_class($gkey),
                'roles'     => [],
            ];
        }
        $groups[$gidx[$gkey]]['roles'][] = [
            'key'      => $token['key'],
            'role'     => $token['role'],
            'section'  => $token['section'],
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

    // The site's own light/dark pair: which group each scheme is worn as. Same
    // map the navbar switch uses, so a client that reads it is looking at the
    // site's answer rather than at a copy of it made on release day.
    return [
        'roles' => $roles,
        'schemes' => theme_nit_mode_groups(),
        // The scheme a visitor with no stored choice opens in — the same answer
        // theme_nit_current_mode() gives a request that carries no cookie.
        'defaultscheme' => theme_nit_default_mode(),
        'groups' => $groups,
    ];
}

/**
 * Category styles tab, as data: how each main category is branded, per mode.
 *
 * Only top-level (main) categories are assignable; subcategories inherit their
 * top ancestor's branding. Visibility-aware: an anonymous request sees only the
 * categories a guest may see.
 *
 * Each category carries a `modes` map — `light` and `dark`, each with the group
 * it renders in, the wrapper class that skins it, and the URL of the logo drawn
 * for that mode (empty string = "use the site logo"). The flat `group` /
 * `groupname` / `class` / `isdefault` keys are the LIGHT values and are kept
 * because they are what the app already reads; a category that was branded
 * before the site had two styles per category still answers there exactly as it
 * did.
 *
 * Each mode also carries `scheme` — how the group it names is authored — so a
 * client can see that a category's light mode really is pointed at a light
 * palette without having to know which group key that is.
 *
 * `isdefault` means "this category has no style of its own for this mode, so it
 * shows the site's group for that mode" — which is also what `group` answers in
 * that case, because it is the group those pages actually render in.
 *
 * @return array{groups: array<int, array{key:string, name:string, scheme:string}>,
 *         categories: array<int, array{id:int, name:string, group:string,
 *         groupname:string, class:string, isdefault:bool, modes:array}>}
 */
function theme_nit_category_styles_export(): array {
    $grouplabels = theme_nit_brand_groups();

    $groups = [];
    foreach ($grouplabels as $gkey => $glabel) {
        $groups[] = [
            'key'    => $gkey,
            'name'   => $glabel,
            'scheme' => theme_nit_brand_group_scheme($gkey),
        ];
    }

    $maps = [];
    foreach (array_keys(theme_nit_modes()) as $mode) {
        $maps[$mode] = theme_nit_category_group_map($mode);
    }
    // What an UNASSIGNED category renders in: the site's group for that mode,
    // which is what theme_nit_active_chrome_group() falls back to on the web.
    // Answering 'g1' here (as this used to) told the app a category was black
    // in light mode whenever the site's light group was anything but Group 1.
    $sitegroups = theme_nit_mode_groups();

    $categories = [];
    foreach (core_course_category::top()->get_children() as $cat) {
        $modes = [];
        foreach ($maps as $mode => $map) {
            $assigned = $map[$cat->id] ?? null;
            $isdefault = ($assigned === null || !array_key_exists($assigned, $grouplabels));
            $gkey = $isdefault ? ($sitegroups[$mode] ?? 'g1') : $assigned;
            if (!array_key_exists($gkey, $grouplabels)) {
                $gkey = 'g1';
            }
            $logo = theme_nit_category_logo_url((int) $cat->id, $mode);
            $modes[$mode] = [
                'group'     => $gkey,
                'groupname' => $grouplabels[$gkey],
                'class'     => theme_nit_brand_group_class($gkey),
                'scheme'    => theme_nit_brand_group_scheme($gkey),
                'isdefault' => $isdefault,
                'logo'      => $logo ? $logo->out(false) : '',
            ];
        }

        $light = $modes['light'];
        $categories[] = [
            'id'        => (int) $cat->id,
            'name'      => $cat->get_formatted_name(),
            'group'     => $light['group'],
            'groupname' => $light['groupname'],
            'class'     => $light['class'],
            'isdefault' => $light['isdefault'],
            'modes'     => $modes,
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
        'categorystyles' => theme_nit_category_styles_export(),
        'fonts'          => theme_nit_fonts_export(),
        'components'     => theme_nit_components_export(),
    ];
    // Return the academy's ACTUAL brand palette (the root --nit-brand-* values) as
    // configured — the app renders a single theme (no dark/light split), so it
    // uses these colours directly, exactly like the web. No dark/light transform.
    $payload['brandcolors'] = theme_nit_brand_export();
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
        // supportedapp is set from the dynamic licence below (defaults true only
        // when local_license isn't installed at all — a non-SaaS host).
        'supportedapp'    => true,
        // get_config() answers false (not null) when unset → default provisioned.
        'provisioned'     => in_array(get_config('theme_nit', 'provisioned'), [false, null, ''], true)
            ? true : (bool) (int) get_config('theme_nit', 'provisioned'),
        'status'          => 'active',        // active | expired | suspended
        'defaultlanguage' => $CFG->lang ?? 'en',
        'languages'       => array_values(array_keys(
            get_string_manager()->get_list_of_translations() ?: [($CFG->lang ?? 'en') => 1])),
    ];

    if (class_exists('\local_license\license')) {
        $lic = '\local_license\license';
        $site['package'] = ['code' => $lic::tier(), 'name' => $lic::tiername()];
        // Dynamic app-access gate (Demo = false), from the pushed licence definition.
        if (method_exists($lic, 'supported_app')) {
            $site['supportedapp'] = $lic::supported_app();
        }
        if (method_exists($lic, 'is_expired') && $lic::is_expired()) {
            $site['status'] = 'expired';
        }
        if (method_exists($lic, 'is_suspended') && $lic::is_suspended()) {
            $site['status'] = 'suspended';
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
    global $OUTPUT;

    // A dedicated mobile override stored in theme_nit config (a URL). Rarely set;
    // the real site assets below are the normal source.
    $url = static function (string $key): string {
        $v = get_config('theme_nit', $key);
        return ($v === false || $v === null) ? '' : (string) $v;
    };
    // {light, dark} pair from optional config overrides, falling back to a shared
    // asset for both themes (the app accepts one variant used for both).
    $pair = static function (string $light, string $dark, string $fallback = '') use ($url): array {
        $l = $url($light) ?: $fallback;
        $d = $url($dark) ?: $fallback;
        return array_filter(['light' => $l, 'dark' => $d]);
    };

    // The anonymous, public assets provisioning actually sets:
    //   logo / logocompact  -> core_admin site files  (get_logo_url / get_compact_logo_url)
    //   login background    -> theme_nit/loginbackgroundimage  (setting_file_url)
    // These pluginfile URLs are public (no forcelogin) and their path keeps the
    // real file extension, so the app's image/SVG renderer picks correctly.
    $sitelogo = '';
    $compact  = '';
    try {
        if ($OUTPUT && ($u = $OUTPUT->get_logo_url())) {
            $sitelogo = $u->out(false);
        }
        if ($OUTPUT && ($u = $OUTPUT->get_compact_logo_url())) {
            $compact = $u->out(false);
        }
    } catch (\Throwable $e) {
        // Bootstrap renderer edge cases — fall back to no logo rather than 500.
    }
    $loginbg = '';
    try {
        $loginbg = (string) theme_config::load('nit')
            ->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    } catch (\Throwable $e) {
        $loginbg = '';
    }

    $branding = array_filter([
        // Wordmark: dedicated mobile override, else the real site logo (both themes).
        'logo'      => $pair('mobile_logo_light', 'mobile_logo_dark', $sitelogo),
        // Square mark: override, else the compact logo (falls back to the full logo).
        'logomark'  => $pair('mobile_logomark_light', 'mobile_logomark_dark', $compact ?: $sitelogo),
        // Login screen illustration: override, else the login background image.
        'loginhero' => $pair('mobile_loginhero_light', 'mobile_loginhero_dark', $loginbg),
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
    global $CFG;

    $s = static function (string $key): string {
        $v = get_config('theme_nit', $key);
        return ($v === false || $v === null) ? '' : (string) $v;
    };
    $bilingual = static function (string $base) use ($s): array {
        return array_filter(['en' => $s($base . '_en'), 'ar' => $s($base . '_ar')]);
    };

    // Support contact falls back to the academy's core support settings so the
    // app always has a way to reach the academy even before legal URLs are set.
    $supportemail = $s('support_email') ?: trim((string) ($CFG->supportemail ?? ''));
    $supportphone = $s('support_phone') ?: $s('contact_phone');

    // Terms / Privacy default to the built-in chrome-less pages (local_multitopics
    // /legal.php) unless an academy set an explicit URL. So the app always has a
    // working Terms + Privacy link out of the box.
    $legal = static function (string $doc) use ($CFG): array {
        $base = $CFG->wwwroot . '/local/multitopics/legal.php?doc=' . $doc . '&embedded=1';
        return ['en' => $base . '&lang=en', 'ar' => $base . '&lang=ar'];
    };
    $terms   = $bilingual('link_terms')   ?: $legal('terms');
    $privacy = $bilingual('link_privacy') ?: $legal('privacy');
    // Google Play requires a public account-deletion URL. Always available.
    $delete  = $bilingual('link_delete')  ?: $legal('delete');

    return array_filter([
        'about'          => $bilingual('link_about'),
        'privacy'        => $privacy,
        'terms'          => $terms,
        'delete_account' => $delete,
        'faq'            => $bilingual('link_faq'),
        'support_email'  => $supportemail,
        'support_phone' => $supportphone,
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
/**
 * The login background image URL, with the stored filename normalised to a
 * leading-slash filepath first. Older uploads stored the value without the slash,
 * which made setting_file_url() emit ".../<rev><filename>" (no separator) → 404;
 * patch the in-memory setting so the URL is ".../<rev>/<filename>". Works for
 * existing academies with no re-upload and no DB write.
 *
 * @param theme_config $theme
 * @return string the URL, or '' when no login background is set
 */
function theme_nit_loginbg_url($theme): string {
    $fn = (string) ($theme->settings->loginbackgroundimage ?? '');
    if ($fn === '') {
        return '';
    }
    if ($fn[0] !== '/') {
        // Normalise in-memory only; replace_stored_file() stores it correctly now.
        $theme->settings->loginbackgroundimage = '/' . ltrim($fn, '/');
    }
    return (string) $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
}

function theme_nit_login_bg_scss($theme): string {
    $url = theme_nit_loginbg_url($theme);
    if (empty($url)) {
        return '';
    }
    // theme_nit uses a custom two-column login (login_panel.mustache): the left
    // aside (.login-layout-left) is the branded image panel. Put the uploaded
    // photo THERE ONLY — one clean side — with a dark scrim so the panel's white
    // copy stays legible. Do NOT also set it on #page, or the image bleeds across
    // the whole page behind the form.
    return '.login-layout-left, .nit-auth-side .art {'
        . 'background-image: linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.55)), url("' . $url . '") !important;'
        . 'background-size: cover !important;'
        . 'background-position: center center !important;'
        . 'background-repeat: no-repeat !important;'
        . "}\n";
}

// NOTE: the login-page background is injected inline via the modern hook
// \core\hook\output\before_standard_head_html_generation
// (theme_nit\local\hook_callbacks::before_standard_head_html_generation), NOT the
// deprecated before_standard_html_head plugin callback — which, with developer
// debugging on, printed a migration notice mid-<head> and corrupted the page.

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
        // Real learner rating across every approved course review (local_nit_reviews);
        // 0 / 0 when the plugin is absent or nobody has rated yet — the templates
        // hide their rating badge in that case rather than show a made-up figure.
        'rating' => 0,
        'ratingcount' => 0,
    ];
    if ($DB->get_manager()->table_exists('local_nit_reviews')) {
        $agg = $DB->get_record_sql('SELECT AVG(rating) AS avg, COUNT(id) AS cnt FROM {local_nit_reviews}
                                     WHERE teacherid = 0 AND status = ?', [\local_nit_reviews\api::STATUS_APPROVED]);
        if ($agg && (int) $agg->cnt > 0) {
            $stats['rating'] = round((float) $agg->avg, 1);
            $stats['ratingcount'] = (int) $agg->cnt;
        }
    }

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

    // The top bar has to be at least as tall as the logo it carries. The navbar
    // logo height is an admin setting now (Appearance → Logos), and
    // `$navbar-height` is what both the bar and the content offset beneath it are
    // measured from, so it is recomputed here — after _semantic.scss set it, and
    // before Bootstrap and Boost read it. Grow-only. See theme_nit_logo_slots().
    $scss .= '$navbar-height: ' . theme_nit_navbar_height() . "px;\n";

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
    // Text ON the primary fill (button labels). Auto = color-contrast() of the
    // primary; the owner may pin it in the homepage editor (Colours → "Text on
    // buttons") when the automatic black/white pick does not suit the brand.
    $onprimary = trim((string) get_config('theme_nit', 'brandcolour_g1_onprimary'));
    $scss .= '$nit-b-g1-onprimary: ' . (preg_match('/^#[0-9a-fA-F]{6}$/', $onprimary) ? $onprimary : 'auto') . ";\n";

    // An outline button's resting fill is transparent — re-declare each group's
    // outline "background" role as transparent unless that block's Fill switch is
    // on. _brand.scss reads every token by name, so it must exist in all cases; a
    // later `$x: y` wins in SCSS, so this second declaration reaches the sheet.
    foreach (array_keys(theme_nit_brand_groups()) as $gkey) {
        foreach (array_keys(theme_nit_button_outline_variants()) as $variant) {
            if (!theme_nit_button_outline_fill($gkey, $variant)) {
                $scss .= '$nit-b-' . $gkey . '-' . $variant . "bg: transparent;\n";
            }
        }
    }

    // The same tokens once more as ONE nested map — `$nit-b-groups`, group key =>
    // (role => value) — which _brand.scss loops over to publish every group's
    // custom properties, switch class, Bootstrap bridge and checkbox pictures.
    // SCSS cannot build a variable name from a string, so a group is reachable
    // only via a map, emitted here where the group list is known.
    $scss .= '$nit-b-groups: (' . "\n";
    foreach (array_keys(theme_nit_brand_groups()) as $gkey) {
        $entries = [];
        foreach (array_keys(theme_nit_brand_roles()) as $role) {
            $value = theme_nit_brandcolour($gkey . '_' . $role);
            foreach (array_keys(theme_nit_button_outline_variants()) as $variant) {
                if ($role === $variant . 'bg' && !theme_nit_button_outline_fill($gkey, $variant)) {
                    $value = 'transparent';
                }
            }
            $entries[] = '"' . $role . '": ' . $value;
        }
        $scss .= '    "' . $gkey . '": (' . implode(', ', $entries) . "),\n";
    }
    $scss .= ");\n";
    // Which groups are authored light (for the one _root.scss rule that treats a
    // light bar differently from a dark one).
    $light = '';
    foreach (theme_nit_groups_for_scheme('light') as $gkey) {
        $light .= '"' . $gkey . '", ';
    }
    $scss .= '$nit-b-lightgroups: (' . $light . ");\n";

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
/**
 * The per-template structure token sets (Phase 2 "template token layer").
 *
 * Each of the 10 homepage templates carries its own STRUCTURE palette — page and
 * surface backgrounds, text inks, borders, corner radius. The accent is never in
 * here: it always comes from the academy brand (--nit-brand-*). The active
 * template's set is emitted once on :root (see theme_nit_template_tokens_scss),
 * so every NIT app screen (catalog, dashboard, course, checkout, profile, player,
 * auth) reads the same --t-* tokens the homepage uses and reskins as a set when
 * the template changes. Values mirror each template's own hero.html palette so a
 * screen matches its homepage; light/dark flips are honoured (t4 is dark).
 *
 * @return array<string, array<string,string>> template id => token => CSS value
 */
function theme_nit_template_tokens(): array {
    return [
        // id  => [bg, surface, field, ink, muted, muted2, border, border2, line, radius]
        't1'  => ['bg' => '#FFFFFF', 'surface' => '#FAFAF8', 'field' => '#FBFBF9', 'ink' => '#16191D', 'muted' => '#6E7781', 'muted2' => '#8A8A82', 'border' => '#EDEDE9', 'border2' => '#DCDCD7', 'line' => '#F1F1ED', 'radius' => '16px'],
        't2'  => ['bg' => '#FFFFFF', 'surface' => '#F7F5FC', 'field' => '#FBFAFE', 'ink' => '#1B1035', 'muted' => '#5A5170', 'muted2' => '#7A7191', 'border' => '#EAE6F2', 'border2' => '#D9D2E8', 'line' => '#F1EEF8', 'radius' => '18px'],
        't3'  => ['bg' => '#FBF9F4', 'surface' => '#FFFFFF', 'field' => '#FFFFFF', 'ink' => '#0B2545', 'muted' => '#33475F', 'muted2' => '#6A7789', 'border' => '#DED6C6', 'border2' => '#CFC5B2', 'line' => '#ECE6DA', 'radius' => '10px'],
        't4'  => ['bg' => '#07090D', 'surface' => '#12151C', 'field' => '#171B24', 'ink' => '#E8ECF1', 'muted' => '#9AA5B4', 'muted2' => '#778393', 'border' => 'rgba(255,255,255,0.10)', 'border2' => 'rgba(255,255,255,0.16)', 'line' => 'rgba(255,255,255,0.07)', 'radius' => '16px'],
        't5'  => ['bg' => '#FAF3E7', 'surface' => '#FFFDF7', 'field' => '#F3E8D5', 'ink' => '#241A12', 'muted' => '#5C4A3C', 'muted2' => '#8A7666', 'border' => '#D6C6B2', 'border2' => '#C7B49B', 'line' => '#E7DAC6', 'radius' => '12px'],
        't6'  => ['bg' => '#FFFFFF', 'surface' => '#F5F7FB', 'field' => '#FAFBFD', 'ink' => '#10243E', 'muted' => '#5C6480', 'muted2' => '#8E93A8', 'border' => '#E6E9F0', 'border2' => '#D5DAE6', 'line' => '#F0F2F7', 'radius' => '20px'],
        't7'  => ['bg' => '#FFFDF7', 'surface' => '#FFFFFF', 'field' => '#F5F9FE', 'ink' => '#2A2140', 'muted' => '#45618A', 'muted2' => '#6C86A6', 'border' => '#DCE5F0', 'border2' => '#C3D4E9', 'line' => '#EAF0F8', 'radius' => '12px'],
        't8'  => ['bg' => '#FFFDF7', 'surface' => '#FFFFFF', 'field' => '#FBFAF6', 'ink' => '#2A2140', 'muted' => '#5A4E78', 'muted2' => '#8A8A82', 'border' => '#ECE6F2', 'border2' => '#D9CFE6', 'line' => '#F3EEF8', 'radius' => '22px'],
        't9'  => ['bg' => '#F4F1EA', 'surface' => '#FFFFFF', 'field' => '#FAF8F3', 'ink' => '#14121F', 'muted' => '#5E5B54', 'muted2' => '#8F8B80', 'border' => '#DAD5C7', 'border2' => '#C9C3B2', 'line' => '#E9E4D7', 'radius' => '4px'],
        't10' => ['bg' => '#F4F1EA', 'surface' => '#FFFFFF', 'field' => '#FAF8F3', 'ink' => '#14121F', 'muted' => '#5C5872', 'muted2' => '#8B87A3', 'border' => '#E2DDD2', 'border2' => '#332F47', 'line' => '#ECE7DC', 'radius' => '14px'],
    ];
}

/**
 * The active homepage template id (config theme_nit/homepage_template), validated
 * against the token registry and defaulting to t1.
 *
 * @return string
 */
function theme_nit_active_template(): string {
    $tpl = (string) (get_config('theme_nit', 'homepage_template') ?: 't1');
    $sets = theme_nit_template_tokens();
    return isset($sets[$tpl]) ? $tpl : 't1';
}

/**
 * Emit the active template's structure tokens on :root as --t-* custom properties,
 * so every app screen inherits them. Screens keep a T1 literal fallback
 * (var(--t-bg, #FFFFFF)) so they render correctly even before this is compiled in.
 *
 * @return string a :root { … } CSS block (valid SCSS passthrough)
 */
function theme_nit_template_tokens_scss(): string {
    $tpl = theme_nit_active_template();
    $set = theme_nit_template_tokens()[$tpl];
    $lines = ':root{';
    foreach ($set as $key => $val) {
        $lines .= '--t-' . $key . ':' . $val . ';';
    }
    // A scheme hint some screens use to keep on-dark elements consistent.
    $lines .= '--t-scheme:' . ($tpl === 't4' ? 'dark' : 'light') . ';';
    $lines .= '}';

    // Repoint the navbar palette (--nit-navbar*) onto the active template's
    // structure tokens, so the ONE themed navbar (theme_boost/navbar override)
    // follows the template like the rest of the common pages — light on light
    // templates, dark on t4. Emitted here (extra_scss, after _root.scss) and
    // scoped to .nit-navbar so it wins over the gallery palette defaults. The
    // structural T1 shape lives in scss/components/_navbar_t1.scss.
    $nav = '.nit-navbar{'
        . '--nit-navbarbg:var(--t-bg);'
        . '--nit-navbarsurface:var(--t-surface);'
        . '--nit-navbarborder:var(--t-border);'
        . '--nit-navbartext:var(--t-ink);'
        . '--nit-navbaraccent:var(--t-ink);'
        . '--nit-navbaraccenthover:var(--nit-brand-primary);'
        . '--nit-navbariconaccent:var(--t-muted);'
        . '--nit-navbariconaccenthover:var(--nit-brand-primary);'
        . '--nit-navbarpanel:var(--t-bg);'
        . '--nit-navbarpaneltext:var(--t-muted);'
        . '--nit-navbarpanelborder:var(--t-border);'
        . '}';

    return "\n/* NIT active-template structure tokens (" . $tpl . ") */\n" . $lines . "\n" . $nav . "\n";
}

function theme_nit_get_extra_scss($theme) {
    $scss = '';

    // _brand.scss must come before _root.scss: it declares the --nit-brand-*
    // custom properties (active layer + per-group + switch classes) that
    // _root.scss then aliases the legacy --nit-* properties onto.
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_brand.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_root.scss');
    // _corebridge.scss re-points the colours CORE baked from Group 1 (page
    // grounds, form fields, dimmed text, borders, the secondary button) at the
    // ACTIVE brand roles, so a group switch reaches core's own CSS. It must come
    // after _brand.scss for the roles; under Group 1 it resolves to what core
    // already baked, so nothing changes there.
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_corebridge.scss');
    $scss .= file_get_contents(__DIR__ . '/scss/foundation/_fonts.scss');

    // Phase 2: emit the active template's --t-* structure tokens on :root so the
    // app screens reskin with the chosen template (see theme_nit_template_tokens).
    $scss .= theme_nit_template_tokens_scss();

    // Admin-uploaded, per-language custom fonts (edited on the gallery page).
    $scss .= theme_nit_font_scss($theme);

    // Admin-uploaded pictures beside the log-in / sign-up cards (gallery → Auth).
    $scss .= theme_nit_auth_background_scss($theme);

    // Admin/provision-uploaded login + signup page background image.
    $scss .= theme_nit_login_bg_scss($theme);

    // How large to draw the site logo, per place (gallery → Logos).
    $scss .= theme_nit_logo_scss();

    // Navbar title/icon size, weight and hover/active shape — per brand group
    // (gallery → Brand Colors → Navbar).
    $scss .= theme_nit_navbar_style_scss();

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
    $imageareas = ['loginbackgroundimage', 'brandlogo'];
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
 * The coloured (light-background) logo URL.
 *
 * Uploaded on Site administration → Appearance → Logos ("Coloured logo",
 * theme_nit/brandlogo). Drawn where the background is light: the site footer,
 * the log-in card and the registration card. Falls back to the bundled
 * Bassthalk wordmark when nothing is uploaded.
 *
 * @return string absolute URL
 */
function theme_nit_brand_logo_url(): string {
    global $OUTPUT;
    $theme = theme_config::load('nit');
    $url = $theme->setting_file_url('brandlogo', 'brandlogo');
    if ($url) {
        return ($url instanceof moodle_url) ? $url->out(false) : (string) $url;
    }
    return $OUTPUT->image_url('bassthalk_logo', 'theme_nit')->out(false);
}

/**
 * The social networks the site footer can link to, in display order.
 *
 * Each has its own URL setting (theme_nit/footer_<key>); a network shows only
 * when its URL is filled in. The icons are the networks' own marks, so they keep
 * the networks' own colours.
 *
 * @return array<string, array{label:string[], icon:string}> label = [English, Arabic]
 */
function theme_nit_footer_social_networks(): array {
    return [
        'facebook' => ['label' => ['Facebook', 'فيسبوك'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="5" fill="#1877F2"/><path fill="#fff" d="M15.6 12.6h-2.3V20h-3v-7.4H8.6v-2.6h1.7V8.4c0-2.1 1-3.4 3.4-3.4h2v2.6h-1.3c-.9 0-1.1.4-1.1 1.1v1.3h2.4z"/></svg>'],
        'instagram' => ['label' => ['Instagram', 'انستجرام'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#E4405F"/><rect x="5.5" y="5.5" width="13" height="13" rx="4" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="16.2" cy="7.8" r="1" fill="#fff"/></svg>'],
        'tiktok' => ['label' => ['TikTok', 'تيك توك'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#111827"/><path fill="#fff" d="M16.6 8.3a3.6 3.6 0 0 1-2.2-.8v5.6a3.9 3.9 0 1 1-3.4-3.9v2a1.9 1.9 0 1 0 1.4 1.9V4.5h2a3.6 3.6 0 0 0 2.2 2.6z"/></svg>'],
        'youtube' => ['label' => ['YouTube', 'يوتيوب'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect y="3.5" width="24" height="17" rx="5" fill="#FF0000"/><path fill="#fff" d="M10 8.5v7l6-3.5z"/></svg>'],
        'whatsapp' => ['label' => ['WhatsApp', 'واتساب'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#25D366"/><path fill="#fff" d="M12 5.2a6.7 6.7 0 0 0-5.8 10.1L5.3 18.8l3.6-.9A6.7 6.7 0 1 0 12 5.2zm3.9 9.4c-.2.5-1 1-1.4 1-.4.1-.8.1-2.6-.6-2.2-.9-3.6-3.1-3.7-3.3-.1-.1-.9-1.2-.9-2.3s.6-1.6.8-1.8c.2-.2.4-.3.6-.3h.4c.1 0 .3 0 .5.4l.7 1.6c.1.1.1.3 0 .4l-.3.4-.3.3c-.1.1-.2.2-.1.5.1.2.6 1 1.3 1.6.9.8 1.6 1 1.8 1.1.2.1.4.1.5-.1l.7-.8c.2-.2.3-.2.5-.1l1.5.7c.2.1.4.2.4.3.1.2.1.6-.1 1z"/></svg>'],
        'telegram' => ['label' => ['Telegram', 'تليجرام'], 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#229ED9"/><path fill="#fff" d="M17.8 6.6 5.9 11.2c-.8.3-.8.8-.1 1l3 .9 1.2 3.6c.1.4.3.5.6.5.2 0 .4-.1.6-.3l1.5-1.4 3 2.2c.6.3 1 .2 1.1-.5l2-9.6c.2-.9-.3-1.3-1-1z"/></svg>'],
    ];
}

/**
 * The default rows of the footer "pages" column (used until an admin saves the list).
 *
 * `show` is who sees the row: all | guest (signed-out visitors) | user (signed in).
 *
 * @return array<int, array{name:string, url:string, show:string}>
 */
function theme_nit_footer_pages_default(): array {
    return [
        ['name' => theme_nit_ml('Home', 'الرئيسية'), 'url' => '/', 'show' => 'all'],
        ['name' => theme_nit_ml('Help', 'المساعدة'), 'url' => '/user/contactsitesupport.php', 'show' => 'all'],
        ['name' => theme_nit_ml('Create an account', 'انشاء حساب جديد'), 'url' => '/local/academy/register.php', 'show' => 'guest'],
        ['name' => theme_nit_ml('Log in', 'تسجيل الدخول'), 'url' => '/login/index.php', 'show' => 'guest'],
        ['name' => theme_nit_ml('Dashboard', 'لوحة التحكم'), 'url' => '/my/', 'show' => 'user'],
        ['name' => theme_nit_ml('Profile', 'الملف الشخصي'), 'url' => '/user/profile.php', 'show' => 'user'],
    ];
}

/**
 * A text in English and Arabic as one {mlang} string (multilang2 shows the
 * visitor's language wherever it goes through format_string / format_text).
 *
 * @param string $en
 * @param string $ar
 * @return string
 */
function theme_nit_ml(string $en, string $ar): string {
    return '{mlang en}' . $en . '{mlang}{mlang ar}' . $ar . '{mlang}';
}

/**
 * The footer texts the theme used to ship in Arabic only, as [old Arabic => English].
 * {@see theme_nit_footer_translate_defaults()} turns saved copies of them into {mlang} pairs.
 *
 * @return array<string, string>
 */
function theme_nit_footer_old_defaults(): array {
    return [
        'الرئيسية' => 'Home',
        'المساعدة' => 'Help',
        'انشاء حساب جديد' => 'Create an account',
        'تسجيل الدخول' => 'Log in',
        'لوحة التحكم' => 'Dashboard',
        'الملف الشخصي' => 'Profile',
        'تم صنع هذه المنصة بهدف تهيئة الطالب لـ كامل جوانب الثانوية العامة و ما بعدها' =>
            'This platform was made to prepare students for every side of secondary school and beyond',
        'جميع الحقوق محفوظة © {year}' => 'All rights reserved © {year}',
    ];
}

/**
 * Give the saved footer settings English too: page names, description and copyright
 * still holding the old Arabic-only defaults become {mlang} pairs. Texts an admin
 * wrote are left alone.
 */
function theme_nit_footer_translate_defaults(): void {
    $old = theme_nit_footer_old_defaults();
    $pages = get_config('theme_nit', 'footer_pages');
    if ($pages !== false && $pages !== null) {
        $rows = json_decode((string) $pages, true);
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                $name = trim((string) ($row['name'] ?? ''));
                if (isset($old[$name])) {
                    $row['name'] = theme_nit_ml($old[$name], $name);
                }
            }
            unset($row);
            set_config('footer_pages', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'theme_nit');
        }
    }
    foreach (['footer_description', 'footer_copyright'] as $key) {
        $value = trim((string) get_config('theme_nit', $key));
        if (isset($old[$value])) {
            set_config($key, theme_nit_ml($old[$value], $value), 'theme_nit');
        }
    }
}

/**
 * The saved footer pages (theme_nit/footer_pages, JSON), else the defaults.
 *
 * @return array<int, array{name:string, url:string, show:string}>
 */
function theme_nit_footer_pages(): array {
    $raw = get_config('theme_nit', 'footer_pages');
    if ($raw === false || $raw === null) {
        return theme_nit_footer_pages_default();
    }
    $rows = json_decode((string) $raw, true);
    return is_array($rows) ? $rows : [];
}

/**
 * Turn a footer link as typed by the admin into an absolute URL.
 *
 * A link starting with "/" is relative to the site; anything else must already
 * be an http(s) URL.
 *
 * @param string $url the stored link
 * @return string absolute URL ('' when unusable)
 */
function theme_nit_footer_absolute_url(string $url): string {
    global $CFG;
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if ($url[0] === '/') {
        return $CFG->wwwroot . $url;
    }
    return preg_match('~^https?://~i', $url) ? $url : '';
}

/**
 * Everything the site footer template draws (theme_boost/footer override).
 *
 * @return array view-model: logourl, description, copyright, pages[], socials[]
 */
function theme_nit_footer_context(): array {
    $isguest = !isloggedin() || isguestuser();

    $pages = [];
    foreach (theme_nit_footer_pages() as $row) {
        $show = $row['show'] ?? 'all';
        if (($show === 'guest' && !$isguest) || ($show === 'user' && $isguest)) {
            continue;
        }
        $url = theme_nit_footer_absolute_url((string) ($row['url'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($url === '' || $name === '') {
            continue;
        }
        $pages[] = ['name' => format_string($name), 'url' => $url];
    }

    $socials = [];
    foreach (theme_nit_footer_social_networks() as $key => $net) {
        $url = theme_nit_footer_absolute_url((string) get_config('theme_nit', 'footer_' . $key));
        if ($url !== '') {
            $label = strpos(current_language(), 'ar') === 0 ? $net['label'][1] : $net['label'][0];
            $socials[] = ['label' => $label, 'url' => $url, 'icon' => $net['icon']];
        }
    }

    $desc = get_config('theme_nit', 'footer_description');
    if ($desc === false) {
        $desc = theme_nit_ml('This platform was made to prepare students for every side of secondary school and beyond',
            'تم صنع هذه المنصة بهدف تهيئة الطالب لـ كامل جوانب الثانوية العامة و ما بعدها');
    }
    $copy = get_config('theme_nit', 'footer_copyright');
    if ($copy === false) {
        $copy = theme_nit_ml('All rights reserved © {year}', 'جميع الحقوق محفوظة © {year}');
    }
    $copy = str_replace('{year}', date('Y'), (string) $copy);

    return [
        'logourl' => theme_nit_brand_logo_url(),
        'description' => format_string((string) $desc),
        'hasdescription' => trim((string) $desc) !== '',
        'copyright' => format_string($copy),
        'hascopyright' => trim($copy) !== '',
        'pages' => $pages,
        'haspages' => !empty($pages),
        'socials' => $socials,
        'hassocials' => !empty($socials),
    ];
}

/**
 * Who a navbar menu link can be for (the "Who sees it" choices, the first is the default).
 *
 * @return string[]
 */
function theme_nit_navmenu_audiences(): array {
    return ['all', 'student', 'teacher', 'admin'];
}

/**
 * A lang string in English and Arabic as one {mlang} text.
 *
 * @param string $identifier
 * @param string $component
 * @return string ('' when the component is not installed)
 */
function theme_nit_ml_string(string $identifier, string $component): string {
    $sm = get_string_manager();
    if (!$sm->string_exists($identifier, $component)) {
        return '';
    }
    return theme_nit_ml($sm->get_string($identifier, $component, null, 'en'),
        $sm->get_string($identifier, $component, null, 'ar'));
}

/**
 * The rows a navbar menu starts with: today's links, so saving the list
 * unchanged keeps the menu as it was.
 *
 * - gear: Moodle's primary navigation plus the pages our plugins add to it.
 * - user: the avatar menu (Site administration → Navigation → User menu items, + Preferences).
 *
 * @param string $menu 'gear' or 'user'
 * @return array<int, array{name:string, url:string, show:string}>
 */
function theme_nit_navmenu_default(string $menu): array {
    global $CFG;
    $rows = [];
    $add = function(string $name, string $url, string $show = 'all') use (&$rows) {
        if ($name !== '') {
            $rows[] = ['name' => $name, 'url' => $url, 'show' => $show];
        }
    };
    if ($menu === 'gear') {
        $add(theme_nit_ml_string('home', 'core'), '/');
        $add(theme_nit_ml_string('myhome', 'core'), '/my/');
        $add(theme_nit_ml_string('mycourses', 'core'), '/my/courses.php');
        $add(theme_nit_ml_string('mywallet', 'local_nit_finance'), '/local/nit_finance/wallet.php');
        $add(theme_nit_ml_string('myearnings', 'local_nit_finance'), '/local/nit_finance/earnings.php', 'teacher');
        $add(theme_nit_ml_string('availablepackages', 'local_nit_flex'), '/local/nit_flex/packages.php');
        $add(theme_nit_ml_string('studenthub', 'local_nit_lessons'), '/local/nit_lessons/student.php');
        $add(theme_nit_ml_string('mylessons', 'local_nit_lessons'), '/local/nit_lessons/my_lessons.php', 'teacher');
        $add(theme_nit_ml_string('teacherprofile', 'local_nit_lessons'), '/local/academy/profile.php#lessons', 'teacher');
        $add(theme_nit_ml_string('managelessons', 'local_nit_lessons'), '/local/nit_lessons/manage_lessons.php', 'admin');
        $add(theme_nit_ml_string('managepackages', 'local_nit_flex'), '/local/nit_flex/manage_packages.php', 'admin');
        $add(theme_nit_ml_string('managecoupons', 'local_nit_commerce'), '/local/nit_commerce/manage_coupons.php', 'admin');
        $add(theme_nit_ml_string('manageoffers', 'local_nit_commerce'), '/local/nit_commerce/manage_offers.php', 'admin');
        $add(theme_nit_ml_string('managesubscriptions', 'local_nit_subscriptions'),
            '/local/nit_subscriptions/manage_subscriptions.php', 'admin');
        $add(theme_nit_ml_string('administrationsite', 'core'), '/admin/search.php', 'admin');
        return $rows;
    }

    // The avatar menu: Moodle's "User menu items" lines ("name,component|/url" or "Text|/url").
    foreach (preg_split('/\R/u', (string) ($CFG->customusermenuitems ?? '')) as $line) {
        $bits = explode('|', trim($line), 2);
        if (count($bits) !== 2 || trim($bits[0]) === '' || trim($bits[1]) === '') {
            continue;
        }
        $namebits = explode(',', trim($bits[0]), 2);
        $name = count($namebits) === 2 ? theme_nit_ml_string(trim($namebits[0]), trim($namebits[1])) : '';
        $url = trim($bits[1]);
        if (strpos($url, $CFG->wwwroot) === 0) {
            $url = substr($url, strlen($CFG->wwwroot)) ?: '/';
        }
        $add($name !== '' ? $name : trim($bits[0]), $url);
    }
    $add(theme_nit_ml_string('preferences', 'core'), '/user/preferences.php');
    return $rows;
}

/**
 * Add the gear menu's current links (Moodle's primary navigation as the admin
 * sees it) that the default rows miss, so the editor starts from today's menu.
 *
 * @param array $rows default rows
 * @param array $nav primary navigation items (text, url, haschildren, children)
 * @return array
 */
function theme_nit_navmenu_merge_current(array $rows, array $nav): array {
    global $CFG;
    $path = function(string $url) use ($CFG): string {
        if (strpos($url, $CFG->wwwroot) === 0) {
            $url = substr($url, strlen($CFG->wwwroot));
        }
        $p = (string) parse_url($url, PHP_URL_PATH);
        return rtrim($p === '' ? '/' : $p, '/') ?: '/';
    };
    $known = array_map(fn($r) => $path((string) $r['url']), $rows);
    $flat = [];
    foreach ($nav as $item) {
        $item = (array) $item;
        if (!empty($item['haschildren'])) {
            foreach ((array) ($item['children'] ?? []) as $child) {
                $flat[] = (array) $child;
            }
        } else {
            $flat[] = $item;
        }
    }
    foreach ($flat as $item) {
        $url = (string) ($item['url'] ?? '');
        $text = trim(strip_tags((string) ($item['text'] ?? '')));
        if ($url === '' || $text === '' || !empty($item['divider']) || strpos($url, $CFG->wwwroot) !== 0) {
            continue;
        }
        if (!in_array($path($url), $known, true)) {
            $local = substr($url, strlen($CFG->wwwroot)) ?: '/';
            // Management pages stay with admins; the rest is for everyone (the admin can change it).
            $admin = preg_match('~^/(admin/|.*/manage|.*/admin)~', $path($url));
            $rows[] = ['name' => $text, 'url' => $local, 'show' => $admin ? 'admin' : 'all'];
            $known[] = $path($url);
        }
    }
    return $rows;
}

/**
 * Whether a navbar menu link is for this user.
 *
 * @param string $show all | student | teacher | admin
 * @param int $userid
 * @return bool
 */
function theme_nit_navmenu_audience_ok(string $show, int $userid): bool {
    if ($show === 'all') {
        return true;
    }
    $sys = \context_system::instance();
    $isadmin = is_siteadmin($userid) || has_capability('moodle/site:configview', $sys, $userid);
    if ($show === 'admin') {
        return $isadmin;
    }
    $isteacher = class_exists('\local_academy\teacher_manager') && \local_academy\teacher_manager::is_teacher($userid);
    if ($show === 'teacher') {
        return $isteacher;
    }
    return $show === 'student' && !$isteacher && !$isadmin;
}

/**
 * The links an admin set for a navbar menu that the current user sees.
 *
 * @param string $menu 'gear' or 'user'
 * @return array<int, array{name:string, url:string}>|null null while the list was
 *         never saved (the menu then keeps Moodle's own links)
 */
function theme_nit_navmenu_links(string $menu): ?array {
    global $USER;
    $raw = get_config('theme_nit', 'navmenu_' . $menu);
    if ($raw === false || $raw === null || $raw === '') {
        return null;
    }
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows) || !$rows) {
        return null; // An emptied list keeps Moodle's links: a menu is never left blank.
    }
    $links = [];
    foreach ($rows as $row) {
        $url = theme_nit_footer_absolute_url((string) ($row['url'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($url === '' || $name === '' || !isloggedin() || isguestuser()
                || !theme_nit_navmenu_audience_ok((string) ($row['show'] ?? 'all'), (int) $USER->id)) {
            continue;
        }
        $links[] = ['name' => format_string($name), 'url' => $url];
    }
    return $links;
}

/**
 * The avatar menu items with the admin's links in place of Moodle's: the
 * language submenu, "Switch role" / "Return to my role" and "Log out" are kept,
 * after a divider.
 *
 * @param array $links from theme_nit_navmenu_links('user')
 * @param array $items Moodle's items (primary::get_user_menu()['items'])
 * @return array items for core/user_action_menu_items
 */
function theme_nit_user_menu_items(array $links, array $items): array {
    $out = [];
    foreach ($links as $link) {
        $out[] = (object) ['itemtype' => 'link', 'link' => true, 'divider' => false,
            'url' => $link['url'], 'title' => $link['name'], 'titleidentifier' => ''];
    }
    $kept = [];
    foreach ($items as $item) {
        $item = (object) $item;
        $url = isset($item->url) ? (string) ($item->url instanceof moodle_url ? $item->url->out(false) : $item->url) : '';
        if (($item->itemtype ?? '') === 'submenu-link'
                || preg_match('~/(login/logout|course/switchrole|course/loginas)\.php~', $url)) {
            $item->divider = false;
            $kept[] = $item;
        }
    }
    if ($out && $kept) {
        $out[count($out) - 1]->divider = true;
    }
    return array_merge($out, $kept);
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

/**
 * The academy's own pages an owner can link to from the navbar / footer
 * (homepage editor → "Brand & navbar" / "Footer"). Real routes only — anchors
 * point at homepage sections the front page tags with id="nit-<section>".
 *
 * @return array<int, array{key:string,label:array{en:string,ar:string},url:string}>
 */
function theme_nit_site_pages(): array {
    $pages = [
        ['key' => 'home',          'label' => ['en' => 'Home',            'ar' => 'الرئيسية'],        'url' => '/'],
        ['key' => 'catalog',       'label' => ['en' => 'All courses',     'ar' => 'كل الدورات'],      'url' => '/local/nit_category/index.php'],
        ['key' => 'about',         'label' => ['en' => 'About',           'ar' => 'من نحن'],          'url' => '/#nit-about'],
        ['key' => 'subscriptions', 'label' => ['en' => 'Plans',           'ar' => 'الخطط'],           'url' => '/#nit-subscriptions'],
        ['key' => 'coupons',       'label' => ['en' => 'Offers',          'ar' => 'العروض'],          'url' => '/#nit-coupons'],
        ['key' => 'gallery',       'label' => ['en' => 'Gallery',         'ar' => 'المعرض'],          'url' => '/#nit-gallery'],
        ['key' => 'testimonials',  'label' => ['en' => 'Testimonials',    'ar' => 'آراء المتعلمين'],  'url' => '/#nit-testimonials'],
        ['key' => 'faq',           'label' => ['en' => 'FAQ',             'ar' => 'الأسئلة الشائعة'], 'url' => '/#nit-faq'],
        ['key' => 'contact',       'label' => ['en' => 'Contact',         'ar' => 'تواصل معنا'],      'url' => '/#nit-contact'],
        ['key' => 'certificates',  'label' => ['en' => 'Certificates',    'ar' => 'الشهادات'],        'url' => '/local/academy/certificate.php'],
        ['key' => 'dashboard',     'label' => ['en' => 'Dashboard',       'ar' => 'لوحة التحكم'],     'url' => '/my/'],
    ];
    // Account / auth destinations (sign in, sign up, profile, dashboard) are NOT
    // offered: the navbar's user menu and the auth buttons already own them.
    // Unlicensed features never become links.
    if (class_exists('\theme_nit\local\home_picks')) {
        $pages = array_values(array_filter($pages, static function ($p) {
            return !in_array($p['key'], ['subscriptions', 'coupons'], true)
                || \theme_nit\local\home_picks::licensed($p['key']);
        }));
    }
    return $pages;
}

/**
 * The owner's picked navbar pages → Moodle's own custom menu ($CFG->custommenuitems),
 * one line per language so the label follows the UI language. Keys are page
 * keys from theme_nit_site_pages(); unknown keys are dropped. An empty pick
 * clears the custom menu.
 *
 * @param string[] $keys
 */
function theme_nit_save_nav_pages(array $keys): void {
    $pages = [];
    foreach (theme_nit_site_pages() as $p) {
        $pages[$p['key']] = $p;
    }
    $lines = [];
    $picked = [];
    foreach ($keys as $k) {
        if (isset($pages[$k])) {
            $picked[] = $k;
            $lines[] = $pages[$k]['label']['en'] . '|' . $pages[$k]['url'] . '||en';
            $lines[] = $pages[$k]['label']['ar'] . '|' . $pages[$k]['url'] . '||ar';
        }
    }
    set_config('nav_pages', json_encode(array_values(array_unique($picked))), 'theme_nit');
    set_config('custommenuitems', implode("\n", $lines));
    // With picked pages the navbar shows THOSE only — theme config.php sets
    // $THEME->removedprimarynavitems from nav_pages (core's supported switch).
    theme_reset_all_caches();
    purge_all_caches();
}

/**
 * The footer's picked pages as JSON [{label,url}] in the current language, or
 * "null" when the owner has not picked any (template defaults stay).
 *
 * @return string JSON
 */
function theme_nit_footer_links_json(): string {
    $keys = json_decode((string) get_config('theme_nit', 'footer_links'), true);
    if (!is_array($keys) || !$keys) {
        return 'null';
    }
    $lang = (strpos(current_language(), 'ar') === 0) ? 'ar' : 'en';
    $pages = [];
    foreach (theme_nit_site_pages() as $p) {
        $pages[$p['key']] = $p;
    }
    $out = [];
    foreach ($keys as $k) {
        if (isset($pages[$k])) {
            $out[] = ['label' => $pages[$k]['label'][$lang], 'url' => $pages[$k]['url']];
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null';
}

// ─────────────────────────────────────────────────────────────────────────
// Design Gallery / Brand Colors suite — ported from EAAC theme_nit.
// Navbar / category / mode / logo / fonts / auth branding subsystems that the
// gallery.php admin page and the 17-group Brand Colors system depend on.
// ─────────────────────────────────────────────────────────────────────────
define('THEME_NIT_NAVBAR_BLUR', 'blur(18px) saturate(160%)');
// The cookie the light/dark switch stores the visitor's mode in (read by
// theme_nit_current_mode()). A cookie, not a preference, so it works logged-out.
if (!defined('THEME_NIT_MODE_COOKIE')) {
    define('THEME_NIT_MODE_COOKIE', 'nit_mode');
}

/**
 * The named sections the roles are grouped into, in display order.
 *
 * A group is 59 roles now, which is more than anybody can scan as one flat
 * grid. The section is purely an editing aid — it changes no CSS and no export
 * shape — but it is declared here rather than in the template because the ORDER
 * of theme_nit_brand_roles() is what the gallery renders, and the two have to
 * agree. `key` is also the anchor the gallery's in-group jump links use.
 *
 * @return array<string, string> section key => display label
 */
function theme_nit_brand_role_sections(): array {
    return [
        'brand'   => 'Brand',
        'navbar'  => 'Navbar',
        'footer'  => 'Footer',
        'surface' => 'Surfaces & text',
        'status'  => 'Status',
        'bassthalk' => 'Bassthalk',
    ];
}

/**
 * The named blocks a section's cards are grouped under, in display order.
 *
 * One level below the section. The Navbar section alone is twenty cards — three
 * colours, a size, a weight and two shapes for each of the things the bar draws
 * — and as one flat grid the only way to find "the icon hover colour" was to
 * read every card. Grouped under the four things the bar is MADE of, it is two
 * glances: which part, then which state.
 *
 * The Brand section is broken up the same way, around the three kinds of button
 * plus the accents: a button's six cards (background / text / border, and the
 * same three for hover) are one block, so "what does an outline button look
 * like" is a heading rather than a search.
 *
 * A role, a typography card or a shape card names its block with `sub`. Anything
 * without one falls into a single unlabelled block at the top of its section, so
 * the sections that have not been broken up render exactly as before.
 *
 * The keys are flat across every section, so a new one must not collide with a
 * block name already in use elsewhere in the list.
 *
 * @return array<string, string> block key => display label
 */
function theme_nit_brand_role_subsections(): array {
    return [
        // Brand. Two outline blocks, because the site draws two different
        // outline buttons and always has: the BRAND one carries the public
        // pages (the hero CTAs, the category filter pills, "Course details" on
        // every course card) and the NEUTRAL one is back-office chrome (the
        // Cancels and Resets on the payments, refunds and email screens). One
        // shared block would have had to repaint one of them: either a Cancel
        // button picks up the brand ring and stops reading differently from the
        // Save beside it, or every public CTA goes grey.
        'core'              => 'Core',
        'btnprimary'        => 'Main button',
        'btnsecondary'      => 'Secondary button',
        'btnoutlineprimary' => 'Outline button (brand)',
        'btnoutline'        => 'Outline button (neutral)',
        'check'             => 'Checkbox & switch',
        'link'              => 'Links and words',
        // Navbar.
        'background' => 'Background',
        'title'      => 'Titles',
        'icon'       => 'Icons',
        'login'      => 'Login',
        'scroll'     => 'On scroll',
        // Bassthalk.
        'bthnavbar'  => 'Navbar extras',
        'bthauth'    => 'Log-in & registration',
        'bthhome'    => 'Home — hero',
        'bthhow'     => 'Home — how it works',
        'bthselected' => 'Home — selected courses',
        'bthteach'   => 'Home — teachers',
        'bthlessons' => 'Home — suggested lessons',
        'bthcourse'  => 'Course details page',
        'bthteacherpage' => 'Teacher page',
        'bthparent'  => 'Parent dashboard',
    ];
}

/**
 * The two outline buttons, keyed by the prefix their six roles share.
 *
 * The prefix is load-bearing: it is the role-key prefix (`btnoutlineborder`,
 * `btnoutlineprimaryborder`, …), the editor block key, and the stem of the Fill
 * switch's config name. Naming the variants once here is what keeps the roles,
 * the switch, the `transparent` override in theme_nit_get_pre_scss() and the
 * editor cards from each carrying their own copy of the list.
 *
 * Order is display order, and the brand one leads because it is the one an
 * administrator means by "the outline button" — it is what the public pages are
 * built from.
 *
 * @return array<string, string> role-key prefix => the CSS class it drives
 */
function theme_nit_button_outline_variants(): array {
    return [
        'btnoutlineprimary' => '.btn-outline-primary',
        'btnoutline'        => '.btn-outline-secondary',
    ];
}

/**
 * Whether one of a group's outline buttons paints its Background role at rest.
 *
 * Off by default, and that default is the whole reason the switch exists: an
 * outline button's resting fill is transparent, so it borrows the colour of
 * whatever it sits on — the page ground on a course page, a card surface inside
 * a filter panel. No single hex is right in both places, so the role is only
 * consumed once an admin has said they want a filled button; until then
 * theme_nit_get_pre_scss() re-emits the token as `transparent` and the button
 * looks exactly as it always did.
 *
 * A hue group with no row of its own follows its parent's switch
 * (theme_nit_group_setting()).
 *
 * @param string $group brand group key (g1..g17)
 * @param string $variant an outline variant key (theme_nit_button_outline_variants())
 * @return bool true if that variant's Background role should be painted
 */
function theme_nit_button_outline_fill(string $group, string $variant): bool {
    $value = theme_nit_group_setting($group, $variant . 'fill_');
    // Bassthalk (g18) draws its neutral outline button ("إنشاء حساب ولي أمر" on
    // the log-in card) filled white on the grey card, so the switch starts ON
    // there until an admin un-ticks it on the gallery.
    if (!is_string($value) && $group === 'g18' && $variant === 'btnoutline') {
        return true;
    }
    return $value === '1';
}

/**
 * The SHAPE treatments — the navbar decisions that are not a colour.
 *
 * A title (and, since they answer the cursor the same way, an icon) can mark a
 * state with an underline, with a filled square behind it, with both, or with
 * neither. They are INDEPENDENT switches rather than named looks: an "All" entry
 * beside them was only ever every switch ticked together, and it made
 * combinations unsayable while making "the same thing" sayable twice.
 *
 * There is no BOLD. There was, and it could not draw: the site's fonts are
 * single-file admin uploads declared at `font-weight: normal`
 * (theme_nit_font_scss()), so the browser synthesises every heavier weight — and
 * synthesis is a switch, not a ramp. It flips at 600 and does the same thing at
 * 700, 800 and 900, which measured pixel-for-pixel identical on the live bar
 * against a resting weight of 600. An icon font is worse still: one weight per
 * face, so five of the six controls in the cluster could not have moved at all.
 * A tick that does nothing is worse than a tick that is not offered.
 *
 * A choice is a SET, stored per group as `theme_nit/navbarshape_<gkey>_<state>`
 * holding the ticked keys comma-separated, and consumed as two CSS custom
 * properties per state (see theme_nit_navbar_style_scss()). An empty string is a
 * real answer there — "mark this state with no shape at all" — and is why the
 * config row is written rather than removed when nothing is ticked.
 *
 * @return array<string, array{label:string}> treatment key => meta
 */
function theme_nit_navbar_shape_treatments(): array {
    return [
        'underline' => ['label' => 'Under line'],
        'square'    => ['label' => 'Square background'],
    ];
}

/**
 * The six states that carry a shape, and what each one is drawn with.
 *
 * `subject` and `phase` say which set of CSS custom properties the state feeds
 * (`--nit-nav<subject>-<phase>-*`) and, for the bold treatment, which resting
 * weight it steps up from. `underline` / `bg` are the colours the two painting
 * treatments use:
 *
 *   * A TITLE and the LOG-IN link each have two dedicated "style color" roles,
 *     so an admin picks the shape and the colour it is drawn in side by side.
 *     They are TEXT: a pill behind a word has to be a colour of its own, because
 *     the word's own ink painted behind the word leaves nothing to read.
 *   * An ICON has none, and does not need them: the state already has a colour,
 *     and a glyph's underline wants to be exactly that colour. Its square is the
 *     same colour at a 14% tint (`--nit-navbaricon*bg`, scss/foundation/
 *     _root.scss) rather than the flat fill — a solid accent behind a 22px glyph
 *     is a badge, not a hover, and it takes the glyph's own contrast with it.
 *
 * The defaults are the look the bar already had: a title's hover paints a soft
 * pad and its current page is underlined; an icon paints its pad in both states.
 * The log-in link starts on the title's pair, which is the closest thing to the
 * plain link it was before it had shapes at all.
 *
 * @return array<string, array{subject:string, phase:string, sub:string,
 *         label:string, short:string, underline:string, bg:string, default:string[]}>
 */
function theme_nit_navbar_shape_states(): array {
    return [
        'titlehover'  => [
            'subject' => 'title', 'phase' => 'hover', 'sub' => 'title',
            'label' => 'Navbar title hover style shape', 'short' => 'Title hover style shape',
            'underline' => 'var(--nit-brand-navbartitlehoverstylecolor)',
            'bg' => 'var(--nit-brand-navbartitlehoverstylecolor)',
            'default' => ['square'],
        ],
        'titleactive' => [
            'subject' => 'title', 'phase' => 'active', 'sub' => 'title',
            'label' => 'Navbar title active style shape', 'short' => 'Title active style shape',
            'underline' => 'var(--nit-brand-navbartitleactivestylecolor)',
            'bg' => 'var(--nit-brand-navbartitleactivestylecolor)',
            'default' => ['underline'],
        ],
        'iconhover'   => [
            'subject' => 'icon', 'phase' => 'hover', 'sub' => 'icon',
            'label' => 'Navbar icon hover style shape', 'short' => 'Icon hover style shape',
            'underline' => 'var(--nit-brand-navbariconhovercolor)',
            'bg' => 'var(--nit-navbariconbg)',
            'default' => ['square'],
        ],
        'iconactive'  => [
            'subject' => 'icon', 'phase' => 'active', 'sub' => 'icon',
            'label' => 'Navbar icon active style shape', 'short' => 'Icon active style shape',
            'underline' => 'var(--nit-brand-navbariconactivecolor)',
            'bg' => 'var(--nit-navbariconactivebg)',
            'default' => ['square'],
        ],
        'loginhover'  => [
            'subject' => 'login', 'phase' => 'hover', 'sub' => 'login',
            'label' => 'Navbar login hover style shape', 'short' => 'Login hover style shape',
            'underline' => 'var(--nit-brand-navbarloginhoverstylecolor)',
            'bg' => 'var(--nit-brand-navbarloginhoverstylecolor)',
            'default' => ['square'],
        ],
        'loginactive' => [
            'subject' => 'login', 'phase' => 'active', 'sub' => 'login',
            'label' => 'Navbar login active style shape', 'short' => 'Login active style shape',
            'underline' => 'var(--nit-brand-navbarloginactivestylecolor)',
            'bg' => 'var(--nit-brand-navbarloginactivestylecolor)',
            'default' => ['underline'],
        ],
    ];
}

/**
 * The treatments an administrator ticked for one group / state.
 *
 * Three answers are distinguishable and all three are meant: never saved (the
 * state's default), saved empty (no shape — a deliberate choice), and a set.
 * A hue group that was never saved reads its parent's row first
 * (theme_nit_group_setting()), so Emerald-light starts with Daylight's shapes.
 *
 * @param string $group group key (g1..g17)
 * @param string $state state key (see theme_nit_navbar_shape_states())
 * @return string[] keys of theme_nit_navbar_shape_treatments(), possibly empty
 */
function theme_nit_navbar_shape(string $group, string $state): array {
    $states = theme_nit_navbar_shape_states();
    $valid = array_keys(theme_nit_navbar_shape_treatments());
    $value = theme_nit_group_setting($group, 'navbarshape_', '_' . $state);

    if (!is_string($value)) {
        return $states[$state]['default'] ?? [];
    }
    // Sites that chose the retired "All" entry keep what they picked: it was
    // every treatment ticked together.
    if ($value === 'all') {
        return $valid;
    }
    // Intersect against the catalogue, in ITS order — so the emitted CSS does
    // not depend on the order the checkboxes happened to post in, and a
    // treatment that no longer exists (a `bold` saved before it was retired) is
    // dropped on read rather than lingering in the stylesheet.
    return array_values(array_intersect($valid, array_map('trim', explode(',', $value))));
}

/**
 * The three things drawn on the bar, and how big / how heavy each is set.
 *
 * The same three subjects the navbar COLOUR roles are built around (titles,
 * icons, the log-in link) each get a size and a weight, per Brand-Colors group,
 * stored as `theme_nit/navbarsize_<gkey>_<subject>` and
 * `theme_nit/navbarweight_<gkey>_<subject>`. Per group and not site-wide because
 * that is what a group IS here — a complete description of how the site looks in
 * that palette — and because the shapes above already work that way; one of the
 * two being global would be a trap, since an admin editing it under "Group 3"
 * would silently move Group 1 as well.
 *
 * `size` and `weight` are the values the stylesheet already used, so an untouched
 * site renders exactly as it did: 16px/600 titles, 22px glyphs, a 16px/600 log-in
 * link. `min`/`max` are clamps, not suggestions — a 60px title would break the
 * bar out of its own height.
 *
 * Two notes on what "icon size" reaches. It is the GLYPH, not the button: the
 * 42px pointer target grows only if the glyph outgrows it
 * (theme_nit_navbar_icon_box()), so making icons smaller never makes them harder
 * to hit. And "icon weight" is only visible on the parts of the cluster that are
 * type rather than a glyph — the language code (EN / AR) — because Font Awesome
 * ships one weight per face.
 *
 * @return array<string, array{label:string, usage:string[], size:int, weight:int, min:int, max:int}>
 */
function theme_nit_navbar_type_subjects(): array {
    return [
        'title' => [
            'sub'    => 'title',
            'label'  => 'Navbar title text',
            'short'  => 'Title text',
            'usage'  => ['size and weight of the site links across the bar'],
            'size'   => 16, 'weight' => 600, 'min' => 12, 'max' => 28,
        ],
        'icon'  => [
            'sub'    => 'icon',
            'label'  => 'Navbar icon size',
            'short'  => 'Icon size',
            'usage'  => ['size of the navbar glyphs', 'the button grows only if the glyph outgrows it', 'weight reaches the language code (EN / AR), not the glyphs'],
            'size'   => 22, 'weight' => 500, 'min' => 14, 'max' => 36,
        ],
        'login' => [
            'sub'    => 'login',
            'label'  => 'Navbar login text',
            'short'  => 'Login text',
            'usage'  => ['size and weight of the "Log in" link'],
            'size'   => 16, 'weight' => 600, 'min' => 12, 'max' => 28,
        ],
    ];
}

/**
 * The font weights offered, keyed by the CSS number.
 *
 * A fixed ladder rather than a free number: these are the weights a variable
 * face actually has stops for, and the names are what an administrator thinks in.
 *
 * @return array<int, string> weight => label
 */
function theme_nit_navbar_weights(): array {
    return [
        300 => 'Light (300)',
        400 => 'Regular (400)',
        500 => 'Medium (500)',
        600 => 'Semi-bold (600)',
        700 => 'Bold (700)',
        800 => 'Extra bold (800)',
    ];
}

/**
 * The size an administrator set for one group / subject, in px.
 *
 * Clamped to the subject's own range, so a saved value that predates a narrower
 * range (or a hand-edited config row) still renders something sane. A hue group
 * with no row of its own reads its parent's (theme_nit_group_setting()).
 *
 * @param string $group group key (g1..g17)
 * @param string $subject subject key (see theme_nit_navbar_type_subjects())
 * @return int pixels
 */
function theme_nit_navbar_type_size(string $group, string $subject): int {
    $meta = theme_nit_navbar_type_subjects()[$subject] ?? null;
    if ($meta === null) {
        return 16;
    }
    $value = theme_nit_group_setting($group, 'navbarsize_', '_' . $subject);
    if (!is_numeric($value)) {
        return $meta['size'];
    }
    return min($meta['max'], max($meta['min'], (int) $value));
}

/**
 * The font weight an administrator set for one group / subject — or, for a hue
 * group that was never saved, for its parent (theme_nit_group_setting()).
 *
 * @param string $group group key (g1..g17)
 * @param string $subject subject key (see theme_nit_navbar_type_subjects())
 * @return int a key of theme_nit_navbar_weights()
 */
function theme_nit_navbar_type_weight(string $group, string $subject): int {
    $meta = theme_nit_navbar_type_subjects()[$subject] ?? null;
    if ($meta === null) {
        return 400;
    }
    $value = theme_nit_group_setting($group, 'navbarweight_', '_' . $subject);
    return array_key_exists((int) $value, theme_nit_navbar_weights()) ? (int) $value : $meta['weight'];
}

/**
 * How wide the square icon button is, for a given glyph size.
 *
 * The button is 42px and stays 42px until the glyph would not fit in it — a
 * pointer target that shrinks with the glyph would put the smallest setting well
 * under what a finger can hit, which is the one thing an admin choosing "small
 * icons" is not asking for.
 *
 * @param int $glyph glyph size in px
 * @return int the button's side, in px
 */
function theme_nit_navbar_icon_box(int $glyph): int {
    return max(42, $glyph + 20);
}

/**
 * Whether the bar is see-through in this group, and by how much.
 *
 * Two knobs on one decision. `theme_nit/navbarglass_<gkey>` is the switch —
 * frost the bar or paint it solid — and `theme_nit/navbartransparency_<gkey>` is
 * how far, as a TRANSPARENCY percentage: 0 is a solid bar and 60 lets most of
 * the page through. Transparency and not opacity because that is the word the
 * control is labelled with, and a number that goes UP as the bar gets more
 * see-through is the one an administrator predicts correctly; the CSS wants the
 * complement, so theme_nit_navbar_style_scss() does that one subtraction.
 *
 * `off` is not a second code path in the stylesheet: it is the same mix at 100%
 * opacity with the blur set to `none`, which is a solid bar.
 *
 * The cap is 90 rather than 100 because a bar you can see straight through is
 * not a bar — the logo and the links would sit on the scrolling page with
 * nothing behind them, which no administrator reaching for this control is
 * asking for.
 *
 * Both rows fall back to the parent group's for a hue group that has never been
 * saved (theme_nit_group_setting()).
 *
 * @param string $group group key (g1..g17)
 * @return array{on:bool, transparency:int} the switch, and 0-90
 */
function theme_nit_navbar_glass(string $group): array {
    $on = theme_nit_group_setting($group, 'navbarglass_');
    $value = theme_nit_group_setting($group, 'navbartransparency_');
    return [
        'on' => !is_string($on) || $on !== '0',
        'transparency' => is_numeric($value) ? min(90, max(0, (int) $value)) : 28,
    ];
}

/**
 * The group the bar switches to once the page scrolls.
 *
 * A bar over the top of a page and a bar floating over scrolled content are two
 * different design problems — the first can be part of the hero, the second has
 * to separate itself from whatever is passing underneath. So a group can name a
 * SECOND group for the bar to wear from the moment the page moves, and the
 * switch carries the whole navbar style with it: colours, sizes, shapes and how
 * see-through the bar is.
 *
 * It works by putting the other group's class on the BAR (not on <html>, which
 * would re-skin the page too) — see the `.nit-navbar` selector in
 * scss/foundation/_root.scss for why every navbar alias is declared there as
 * well, and theme_nit\output\core_renderer::navbar_scroll_class() for the class
 * the template hands to the browser.
 *
 * Answering with the group itself — the default — means "do not change", and
 * nothing is emitted or scripted for it at all.
 *
 * A hue group with no answer of its own follows its parent — translated, not
 * copied: if Daylight scrolls into Graphite, an Emerald-light bar scrolls into
 * Emerald-dark, not into Graphite; if Daylight scrolls into Group 2, so does
 * Emerald; and "do not change" stays "do not change".
 *
 * @param string $group group key (g1..g17)
 * @return string a group key; equal to $group when the bar does not change
 */
function theme_nit_navbar_scroll_group(string $group): string {
    $value = get_config('theme_nit', 'navbarscrollgroup_' . $group);
    if (!is_string($value) && ($parent = theme_nit_brand_group_parent($group)) !== null) {
        $value = get_config('theme_nit', 'navbarscrollgroup_' . $parent);
        if ($value === $parent) {
            $value = $group;
        } else if ($value === theme_nit_brand_group_partner($parent)) {
            $value = theme_nit_brand_group_partner($group);
        }
    }
    return (is_string($value) && array_key_exists($value, theme_nit_brand_groups()))
        ? $value : $group;
}

/**
 * The category accents: one light + dark pair of groups per hue.
 *
 * Every main category on the catalogue carries an icon drawn in its own colour,
 * and a category page should wear that colour the way the site wears azure —
 * not as a re-coloured logo but as the whole palette: buttons, links, hover
 * states, the navbar underline, the footer. So each hue gets a pair of groups
 * that are Daylight (Group 4) and Graphite (Group 5) with the azure swapped out
 * and NOTHING else changed: the same neutrals, the same grounds, the same text
 * inks, the same status colours. Assign the pair on the gallery "Change style"
 * tab (Category styles → light / dark) and the category is branded.
 *
 * `rungs` is the azure ramp of the two parent groups rebuilt in this hue. Each
 * rung keeps the LIGHTNESS of the azure rung it replaces (OKLCH L, the scale
 * contrast is measured on), takes the hue of the icon, and carries the smaller
 * of the azure rung's chroma and the icon's — so a vivid icon does not become a
 * neon palette, a calm one is not pushed past what it is, and every text /
 * background pair lands within a few percent of the contrast the parent group
 * was tuned to (white on A600 is 5.2–6.0 across the six; on the parent, 5.6).
 *
 *   A200 A300 A400          the light tints (dark-mode links, hover text, checks)
 *   A600 A700 A800          the deep inks (light-mode buttons, links, hovers)
 *   H85 H90                 A600 mixed 85 / 90 % with white — the primary button's
 *                           hover fill and border, exactly as Daylight seeds them
 *   V Vh Vd                 the vivid fill Graphite's buttons are painted in, its
 *                           hover step, and the deeper tone under the navbar's
 *                           hover / active shape
 *
 * `icon` is the dominant fill measured off the category icon the hue was taken
 * from (local_nit_category `categoryicon`), kept so the next person can tell
 * where a hue came from and re-measure it if the icon is redrawn. The rung
 * hexes are literals on purpose — the theme has no colour maths at runtime, and
 * a palette that is computed on every request is a palette nobody can read.
 *
 * Keys are assigned in pairs from g6 upward, so a new hue is one more entry
 * here and nothing else: the groups list, the switch classes, the defaults, the
 * SCSS maps and the gallery editor all read this table.
 *
 * @return array<string, array{name:string, icon:string, light:string, dark:string,
 *         rungs: array<string, string>}> keyed by hue slug, in display order
 */
function theme_nit_brand_hues(): array {
    return [
        // Accounting & Finance.
        'emerald' => [
            'name' => 'Emerald', 'icon' => '#4fa92e', 'light' => 'g6', 'dark' => 'g7',
            'rungs' => [
                'A200' => '#c6e1be', 'A300' => '#a0cc94', 'A400' => '#7db76c',
                'A600' => '#347c18', 'A700' => '#216300', 'A800' => '#174a00',
                'H85' => '#52903b', 'H90' => '#48892f',
                'V' => '#4ca62a', 'Vh' => '#459f21', 'Vd' => '#297600',
            ],
        ],
        // Engineering & Industry.
        'amber' => [
            'name' => 'Amber', 'icon' => '#fe7e02', 'light' => 'g8', 'dark' => 'g9',
            'rungs' => [
                'A200' => '#f6ceb7', 'A300' => '#ecae88', 'A400' => '#e08e5a',
                'A600' => '#a34e00', 'A700' => '#813c00', 'A800' => '#612c00',
                'H85' => '#b16926', 'H90' => '#ac6019',
                'V' => '#d96a00', 'Vh' => '#cf6500', 'Vd' => '#984900',
            ],
        ],
        // Hospital Management.
        'teal' => [
            'name' => 'Teal', 'icon' => '#02a0b2', 'light' => 'g10', 'dark' => 'g11',
            'rungs' => [
                'A200' => '#aee2eb', 'A300' => '#72cedc', 'A400' => '#36b8ca',
                'A600' => '#007785', 'A700' => '#005d68', 'A800' => '#00464e',
                'H85' => '#268b97', 'H90' => '#198591',
                'V' => '#01a0b2', 'Vh' => '#0098a9', 'Vd' => '#006f7c',
            ],
        ],
        // International Trade / Logistics.
        'sapphire' => [
            'name' => 'Sapphire', 'icon' => '#056dca', 'light' => 'g12', 'dark' => 'g13',
            'rungs' => [
                'A200' => '#bfdafc', 'A300' => '#95c1f6', 'A400' => '#6da8ee',
                'A600' => '#1869bc', 'A700' => '#00529b', 'A800' => '#003c76',
                'H85' => '#3b80c6', 'H90' => '#2f78c3',
                'V' => '#378fef', 'Vh' => '#2f88e7', 'Vd' => '#0062b7',
            ],
        ],
        // Languages.
        'rose' => [
            'name' => 'Rose', 'icon' => '#ee3071', 'light' => 'g14', 'dark' => 'g15',
            'rungs' => [
                'A200' => '#f9c9d1', 'A300' => '#f0a5b3', 'A400' => '#e48398',
                'A600' => '#ac3b5a', 'A700' => '#8d2545', 'A800' => '#6b1932',
                'H85' => '#b85873', 'H90' => '#b44f6b',
                'V' => '#f43776', 'Vh' => '#ec2e6f', 'Vd' => '#b5004f',
            ],
        ],
        // Law.
        'amethyst' => [
            'name' => 'Amethyst', 'icon' => '#613dab', 'light' => 'g16', 'dark' => 'g17',
            'rungs' => [
                'A200' => '#d9d1f9', 'A300' => '#c0b3f2', 'A400' => '#a995e9',
                'A600' => '#7053b6', 'A700' => '#583d96', 'A800' => '#422c73',
                'H85' => '#856dc1', 'H90' => '#7e64bd',
                'V' => '#9575e8', 'Vh' => '#8e6ee1', 'Vd' => '#6a48b6',
            ],
        ],
    ];
}

/**
 * The group a hue group was built from: Group 4 for a light one, Group 5 for a
 * dark one, null for the five originals.
 *
 * Read by the per-group NAVBAR settings (shape, size, weight, glass, outline
 * fill — theme_nit_group_setting()) so a hue group that has never been saved
 * looks like its parent in every way and not only in colour: the same title
 * size, the same hover treatment, the same frosting. Its colours do not go
 * through here — they are seeded once from the parent (theme_nit_brand_hue_templates())
 * and are its own from then on, because a parent role that later turns azure
 * must not leak into a group whose whole point is not being azure.
 *
 * @param string $group group key (g1..g17)
 * @return string|null 'g4' | 'g5' | null
 */
function theme_nit_brand_group_parent(string $group): ?string {
    foreach (theme_nit_brand_hues() as $hue) {
        if ($hue['light'] === $group) {
            return 'g4';
        }
        if ($hue['dark'] === $group) {
            return 'g5';
        }
    }
    return null;
}

/**
 * The other half of a group's light/dark pair, if it has one.
 *
 * @param string $group group key (g1..g17)
 * @return string|null the partner's key, or null for Groups 1-3
 */
function theme_nit_brand_group_partner(string $group): ?string {
    if ($group === 'g4' || $group === 'g5') {
        return $group === 'g4' ? 'g5' : 'g4';
    }
    foreach (theme_nit_brand_hues() as $hue) {
        if ($hue['light'] === $group) {
            return $hue['dark'];
        }
        if ($hue['dark'] === $group) {
            return $hue['light'];
        }
    }
    return null;
}

/**
 * One of a group's non-colour settings, falling back to its parent group's.
 *
 * `theme_nit/<prefix><group><suffix>` if that row exists; else, for a hue group,
 * the same row for its parent (theme_nit_brand_group_parent()); else false —
 * exactly what get_config() answers for a missing row, so every caller's own
 * default still applies after it. Saving a hue group's navbar section writes
 * its own rows and it stops following; Reset removes them and it follows again.
 *
 * @param string $group group key (g1..g17)
 * @param string $prefix the config name up to the group key, e.g. 'navbarsize_'
 * @param string $suffix the rest of it, e.g. '_title', or ''
 * @return mixed the stored string, or false
 */
function theme_nit_group_setting(string $group, string $prefix, string $suffix = '') {
    $value = get_config('theme_nit', $prefix . $group . $suffix);
    if (!is_string($value) && ($parent = theme_nit_brand_group_parent($group)) !== null) {
        $value = get_config('theme_nit', $prefix . $parent . $suffix);
    }
    return $value;
}

/**
 * The WCAG relative luminance of a `#rrggbb` colour — 0 (black) .. 1 (white).
 *
 * Used by theme_nit_brand_group_scheme() only. theme_nit_group_is_light() runs
 * the same maths inline over a different role; the two were deliberately left
 * separate so that changing what the API reports can never move what the site
 * renders.
 *
 * @param string $hex `#rrggbb` or `#rgb`, with or without the hash
 * @return float|null the luminance, or null when the string is not a colour
 */
function theme_nit_hex_luminance(string $hex): ?float {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return null;
    }
    // Linearise each channel, then weight it.
    $lum = 0.0;
    foreach ([[0, 0.2126], [2, 0.7152], [4, 0.0722]] as [$offset, $weight]) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $c = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        $lum += $c * $weight;
    }
    return $lum;
}

/**
 * Which colour scheme a Brand-Colors group's own roles are authored for.
 *
 * "light" = dark ink on a bright ground, "dark" = the other way round. It
 * describes how the GROUP is painted, not where it is used: a light group is
 * still a light group when somebody points dark mode at it (which is exactly
 * the mistake this answer exists to make visible).
 *
 * Measured, not declared — the group's page Background against its Text
 * primary — for the same reason theme_nit_group_is_light() is measured: an
 * admin who retunes a group on the Brand Colors tab changes what it IS, and
 * nobody should have to remember to flip a second switch to say so. Comparing
 * the two roles rather than testing the background against a fixed threshold
 * keeps the answer right for the mid-toned grounds where a threshold is a coin
 * toss: whichever of ground and ink is brighter decides it.
 *
 * REPORTING ONLY. It is published per group on the design-system API
 * (`brandcolors.groups[].scheme`) so the mobile app can pair modes to groups
 * from the payload instead of hard-coding group keys, and it is printed by
 * theme/nit/cli/scheme_diagnose.php. Nothing that renders a page consults it:
 * the settings forms still offer every group, and an admin can still point a
 * mode wherever they like. A filtered picker was tried and taken back out —
 * hiding groups is a change to the site, and this answer exists to describe the
 * site, not to overrule it.
 *
 * @param string $group group key (g1..g17)
 * @return string 'light' | 'dark'
 */
function theme_nit_brand_group_scheme(string $group): string {
    static $cache = [];
    if (array_key_exists($group, $cache)) {
        return $cache[$group];
    }

    $bg = theme_nit_hex_luminance(theme_nit_brandcolour($group . '_background'));
    $ink = theme_nit_hex_luminance(theme_nit_brandcolour($group . '_textprimary'));

    if ($bg === null) {
        // No readable ground: fall back to the chrome answer, which reads a
        // different role and so may still know something.
        $scheme = theme_nit_group_is_light($group) ? 'light' : 'dark';
    } else if ($ink === null) {
        $scheme = $bg > 0.5 ? 'light' : 'dark';
    } else {
        $scheme = $bg > $ink ? 'light' : 'dark';
    }

    $cache[$group] = $scheme;
    return $scheme;
}

/**
 * The Brand-Colors groups authored for one colour scheme.
 *
 * Reporting only — read by theme/nit/cli/scheme_diagnose.php to say which
 * groups a mode COULD wear. It is not enforced anywhere: no form filters its
 * options by it and no save is refused because of it.
 *
 * @param string $scheme 'light' | 'dark'
 * @return string[] group keys, in group order
 */
function theme_nit_groups_for_scheme(string $scheme): array {
    $out = [];
    foreach (array_keys(theme_nit_brand_groups()) as $gkey) {
        if (theme_nit_brand_group_scheme($gkey) === $scheme) {
            $out[] = $gkey;
        }
    }
    return $out;
}

/**
 * The theme_nit config row holding the category → group map for one mode.
 *
 * Light keeps the original key so every map an admin saved before the site had
 * two styles per category is still the light one — the look those pages already
 * had. Dark is a second row rather than a second field inside the first, so the
 * two maps stay independently readable and a half-written dark map can never
 * cost a category the style it is showing today.
 *
 * @param string $mode 'light' | 'dark'
 * @return string the config name
 */
function theme_nit_category_groups_config(string $mode): string {
    return $mode === 'dark' ? 'nit_category_groups_dark' : 'nit_category_groups';
}

/**
 * The whole category → group map for one display mode.
 *
 * @param string $mode 'light' | 'dark'
 * @return array<int|string, string> main category id => group key
 */
function theme_nit_category_group_map(string $mode): array {
    static $maps = [];
    $mode = ($mode === 'dark') ? 'dark' : 'light';
    if (!array_key_exists($mode, $maps)) {
        $raw = get_config('theme_nit', theme_nit_category_groups_config($mode));
        $maps[$mode] = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    }
    return $maps[$mode];
}

/**
 * The group an admin EXPLICITLY assigned to a category, or null when there is none.
 *
 * Same resolution as theme_nit_category_brand_group() — the map is keyed by main
 * category, so a subcategory answers with its top-level ancestor's group — but it
 * tells "assigned to Group 1" apart from "never assigned", which the plain
 * function cannot: both come back as `g1` there.
 *
 * That distinction is what lets a course page adopt its category's palette
 * without stealing the light/dark switch from every OTHER course: an unassigned
 * category returns null and the page stays on the mode's group (see
 * theme_nit_page_brand_group()).
 *
 * A category holds one assignment PER MODE, so this is asked per mode too. A
 * category styled for light and left alone for dark answers null in dark, and
 * those pages fall back to the site's dark group — "not answered" must never
 * mean "wear the light palette in the dark", which is the one outcome nobody
 * would have chosen deliberately.
 *
 * @param int $categoryid the category being rendered, or a course's category
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string|null a group key (g1..g17), or null when nothing is assigned
 */
function theme_nit_category_brand_group_assigned(int $categoryid, ?string $mode = null): ?string {
    static $resolved = [];

    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    $map = theme_nit_category_group_map($mode);

    if (empty($map) || $categoryid <= 0) {
        return null;
    }
    $cachekey = $mode . ':' . $categoryid;
    if (array_key_exists($cachekey, $resolved)) {
        return $resolved[$cachekey];
    }

    $topid = theme_nit_category_top_ancestor($categoryid);

    $group = $map[$topid] ?? null;
    if ($group === null || !array_key_exists($group, theme_nit_brand_groups())) {
        $group = null;
    }
    $resolved[$cachekey] = $group;
    return $group;
}

/**
 * The MAIN (top-level) category a category belongs to — itself, when it is one.
 *
 * Both halves of a category's branding are assigned per main category (the
 * palette above, the navbar logo below), so both resolve through this: one
 * lookup, one cache, and no way for the two to disagree about which category a
 * course page is really in.
 *
 * @param int $categoryid any category id
 * @return int the top-level ancestor's id (the input id if it cannot be resolved)
 */
function theme_nit_category_top_ancestor(int $categoryid): int {
    static $tops = [];
    if ($categoryid <= 0) {
        return 0;
    }
    if (array_key_exists($categoryid, $tops)) {
        return $tops[$categoryid];
    }

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

    $tops[$categoryid] = $topid;
    return $topid;
}

/**
 * The category THIS request's page belongs to, or 0.
 *
 * A category's branding is a property of the category, not of one page in it: a
 * visitor who opens a course from a Group 2 category should stay in Group 2 for
 * the whole visit — the course page, its settings form, the participants list,
 * the grader report, the activity overview, every report under "More". So the
 * category is resolved from the page's own context rather than being printed by
 * each screen, which is why only course/view.php used to carry it (the course
 * format renderer was the single place that asked).
 *
 * Four ways a page can name a category, in order:
 *   1. `$PAGE->course->category` — anything inside a course. Off the site course
 *      this is 0, which is how non-course pages fall through.
 *   2. `$PAGE->category` — the category pages proper (course/index.php).
 *   3. A CONTEXT_COURSECAT page context — the category management screens, which
 *      do not always set (2).
 *   4. Any other context that lives inside a course — course, activity, block.
 *      Setting a course CONTEXT without calling set_course() is common in plugin
 *      pages (local/payments/buy.php does exactly that), and those pages have to
 *      match the course they are about.
 *
 * Split out from theme_nit_page_brand_group() when a category gained a second
 * piece of branding (its navbar logo): both answers are about the same category,
 * and asking twice through two copies of this walk is how they would eventually
 * come back about two different ones.
 *
 * @return int the category id, or 0 when this page is not in one
 */
function theme_nit_page_categoryid(): int {
    global $PAGE, $DB;

    // -1 = not computed yet; 0 is a real answer ("this page is not in a category").
    static $catid = -1;
    if ($catid !== -1) {
        return $catid;
    }
    $catid = 0;

    if (!isset($PAGE)) {
        return 0;
    }

    if (!empty($PAGE->course) && !empty($PAGE->course->category)) {
        $catid = (int) $PAGE->course->category;
    }
    if (!$catid) {
        try {
            $cat = $PAGE->category;
            if (!empty($cat->id)) {
                $catid = (int) $cat->id;
            }
        } catch (\Throwable $e) {
            $catid = 0;
        }
    }
    if (!$catid) {
        // Guarded: reading $PAGE->context before anything set one emits a
        // debugging notice (and throws outright in an AJAX script under
        // developer mode). Nothing here is worth a warning on somebody else's
        // page, so a page that cannot answer simply does not get a category.
        try {
            $context = $PAGE->context;
            if ($context) {
                if ((int) $context->contextlevel === CONTEXT_COURSECAT) {
                    $catid = (int) $context->instanceid;
                } else {
                    // A course (or activity, or block) context with no
                    // $PAGE->course behind it. That is most plugin pages:
                    // local/payments/buy.php sets the course CONTEXT and never
                    // calls set_course(), so the check above still saw the site
                    // course and the buy screen came out in the site palette
                    // while the course around it was in its category's. Walk the
                    // context up to its course and read the category from there.
                    $coursectx = $context->get_course_context(false);
                    if ($coursectx && (int) $coursectx->instanceid !== (int) SITEID) {
                        $catid = (int) $DB->get_field('course', 'category',
                            ['id' => $coursectx->instanceid]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $catid = 0;
        }
    }

    return $catid;
}

/**
 * The category-assigned brand group THIS request's page belongs to, if any.
 *
 * Returns null unless the page's category (or its main ancestor) has a group
 * assigned for the mode being asked about on the gallery "Category styles" tab:
 * an unassigned category must leave the page on the light/dark switch's group,
 * not pin it to Group 1.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string|null group key (g1..g17), or null when the page is not in a styled category
 */
function theme_nit_page_brand_group(?string $mode = null): ?string {
    $catid = theme_nit_page_categoryid();
    if (!$catid) {
        return null;
    }
    return theme_nit_category_brand_group_assigned($catid, $mode);
}

/**
 * The two display modes of the light/dark switch, in switch order.
 *
 * @return array<string, string> mode key => the mode it toggles to
 */
function theme_nit_modes(): array {
    return ['light' => 'dark', 'dark' => 'light'];
}

/**
 * Which Brand-Colors group each display mode renders in.
 *
 * The light/dark button does not carry a palette of its own: it selects one of
 * the three Brand-Colors groups, exactly like the category styles do. An admin
 * maps mode → group on the gallery "Change style" tab ("Site styles" section);
 * the map is stored as the theme_nit config `nit_mode_groups`
 * (JSON `{"light":"g1","dark":"g2"}`).
 *
 * Defaults: light → Group 1 (the site's normal look), dark → Group 2.
 *
 * This pair is also what the design-system API publishes as
 * `brandcolors.schemes`, so a client can stop hard-coding "g4 is the light one".
 * That is a read of whatever an admin actually saved here — the default above is
 * deliberately left alone, because it is consulted on sites that never saved the
 * row and changing it would repaint them.
 *
 * @return array<string, string> mode key (light/dark) => group key (g1/g2/g3)
 */
function theme_nit_mode_groups(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $defaults = ['light' => 'g1', 'dark' => 'g2'];
    $raw = get_config('theme_nit', 'nit_mode_groups');
    $saved = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];

    $groups = theme_nit_brand_groups();
    $map = [];
    foreach ($defaults as $mode => $default) {
        $group = $saved[$mode] ?? $default;
        $map[$mode] = array_key_exists($group, $groups) ? $group : $default;
    }
    return $map;
}

/**
 * The parts of the page chrome an admin may switch off on the Site home.
 *
 * "Home page chrome" on the gallery's Change style tab: the home page is the
 * marketing landing screen, and its hero / closing blocks often carry a menu
 * and contact strip of their own, so an admin may want the theme's navigation
 * bar and site footer off THAT page while every other page keeps them. Stored
 * as two theme_nit configs, `homechrome_navbar` / `homechrome_footer`
 * ('1' shown, '0' hidden); a row that was never saved means shown, so a site
 * that has not visited the tab looks the way it always has.
 *
 * Only the Site home layout (layout/frontpage.php) and the site-footer band
 * (core_renderer::nit_site_footer) consult this, and both ignore the answer
 * while the page is in edit mode — the edit-mode switch and the user menu live
 * on the navigation bar, and an admin who hid it must still be able to leave
 * editing.
 *
 * @return array{navbar: bool, footer: bool} true = shown
 */
function theme_nit_home_chrome(): array {
    $chrome = [];
    foreach (['navbar', 'footer'] as $part) {
        $chrome[$part] = get_config('theme_nit', 'homechrome_' . $part) !== '0';
    }
    return $chrome;
}

/**
 * The display mode a visitor who has never pressed the switch gets.
 *
 * Chosen by the admin on the gallery "Change style" tab ("Site styles" section,
 * the "Default" radio) and stored as the theme_nit config `nit_mode_default`.
 * Dark when nothing was ever saved: the brand is dark-first, and the site is
 * meant to open that way for a first-time visitor.
 *
 * @return string 'light' | 'dark'
 */
function theme_nit_default_mode(): string {
    $mode = (string) get_config('theme_nit', 'nit_mode_default');
    return array_key_exists($mode, theme_nit_modes()) ? $mode : 'dark';
}

/**
 * The display mode this request should render in.
 *
 * Read from the visitor's cookie; anything we do not recognise (and the very
 * first visit) is the admin's default mode (theme_nit_default_mode()), so the
 * site opens the way the admin chose until somebody presses the button.
 *
 * @return string 'light' | 'dark'
 */
function theme_nit_current_mode(): string {
    $mode = isset($_COOKIE[THEME_NIT_MODE_COOKIE]) ? (string) $_COOKIE[THEME_NIT_MODE_COOKIE] : '';
    return array_key_exists($mode, theme_nit_modes()) ? $mode : theme_nit_default_mode();
}

/**
 * The classes the current display mode puts on the <html> element.
 *
 * Two things: `nit-mode-light` / `nit-mode-dark` (a hook for anything that has
 * to know which mode it is in), and the Brand-Colors group switch class for the
 * group that mode maps to.
 *
 * The classes go on <html>, not <body>, deliberately. The group switch works by
 * re-pointing the `--nit-brand-*` custom properties, and the legacy `--nit-*`
 * aliases are declared on `:root` — i.e. on <html> itself. A custom property
 * holding a var() is substituted on the element that declares it, so an alias on
 * <html> would freeze to Group 1 if the switch class sat any lower in the tree
 * (the site would recolour only half-way). Declaring the switch on the same
 * element the aliases live on makes them resolve from the active group.
 *
 * @return string space-separated class list (never empty)
 */
function theme_nit_mode_classes(): string {
    return theme_nit_mode_classes_for(theme_nit_current_mode());
}

/**
 * The <html> classes a GIVEN mode owns, on THIS page.
 *
 * Split out from theme_nit_mode_classes() so the navbar switch can ask for the
 * other mode's set and swap the two in the browser. One function builds both,
 * because the server-rendered class list and the one the button applies must
 * never be able to disagree.
 *
 * "On this page" is the whole of it: a category carries a style per mode, and
 * inside such a category that style outranks the site's for that mode — the
 * course page, its settings, participants, grades, reports. So the group is
 * looked up per mode and per page, which is exactly what lets the switch keep
 * working inside a styled category (it now moves between the category's own two
 * looks) instead of having to be hidden there.
 *
 * @param string $mode 'light' | 'dark'
 * @return string space-separated class list
 */
function theme_nit_mode_classes_for(string $mode): string {
    return theme_nit_html_classes_for($mode, theme_nit_active_chrome_group($mode));
}

/**
 * The <html> classes for a given mode rendered in a given group.
 *
 * The two are normally tied together (a mode selects a group), but a styled
 * category breaks the tie: the page renders in the CATEGORY's group while the
 * visitor's chosen mode is still what it was. One builder for both cases, so the
 * chrome class can never be derived from a different group than the one actually
 * painting the page.
 *
 * @param string $mode 'light' | 'dark' — the visitor's choice
 * @param string $group the group key this page actually renders in
 * @return string space-separated class list
 */
function theme_nit_html_classes_for(string $mode, string $group): string {
    // `nit-chrome-light` / `nit-chrome-dark` says whether the BAR is light, which
    // is not the same question as which mode the visitor picked: a group is free
    // to run a dark bar over a light page. Measured from the group's own navbar
    // colour (theme_nit_group_is_light), so it stays true when an admin retunes
    // the palette. CSS that has to contrast with the bar — anything drawn ON it
    // whose own colour we do not control, a user's profile picture above all —
    // keys off this rather than naming a group number.
    $chrome = theme_nit_group_is_light($group) ? 'nit-chrome-light' : 'nit-chrome-dark';

    return trim('nit-mode-' . $mode . ' ' . $chrome . ' ' . theme_nit_brand_group_class($group));
}

/**
 * The two palettes every hue pair is cut from, with the accent written as rung
 * names instead of colours.
 *
 * A hex is a hex — the same in every hue. A bare rung name (`A600`, `V`, …) is
 * looked up in the hue's ramp by theme_nit_brand_rehue(). Which is which is the
 * whole design: the neutrals, grounds, inks, borders and status colours are the
 * parent's and identical across the six; only the roles that carried azure (or,
 * on the dark side, Graphite's red) take the hue.
 *
 * These are Groups 4 and 5 AS THE SITE RUNS THEM — a snapshot of the live
 * palette taken on 2026-09-16, not the code seeds above. The two differ: the
 * site's Daylight puts its navbar titles in the body ink and its hover shape in
 * A700, and its Graphite paints its buttons in a vivid fill under a near-black
 * page. The admin was asked for "the same as 4 and 5", and that is the pair
 * they see, so it is the pair these copy. A snapshot and not a live read on
 * purpose (see theme_nit_brand_group_parent()).
 *
 * The one deliberate departure: the dark template labels its vivid fill with
 * the page ink (#0d1117) where the live Graphite uses white. White on a fill at
 * this lightness is 3.1-3.7:1 across the six hues — under the 4.5 a button label
 * needs — and the ink is 5.1-6.1, which is also how Groups 4/5 were seeded
 * before the site re-painted them. It is one role per group to flip back on the
 * gallery page (onprimary, and the three hover-text roles beside it) for a site
 * that wants the white regardless.
 *
 * @return array{light: array<string, string>, dark: array<string, string>}
 *         role => #hex or rung name
 */
function theme_nit_brand_hue_templates(): array {
    return [
        // --- Daylight, re-hued. Light content, light chrome, deep-ink accent.
        'light' => [
            'primary'           => 'A600',
            'secondary'         => '#e6e8eb',   // N200
            'onprimary'         => '#ffffff',
            'onsecondary'       => '#000000',
            'accent'            => 'A600',
            'accenttext'        => 'A700',
            'accentwords'       => 'A600',
            'accentunderline'   => 'A800',
            'btnprimaryborder'  => 'A600',
            'btnprimaryhoverbg' => 'H85',
            'btnprimaryhovertext' => '#ffffff',
            'btnprimaryhoverborder' => 'H90',
            'btnsecondaryborder' => '#e6e8eb',   // N200
            'btnsecondaryhoverbg' => '#d5d9df',   // N300
            'btnsecondaryhovertext' => '#14191f',   // N900
            'btnsecondaryhoverborder' => '#d5d9df',   // N300
            'btnoutlineprimarybg' => '#f6f8fb',   // N50
            'btnoutlineprimarytext' => 'A600',
            'btnoutlineprimaryborder' => 'A600',
            'btnoutlineprimaryhoverbg' => 'A600',
            'btnoutlineprimaryhovertext' => '#ffffff',
            'btnoutlineprimaryhoverborder' => 'A600',
            'btnoutlinebg'      => '#f6f8fb',   // N50
            'btnoutlinetext'    => '#14191f',   // N900
            'btnoutlineborder'  => '#a7abb1',   // N400
            'btnoutlinehoverbg' => '#f1f3f6',   // N100
            'btnoutlinehovertext' => 'A800',
            'btnoutlinehoverborder' => '#a7abb1',   // N400
            'checkbg'           => '#ffffff',
            'checkborder'       => '#d5d9df',   // N300
            'checkknob'         => '#5e646b',   // N600
            'checkcheckedbg'    => 'A600',
            'checkcheckedborder' => 'A600',
            'checkcheckedmark'  => '#ffffff',
            'background'        => '#f6f8fb',   // N50
            'background2'       => '#f1f3f6',   // N100
            'navbarbackground1' => '#ffffff',
            'navbarbackground2' => '#f6f8fb',   // N50
            // The bar's three subjects stay the body ink in every state; only
            // the shape under them (the underline) takes the hue.
            'navbartitlecolor'  => '#14191f',   // N900
            'navbartitlehovercolor' => '#14191f',
            'navbartitleactivecolor' => '#14191f',
            'navbartitlehoverstylecolor' => 'A700',
            'navbartitleactivestylecolor' => 'A700',
            'navbariconcolor'   => '#14191f',
            'navbariconhovercolor' => 'A800',
            'navbariconactivecolor' => 'A700',
            'navbarlogincolor'  => '#14191f',
            'navbarloginhovercolor' => '#14191f',
            'navbarloginactivecolor' => '#14191f',
            'navbarloginhoverstylecolor' => 'A700',
            'navbarloginactivestylecolor' => 'A700',
            'footerbackground1' => '#f1f3f6',   // N100
            'footerbackground2' => '#e6e8eb',   // N200
            'footerheading'     => 'A700',
            'footerlink'        => 'A600',
            'footericon'        => 'A600',
            'surface'           => '#ffffff',
            'textprimary'       => '#14191f',   // N900
            'textsecondary'     => '#5e646b',   // N600
            'borderprimary'     => '#d5d9df',   // N300
            'bordersecondary'   => '#a7abb1',   // N400
            'hoverbackground'   => '#f1f3f6',   // N100
            'hoverbackgroundsecondary' => '#e6e8eb',   // N200
            'hovertext'         => 'A800',
            'hovertextsecondary' => '#43484f',   // N700
            'error'             => '#9a3c16',
            'success'           => '#00703e',
            'warning'           => '#775800',
            'info'              => '#006789',
        ],
        // --- Graphite, re-hued. Near-black page, white text, vivid fill.
        'dark' => [
            'primary'           => 'V',
            'secondary'         => '#2a2e35',   // N800
            'onprimary'         => '#0d1117',   // N950 — see the docblock
            'onsecondary'       => '#f6f8fb',   // N50
            'accent'            => 'V',
            // Links are white in the site's Graphite and hover to the pale tint.
            'accenttext'        => '#ffffff',
            'accentwords'       => 'V',
            'accentunderline'   => '#ffffff',
            'btnprimaryborder'  => 'V',
            'btnprimaryhoverbg' => 'Vh',
            'btnprimaryhovertext' => '#0d1117',
            'btnprimaryhoverborder' => 'Vh',
            'btnsecondaryborder' => '#2a2e35',   // N800
            'btnsecondaryhoverbg' => '#24272d',
            'btnsecondaryhovertext' => '#f6f8fb',   // N50
            'btnsecondaryhoverborder' => '#22252a',
            'btnoutlineprimarybg' => '#0d1117',   // N950
            'btnoutlineprimarytext' => '#ffffff',
            'btnoutlineprimaryborder' => 'V',
            'btnoutlineprimaryhoverbg' => 'V',
            'btnoutlineprimaryhovertext' => '#0d1117',
            'btnoutlineprimaryhoverborder' => 'V',
            'btnoutlinebg'      => '#0d1117',   // N950
            'btnoutlinetext'    => '#ffffff',
            'btnoutlineborder'  => '#43484f',   // N700
            // The neutral outline button fills with the hue on hover, as the
            // site's Graphite fills with red.
            'btnoutlinehoverbg' => 'V',
            'btnoutlinehovertext' => '#0d1117',
            'btnoutlinehoverborder' => 'V',
            'checkbg'           => '#1f232a',   // N850
            'checkborder'       => '#a7abb1',   // N400
            'checkknob'         => '#a7abb1',   // N400
            'checkcheckedbg'    => 'A400',
            'checkcheckedborder' => 'A400',
            'checkcheckedmark'  => '#0d1117',   // N950
            'background'        => '#121212',
            'background2'       => '#14191f',   // N900
            'navbarbackground1' => '#0d1117',
            'navbarbackground2' => '#121212',
            'navbartitlecolor'  => '#ffffff',
            'navbartitlehovercolor' => '#ffffff',
            'navbartitleactivecolor' => '#ffffff',
            'navbartitlehoverstylecolor' => 'Vd',
            'navbartitleactivestylecolor' => 'Vd',
            'navbariconcolor'   => '#f6f8fb',
            'navbariconhovercolor' => 'A200',
            'navbariconactivecolor' => 'A300',
            'navbarlogincolor'  => '#ffffff',
            'navbarloginhovercolor' => '#ffffff',
            'navbarloginactivecolor' => '#ffffff',
            'navbarloginhoverstylecolor' => 'Vd',
            'navbarloginactivestylecolor' => 'Vd',
            'footerbackground1' => '#0d1117',
            'footerbackground2' => '#14191f',
            'footerheading'     => 'A300',
            'footerlink'        => 'A400',
            'footericon'        => 'A400',
            'surface'           => '#1f232a',   // N850
            'textprimary'       => '#f6f8fb',   // N50
            'textsecondary'     => '#a7abb1',   // N400
            'borderprimary'     => '#2a2e35',   // N800
            'bordersecondary'   => '#43484f',   // N700
            'hoverbackground'   => '#14191f',   // N900
            'hoverbackgroundsecondary' => '#1f232a',   // N850
            'hovertext'         => 'A200',
            'hovertextsecondary' => '#d5d9df',   // N300
            'error'             => '#e68867',
            'success'           => '#5cbc82',
            'warning'           => '#c89e3a',
            'info'              => '#3bb2e3',
        ],
    ];
}

/**
 * One hue's palette: a template with every rung name replaced by that hue's hex.
 *
 * @param array<string, string> $template role => #hex or rung name
 * @param array<string, string> $rungs rung name => #hex (theme_nit_brand_hues()['rungs'])
 * @return array<string, string> role => #hex
 */
function theme_nit_brand_rehue(array $template, array $rungs): array {
    $out = [];
    foreach ($template as $role => $value) {
        $out[$role] = ($value[0] === '#') ? $value : ($rungs[$value] ?? $value);
    }
    return $out;
}

/**
 * The course's lead teacher as a link to their public instructor profile.
 *
 * AC-4.5.17: "The instructor's name and photograph on the course details page
 * link to the public instructor profile." The public page is the one that shows
 * their background and nothing private (AC-4.5.16); Moodle's own /user/view.php
 * decides what to reveal from capabilities and site settings, which is exactly the
 * decision the specification does not want made per-site.
 *
 * Falls back to the plain name whenever there is no profile to link to - the
 * plugin absent, or the teacher not recognised as an instructor - so a caller can
 * use this everywhere the name appears without checking first.
 *
 * @param int $courseid
 * @return string HTML: a link, an escaped name, or ''
 */
function theme_nit_course_teacher_link(int $courseid): string {
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

    if (!$teacher) {
        return '';
    }

    $name = fullname($teacher);

    if (!class_exists('\local_nit_instructors\profile')
            || !\local_nit_instructors\profile::is_instructor((int) $teacher->id)) {
        return s($name);
    }

    return html_writer::link(
        new moodle_url('/local/nit_instructors/view.php', ['id' => $teacher->id]),
        s($name),
        ['class' => 'nit-instructor-link']
    );
}

/**
 * How big the site logo is drawn, in each place the site draws it.
 *
 * The logo file itself is uploaded on the core Logos page (Site administration →
 * Appearance → Logos). Nothing on that page ever said how *large* to draw it, so
 * the size lived in five hard-coded CSS rules and only a developer could change
 * it. Each slot below now publishes its height as a CSS custom property that the
 * matching rule reads via `var()`, and each has an admin setting sitting beside
 * the upload it belongs to (added by theme/nit/settings.php).
 *
 * `default` is the height the stylesheet already used, so a site that never
 * touches the new settings renders exactly as it did before they existed.
 *
 * On top of every slot sits one master multiplier — `theme_nit/logoscale`, a
 * percentage — because "make the logo bigger" is the whole request most of the
 * time and nobody should have to edit five numbers to answer it.
 *
 * @return array<string, array{setting:string, property:string, default:int}>
 */
function theme_nit_logo_slots(): array {
    return [
        // The corner of every page — theme/nit/templates/theme_boost/navbar.mustache,
        // sized by `.nit-navbar-logo` in scss/components/_navbar.scss. This is the
        // one an administrator means when they say "the logo".
        'navbar' => [
            'setting'  => 'logoheightnavbar',
            'property' => '--nit-logo-navbar',
            'default'  => 66,
        ],
        // The header of the phone-width primary drawer (Boost's
        // primary-drawer-mobile template): the same mark, in the menu that
        // replaces the navbar links on a small screen.
        'drawer' => [
            'setting'  => 'logoheightdrawer',
            'property' => '--nit-logo-drawer',
            'default'  => 100,
        ],
        // The brand column of the site footer — theme_nit/site_footer.
        'footer' => [
            'setting'  => 'logoheightfooter',
            'property' => '--nit-logo-footer',
            'default'  => 100,
        ],
        // Over the picture beside the account screens — core/login_panel.
        'authpanel' => [
            'setting'  => 'logoheightauthpanel',
            'property' => '--nit-logo-authpanel',
            'default'  => 48,
        ],
        // Inside the log-in / sign-up card itself — `.login-logo` in core/loginform.
        'authcard' => [
            'setting'  => 'logoheightauthcard',
            'property' => '--nit-logo-authcard',
            'default'  => 56,
        ],
    ];
}

/**
 * The alternative logo slots — one per core logo, for the other display mode.
 *
 * Core ships three logo settings on Appearance → Logos (`logo`, `logocompact`,
 * `favicon`) and exactly one of each. That is enough for a site with one look,
 * and not enough for a site with a light mode and a dark one: a mark drawn in
 * white for a navy bar disappears the moment the bar turns white, and no amount
 * of CSS can fix that honestly — filtering it would rewrite a picture the site
 * owner uploaded.
 *
 * So the admin says which mode the CORE logos were drawn for
 * (`theme_nit/logosfor`), and uploads the other set here. Pages rendering in the
 * mode the core logos suit use core's; pages in the other mode use these, and
 * fall back to core's when a slot is empty — an empty slot must never mean "no
 * logo", only "no separate version".
 *
 * Stored exactly like the fonts and the auth pictures: system context, itemid 0,
 * config `theme_nit/<setting>` = the filename, served by theme_nit_pluginfile().
 *
 * @return array<string, array{setting:string, filearea:string, basename:string,
 *         strkey:string, deskey:string, core:string}> keyed by slot
 */
function theme_nit_logo_variants(): array {
    return [
        'logo' => [
            'setting'  => 'altlogo',
            'filearea' => 'altlogo',
            'basename' => 'alt-logo',
            'strkey'   => 'altlogo',
            'deskey'   => 'altlogo_desc',
            'core'     => 'logo',
        ],
        'logocompact' => [
            'setting'  => 'altlogocompact',
            'filearea' => 'altlogocompact',
            'basename' => 'alt-logo-compact',
            'strkey'   => 'altlogocompact',
            'deskey'   => 'altlogocompact_desc',
            'core'     => 'logocompact',
        ],
        'favicon' => [
            'setting'  => 'altfavicon',
            'filearea' => 'altfavicon',
            'basename' => 'alt-favicon',
            'strkey'   => 'altfavicon',
            'deskey'   => 'altfavicon_desc',
            'core'     => 'favicon',
        ],
    ];
}

/**
 * Whether a brand group renders its CHROME light.
 *
 * Measured, not declared: the relative luminance of the group's "Navbar
 * background 1" decides it. That is the surface the logo is actually drawn on,
 * and it means a group an admin retunes on the Brand Colors tab starts or stops
 * counting as light on its own — nobody has to remember to flip a second switch.
 *
 * @param string $group group key (g1..g17)
 * @return bool true when the bar is light enough to need a dark mark
 */
function theme_nit_group_is_light(string $group): bool {
    $hex = theme_nit_brandcolour($group . '_navbarbackground1');
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return false;
    }
    // WCAG relative luminance: linearise each channel, then weight it.
    $lum = 0;
    foreach ([[0, 0.2126], [2, 0.7152], [4, 0.0722]] as [$offset, $weight]) {
        $c = hexdec(substr($hex, $offset, 2)) / 255;
        $c = $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        $lum += $c * $weight;
    }
    return $lum > 0.5;
}

/**
 * The brand group this request's page CHROME renders in.
 *
 * Normally the group the light/dark switch selected. Inside a category that has
 * a style assigned it is that category's group instead: the switch class lands on
 * <html>, so the navbar and the footer are inside it like everything else, and
 * the things that have to know how bright the bar is — `nit-chrome-*`, and above
 * all which logo file to serve — must read the group that is actually painting
 * it. Asking the switch here instead would hand a dark navbar the logo drawn for
 * a light one, and the mark would disappear.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return string group key (g1..g17)
 */
function theme_nit_active_chrome_group(?string $mode = null): string {
    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    return theme_nit_page_brand_group($mode)
        ?? (theme_nit_mode_groups()[$mode] ?? 'g1');
}

/**
 * The per-category logo slots — one per display mode.
 *
 * A category is a brand of its own on this site: it has its own palette (a brand
 * group per mode, above) and its own mark. The mark needs the same two versions
 * for the same reason the site's does — a logo drawn in white for a navy bar
 * disappears the moment the bar turns white — so there is one slot per mode, and
 * an empty slot means "use the site logo", never "no logo".
 *
 * Stored the way local_nit_category stores a category's picture: in the
 * CATEGORY's own context, itemid 0, one file per area. That keeps a category's
 * files with the category — delete the category and they go with it — and it
 * gives the file a context whose visibility we can honour when serving it (see
 * theme_nit_pluginfile()).
 *
 * `input` is the multipart field-name PREFIX on the gallery form; the category
 * id is appended, because one form posts a row per category.
 *
 * @return array<string, array{filearea:string, input:string, basename:string,
 *         strkey:string, remove:string}> keyed by mode
 */
function theme_nit_category_logo_slots(): array {
    return [
        'light' => [
            'filearea' => 'categorylogolight',
            'input'    => 'catlogolight',
            'basename' => 'category-logo-light',
            'strkey'   => 'categorystyles_col_logolight',
            'remove'   => 'removecatlogolight',
        ],
        'dark' => [
            'filearea' => 'categorylogodark',
            'input'    => 'catlogodark',
            'basename' => 'category-logo-dark',
            'strkey'   => 'categorystyles_col_logodark',
            'remove'   => 'removecatlogodark',
        ],
    ];
}

/**
 * The logo file uploaded for a category in one display mode, if there is one.
 *
 * Asked about the MAIN category, the same way the palette is: the gallery table
 * lists top-level categories only, so a course three levels down answers with
 * its main category's mark.
 *
 * @param int $categoryid any category id (resolved to its top-level ancestor)
 * @param string $mode 'light' | 'dark'
 * @return stored_file|null the file, or null when that slot is empty
 */
function theme_nit_category_logo_file(int $categoryid, string $mode): ?stored_file {
    static $cache = [];

    $slots = theme_nit_category_logo_slots();
    if (!isset($slots[$mode]) || $categoryid <= 0) {
        return null;
    }

    $topid = theme_nit_category_top_ancestor($categoryid);
    $cachekey = $mode . ':' . $topid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    $cache[$cachekey] = null;

    $context = \context_coursecat::instance($topid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $files = get_file_storage()->get_area_files(
        $context->id,
        'theme_nit',
        $slots[$mode]['filearea'],
        0,
        'filename',
        false
    );
    $cache[$cachekey] = $files ? reset($files) : null;
    return $cache[$cachekey];
}

/**
 * The URL of a category's own logo for one display mode.
 *
 * @param int $categoryid any category id (resolved to its top-level ancestor)
 * @param string $mode 'light' | 'dark'
 * @return moodle_url|false the URL, or false when that slot is empty
 */
function theme_nit_category_logo_url(int $categoryid, string $mode) {
    $file = theme_nit_category_logo_file($categoryid, $mode);
    if (!$file) {
        return false;
    }
    // `theme_get_revision()` as the itemid segment is the cache buster, exactly
    // as the site logos use it — the stored file's real itemid is 0, and
    // theme_nit_pluginfile() drops this segment before looking the file up.
    return \moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'theme_nit',
        $file->get_filearea(),
        theme_get_revision(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

/**
 * The category logo THIS request's page should draw, if its category has one.
 *
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return moodle_url|false
 */
function theme_nit_page_category_logo_url(?string $mode = null) {
    $catid = theme_nit_page_categoryid();
    if (!$catid) {
        return false;
    }
    $mode = ($mode === 'dark' || $mode === 'light') ? $mode : theme_nit_current_mode();
    return theme_nit_category_logo_url($catid, $mode);
}

/**
 * The URL of a logo, for the mode this page is rendering in.
 *
 * Three answers, in order:
 *
 *   1. The page's CATEGORY logo, when it has one for this mode. A category is a
 *      brand of its own here — it already carries its own palette — so on its
 *      pages its own mark replaces the site's, in the navbar and everywhere else
 *      the compact logo is drawn. The browser-tab icon is deliberately excluded:
 *      the favicon says which SITE a tab belongs to, and a per-category one only
 *      makes a row of tabs harder to read.
 *   2. The alternative site upload, when the page's chrome is light and the core
 *      logos were drawn for dark (or the other way round) AND that alternative
 *      was actually uploaded.
 *   3. Core's own logo, unchanged — including when the admin has not said which
 *      mode the core logos are for, because guessing at somebody's artwork is
 *      worse than leaving it alone.
 *
 * Resolving all of this in ONE function is the point: every caller — the three
 * overridden renderer accessors, the switch's swap payload, the site footer —
 * gets the same answer, and no screen has to know that categories can have logos.
 *
 * @param string $slot 'logo' | 'logocompact' | 'favicon'
 * @param int $maxwidth passed through to core's sizing for 'logo'/'logocompact'
 * @param int $maxheight
 * @param string|null $mode 'light' | 'dark'; null = the mode this request renders in
 * @return moodle_url|false the URL, or false exactly as core returns for "none"
 */
function theme_nit_logo_url(string $slot, int $maxwidth = 300, int $maxheight = 300, ?string $mode = null) {
    $variants = theme_nit_logo_variants();

    // (1) The category's own mark, when this page is inside one that has it.
    if ($slot !== 'favicon') {
        $catlogo = theme_nit_page_category_logo_url($mode);
        if ($catlogo) {
            return $catlogo;
        }
    }

    // Core's own URL for this slot, built the way renderer_base builds it — the
    // size is hidden in the file path and `theme_get_revision()` is the cache
    // buster. Rebuilt here rather than called on the renderer because this
    // function is also used outside a rendering context (settings, CLI checks).
    $corelogo = function () use ($slot, $maxwidth, $maxheight) {
        $filename = get_config('core_admin', $slot);
        if (empty($filename)) {
            return false;
        }
        $filepath = $slot === 'favicon' ? '64x64/' : ((int) $maxwidth . 'x' . (int) $maxheight) . '/';
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'core_admin',
            $slot,
            $filepath,
            theme_get_revision(),
            $filename
        );
    };

    if (!isset($variants[$slot])) {
        return $corelogo();
    }

    // Which mode the core uploads were drawn for. Unset = "not answered", and
    // then nothing is swapped.
    $corelogosfor = get_config('theme_nit', 'logosfor');
    if ($corelogosfor !== 'dark' && $corelogosfor !== 'light') {
        return $corelogo();
    }

    // `$mode` lets a caller ask "what would this be in the OTHER mode?" — which is
    // what the navbar switch needs, so it can swap the picture in the browser
    // instead of making the visitor reload the page to see it change. Asked
    // through the chrome-group resolver, so inside a styled category it answers
    // with the group that category renders in for that mode — not the site's.
    $group = theme_nit_active_chrome_group($mode);
    $pagemode = theme_nit_group_is_light($group) ? 'light' : 'dark';
    if ($pagemode === $corelogosfor) {
        return $corelogo();
    }

    // The other mode: use the alternative, if there is one.
    $variant = $variants[$slot];
    $filename = get_config('theme_nit', $variant['setting']);
    if (!is_string($filename) || $filename === '') {
        return $corelogo();
    }

    // The itemid of a theme stored file is the THEME REVISION, not 0 — that is
    // what makes the browser drop the old picture when a new one is uploaded,
    // and theme_config::setting_file_serve() will not resolve a path without it.
    // Built here rather than through setting_file_url() only because that returns
    // a protocol-relative string and every caller of this function wants the
    // moodle_url core's own accessors return.
    return \moodle_url::make_pluginfile_url(
        \context_system::instance()->id,
        'theme_nit',
        $variant['filearea'],
        theme_get_revision(),
        '/',
        ltrim($filename, '/')
    );
}

/**
 * The master logo multiplier, as a percentage.
 *
 * Clamped rather than validated away: PARAM_INT on the setting will happily
 * accept 0 or 9999, and neither the navbar nor the footer survives that. The
 * range is wide enough to be useful and narrow enough that no value can make
 * the logo vanish or push the page apart.
 *
 * @return int percentage, 25..400
 */
function theme_nit_logo_scale(): int {
    $scale = (int) get_config('theme_nit', 'logoscale');
    if ($scale <= 0) {
        $scale = 100;
    }
    return (int) min(400, max(25, $scale));
}

/**
 * The height one logo slot is drawn at, in pixels, with the master scale applied.
 *
 * `get_config()` returns false until admin_apply_default_settings() has run for
 * a newly added setting, so the slot's own default is the fallback — that is
 * what keeps the first page load after an upgrade looking like the last one
 * before it.
 *
 * @param string $slot a key of theme_nit_logo_slots()
 * @return int height in pixels, 8..400
 */
function theme_nit_logo_height(string $slot): int {
    $slots = theme_nit_logo_slots();
    if (!isset($slots[$slot])) {
        return 0;
    }

    $height = (int) get_config('theme_nit', $slots[$slot]['setting']);
    if ($height <= 0) {
        $height = $slots[$slot]['default'];
    }

    $height = (int) round($height * theme_nit_logo_scale() / 100);

    return (int) min(400, max(8, $height));
}

/**
 * How tall the top bar has to be to carry the logo it has been given.
 *
 * `$navbar-height` drives both the bar itself and the content offset underneath
 * it in every Boost layout (drawer.scss, layout.scss, navbar.scss), so a logo
 * taller than the bar would spill out of it rather than push it open. Grow-only:
 * the bar keeps its designed 100px until the logo plus its breathing room needs
 * more, so shrinking the logo never shrinks the header.
 *
 * @return int height in pixels
 */
function theme_nit_navbar_height(): int {
    // 12px of clearance above and below the mark, matching the space the 66px
    // default leaves in the 100px bar.
    return (int) max(100, theme_nit_logo_height('navbar') + 24);
}

/**
 * Publish the resolved logo heights as CSS custom properties.
 *
 * One `:root` block, one property per slot. The rules that consume them live in
 * the component stylesheets (scss/components/_navbar.scss, _sitefooter.scss,
 * _login.scss, _authpages.scss, _logo.scss), each keeping the original height as
 * the `var()` fallback so a stale or half-built stylesheet still draws a logo.
 *
 * @return string SCSS
 */
function theme_nit_logo_scss(): string {
    $scss = "\n:root {\n";
    foreach (theme_nit_logo_slots() as $key => $slot) {
        $scss .= '    ' . $slot['property'] . ': ' . theme_nit_logo_height($key) . "px;\n";
    }
    $scss .= "}\n";

    return $scss;
}

/**
 * The bar's non-colour style layer, as CSS — one block per brand group.
 *
 * Two things live here, and both are per group for the same reason: how big and
 * how heavy each of the three subjects is set (titles / icons / log-in), and
 * which SHAPES each of them takes on hover and when active.
 *
 * The shapes are here rather than in the stylesheet because CSS has no way to
 * switch which RULES apply from a custom property. So `_navbar.scss` writes the
 * rule once, reading three properties per state, and this decides what those
 * properties hold: the state's own colour where a treatment is ticked, and an
 * inert value (`transparent`, the resting weight) where it is not. That file
 * therefore needs no knowledge of the treatment names at all — which is also why
 * "nothing ticked" needs no special case anywhere: it is three inert values.
 *
 * Emitted for every group in the same order _brand.scss uses — `:root` first,
 * then the four switch classes — because a brand group class lands on <html>,
 * the very element `:root` matches: the two selectors tie on specificity and
 * source order is what decides. Same reason _brand.scss lists its roles that
 * way.
 *
 * "Bold" is one step up from whatever the group's resting weight for that
 * subject is, not a fixed 800 — a group set to Regular titles should get a
 * visible step, not a jump past every weight in between. It never reflows the
 * bar either: the link reserves the heaviest width up front through a hidden
 * twin sized at `max()` of the three weights (see `.nit-navbar-link` in
 * scss/components/_navbar.scss), so a group that uses no bold reserves nothing.
 *
 * @return string CSS
 */
function theme_nit_navbar_style_scss(): string {
    $states = theme_nit_navbar_shape_states();
    $subjects = theme_nit_navbar_type_subjects();

    $css = "\n";
    foreach (array_keys(theme_nit_brand_groups()) as $gkey) {
        $class = theme_nit_brand_group_class($gkey);
        // Bare class, not `:root.<class>` — a group wrapper anywhere in the page
        // then carries these too, exactly as it carries its colours, and that is
        // what lets the scroll switch work by classing the BAR. Group 1 is the
        // site default so it also answers to plain `:root`, but it needs the
        // class as well: a bar scrolling INTO Group 1 has to be able to say so.
        $selector = ($class === '') ? ':root, .nit-brand-1' : '.' . $class;
        $css .= $selector . " {\n";

        // How see-through the bar is, and whether it frosts at all. "Off" is not
        // a separate rule anywhere: it is this mix at full opacity with no blur.
        $glass = theme_nit_navbar_glass($gkey);
        $opacity = $glass['on'] ? (100 - $glass['transparency']) : 100;
        $css .= '    --nit-navbaropacity: ' . $opacity . "%;\n";
        $css .= '    --nit-navbarblur: ' . ($glass['on'] ? THEME_NIT_NAVBAR_BLUR : 'none') . ";\n";

        // Size and weight, one pair per subject. The weight is the RESTING one an
        // administrator sets on the typography card, and it is the only weight
        // the bar has: no shape changes it, so a title is the same weight resting,
        // hovered and current. See theme_nit_navbar_shape_treatments() for why
        // there is no Bold treatment to change it.
        foreach (array_keys($subjects) as $subject) {
            $size = theme_nit_navbar_type_size($gkey, $subject);
            $prefix = '--nit-nav' . $subject . '-';
            $css .= '    ' . $prefix . 'size: ' . $size . "px;\n";
            $css .= '    ' . $prefix . 'weight: ' . theme_nit_navbar_type_weight($gkey, $subject) . ";\n";
            if ($subject === 'icon') {
                // The pointer target, which follows the glyph up but never down.
                $css .= '    ' . $prefix . 'box: ' . theme_nit_navbar_icon_box($size) . "px;\n";
            }
        }

        foreach ($states as $state => $meta) {
            $ticked = theme_nit_navbar_shape($gkey, $state);
            $prefix = '--nit-nav' . $meta['subject'] . '-' . $meta['phase'] . '-';
            $css .= '    ' . $prefix . 'underline: '
                . (in_array('underline', $ticked, true) ? $meta['underline'] : 'transparent') . ";\n";
            $css .= '    ' . $prefix . 'bg: '
                . (in_array('square', $ticked, true) ? $meta['bg'] : 'transparent') . ";\n";
        }
        $css .= "}\n";
    }

    return $css;
}

/**
 * The two pictures beside the account screens: one for log-in, one for sign-up.
 *
 * Both live in a system-context file area of their own and are uploaded on the
 * gallery page's "Log-in & sign-up" tab (theme/nit/gallery.php), stored exactly
 * the way the per-language fonts are — fixed basename, config `theme_nit/<setting>`
 * holding the filename — so theme_config::setting_file_url() and
 * theme_nit_pluginfile() serve them with no extra plumbing.
 *
 * `selector` is what separates the two. Every screen in this journey renders on
 * the same `pagelayout-login` layout, so the log-in picture is written against
 * the layout (which covers forgotten-password and the rest of the flow) and the
 * sign-up one against that page's body id. An id outranks the class, so sign-up
 * overrides log-in wherever both are set, and inherits it wherever it is not —
 * which is the behaviour there was before the pictures were split in two.
 *
 * The `login` slot deliberately keeps the `loginbackgroundimage` name Boost uses:
 * the setting started life as a Boost-shaped stored file and a site that already
 * has one uploaded keeps it.
 *
 * @return array<string, array{setting:string, filearea:string, input:string,
 *     basename:string, strkey:string, deskey:string, selector:string}>
 */
function theme_nit_auth_image_slots(): array {
    return [
        'login' => [
            'setting'  => 'loginbackgroundimage',
            'filearea' => 'loginbackgroundimage',
            'input'    => 'authimage_login',
            'basename' => 'login-background',
            'strkey'   => 'authimagelogin',
            'deskey'   => 'authimagelogin_desc',
            'selector' => 'body.pagelayout-login #page .login-layout-left',
        ],
        'signup' => [
            'setting'  => 'signupbackgroundimage',
            'filearea' => 'signupbackgroundimage',
            'input'    => 'authimage_signup',
            'basename' => 'signup-background',
            'strkey'   => 'authimagesignup',
            'deskey'   => 'authimagesignup_desc',
            'selector' => 'body#page-login-signup.pagelayout-login #page .login-layout-left',
        ],
    ];
}

/**
 * The languages the account-screen quote is written in.
 *
 * The same two the site's fonts are chosen per (theme_nit_font_slots()), and for
 * the same reason: a learner who switched the interface to Arabic should not be
 * read to in English by the one piece of copy on the screen that an administrator
 * wrote rather than translated.
 *
 * @return array<string, array{strkey:string, rtl:bool}>
 */
function theme_nit_auth_text_langs(): array {
    return [
        'en' => ['strkey' => 'fonten', 'rtl' => false],
        'ar' => ['strkey' => 'fontar', 'rtl' => true],
    ];
}

/**
 * The quote and its attribution for one language, as stored.
 *
 * Raw values — no formatting, no escaping. For display use
 * theme_nit_auth_panel_content(), which resolves the language and formats.
 *
 * @param string $lang language key from theme_nit_auth_text_langs()
 * @return array{quote:string, author:string}
 */
function theme_nit_auth_text(string $lang): array {
    return [
        'quote'  => (string) get_config('theme_nit', 'authpanelquote_' . $lang),
        'author' => (string) get_config('theme_nit', 'authpanelauthor_' . $lang),
    ];
}

/**
 * What the left panel of the account screens shows over the picture.
 *
 * Two things, both of which used to be baked into the photograph itself — which
 * meant re-exporting an image to correct a typo, and a logo that went stale the
 * moment the site's did.
 *
 * The logo is the navbar's, resolved through theme_nit_logo_url() — the same
 * function behind the navbar's renderer accessors — so there is one logo on the
 * site and not two. The full logo is the fallback: a site that has set only the
 * full logo and no compact one would otherwise get a blank corner, which is a
 * worse answer than the logo that is actually configured.
 *
 * Always the DARK-mode mark, whichever mode the visitor is in. The panel is a
 * photograph under a dark scrim, not the page's chrome: it is dark in both
 * modes, so the logo drawn on it is the one made for dark chrome in both modes.
 * The renderer accessors answer for the current mode, which is why they are not
 * used here.
 *
 * The quote is resolved to the interface language, falling back to English and
 * then to whichever language has been filled in — an administrator who wrote only
 * one of the two gets that one everywhere rather than an empty card.
 *
 * @param renderer_base $output the renderer (kept for the signature; unused)
 * @return array template context for theme_nit/core/login_panel
 */
function theme_nit_auth_panel_content($output): array {
    global $SITE;

    // Same sizes core's get_compact_logo_url(null, 120) / get_logo_url(null, 120)
    // would request, so the cached file is the one the navbar already serves.
    $logourl = theme_nit_logo_url('logocompact', 0, 120, 'dark');
    if (empty($logourl)) {
        $logourl = theme_nit_logo_url('logo', 0, 120, 'dark');
    }

    $langs = theme_nit_auth_text_langs();
    $current = current_language();

    // Preference order: the interface language, then English, then anything that
    // has been written at all.
    $order = array_unique(array_merge(
        array_key_exists($current, $langs) ? [$current] : [],
        ['en'],
        array_keys($langs)
    ));

    $quote = '';
    $author = '';
    foreach ($order as $lang) {
        if (!array_key_exists($lang, $langs)) {
            continue;
        }
        $text = theme_nit_auth_text($lang);
        if (trim($text['quote']) !== '') {
            $quote = $text['quote'];
            $author = $text['author'];
            break;
        }
    }

    $context = context_system::instance();

    return [
        'haslogo'  => !empty($logourl),
        'logourl'  => !empty($logourl) ? $logourl->out(false) : '',
        // The logo on the account screens is a link home, the same as the navbar
        // brand is on every other page. The panel is rendered as a Mustache
        // PARTIAL, so it cannot reach `{{config.wwwroot}}` on its own — the URL
        // has to travel in the context with the rest of the panel's data.
        'homeurl'  => (new moodle_url('/'))->out(false),
        'sitename' => format_string($SITE->fullname, true, ['context' => $context, 'escape' => false]),
        // format_string() escapes and runs the multilang filter, so a bilingual
        // site can also write one field with {mlang} markup instead of two.
        'hasquote'  => (trim($quote) !== ''),
        'quote'     => format_string($quote, true, ['context' => $context]),
        'hasauthor' => (trim($author) !== ''),
        'author'    => format_string($author, true, ['context' => $context]),
    ];
}

/**
 * CSS for the account-screen pictures.
 *
 * Boost writes a `.login-layout-left` rule of its own from theme_boost_get_extra_scss(),
 * which still runs here — theme_config::get_extra_scss_code() calls the parent's
 * callback and then the child's. What it cannot do is find a picture on its own:
 * theme_config::setting_file_url() builds its URL from the ACTIVE theme's name,
 * so with NIT running it looks up `theme_nit/loginbackgroundimage` and, until
 * that setting existed, found nothing and fell back to Boost's bundled
 * AI-generated photo. That fallback is what the log-in page had been showing,
 * watermark and all — and it is why the pictures belong to THIS theme rather than
 * to an upload against Boost's setting, which nothing would ever read.
 *
 * With a picture uploaded, Boost's own rule picks the log-in one up. Three things
 * it does not do are left here:
 *
 *  - the sign-up picture. Boost has no concept of a second one.
 *  - centre the crop. Boost sets `background-position: center` only for its
 *    default photo; an uploaded one gets the CSS initial value, which pins a
 *    cover-sized image to its top-left corner and cuts off everything else.
 *  - `content: none` on the watermark, per slot. Boost drops the "AI-generated
 *    image" caption once the log-in picture is uploaded, but it knows nothing
 *    about the sign-up one — so a site that sets only sign-up would otherwise
 *    caption its own photograph.
 *
 * Boost's rule and the log-in rule here are the same selector at the same
 * specificity, and this one is emitted second, so it wins on cascade order.
 * Nothing in Boost is edited or disabled; its rule is simply the one underneath.
 *
 * A slot with nothing uploaded emits nothing, so log-in falls back to Boost's
 * default (watermark included, because the caption is true of that image) and
 * sign-up falls back to log-in.
 *
 * @param theme_config $theme the theme config object (carries the settings)
 * @return string SCSS, possibly empty
 */
function theme_nit_auth_background_scss($theme): string {
    $scss = '';

    foreach (theme_nit_auth_image_slots() as $slot) {
        $url = $theme->setting_file_url($slot['setting'], $slot['filearea']);
        if (empty($url)) {
            continue;
        }

        // setting_file_url() returns a protocol-relative pluginfile URL string.
        // It is built from the file name the admin uploaded, so it can carry
        // quotes and parentheses that would end the CSS url() early.
        $safe = addcslashes((string) $url, "'\\");
        $sel = $slot['selector'];

        $scss .= "\n{$sel} {"
            . " background-image: url('{$safe}');"
            . " background-size: cover;"
            . " background-position: center;"
            . " }\n"
            // The caption belongs to Boost's default photo, and that photo is gone.
            . "{$sel}::after { content: none; }\n";
    }

    return $scss;
}

/**
 * Split a multi-language string into one plain-text value per language.
 *
 * `format_string()` — and therefore `get_formatted_name()` — answers with the
 * ONE language the page is currently being read in, which is right for almost
 * every caller and wrong for a card that has to print the Arabic name and the
 * English name side by side. This reads the raw, unfiltered value instead and
 * hands back every translation it carries, so the caller can show two at once.
 *
 * Both markups the site can hold are understood: the "Multi-language content
 * (v2)" filter's `{mlang xx}…{mlang}` — what the front-page blocks and our
 * category names use — and core's older multilang spans. A value in neither
 * form is not a translation set, so it yields an empty array and the caller
 * falls back to the formatted name.
 *
 * The pieces come back as plain text (tags dropped, entities decoded) because
 * they are written into the page with `textContent`, which escapes on output;
 * leaving them escaped here would print a literal `&amp;` on the card.
 *
 * @param string $text the raw field value, before any filter has run
 * @return array<string, string> language code (lower case, e.g. 'en', 'ar') => value
 */
function theme_nit_multilang_variants(string $text): array {
    $plain = static function (string $value): string {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    };

    $variants = [];

    // v2: {mlang en}Law{mlang}{mlang ar}القانون{mlang}. One tag may list several
    // codes ({mlang en,fr}), and "other" is a code like any other here — it is the
    // caller's business whether a fallback means anything to it.
    if (preg_match_all('/\{\s*mlang\s+([^}]+)\}(.*?)\{\s*mlang\s*\}/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            foreach (preg_split('/\s*,\s*/', trim($match[1])) as $lang) {
                $lang = strtolower(trim($lang));
                // First one wins: a name repeating a language is a typo, not an override.
                if ($lang !== '' && !isset($variants[$lang])) {
                    $variants[$lang] = $plain($match[2]);
                }
            }
        }
    }

    // v1: a span carrying class="multilang". The two attributes appear in either
    // order in the wild, so the span is matched on the class and the code is read
    // out of it afterwards rather than pinning both into one pattern.
    if (preg_match_all('/<span\b([^>]*\bmultilang\b[^>]*)>(.*?)<\/span>/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if (preg_match('/\blang\s*=\s*["\']?([a-zA-Z0-9_-]+)/', $match[1], $attr)) {
                $lang = strtolower($attr[1]);
                if (!isset($variants[$lang])) {
                    $variants[$lang] = $plain($match[2]);
                }
            }
        }
    }

    return array_filter($variants, static function (string $value): bool {
        return $value !== '';
    });
}

/**
 * The site footer (AC-4.7.13), as template context.
 *
 * The footer appears on every page, so this runs on every page: it is a handful
 * of get_config() reads, all of which Moodle's config cache already holds in
 * memory, so there is nothing here worth a cache of its own.
 *
 * The content comes from local_profilefields (the Footer tab of the Site pages
 * manager), guarded on the plugin being installed at all - theme_nit has to keep
 * rendering a page on a site that does not run it. Everything an administrator
 * typed goes through format_string(), which escapes it AND applies the multilang
 * filter, so one field can carry both languages via {mlang} markup.
 *
 * @param renderer_base $output the renderer, for the logo URLs
 * @return array|null template context for theme_nit/site_footer, or null when the
 *                    footer is switched off or the content plugin is absent
 */
function theme_nit_get_site_footer_context($output): ?array {
    global $SITE;

    if (!class_exists('\local_profilefields\footer')) {
        return null;
    }

    $data = \local_profilefields\footer::config();
    if (empty($data['enabled'])) {
        return null;
    }

    $context = context_system::instance();
    $opts = ['context' => $context];

    $rows = [];
    foreach ($data['contact']['rows'] as $row) {
        $rows[] = [
            'icon'    => $row['icon'],
            'text'    => format_string($row['text'], true, $opts),
            'url'     => $row['url'],
            'haslink' => $row['url'] !== '',
        ];
    }

    $columns = [];
    foreach ($data['columns'] as $column) {
        $links = [];
        foreach ($column['links'] as $link) {
            $links[] = [
                'label' => format_string($link['label'], true, $opts),
                // Already through clean_param(PARAM_URL) on save; out() here so a
                // site path such as /course/ becomes a real link under any wwwroot.
                'url'   => (new moodle_url($link['url']))->out(false),
            ];
        }
        $columns[] = [
            'heading'    => format_string($column['heading'], true, $opts),
            'hasheading' => trim($column['heading']) !== '',
            'links'      => $links,
        ];
    }

    $social = [];
    foreach ($data['social'] as $item) {
        $social[] = [
            'icon'    => $item['icon'],
            'url'     => $item['url'],
            // The aria-label on an icon-only link: the network's own name, which
            // is a brand and the same in both languages. It comes with the row so
            // "LinkedIn" and "X" read as their brands rather than as ucfirst() of
            // a config key.
            'network' => $item['name'],
        ];
    }

    // The site logo, read exactly the way the navbar reads it - the compact one
    // first, then the full one - so the footer never shows a different logo from
    // the corner of the same page, and an administrator who replaces the site
    // logo has replaced this one too. `get_logo_url()` is the fallback because
    // `should_display_navbar_logo()` is false on a site that set only the full
    // logo. The packaged image is the last resort, for a site that has set
    // neither: a blank column is worse than the brand's own mark.
    $logourl = $output->get_compact_logo_url(null, 200);
    if (empty($logourl)) {
        $logourl = $output->get_logo_url(null, 200);
    }
    $logourl = !empty($logourl)
        ? $logourl->out(false)
        : (new moodle_url('/theme/nit/pix/footer-logo.png'))->out(false);

    return [
        'hascontact'     => trim($data['contact']['heading']) !== '' || !empty($rows),
        'contactheading' => format_string($data['contact']['heading'], true, $opts),
        'contactrows'    => $rows,
        'columns'        => $columns,
        'haslogo'        => $logourl !== '',
        'logourl'        => $logourl,
        'sitename'       => format_string($SITE->fullname, true, $opts + ['escape' => false]),
        'hassocial'      => !empty($social),
        'social'         => $social,
        'copyright'      => format_string($data['copyright'], true, $opts),
    ];
}

