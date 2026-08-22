<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy licence';

// Settings.
$string['enabled']          = 'Enforce licence limits';
$string['enabled_desc']     = 'Master switch. When off, nothing is limited (the academy runs with all features) — turn it on to apply the tier below. Off by default so installing this plugin changes nothing until you opt in.';
$string['tier']             = 'Tier';
$string['tier_desc']        = 'Which plan this academy is on. Each tier sets its own course/activity limits and feature set.';
$string['expirydate']       = 'Expiry date';
$string['expirydate_desc']  = 'When this academy locks (format: YYYY-MM-DD). Leave empty for no expiry. Demo is typically 2 weeks; paid tiers 1 year.';
$string['gracedays']        = 'Grace period (days)';
$string['gracedays_desc']   = 'Extra days after the expiry date before the academy actually locks. 0 = lock on the expiry date.';
$string['statuspage']       = 'Academy licence — status';

// Status page.
$string['status_heading']   = 'Licence status';
$string['status_enforced']  = 'Enforcement';
$string['status_on']        = 'ON';
$string['status_off']       = 'OFF (no limits applied)';
$string['status_tier']      = 'Tier';
$string['status_expiry']    = 'Expires';
$string['status_daysleft']  = 'Days left';
$string['status_never']     = 'never';
$string['status_features']  = 'Features unlocked';
$string['status_videosrc']  = 'Video source';
$string['status_none']      = 'none';
$string['usage_heading']    = 'Usage vs. limits';
$string['usage_item']       = 'Item';
$string['usage_used']       = 'Used / allowed';
$string['usage_courses']    = 'Courses';
$string['usage_teachers']   = 'Teachers';
$string['usage_quiz']       = 'Quizzes';
$string['usage_video']      = 'Videos';
$string['usage_pdf']        = 'Files / PDFs';

// Enforcement messages.
$string['limit_course']     = 'Your {$a} plan has reached its course limit. Upgrade to add more courses.';
$string['limit_activity']   = 'Your {$a->tier} plan has reached its limit for this activity type ({$a->type}). Upgrade to add more.';
$string['feature_locked']   = 'The {$a->type} activity is not included in your {$a->tier} plan. Upgrade to use it.';

// Expired page.
$string['expired_title']    = 'Academy expired';
$string['expired_heading']  = 'This academy has expired';
$string['expired_body']     = 'Your {$a} period has ended. Upgrade to reactivate your academy and keep your content.';
$string['expired_contact']  = 'Contact NIT to upgrade or renew.';
$string['suspended_title']   = 'Academy suspended';
$string['suspended_heading'] = 'This academy is suspended';
$string['suspended_body']    = 'Access to this academy has been temporarily suspended. Your content is safe — contact NIT to restore it.';
$string['backtosite']       = 'Back to site';
