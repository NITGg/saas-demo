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
 * NIT theme settings (M2 placeholder) + admin links.
 *
 * Branding controls (colours, logo, fonts, presets) arrive in M5. For now this
 * reserves the settings surface and adds an admin-only link to the design-system
 * gallery under Site administration → Appearance.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The footer settings below use theme helpers; the admin tree can be built
// (e.g. on a settings save) before the theme library has been loaded.
require_once(__DIR__ . '/lib.php');

// Admin-only link to the design-system gallery (not shown to end users).
$ADMIN->add('appearance', new admin_externalpage(
    'theme_nit_gallery',
    get_string('gallery', 'theme_nit'),
    new moodle_url('/theme/nit/gallery.php'),
    'moodle/site:config'
));

// Admin-only homepage template picker (T1..T10).
$ADMIN->add('appearance', new admin_externalpage(
    'theme_nit_homepage',
    get_string('homepagetemplates', 'theme_nit'),
    new moodle_url('/theme/nit/homepage.php'),
    'moodle/site:config'
));

// Admin-only homepage content editor (fills the template's editable hooks).
$ADMIN->add('appearance', new admin_externalpage(
    'theme_nit_homepage_content',
    get_string('homepagecontent', 'theme_nit'),
    new moodle_url('/theme/nit/homepage_content.php'),
    'moodle/site:config'
));

// Site footer content (Bassthalk-style footer on every page): texts, the pages
// column and the social links. Read by theme_nit_footer_context().
$footerpage = new admin_settingpage('theme_nit_footer', get_string('footersettings', 'theme_nit'));
if ($ADMIN->fulltree) {
    $footerpage->add(new admin_setting_configtextarea(
        'theme_nit/footer_description',
        get_string('footerdescription', 'theme_nit'),
        get_string('footerdescription_desc', 'theme_nit'),
        'تم صنع هذه المنصة بهدف تهيئة الطالب لـ كامل جوانب الثانوية العامة و ما بعدها',
        PARAM_TEXT, 60, 3
    ));
    $footerpage->add(new admin_setting_configtext(
        'theme_nit/footer_copyright',
        get_string('footercopyright', 'theme_nit'),
        get_string('footercopyright_desc', 'theme_nit'),
        'جميع الحقوق محفوظة © {year}',
        PARAM_TEXT
    ));
    $footerpage->add(new \theme_nit\admin_setting_footerpages(
        'theme_nit/footer_pages',
        get_string('footerpages', 'theme_nit'),
        get_string('footerpages_desc', 'theme_nit'),
        json_encode(theme_nit_footer_pages_default(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ));
    $footerpage->add(new admin_setting_heading(
        'theme_nit/footer_socialheading',
        get_string('footersocial', 'theme_nit'),
        get_string('footersocial_desc', 'theme_nit')
    ));
    foreach (theme_nit_footer_social_networks() as $key => $net) {
        $footerpage->add(new admin_setting_configtext(
            'theme_nit/footer_' . $key,
            get_string('footersocial_' . $key, 'theme_nit'),
            '',
            '',
            PARAM_URL
        ));
    }
}
$ADMIN->add('appearance', $footerpage);

// Bassthalk home: the code-owned front-page sections (theme_nit/bassthalk/*)
// and their texts/images. Read by theme_nit_bthhome_context().
$bthhomepage = new admin_settingpage('theme_nit_bthhome', get_string('bthhome', 'theme_nit'));
if ($ADMIN->fulltree) {
    $bthdefaults = theme_nit_bthhome_defaults();
    $bthhomepage->add(new admin_setting_configcheckbox(
        'theme_nit/bthhome_enabled',
        get_string('bthhome_enabled', 'theme_nit'),
        get_string('bthhome_enabled_desc', 'theme_nit'),
        1
    ));
    $bthhomepage->add(new admin_setting_heading(
        'theme_nit/bthhome_heroheading',
        get_string('bthhome_hero', 'theme_nit'),
        get_string('bthhome_hero_desc', 'theme_nit')
    ));
    foreach (['hero_title1', 'hero_title2', 'hero_highlight'] as $key) {
        $bthhomepage->add(new admin_setting_configtext(
            'theme_nit/bthhome_' . $key,
            get_string('bthhome_' . $key, 'theme_nit'),
            get_string('bthhome_' . $key . '_desc', 'theme_nit'),
            $bthdefaults[$key]
        ));
    }
    $bthhomepage->add(new admin_setting_configtextarea(
        'theme_nit/bthhome_hero_text',
        get_string('bthhome_hero_text', 'theme_nit'),
        get_string('bthhome_hero_text_desc', 'theme_nit'),
        $bthdefaults['hero_text']
    ));
    $bthhomepage->add(new admin_setting_configtext(
        'theme_nit/bthhome_hero_button',
        get_string('bthhome_hero_button', 'theme_nit'),
        get_string('bthhome_hero_button_desc', 'theme_nit'),
        $bthdefaults['hero_button']
    ));
    $heroimage = new admin_setting_configstoredfile(
        'theme_nit/bthhome_hero_image',
        get_string('bthhome_hero_image', 'theme_nit'),
        get_string('bthhome_hero_image_desc', 'theme_nit'),
        'bthheroimage', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.svg', '.webp']]
    );
    $heroimage->set_updatedcallback('theme_reset_all_caches');
    $bthhomepage->add($heroimage);
    $heroimagemobile = new admin_setting_configstoredfile(
        'theme_nit/bthhome_hero_imagemobile',
        get_string('bthhome_hero_imagemobile', 'theme_nit'),
        get_string('bthhome_hero_imagemobile_desc', 'theme_nit'),
        'bthheroimagemobile', 0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.svg', '.webp']]
    );
    $heroimagemobile->set_updatedcallback('theme_reset_all_caches');
    $bthhomepage->add($heroimagemobile);
}
$ADMIN->add('appearance', $bthhomepage);

// A second, coloured logo on the core Logos page (Appearance → Logos), for the
// light backgrounds: the site footer, the log-in card and the registration card.
// Added to the existing core page through the admin tree — core is not edited.
if ($ADMIN->fulltree) {
    $logospage = $ADMIN->locate('logos');
    if ($logospage instanceof admin_settingpage) {
        $brandlogo = new admin_setting_configstoredfile(
            'theme_nit/brandlogo',
            get_string('brandlogo', 'theme_nit'),
            get_string('brandlogo_desc', 'theme_nit'),
            'brandlogo', 0,
            ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png', '.svg', '.webp']]
        );
        $brandlogo->set_updatedcallback('theme_reset_all_caches');
        $logospage->add($brandlogo);
    }
}

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('themesettingnit', get_string('configtitle', 'theme_nit'));

    $settings->add(new admin_setting_heading(
        'theme_nit/foundationinfo',
        get_string('foundation', 'theme_nit'),
        get_string('foundation_desc', 'theme_nit')
    ));

    // The colour palette is edited on the design-system gallery page
    // (Appearance → NIT Design System), not here — it lives beside the live
    // component preview so changes can be seen in context.
    $settings->add(new admin_setting_description(
        'theme_nit/colourslink',
        get_string('colours', 'theme_nit'),
        get_string('colours_desc', 'theme_nit') . ' ' .
            html_writer::link(
                new moodle_url('/theme/nit/gallery.php'),
                get_string('gallery', 'theme_nit')
            )
    ));

    // Performance: how long the Site home caches its course cards + site
    // counters before recomputing them (see theme_nit_get_courses /
    // theme_nit_get_site_stats in lib.php). Higher = less DB load but staler
    // numbers. Set to 0 to disable caching. The picker stores seconds.
    $settings->add(new admin_setting_configduration(
        'theme_nit/frontpagecachettl',
        get_string('frontpagecachettl', 'theme_nit'),
        get_string('frontpagecachettl_desc', 'theme_nit'),
        300
    ));
}
