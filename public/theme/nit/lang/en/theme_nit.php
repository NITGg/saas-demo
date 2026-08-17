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
 * Language strings for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT';
$string['choosereadme'] = 'NIT is a Boost-based theme foundation for the NIT LMS Framework. This M2 release provides the theme skeleton and asset pipeline; the design system and branding arrive in later milestones.';
$string['configtitle'] = 'NIT settings';
$string['frontpagecachettl'] = 'Front page cache lifetime';
$string['frontpagecachettl_desc'] = 'How long the Site home caches its course cards and site counters before recomputing them from the database. Higher values reduce database load on the busiest page but make the numbers slightly staler. Set to 0 to disable caching (recompute on every request).';
$string['foundation'] = 'Foundation';
$string['gallery'] = 'NIT Design System — Component Gallery';
$string['foundation_desc'] = 'This is the M2 foundation release: a thin Boost child with the SCSS and JavaScript build pipeline in place. Branding and component controls arrive in later milestones.';

// Colour palette (edited on the gallery page).
$string['colours'] = 'Colour palette';
$string['colours_desc'] = 'Edit the site colour palette on the design-system gallery page:';
$string['coloureditor'] = 'Colour palette';
$string['coloureditor_desc'] = 'The colours the whole site is built from. Each is published as a CSS custom property (<code>--nit-primary</code>, <code>--nit-navbaraccent</code>, …), so components — the navbar included — read their colour from here. Pick a colour and save to recolour the site.';
$string['colourssaved'] = 'Colour palette saved. The theme CSS has been rebuilt.';
$string['coloursreset'] = 'Colour palette reset to the defaults.';
$string['savecolours'] = 'Save colours';
$string['resetcolours'] = 'Reset to defaults';

// Brand Colors palette (the new semantic layer — edited on the gallery page).
$string['brandcolours_desc'] = 'The semantic colours the whole site is built from. Each role is published as a CSS custom property (<code>--nit-brand-primary</code>, <code>--nit-brand-surface</code>, …) that defaults to <strong>Group 1</strong>. A component can opt into another group by carrying its class (<code>.nit-brand-2</code>, <code>.nit-brand-3</code>) — same variable names, that group\'s values. Pick a colour and save to recolour the site.';
$string['brandcolourssaved'] = 'Brand Colors saved. The theme CSS has been rebuilt.';
$string['brandcoloursreset'] = 'Brand Colors reset to the defaults.';
$string['savebrandcolours'] = 'Save Brand Colors';
$string['resetbrandcolours'] = 'Reset to defaults';

// Category styles (assign a brand group to each category details page).
$string['categorystyles_desc'] = 'Choose which Brand Colors group each category\'s details page uses. The page re-skins from that group (via the <code>.nit-brand-2</code> / <code>.nit-brand-3</code> switch classes); <strong>Group 1</strong> is the default look. Tune each group\'s colours on the Brand Colors tab.';
$string['categorystyles_col_category'] = 'Category';
$string['categorystyles_col_group'] = 'Brand group';
$string['categorystyles_none'] = 'No categories found.';
$string['savecategorygroups'] = 'Save category styles';
$string['categorygroupssaved'] = 'Category styles saved.';

// Design-system gallery tabs.
$string['tab_brandcolours'] = 'Brand Colors';
$string['tab_categorystyles'] = 'Category styles';
$string['tab_colours'] = 'Colours';
$string['tab_fonts'] = 'Fonts';
$string['tab_components'] = 'Components';

// Fonts (edited on the gallery page). One self-hosted font file per site
// language: applied when the site runs in that language.
$string['fonts'] = 'Fonts';
$string['fonts_desc'] = 'Upload a font file (.ttf or .otf) for each site language. The English font is applied when the site is in English (<code>html[lang="en"]</code>) and the Arabic font when the site is in Arabic (<code>html[lang="ar"]</code>). Fonts are self-hosted — no external request is ever made. Leave a slot empty to keep the current font; the built-in system font is used until you upload one.';
$string['fonten'] = 'English font';
$string['fontar'] = 'Arabic font';
$string['fonten_help'] = 'Applied when the site language is English.';
$string['fontar_help'] = 'Applied when the site language is Arabic.';
$string['fontactive'] = 'Active';
$string['fontnone'] = 'Using the default system font.';
$string['fontpreview'] = 'Preview';
$string['fontsampleen'] = 'The quick brown fox jumps over the lazy dog — 0123456789';
$string['fontsamplear'] = 'أبجد هوّز حطّي كلمن — نصّ تجريبي ٠١٢٣٤٥٦٧٨٩';
$string['savefonts'] = 'Save fonts';
$string['resetfonts'] = 'Remove all fonts';
$string['fontssaved'] = 'Fonts saved. The theme CSS has been rebuilt.';
$string['fontsreset'] = 'Fonts removed. The site is back to the default system font.';
$string['fontinvalidtype'] = 'The {$a} was ignored: only .ttf and .otf font files are accepted.';
$string['fontuploaderror'] = 'The {$a} could not be uploaded. Please try again.';

// Sign-up page: prompt sending existing users to the login page.
$string['alreadyhaveaccount'] = 'Already have an account?';
$string['logintoaccount'] = 'Log in';

// Block region (inherited Boost layouts use side-pre).
$string['region-side-pre'] = 'Right';

// Front page (Site home) full-width block regions — see config.php.
$string['region-fullwidth-top'] = 'Full width (top)';
$string['region-above-content'] = 'Above content';
$string['region-below-content'] = 'Below content';
$string['region-fullwidth-bottom'] = 'Full width (bottom)';

// Privacy.
$string['privacy:metadata'] = 'The NIT theme does not store any personal data.';

// Branded course-detail page (theme_nit\output\format_topics_renderer).
$string['acad_browse'] = 'Browse';
$string['acad_about'] = 'About';
$string['acad_skills_tab'] = 'Skills';
$string['acad_requirements'] = 'Requirements';
$string['acad_modules'] = 'Modules';
$string['acad_instructor'] = 'Instructor:';
$string['acad_plusmore'] = '+{$a} more';
$string['acad_gotocourse'] = 'Go to course';
$string['acad_enrol'] = 'Enroll';
$string['acad_free'] = 'Free';
$string['acad_starts'] = 'Starts {$a}';
$string['acad_enrolledcount'] = '{$a} already enrolled';
$string['acad_ataglance'] = 'At a glance';
$string['acad_nmodules'] = '{$a} modules';
$string['acad_duration'] = 'Duration';
$string['acad_nhours'] = '{$a} hours';
$string['acad_assessments'] = 'Assessments';
$string['acad_nassessments'] = '{$a} assessments';
$string['acad_language'] = 'Language';
$string['acad_certificate'] = 'Certificate';
$string['acad_certificate_sub'] = 'Shareable certificate';
$string['acad_learn'] = 'What you\'ll learn';
$string['acad_skills'] = 'Skills you\'ll gain';
$string['acad_audience'] = 'Who this course is for';
$string['acad_prerequisites'] = 'Prerequisites';
$string['acad_about_h'] = 'About this course';
$string['acad_nmodulesin'] = 'There are {$a} modules in this course';
$string['acad_modulen'] = 'Module {$a}';
$string['acad_nitems'] = '{$a} items';
$string['acad_moduledetails'] = 'Module details';
$string['acad_included'] = 'What\'s included';
$string['acad_instructors'] = 'Instructors';
$string['acad_instructorrole'] = 'Instructor';
$string['acad_offeredby'] = 'Offered by';
// Singular count variants.
$string['acad_nmodule'] = '{$a} module';
$string['acad_nhour'] = '{$a} hour';
$string['acad_nassessment'] = '{$a} assessment';
$string['acad_nitem'] = '{$a} item';
$string['acad_1modulein'] = 'There is {$a} module in this course';
