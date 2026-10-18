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
 * English strings for local_nit_analytics.
 *
 * @package    local_nit_analytics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Marketing analytics';
$string['nit_analytics:viewreport'] = 'See sign-ups by source';

$string['ga4_id'] = 'Google Analytics 4 measurement ID';
$string['ga4_id_desc'] = 'Like G-XXXXXXXXXX. Leave empty to turn GA4 off. Not needed if GA4 is loaded through Tag Manager.';
$string['gtm_id'] = 'Google Tag Manager container ID';
$string['gtm_id_desc'] = 'Like GTM-XXXXXXX. Leave empty to turn Tag Manager off.';
$string['meta_pixel_id'] = 'Meta (Facebook) Pixel ID';
$string['meta_pixel_id_desc'] = 'The numeric pixel ID. Leave empty to turn the pixel off.';
$string['trackadmins'] = 'Track site administrators';
$string['trackadmins_desc'] = 'Off by default, so admin visits do not count as traffic.';

$string['lastndays'] = 'Last {$a} days';
$string['direct'] = '(direct / unknown)';

$string['privacy:metadata:preference:utm_source'] = 'The campaign source (utm_source) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_medium'] = 'The campaign medium (utm_medium) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_campaign'] = 'The campaign name (utm_campaign) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_term'] = 'The campaign term (utm_term) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_content'] = 'The campaign content (utm_content) the user arrived from before signing up.';
$string['privacy:metadata:preference:signup_pending'] = 'Whether the sign-up conversion event has yet to be sent.';
$string['privacy:metadata:trackers'] = 'When configured, Google Analytics, Google Tag Manager and the Meta Pixel are loaded in the browser and receive page visits and the sign-up and purchase events, only after the visitor accepts analytics cookies.';
$string['privacy:metadata:trackers:pageurl'] = 'The page visited.';

$string['requireconsent'] = 'Ask for consent first';
$string['requireconsent_desc'] = 'Show a consent banner and load no tracker (GA4, Tag Manager, Pixel) until the visitor accepts. Turn off only where the law allows tracking without consent.';
$string['consent_text'] = 'We use analytics cookies (Google, Meta) to understand which ads and pages bring learners. Do you allow them?';
$string['consent_accept'] = 'Accept';
$string['consent_decline'] = 'Decline';
$string['funnelreport'] = 'Acquisition funnel';
$string['funnelintro'] = 'Visits that arrived from a campaign link, the sign-ups and purchases they led to, per source. Sign-ups and purchases count against the source a learner first came from.';
$string['nodata'] = 'Nothing recorded in this period.';
$string['downloadcsv'] = 'Download CSV';
$string['col_source'] = 'Source';
$string['col_medium'] = 'Medium';
$string['col_campaign'] = 'Campaign';
$string['col_landings'] = 'Visits';
$string['col_signups'] = 'Sign-ups';
$string['col_signuprate'] = 'Visit → sign-up';
$string['col_buyers'] = 'Buyers';
$string['col_buyerrate'] = 'Sign-up → buyer';
$string['col_purchases'] = 'Purchases';
$string['col_revenue'] = 'Revenue';
$string['privacy:metadata:preference:purchase_pending'] = 'Purchases whose conversion event has yet to be sent to the analytics tools.';
$string['privacy:metadata:conv'] = 'Paid purchases, kept to measure which campaigns lead to sales.';
$string['privacy:metadata:conv:userid'] = 'The buyer.';
$string['privacy:metadata:conv:kind'] = 'What was bought: a course, a lesson package or a subscription.';
$string['privacy:metadata:conv:amount_minor'] = 'The amount paid.';
$string['privacy:metadata:conv:currency'] = 'The currency.';
$string['privacy:metadata:conv:timecreated'] = 'When it was bought.';
$string['linksheading'] = 'Campaign links';
$string['linksintro'] = 'Build the link to put in an ad or post. Visits, sign-ups and purchases from it then show under its source and campaign in the acquisition funnel and in Google Analytics.';
$string['links_path'] = 'Page';
$string['links_path_help'] = 'The page on this site the link opens, e.g. / for the home page or /local/academy/course.php?id=3.';
$string['links_source'] = 'Source';
$string['links_source_help'] = 'Where the link is shown: google, facebook, instagram, tiktok…';
$string['links_medium'] = 'Medium';
$string['links_medium_help'] = 'The kind of traffic: cpc for paid ads, paid_social, social, email…';
$string['links_campaign'] = 'Campaign';
$string['links_campaign_help'] = 'A short name you will recognise in the reports, e.g. oct_english.';
$string['links_content'] = 'Content (optional)';
$string['links_content_help'] = 'To compare two ads or banners of one campaign, e.g. video_a.';
$string['links_build'] = 'Build link';
$string['links_result'] = 'Your link';
$string['links_copy'] = 'Copy';
$string['links_copied'] = 'Copied';
$string['links_missing'] = 'Source, medium and campaign are all needed.';
$string['links_googleads'] = 'Google Ads: you can also leave auto-tagging on (it adds gclid). Those visits count as google / cpc automatically; the campaign name then comes from Google Ads in Google Analytics.';
