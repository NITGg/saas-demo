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
 * NIT theme configuration — a thin Boost child (M2 foundation).
 *
 * Only the `frontpage` layout is redefined (to add full-width Site-home block
 * regions — see below); every other layout inherits Boost's map via the
 * per-key merge in theme_config. Template/renderer overrides are governed by
 * docs/override-budget.md.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'nit';

// Presentation inherits Boost; NIT layers SCSS around it (never forks it).
$THEME->parents = ['boost'];

$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;

// SCSS composition: pre_scss -> (Boost preset + NIT post) -> extra_scss.
$THEME->scss = function($theme) {
    return theme_nit_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_nit_get_pre_scss';
$THEME->extrascsscallback = 'theme_nit_get_extra_scss';

// Keep the overridden renderer factory so M4 can add renderer overrides
// without a config change.
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;

// -----------------------------------------------------------------------------
// Front page (Site home) — full-width block regions.
//
// The Site home is a P1 marketing/landing screen. Boost's `frontpage` layout only
// exposes `side-pre` (a sidebar), which cannot host page-wide hero/section blocks.
// NIT overrides ONLY the `frontpage` layout — every other layout still inherits
// Boost, because theme_config merges the layout map per key (5.02: see the
// parent/child cascade in theme_config::__construct) — to add stacking regions:
//
//   fullwidth-top     — hero band, edge-to-edge, above everything
//   above-content     — between the header and the main content column
//   below-content     — after the main content, before the footer
//   fullwidth-bottom  — CTA / closing band, edge-to-edge
//
// `side-pre` is kept so the inherited sidebar still works. Sections are authored
// with core / NIT-owned blocks — no edumy / cocoon dependency. The matching
// layout file + template are NIT-owned (theme/nit/layout/frontpage.php and
// theme/nit/templates/frontpage.mustache); see docs/override-budget.md.
$THEME->layouts['frontpage'] = [
    'file' => 'frontpage.php',
    'regions' => ['fullwidth-top', 'above-content', 'side-pre', 'below-content', 'fullwidth-bottom'],
    'defaultregion' => 'fullwidth-top',
    'options' => ['nonavbar' => true],
];

// -----------------------------------------------------------------------------
// NIT full-width page — an edge-to-edge canvas that keeps the top navbar and
// footer but drops the content column max-width, the page heading, and the
// secondary navigation tabs. Used by NIT-owned marketing/catalog pages such as
// the category details page (local_nit_category). See theme/nit/layout/fullwidth.php.
$THEME->layouts['nit_fullwidth'] = [
    'file' => 'fullwidth.php',
    'regions' => [],
];
