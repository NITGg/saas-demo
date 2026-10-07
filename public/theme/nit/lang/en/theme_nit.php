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
// T1 "Modern Minimal" course landing.
$string['acad_curriculum'] = 'Curriculum';
$string['acad_nlessons'] = '{$a} lessons';
$string['acad_1lesson'] = '{$a} lesson';
$string['acad_nlearners'] = '{$a} learners';
$string['acad_1learner'] = '{$a} learner';
$string['acad_updated'] = 'Updated {$a}';
$string['acad_lifetime'] = 'Lifetime access';
$string['acad_buynow'] = 'Buy now';
$string['acad_insubscription'] = 'In your subscription';
$string['acad_lockedlesson'] = 'Locked';
$string['acad_currency'] = 'EGP';
$string['acad_tababout'] = 'About the course';
$string['acad_tabforum'] = 'Forum';
$string['acad_tabreviews'] = 'Reviews';
$string['acad_nreviews'] = '{$a} reviews';
$string['acad_ratingof'] = 'Rated {$a} out of 5';
$string['acad_ratebtn'] = 'Rate the course and its teachers';
$string['acad_noreviews'] = 'No reviews yet.';
$string['acad_lessons'] = 'Lessons';
$string['acad_lessonslabel'] = 'Lessons:';
$string['acad_subscribe'] = 'Subscribe now!';
$string['acad_discount'] = '{$a}% off';
$string['acad_parthide'] = 'Hide';
$string['acad_partshow'] = 'Show';
$string['acad_noitems'] = 'Nothing in this lesson yet';

// Inline front-page editor (theme/nit/js/editor.js + edit.php).
$string['edit_editpage'] = 'Edit page';
$string['edit_done'] = 'Done';
$string['edit_editimage'] = 'Edit image';
$string['edit_savefailed'] = 'Could not save';
$string['edit_imagetoolarge'] = 'Image is too large.';
$string['edit_editlogo'] = 'Edit logo';
$string['edit_editbrand'] = 'Edit branding';
$string['edit_academyname'] = 'Academy name';
$string['edit_replacelogo'] = 'Replace logo';
$string['edit_favicon'] = 'Favicon';
$string['edit_faviconhint'] = 'The small icon shown in the browser tab. A square PNG works best.';
$string['edit_replacefavicon'] = 'Replace favicon';
$string['edit_loginbg'] = 'Login page background';
$string['edit_loginbghint'] = 'Shown behind the login & sign-up form. A dark overlay is added for readability.';
$string['edit_edithero'] = 'Edit hero';
$string['edit_replaceimage'] = 'Replace image';
$string['edit_heroheight'] = 'Height';
$string['edit_save'] = 'Save';
$string['edit_saving'] = 'Saving…';
$string['edit_cancel'] = 'Cancel';
$string['edit_editabout'] = 'Edit about';
$string['edit_aboutpoints'] = 'Points';
$string['edit_aboutsubheader'] = 'Subheader';
$string['edit_addpoint'] = 'Add point';
$string['edit_addcourse'] = 'Add course';
$string['edit_editgallery'] = 'Edit gallery';
$string['edit_addimage'] = 'Add image';
$string['edit_galleryempty'] = 'No images yet.';
$string['edit_gallerymax'] = 'Maximum {n} images.';
$string['upgrade_link'] = 'Upgrade plan';
$string['edit_deleteconfirm'] = 'Delete this image?';
$string['edit_close'] = 'Close';
$string['edit_editapps'] = 'Edit app links';
$string['edit_appsandroid'] = 'Google Play URL';
$string['edit_appsios'] = 'App Store URL';
$string['edit_appshint'] = 'Leave empty to use the default NIT Academy app link for this academy (opens the app pointed here).';
$string['edit_editcontact'] = 'Edit contact';
$string['edit_c_phone'] = 'Phone';
$string['edit_c_whatsapp'] = 'WhatsApp';
$string['edit_c_facebook'] = 'Facebook URL';
$string['edit_c_instagram'] = 'Instagram URL';
$string['edit_c_youtube'] = 'YouTube URL';
$string['edit_c_tiktok'] = 'TikTok URL';
$string['edit_c_website'] = 'Website URL';
$string['edit_editfooter'] = 'Edit footer';
$string['edit_footername'] = 'Academy name';
$string['edit_footerdesc'] = 'Description';
$string['edit_footershowlogo'] = 'Show logo';
$string['edit_colours'] = 'Colours';
$string['edit_palettenote'] = 'Text, borders and hover shades are derived automatically.';
$string['edit_col_primary'] = 'Primary';
$string['edit_col_accent'] = 'Accent';
$string['edit_col_secondary'] = 'Secondary';
$string['edit_col_background'] = 'Background';
$string['edit_col_surface'] = 'Surface';
$string['edit_col_text'] = 'Text';
$string['edit_gallerydraghint'] = 'Drag to reorder. Changes apply when you press Save.';
$string['edit_new'] = 'new';

// Download-apps band (front page, before the footer).
$string['download_title'] = 'Get the app';
$string['download_sub'] = 'Learn on the go — download the NIT Academy app for Android or iOS.';
$string['download_geton'] = 'Get it on';

// Homepage template picker (T1..T10) — theme/nit/homepage.php.
$string['homepagetemplates'] = 'Homepage template';
$string['homepagetemplates_desc'] = 'Choose the look of this academy\'s homepage. Applying rewrites the homepage sections from the selected template; the academy owner then adds their own images and brand colour on top.';
$string['applytemplate'] = 'Apply this template';
$string['templatereapply'] = 'Re-apply';
$string['templatecurrent'] = 'Current';
$string['templateapplied'] = 'Homepage template "{$a}" applied.';
$string['templateunknown'] = 'Unknown template.';
$string['viewhomepage'] = 'View homepage';
$string['applyconfirm'] = 'Apply the "{$a}" template? This replaces the current homepage sections. Any images or copy already added to those sections will be overwritten.';
$string['applywarning'] = 'Applying a template replaces the homepage sections with the template\'s layout. Do this before the owner adds their images and content — re-applying later resets those sections.';
$string['invalidtemplate'] = 'Unknown homepage template: {$a}';

// Homepage content editor — theme/nit/homepage_content.php.
$string['homepagecontent'] = 'Homepage content';
$string['homepagecontent_desc'] = 'Edit the text and images of this academy\'s homepage template. Bilingual fields have English + Arabic. Leave a field blank to keep the template default. Course/subscription/coupon content is dynamic and filled automatically.';
$string['contentsaved'] = 'Homepage content saved.';
$string['contentimghint'] = 'Choose an image to replace this one. Leave empty to keep the current image.';

// Auth screens (login / signup) — theme_nit login_layout override.
$string['welcometosite'] = 'Welcome to {$a}';
$string['authsidetagline'] = 'Short, structured courses taught by working professionals. Learn at your own pace and earn a certificate.';
$string['loginwelcomeback'] = 'Welcome back';
$string['logincontinue'] = 'Sign in to continue learning.';
$string['keepsignedin'] = 'Keep me signed in';

// Signup screen (T1 "Create your account").
$string['signupcreatetitle'] = 'Create your account';
$string['signupsubtitle'] = 'Free to start — no card needed.';
$string['signupstepaccount'] = 'Account';
$string['signupstepverify'] = 'Verify';
$string['signupstepdone'] = 'Done';

$string['showpassword'] = 'Show password';

// Homepage editor — design panel (v2026.09.86).
$string['edit_discard'] = 'Discard';
$string['edit_unsaved'] = 'You have unsaved changes. Discard them?';
$string['edit_sidehint'] = 'Select a section to edit its text, images and links. Changes preview live; Publish to make them live.';
$string['edit_pagesections'] = 'PAGE SECTIONS';
$string['edit_design'] = 'DESIGN';
$string['edit_settings'] = 'settings';
$string['edit_moveup'] = 'Move up';
$string['edit_movedown'] = 'Move down';
$string['edit_hidesection'] = 'Hide section';
$string['edit_showsection'] = 'Show section';
$string['edit_resetsection'] = 'Reset to template (drops this section\'s edits)';
$string['edit_resetconfirm'] = 'Reset this section to the template? Its edits will be lost.';
$string['edit_addsection'] = 'Add a section';
$string['edit_noeditable'] = 'This section has no editable text or images; it renders live Moodle data.';
$string['edit_notlicensed'] = 'Not included in your plan.';
$string['edit_noreviews'] = 'No learner reviews with text yet — they appear here once learners rate a course.';
$string['edit_noitems'] = 'Nothing to pick yet.';
$string['edit_pickshint'] = 'Untick to hide an item from the homepage. All ticked = show everything.';
$string['edit_selectall'] = 'All';
$string['edit_selectnone'] = 'None';
$string['edit_aboutcards'] = 'Feature cards (max 5)';
$string['edit_cardtitle'] = 'Title';
$string['edit_cardtext'] = 'Text';
$string['edit_addcard'] = 'Add a card';
$string['edit_remove'] = 'Remove';
$string['edit_navlinks'] = 'Navbar links';
$string['edit_navlinkshint'] = 'Pick 3 to 5 pages for the top navigation, in order. None = Moodle\'s default menu.';
$string['edit_footerlinks'] = 'Footer links';
$string['edit_footerlinkshint'] = 'Pages listed in the footer link column, in order. None = the template defaults.';
$string['edit_authwelcome'] = 'Welcome title';
$string['edit_authtagline'] = 'Tagline';
$string['edit_loginpreview'] = 'Login preview';
$string['edit_col_onprimary'] = 'Text on buttons';
$string['edit_auto'] = 'Auto';
$string['edit_toolarge'] = 'Image too large';
$string['edit_advanced'] = 'Advanced…';
$string['edit_hidepanel'] = 'Hide editing panel';
$string['edit_showpanel'] = 'Show editing panel';
$string['edit_publish'] = 'Publish';
$string['edit_unpublished'] = 'Changes preview on the page only. Publish to make them live, or Discard.';
$string['edit_nochanges'] = 'No unpublished changes.';
$string['edit_herohighlight'] = 'Highlighted words (accent colour)';
$string['edit_selected'] = 'selected';
$string['edit_pickatleast'] = 'pick at least';
$string['edit_max'] = 'max';
$string['edit_structurewarn'] = 'This changes the page structure now and discards your unpublished edits. Continue?';
$string['edit_loginpage'] = 'Login page';
$string['edit_signuppage'] = 'Signup page';
$string['edit_signuphint'] = 'Leave empty to reuse the login texts.';

// ── Design Gallery / Brand Colors suite (navbar, category, mode, logo, fonts, auth) — ported from EAAC ──
$string['brandgroupswitch'] = 'Which group to edit';
$string['navbarshape_usage'] = 'tick any, or none — the ticked marks are drawn together';
$string['navbarsize'] = 'size in pixels';
$string['navbarweight'] = 'font weight';
$string['navbarglass'] = 'Background transparency';
$string['navbarglass_usage'] = 'let the page show through the bar, and by how much';
$string['navbarglass_on'] = 'Apply transparency';
$string['navbarglass_degree'] = 'transparency percentage';
$string['navbarscroll'] = 'Style once scrolled';
$string['navbarscroll_usage'] = 'the bar wears this group\'s navbar style from the moment the page moves';
$string['navbarscroll_same'] = 'Same as this group (no change)';
$string['btnoutlinefill'] = 'Fill the background';
$string['btnoutlinefill_usage'] = 'off = transparent, so the button takes the colour of whatever it sits on';
$string['categorystyles_col_stylefor'] = 'Style — {$a}';
$string['categorystyles_col_logofor'] = 'Logo — {$a}';
$string['categorystyles_col_logolight'] = 'Logo — light mode';
$string['categorystyles_col_logodark'] = 'Logo — dark mode';
$string['categorystyles_sitedefault'] = 'Site default';
$string['categorystyles_nologo'] = 'Site logo';
$string['categorystyles_removelogo'] = 'Remove';
$string['categorystyles_col_image'] = 'Image';
$string['categorystyles_noimage'] = 'No image';
$string['categorystyles_editimage'] = 'Set image';
$string['categorylogouploaderror'] = 'Could not upload {$a}. Please try again.';
$string['categorylogoinvalidtype'] = '{$a} must be a PNG, JPG, WebP, GIF or SVG image.';
$string['categorylogotoomany'] = '{$a} logo file(s) were not saved: this server accepts only a limited number of uploads in one request. Upload a few categories at a time.';
$string['sitestyles'] = 'Site styles';
$string['sitestyles_desc'] = 'Choose which Brand Colors group the site uses in each display mode. The light/dark button in the navigation bar switches between the two — it carries no palette of its own, it selects one of the groups below, so a visitor pressing it sees exactly the colours you tuned on the Brand Colors tab. Give the two modes different groups; when they are the same the button changes nothing and is not shown. "Default" is the mode a visitor who has never pressed the button opens in; once pressed, the browser remembers their choice. Pages inside a category that has its own styles use those instead, and the button moves between that category\'s pair.';
$string['sitestyles_col_mode'] = 'Display mode';
$string['sitestyles_col_group'] = 'Brand group';
$string['sitestyles_col_default'] = 'Default';
$string['sitestyles_default_aria'] = 'Open the site in {$a} for visitors who have not chosen a mode';
$string['savesitestyles'] = 'Save site styles';
$string['sitestylessaved'] = 'Site styles saved.';
$string['homechrome'] = 'Home page chrome';
$string['homechrome_desc'] = 'Choose whether the navigation bar and the site footer are shown on the home page. This affects the home page only — every other page keeps both. A part that is switched off is left out of the page entirely, so it takes up no room. Both are always shown while edit mode is on, because the edit-mode switch and the user menu are on the navigation bar.';
$string['homechrome_navbar'] = 'Show the navigation bar on the home page';
$string['homechrome_navbar_desc'] = 'Off: the home page starts at the top of the screen with the first block; the navigation bar (logo, menu, search, language, log in) is not drawn there.';
$string['homechrome_footer'] = 'Show the footer on the home page';
$string['homechrome_footer_desc'] = 'Off: the home page ends with its last block; the site footer band (contact details, link columns, copyright) is not drawn there.';
$string['savehomechrome'] = 'Save home page chrome';
$string['homechromesaved'] = 'Home page chrome saved.';
$string['modelight'] = 'Light mode';
$string['modedark'] = 'Dark mode';
$string['modeswitchtolight'] = 'Switch to light mode';
$string['modeswitchtodark'] = 'Switch to dark mode';
$string['tab_changestyle'] = 'Change style';
$string['tab_authscreens'] = 'Log-in &amp; sign-up';
$string['authscreens_desc'] = 'The picture beside the log-in and sign-up cards, and the quote drawn over it. The site logo is drawn there too — it is the same logo the navigation bar shows (Site administration → Appearance → Logos), so it never needs setting twice and never goes stale. Nothing here appears below 992px wide: the panel is hidden on phones and tablets, where the form fills the screen.';
$string['authimagelogin'] = 'Log-in page picture';
$string['authimagelogin_desc'] = 'Shown on the log-in screen — and on the rest of the account flow (forgotten password, e-mail confirmation) unless a sign-up picture below overrides it. Leave empty to keep Moodle\'s bundled default photo, which carries an "AI-generated image" caption.';
$string['authimagesignup'] = 'Sign-up page picture';
$string['authimagesignup_desc'] = 'Shown on the create-account screen only. Leave empty to use the log-in picture there as well.';
$string['authimageactive'] = 'In use';
$string['authimagenone'] = 'No picture uploaded.';
$string['authimageremove'] = 'Remove this picture when saving';
$string['authimageinvalidtype'] = 'The {$a} was ignored: only .jpg, .png and .webp images are accepted.';
$string['authimageuploaderror'] = 'The {$a} could not be uploaded. Please try again.';
$string['authquote'] = 'Quote';
$string['authquote_desc'] = 'Drawn in a card at the foot of the picture. Write it in each site language — a learner reading the site in Arabic should not be shown English here. Either language may be left empty; whichever is filled in is used for both. Leave both empty and no card is drawn at all. Type any quotation marks you want — none are added for you.';
$string['authquotetext'] = 'Quote text';
$string['authquoteauthor'] = 'Attribution';
$string['authquoteauthorplaceholder'] = 'Brian Herbert · Educational Leader';
$string['saveauthscreens'] = 'Save log-in &amp; sign-up';
$string['authscreenssaved'] = 'Log-in and sign-up screens saved. The theme CSS has been rebuilt.';
$string['acad_level'] = 'Level';
$string['acad_whatlearn_q'] = 'What will you learn in this course?';
$string['acad_videolength'] = 'Video length';
$string['acad_instructorlabel'] = 'Instructor';
$string['acad_watchpromo'] = 'Watch the promo';
$string['acad_closevideo'] = 'Close the video';
$string['acad_hascert'] = 'Certificate included';
$string['acad_startson'] = 'Starts {$a}';
$string['acad_nenrolled'] = '{$a} enrolled';
$string['acad_ilos'] = 'Intended learning outcomes';
$string['acad_bytheend'] = 'By the end of this program you will be able to';
$string['acad_aboutinstructor'] = 'About the instructor';
$string['acad_nyearsexp'] = '{$a} years of experience';
$string['acad_1yearexp'] = '1 year of experience';
$string['acad_yearsexp'] = 'Years of experience';
$string['acad_speaks'] = 'Teaches in';
$string['acad_specialization'] = 'Specialization';
$string['passwordstrength'] = 'Password strength';
$string['passwordstrengthweak'] = 'Weak password';
$string['passwordstrengthfair'] = 'Fair password';
$string['passwordstrengthgood'] = 'Good password';
$string['passwordstrengthstrong'] = 'Strong password';
$string['hidepassword'] = 'Hide password';
$string['gatehint'] = 'Please complete every required field first.';
$string['createaccount'] = 'Create your account';
$string['createaccountsub'] = 'Start learning with the academy.';
$string['or'] = 'or';
$string['continuewith'] = 'Continue with {$a}';
$string['welcomeback'] = 'Welcome back';
$string['welcomebacksub'] = 'Log in to continue learning';
$string['loginemail'] = 'Email address';
$string['loginemailplaceholder'] = 'name@example.com';
$string['forgotyourpassword'] = 'Forgot your password?';
$string['eitherorlockedbyusername'] = 'Locked while a username is entered — search by one or the other.';
$string['eitherorlockedbyemail'] = 'Locked while an email address is entered — search by one or the other.';
$string['eitherorclearusername'] = 'Clear the username';
$string['eitherorclearemail'] = 'Clear the email address';
$string['noaccount'] = 'Don\'t have an account?';
$string['signupnow'] = 'Sign up';
$string['continueasguest'] = 'Continue as a guest';
$string['navmanagement'] = 'Management';
$string['navgallery'] = 'Design gallery';
$string['gearmenu'] = 'Navigation bar gear menu';
$string['gearmenu_desc'] = 'The gear icon on the navigation bar opens a list of groups, each a heading over a few pages — <em>Navigation</em> (My courses, Site administration) and <em>Management</em> (coupons, offers, subscriptions and the other administration screens). The groups, their names in both languages, the pages under each one, their order and who sees each one are all set by the text below, written the same way as Custom menu items above. The Edit mode switch, for users who have one, sits after the first group.';
$string['gearmenuitems'] = 'Gear menu items';
$string['gearmenuitems_desc'] = '<p>One line per item, the parts separated by <code>|</code>:</p>
<ul>
<li>A line <b>without</b> a hyphen starts a group: <code>English name|Arabic name</code></li>
<li>A line <b>starting with a hyphen</b> is a page in that group: <code>-English name|Arabic name|link|who</code></li>
</ul>
<p>The link is a page on this site (<code>/my/courses.php</code>) or a full address. Give one name only and it is used in both languages.</p>
<p><b>who</b> says who sees the page: <code>guest</code> (a visitor who is not logged in), <code>user</code> (a logged-in user who is not an administrator), <code>admin</code> (an administrator), or <code>all</code>. Combine with commas: <code>guest,user</code>. Leave it out and it is worked out from the link: management screens and Site administration pages for administrators, anything else for everyone. Whatever you write, a management screen is never shown to someone who cannot open it.</p>
<p>Leave the box empty to show no groups. For example:</p>
<pre>Navigation|التصفح
-My courses|مقرراتي الدراسية|/my/courses.php|user,admin
-Site administration|إدارة الموقع|/admin/search.php|admin
-Log in|تسجيل الدخول|/login/index.php|guest
Management|الإدارة
-Manage coupons|إدارة الكوبونات|/local/nit_commerce/manage_coupons.php|admin
-Calendar|التقويم|/calendar/view.php|all</pre>';
$string['gearmenuerrornolink'] = 'Line {$a->line} has no link, so nothing was saved: "{$a->text}". Write a page as -English name|Arabic name|link, with a | before the link.';
$string['gearmenuerrornoname'] = 'Line {$a->line} has a link but no name, so nothing was saved: "{$a->text}".';
$string['gearmenuerroraudience'] = 'Line {$a->line} says "{$a->word}" for who sees the page, which is not a known word, so nothing was saved: "{$a->text}". Use guest, user, admin or all — or leave that part out.';
$string['logosize'] = 'Logo size';
$string['logosize_desc'] = 'How large the logo above is drawn. <strong>Logo size</strong> is the only control most sites need: it resizes every logo on the site at once and keeps the proportions between them. The heights beneath it set each place individually, and are multiplied by it.';
$string['logoscale'] = 'Logo size';
$string['logoscale_desc'] = 'A percentage applied to every logo on the site — the navigation bar, the mobile menu, the footer and the log-in screens. 100% draws them at the heights below; 150% makes them half as large again. Allowed range 25–400.';
$string['logoheightnavbar'] = 'Navigation bar logo height';
$string['logoheightnavbar_desc'] = 'Height in pixels of the logo in the top bar of every page. The bar grows taller when the logo needs the room, so a large value here makes the whole header taller rather than spilling out of it.';
$string['logoheightdrawer'] = 'Mobile menu logo height';
$string['logoheightdrawer_desc'] = 'Height in pixels of the logo at the top of the slide-out menu, which is what replaces the navigation bar\'s links on a phone.';
$string['logoheightfooter'] = 'Footer logo height';
$string['logoheightfooter_desc'] = 'Maximum height in pixels of the logo in the site footer. A wide wordmark will hit the width of its column before it reaches this height.';
$string['logoheightauthpanel'] = 'Log-in panel logo height';
$string['logoheightauthpanel_desc'] = 'Maximum height in pixels of the logo drawn over the picture beside the log-in and sign-up forms.';
$string['logoheightauthcard'] = 'Log-in form logo height';
$string['logoheightauthcard_desc'] = 'Maximum height in pixels of the logo inside the log-in and sign-up cards, above the heading.';
$string['logomode'] = 'Logos for light and dark mode';
$string['logomode_desc'] = 'The navigation bar changes colour with the light/dark switch, and a logo drawn for one of them will not read on the other — a white mark disappears on a white bar. Say below which mode the logos above were drawn for, then upload the versions for the other mode. Leave the setting on <em>Not set</em> and nothing is ever swapped: the site uses the logos above everywhere, exactly as before.';
$string['logosfor'] = 'The logos above are drawn for';
$string['logosfor_desc'] = 'Which display mode the Logo, Compact logo and Favicon above suit. Pages rendering in the other mode use the uploads below instead — and still use the ones above for any slot left empty.';
$string['logosfor_unset'] = 'Not set — never swap';
$string['logosfor_dark'] = 'Dark mode (a light mark on a dark bar)';
$string['logosfor_light'] = 'Light mode (a dark mark on a light bar)';
$string['altlogo'] = 'Logo for the other mode';
$string['altlogo_desc'] = 'The full logo, drawn for whichever mode the ones above are not. Leave empty to use the logo above in both modes.';
$string['altlogocompact'] = 'Compact logo for the other mode';
$string['altlogocompact_desc'] = 'The mark shown in the navigation bar. This is the one that matters most — it is the logo on every page. Leave empty to use the compact logo above in both modes.';
$string['altfavicon'] = 'Favicon for the other mode';
$string['altfavicon_desc'] = 'The browser-tab icon. Leave empty to use the favicon above in both modes.';

// Bassthalk: coloured logo, site footer settings, gear menu.
$string['brandlogo'] = 'Coloured logo (light backgrounds)';
$string['brandlogo_desc'] = 'The logo drawn on light backgrounds: the site footer, the log-in card and the registration card. Leave empty to use the built-in logo.';
$string['footerdescription'] = 'Footer description';
$string['footerdescription_desc'] = 'The sentence under the logo in the site footer. Leave empty to hide it.';
$string['footercopyright'] = 'Copyright line';
$string['footercopyright_desc'] = 'The bold line under the description. Write {year} where the current year should appear. Leave empty to hide it.';
$string['footerpages'] = 'Pages column';
$string['footerpages_desc'] = 'The links listed under "الصفحات". A link starting with / is a page on this site (for example /my/); anything else must be a full address starting with http:// or https://. "Show to" decides who sees the link.';
$string['footerpages_name'] = 'Page name';
$string['footerpages_url'] = 'Link';
$string['footerpages_show'] = 'Show to';
$string['footerpages_show_all'] = 'Everyone';
$string['footerpages_show_guest'] = 'Visitors only (not logged in)';
$string['footerpages_show_user'] = 'Logged-in users only';
$string['footerpages_add'] = 'Add page';
$string['footerpages_remove'] = 'Remove';
$string['footerpages_incomplete'] = 'Every page needs both a name and a link. Fill in the missing part or remove the row.';
$string['footerpages_invalidurl'] = 'The link "{$a}" must start with / (a page on this site) or with http:// or https://.';
$string['footersocial'] = 'Social media';
$string['footersocial_desc'] = 'Fill in a network\'s address to show its icon under "السوشيال ميديا". Empty networks are hidden; when all are empty the whole column is hidden.';
$string['footersocial_facebook'] = 'Facebook link';
$string['footersocial_instagram'] = 'Instagram link';
$string['footersocial_tiktok'] = 'TikTok link';
$string['footersocial_youtube'] = 'YouTube link';
$string['footersocial_whatsapp'] = 'WhatsApp link';
$string['footersocial_telegram'] = 'Telegram link';
// Site pages manager (Plugins → Local plugins).
$string['sitepages'] = 'Site pages manager';
$string['sitepages_footer'] = 'Footer';
$string['sitepages_navmenus'] = 'Navbar menus';
$string['navmenu_bar'] = 'Navbar inline links (next to logo)';
$string['navmenu_bar_desc'] = 'The links that appear as text words directly in the header navbar between the logo and the search button. A link starting with "/" is a page on this site (e.g. /local/nit_pages/page.php?p=about).';
$string['navmenu_gear'] = 'Gear menu links';
$string['navmenu_gear_desc'] = 'The links in the gear (⚙) menu of the top bar. Until you save this list the menu shows Moodle\'s own pages. A link starting with "/" is a page on this site. "Who sees it" picks the role; the page itself still checks access.';
$string['navmenu_user'] = 'Profile menu links';
$string['navmenu_user_desc'] = 'The links in the profile (avatar) menu. The language menu, "Switch role" and "Log out" always stay at the bottom. Until you save this list the menu shows Moodle\'s own links.';
$string['navmenu_show_all'] = 'Everyone (visitors and signed-in)';
$string['navmenu_show_guest'] = 'Visitors only (not signed in)';
$string['navmenu_show_user'] = 'Signed-in users only';
$string['navmenu_show_student'] = 'Students';
$string['navmenu_show_teacher'] = 'Teachers';
$string['navmenu_show_admin'] = 'Admins and managers';
$string['navpagesmenu'] = 'Site pages';

// Bassthalk navbar / footer texts.
$string['bth_searchsite'] = 'Search the site';
$string['bth_searchtitle'] = 'Search the site\'s courses ..';
$string['bth_searchlabel'] = 'Search';
$string['bth_searchsubmit'] = 'Search';
$string['bth_login'] = 'Log in';
$string['bth_signup'] = 'New account';
$string['bth_footerpages'] = 'Pages';
$string['bth_footersocial'] = 'Social media';

// Bassthalk login card (templates/core/loginform + login_layout).
$string['bth_backhome'] = 'Back to home';
$string['bth_loginheading'] = 'Welcome back! Ready to study?';
$string['bth_loginsubtitle'] = 'Sign in with the email and password you registered with.';
$string['bth_loginemail'] = 'Email';
$string['bth_loginpassword'] = 'Password';
$string['bth_loginforgot'] = 'Forgot your password?';
$string['bth_loginforgotlink'] = 'Click here';
$string['bth_loginsubmit'] = 'Log in';
$string['bth_loginnoaccount'] = "Don't have an account?";
$string['bth_logincreate'] = 'Create your account now!';
$string['bth_loginparent'] = 'Parent dashboard';
