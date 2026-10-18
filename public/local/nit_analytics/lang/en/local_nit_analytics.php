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

$string['signupsreport'] = 'Sign-ups by source';
$string['lastndays'] = 'Last {$a} days';
$string['source'] = 'Source';
$string['medium'] = 'Medium';
$string['campaign'] = 'Campaign';
$string['signups'] = 'Sign-ups';
$string['direct'] = '(direct / unknown)';
$string['nosignups'] = 'No sign-ups in this period.';

$string['privacy:metadata:preference:utm_source'] = 'The campaign source (utm_source) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_medium'] = 'The campaign medium (utm_medium) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_campaign'] = 'The campaign name (utm_campaign) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_term'] = 'The campaign term (utm_term) the user arrived from before signing up.';
$string['privacy:metadata:preference:utm_content'] = 'The campaign content (utm_content) the user arrived from before signing up.';
$string['privacy:metadata:preference:signup_pending'] = 'Whether the sign-up conversion event has yet to be sent.';
$string['privacy:metadata:trackers'] = 'When configured, Google Analytics, Google Tag Manager and the Meta Pixel are loaded in the browser and receive page visits and the sign-up event.';
$string['privacy:metadata:trackers:pageurl'] = 'The page visited.';
