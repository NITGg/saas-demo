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
 * Language strings for block_nit_section.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Section';
$string['newsectionblock'] = 'New NIT section';
$string['nit_section:addinstance'] = 'Add a new NIT Section block';
$string['nit_section:myaddinstance'] = 'Add a new NIT Section block to the Dashboard';

// Content.
$string['mode'] = 'Content mode';
$string['mode_help'] = 'Visual editor: write formatted text with the toolbar (good for simple content). Raw HTML: paste markup that must be kept exactly — templates, scripts, precise layout — the visual editor would strip these.';
$string['mode_visual'] = 'Visual editor';
$string['mode_html'] = 'Raw HTML';
$string['content'] = 'Content';
$string['contentraw'] = 'HTML content';
$string['contentraw_help'] = 'Raw HTML, rendered exactly as written (templates and scripts included). Reference images by URL. Only roles allowed to add this block can edit it.';
$string['showtitle'] = 'Show a title';
$string['configtitle'] = 'Title';

// Layout.
$string['layoutheading'] = 'Layout';

$string['width'] = 'Width';
$string['width_help'] = 'How much of the region row this block occupies. On small screens every block becomes full width automatically.';
$string['width_full'] = 'Full width';
$string['width_twothirds'] = 'Two thirds (2/3)';
$string['width_half'] = 'Half (1/2)';
$string['width_third'] = 'One third (1/3)';
$string['width_quarter'] = 'One quarter (1/4)';

$string['align'] = 'Horizontal position';
$string['align_help'] = 'When the block is narrower than the full row, this sets whether it sits at the start, centre or end of the row. "Stretch" lets blocks flow next to each other.';
$string['align_stretch'] = 'Flow (sit next to others)';
$string['align_start'] = 'Start';
$string['align_center'] = 'Centre';
$string['align_end'] = 'End';

$string['margintop'] = 'Space above';
$string['margintop_help'] = 'A number in the unit chosen below (e.g. 24 with unit "px", or 2 with unit "rem"). Leave blank to keep the theme default spacing.';
$string['marginbottom'] = 'Space below';
$string['marginbottom_help'] = 'A number in the unit chosen below. Leave blank to keep the theme default spacing.';
$string['spacingunit'] = 'Spacing unit';
$string['spacingnumeric'] = 'Enter a number only (e.g. 24). Leave blank for the default.';

$string['attachprev'] = 'Attach to the block above';
$string['attachprev_help'] = 'Removes the gap above this block so it sits flush against the block above it.';

$string['plain'] = 'Plain (no card frame)';
$string['plain_help'] = 'Removes the card border, background and padding so the content sits edge to edge — useful for hero banners and full-bleed sections.';

// Privacy.
$string['privacy:metadata'] = 'The NIT Section block only stores content configured within the block instance.';
