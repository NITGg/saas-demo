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
 * Language strings for local_nit_core.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Core';

// Privacy.
$string['privacy:metadata'] = 'The NIT Core plugin is an SDK layer and does not store any personal data itself.';

// Cache definitions.
$string['cachedef_registry'] = 'NIT Core shared registry cache';

// Audit events.
$string['event_entity_created'] = 'NIT entity created';
$string['event_entity_updated'] = 'NIT entity updated';
$string['event_entity_deleted'] = 'NIT entity deleted';

// Rendering seam (M4).
$string['showwelcomepanel'] = 'Show dashboard welcome panel';
$string['showwelcomepanel_desc'] = 'Reference implementation of the NIT rendering seam: when enabled, a welcome panel (built by the SDK, rendered by the theme) appears on the dashboard. Off by default.';
$string['welcome_greeting'] = 'Welcome back, {$a}!';
$string['welcome_message'] = 'Here is your learning at a glance.';
$string['welcome_cta'] = 'Go to my courses';

// Branding (M5).
$string['branding'] = 'NIT Branding';
$string['brand_preset'] = 'Brand preset';
$string['brand_preset_desc'] = 'Choose a sector preset. It sets the primary colour, font and density; you can override individual values below.';
$string['brand_primary'] = 'Primary colour';
$string['brand_primary_desc'] = 'Overrides the preset primary colour. Leave empty to use the preset. A readable foreground is chosen automatically.';
$string['brand_accent'] = 'Accent colour';
$string['brand_accent_desc'] = 'Optional accent colour. Leave empty to use the preset.';
$string['brand_font'] = 'Font';
$string['brand_font_desc'] = 'Overrides the preset font. Fonts are self-hosted; no external request is made.';
$string['brand_font_default'] = 'Use preset font';
$string['brand_font_sans'] = 'Sans';
$string['brand_font_rounded'] = 'Rounded';
$string['brand_font_serif'] = 'Serif';
$string['preset_corporate'] = 'Corporate';
$string['preset_education'] = 'Education';
$string['preset_medical'] = 'Medical';
$string['preset_government'] = 'Government';
$string['preset_kids'] = 'Kids';
$string['preset_university'] = 'University';

// Feature flags (M6).
$string['features'] = 'NIT Features';
$string['flagdomain_ui'] = 'User interface';
$string['flagdomain_experimental'] = 'Experimental';
$string['flag_ui_compact_mode'] = 'Compact mode';
$string['flag_ui_compact_mode_desc'] = 'Demo flag: a denser UI layout. Proves the feature-flag engine end to end.';
$string['flag_experimental_preview'] = 'Experimental preview';
$string['flag_experimental_preview_desc'] = 'Demo flag in a second domain, to show grouping. No effect yet.';

// Errors.
$string['error_unknownservice'] = 'Unknown NIT service: {$a}';
$string['error_invalidservice'] = 'Registered service for "{$a}" is not a valid object.';
