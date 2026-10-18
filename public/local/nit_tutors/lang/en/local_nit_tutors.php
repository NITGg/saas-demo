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
 * English strings for local_nit_tutors.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Tutors';
$string['admincategory'] = 'Teacher applications';
$string['task_refreshbadges'] = 'Refresh Super Tutor badges';
$string['nit_tutors:review'] = 'Review teacher applications';
$string['nit_tutors:manage'] = 'Manage teacher application fields';
$string['messageprovider:applicationupdate'] = 'Updates on my teacher application';
$string['messageprovider:applicationsubmitted'] = 'New teacher applications to review';

// Statuses.
$string['status_draft'] = 'Not sent yet';
$string['status_pending'] = 'Waiting for review';
$string['status_changes'] = 'Changes requested';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';
$string['status_suspended'] = 'Suspended';
$string['statushelp_draft'] = 'Complete your teacher profile, then send it for review. Students will see you once the academy approves it.';
$string['statushelp_pending'] = 'Your application is with the academy. We will message you as soon as it is reviewed.';
$string['statushelp_changes'] = 'The academy asked for some changes. Update your profile and send it again.';
$string['statushelp_approved'] = 'You are an approved teacher: students can find and book you. A new intro video is shown after the academy approves it.';
$string['statushelp_rejected'] = 'Your application was not accepted. You can update it and apply again.';
$string['statushelp_suspended'] = 'Your teacher profile is suspended: students cannot find or book you. Lessons already booked are not affected.';
$string['videostatus_pending'] = 'Waiting for review';
$string['videostatus_approved'] = 'Approved';
$string['videostatus_rejected'] = 'Not approved';
$string['reviewnote'] = 'Message from the reviewer';
$string['reviewnote_help'] = 'The reason, or what to change. Required to reject, ask for changes, suspend or refuse a video; the teacher sees it.';
$string['stillmissing'] = 'Still missing';
$string['viewpublicpage'] = 'View public page';
$string['teacherprofile'] = 'Teacher profile';
$string['saved'] = 'Saved.';
$string['submitted'] = 'Your application was sent for review.';
$string['done'] = 'Done.';

// What blocks submitting.
$string['missing_bio'] = 'About you (bio)';
$string['missing_subjects'] = 'At least one subject';
$string['missing_video'] = 'An intro video';
$string['missing_qualifications'] = 'At least {$a} qualification(s)';

// Application form.
$string['form_about'] = 'About you';
$string['form_headline'] = 'Headline';
$string['form_bio'] = 'About you';
$string['form_subjects'] = 'Subjects';
$string['form_video'] = 'Intro video';
$string['form_video_help'] = 'A short video (1–3 minutes) introducing yourself and how you teach. Upload a file or paste a YouTube / Vimeo link. Students see it after the academy approves it.';
$string['form_videotype'] = 'Video';
$string['form_videotype_upload'] = 'Upload a video file';
$string['form_videotype_link'] = 'YouTube or Vimeo link';
$string['form_videofile'] = 'Video file';
$string['form_videourl'] = 'Video link';
$string['form_quals'] = 'Qualifications';
$string['form_quals_help'] = 'Degrees and certificates. Attach the certificate: only the academy sees the file; students see the title and a "verified" mark once the academy checks it.';
$string['form_qualtitle'] = 'Qualification';
$string['form_qualinstitution'] = 'University / institution';
$string['form_qualyear'] = 'Year';
$string['form_qualfile'] = 'Certificate (PDF or image)';
$string['form_qualdelete'] = 'Remove this qualification';
$string['form_qualadd'] = 'Add another qualification';
$string['form_more'] = 'More about you';
$string['form_save'] = 'Save';
$string['form_submit'] = 'Save and send for review';
$string['qual_verified'] = 'Verified';

// Join as a teacher.

// Errors.
$string['err_noteditable'] = 'Your application is being reviewed and cannot be changed now.';
$string['err_noreapply'] = 'This academy does not accept a new application after a rejection.';
$string['err_incomplete'] = 'Complete your application first: {$a}';
$string['err_notereq'] = 'Write a message to the teacher first.';
$string['err_badvideourl'] = 'Paste a YouTube or Vimeo video link.';
$string['err_qualtitle'] = 'Write the qualification\'s name.';
$string['err_badanswer'] = 'The answer to "{$a}" is not valid.';
$string['err_shortname'] = 'Give the field a short name (letters, digits, - and _).';
$string['err_shortnametaken'] = 'Another field already uses this short name.';
$string['err_badtype'] = 'Unknown field type.';
$string['err_fieldname'] = 'Give the field a name.';
$string['err_nooptions'] = 'A dropdown needs options, one per line.';
$string['err_phone'] = 'Enter a valid phone number (digits only, 7–15).';
$string['err_agree'] = 'You must agree to the terms and conditions.';
$string['err_signup'] = 'Check the form: {$a}';

// Admin: list and review.
$string['searchapplicants'] = 'Name or email';
$string['noapplications'] = 'No applications here.';
$string['col_status'] = 'Status';
$string['col_submitted'] = 'Sent';
$string['col_reviewed'] = 'Reviewed';
$string['col_video'] = 'Video';
$string['col_quals'] = 'Verified qualifications';
$string['review'] = 'Review';
$string['userprofile'] = 'User account';
$string['decision'] = 'Decision';
$string['act_approve'] = 'Approve';
$string['act_reinstate'] = 'Reinstate';
$string['act_reject'] = 'Reject';
$string['act_suspend'] = 'Suspend';
$string['novideo'] = 'No intro video.';
$string['noquals'] = 'No qualifications.';
$string['price'] = 'Price';
$string['priceflex'] = '{$a} Flex';

// Admin: fields.
$string['fields_intro'] = 'Questions a teacher answers in their application, besides the fixed ones (about, subjects, intro video, qualifications). A field can be shown on the public teacher page, and a dropdown can be a filter of the teacher search (e.g. "Languages spoken", "Country").';
$string['addfield'] = 'Add a field';
$string['editfield'] = 'Edit field';
$string['nofields'] = 'No application fields yet.';
$string['confirmdeletefield'] = 'Delete the field "{$a}" and every teacher\'s answer to it?';
$string['field_name'] = 'Label';
$string['field_name_help'] = 'Shown to the teacher. Use {mlang ar}…{mlang}{mlang en}…{mlang} for both languages.';
$string['field_shortname'] = 'Short name';
$string['field_type'] = 'Type';
$string['field_options'] = 'Options (one per line)';
$string['field_description'] = 'Help text';
$string['field_required'] = 'Required';
$string['field_public'] = 'Show on the teacher page';
$string['field_public_help'] = 'Students see this answer on the teacher\'s public page.';
$string['field_filter'] = 'Filter in the teacher search';
$string['field_filter_help'] = 'Students can filter the teacher search by this dropdown.';
$string['field_active'] = 'Active';
$string['type_text'] = 'Short text';
$string['type_textarea'] = 'Long text';
$string['type_select'] = 'Dropdown';
$string['type_number'] = 'Number';
$string['type_url'] = 'Link';
$string['type_date'] = 'Date';
$string['type_checkbox'] = 'Yes / no';
$string['type_file'] = 'File';

// Settings.
$string['set_requireapproval'] = 'Approval required';
$string['set_requireapproval_desc'] = 'Only teachers whose application is approved are listed in the teacher search and can be booked. Off: every teacher who takes bookings is listed.';
$string['set_requirevideo'] = 'Intro video required';
$string['set_minqualifications'] = 'Qualifications required';
$string['set_videomaxmb'] = 'Max video upload (MB)';
$string['set_videomaxmb_desc'] = 'Also limited by the server\'s upload limit.';
$string['set_allowreapply'] = 'Allow applying again after a rejection';

// Messages.
$string['msg_submitted_subject'] = 'New teacher application: {$a}';
$string['msg_submitted_body'] = '{$a->name} sent a teacher application. Review it: {$a->url}';
$string['msg_approved_subject'] = 'You are now a teacher on the academy';
$string['msg_approved_body'] = 'Hello {$a->name}, your teacher application was approved. Students can now find and book you. Set your weekly hours and price on your profile: {$a->url}';
$string['msg_rejected_subject'] = 'Your teacher application';
$string['msg_rejected_body'] = 'Hello {$a->name}, your teacher application was not accepted. Reason: {$a->note}. {$a->url}';
$string['msg_changes_subject'] = 'Changes requested on your teacher application';
$string['msg_changes_body'] = 'Hello {$a->name}, please update your teacher application: {$a->note}. Then send it again: {$a->url}';
$string['msg_suspended_subject'] = 'Your teacher profile is suspended';
$string['msg_suspended_body'] = 'Hello {$a->name}, your teacher profile was suspended: {$a->note}. Students cannot find or book you. {$a->url}';
$string['msg_videook_subject'] = 'Your intro video was approved';
$string['msg_videook_body'] = 'Students now see your new intro video. {$a->url}';
$string['msg_videono_subject'] = 'Your intro video was not approved';
$string['msg_videono_body'] = 'Reason: {$a->note}. Upload another video: {$a->url}';

// Teacher search.
$string['findteacher'] = 'Find a teacher';
$string['nteachers'] = '{$a} teachers';
$string['searchin'] = 'Search in';
$string['tab_courses'] = 'Courses';
$string['tab_teachers'] = 'Teachers';
$string['viewallteachers'] = 'All matching teachers';
$string['searchplaceholder'] = 'Teacher name or subject…';
$string['filters'] = 'Filters';
$string['any'] = 'Any';
$string['pricerange'] = 'Price per lesson (Flex)';
$string['min'] = 'Min';
$string['max'] = 'Max';
$string['availableday'] = 'Available on';
$string['availabletime'] = 'Time of day';
$string['time_morning'] = 'Morning (6:00–12:00)';
$string['time_afternoon'] = 'Afternoon (12:00–17:00)';
$string['time_evening'] = 'Evening (17:00–24:00)';
$string['rating'] = 'Rating';
$string['ratingup'] = '{$a} ★ and up';
$string['sortby'] = 'Sort by';
$string['sort_recommended'] = 'Recommended';
$string['sort_rating'] = 'Best rated';
$string['sort_price_asc'] = 'Price: low to high';
$string['sort_price_desc'] = 'Price: high to low';
$string['sort_lessons'] = 'Most lessons';
$string['applyfilters'] = 'Show teachers';
$string['clearfilters'] = 'Clear filters';
$string['hasvideo'] = 'Has an intro video';
$string['newteacher'] = 'New teacher';
$string['nlessons'] = '{$a} lessons';
$string['perlesson'] = 'per lesson';
$string['booklesson'] = 'Book a lesson';
$string['viewprofile'] = 'View profile';
$string['noteachers'] = 'No teacher matches these filters.';
$string['areyouteacher'] = 'Are you a teacher?';
$string['jointeacher'] = 'Apply to teach';
$string['availability'] = 'Weekly availability';
$string['timesin'] = 'Times in {$a}.';

// Privacy.
$string['privacy:metadata:tutor'] = 'A teacher\'s application, intro video and badge.';
$string['privacy:metadata:tutor:userid'] = 'The teacher.';
$string['privacy:metadata:tutor:status'] = 'The application status.';
$string['privacy:metadata:tutor:reviewnote'] = 'The reviewer\'s message to the teacher.';
$string['privacy:metadata:tutor:videourl'] = 'The intro video link.';
$string['privacy:metadata:qual'] = 'A teacher\'s qualifications.';
$string['privacy:metadata:qual:userid'] = 'The teacher.';
$string['privacy:metadata:qual:title'] = 'The qualification.';
$string['privacy:metadata:qual:institution'] = 'The institution.';
$string['privacy:metadata:qual:year'] = 'The year.';
$string['privacy:metadata:data'] = 'A teacher\'s answers to the application fields.';
$string['privacy:metadata:data:userid'] = 'The teacher.';
$string['privacy:metadata:data:value'] = 'The answer.';
$string['privacy:metadata:files'] = 'The intro video, certificates and file answers.';

// Admin page tabs (Local plugins → Teacher applications).
$string['tab_applications'] = 'Applications';
$string['tab_fields'] = 'Application fields';
$string['tab_badges'] = 'Badges';
$string['tab_settings'] = 'Settings';
$string['opencertificate'] = 'Open the certificate';

// Application fields tab: the fixed parts.
$string['builtinfields'] = 'Fields every application has';
$string['extrafields'] = 'Your extra fields';
$string['builtin_video'] = 'Video file or YouTube / Vimeo link';
$string['builtin_quals'] = 'Qualifications with a certificate';
$string['builtin_afterreview'] = 'After the academy approves it';
$string['builtin_atleast'] = 'At least {$a}';

// Badges.
$string['badge'] = 'Badge';
$string['badges_intro'] = 'Badges show on the teacher page and in the teacher search, and students can filter by them. A badge is earned automatically when a teacher meets its rules (checked every day, and taken away when they stop meeting them), or given by hand: open Teachers next to the badge and choose, for each teacher, by the rules, always or never.';
$string['addbadge'] = 'Add a badge';
$string['editbadge'] = 'Edit badge';
$string['nobadges'] = 'No badges yet.';
$string['confirmdeletebadge'] = 'Delete the badge "{$a}"? Every teacher who wears it loses it.';
$string['badge_name'] = 'Name';
$string['badge_name_help'] = 'What students see, e.g. "Super Tutor". Use {mlang ar}…{mlang}{mlang en}…{mlang} for both languages.';
$string['badge_description'] = 'What it means';
$string['badge_color'] = 'Colour';
$string['badge_method'] = 'How it is given';
$string['badgemethod_auto'] = 'Automatically, by rules';
$string['badgemethod_manual'] = 'By hand, by a reviewer';
$string['badgemethod_manual_desc'] = 'Given by a reviewer on the teacher\'s review page.';
$string['badge_rules'] = 'Rules';
$string['badge_rules_help'] = 'A teacher earns the badge when every rule you set holds. Leave a rule at 0 (or the no-show rate at 100) to not check it.';
$string['badge_minrating'] = 'Minimum average rating (0–5)';
$string['badge_minreviews'] = 'Minimum number of ratings';
$string['badge_minlessons'] = 'Minimum completed lessons';
$string['badge_maxabsence'] = 'Maximum teacher no-show rate (%)';
$string['badge_holders'] = 'Teachers wearing it';
$string['badge_wears'] = 'Wears it';
$string['badgemode_auto'] = 'By its rules';
$string['badgemode_auto_manual'] = 'Not given';
$string['badgemode_on'] = 'Always give it';
$string['badgemode_off'] = 'Never give it';
$string['rule_rating'] = 'rating {$a} or more';
$string['rule_reviews'] = 'at least {$a} ratings';
$string['rule_lessons'] = 'at least {$a} completed lessons';
$string['rule_absence'] = 'no-shows {$a}% or less';
$string['rule_none'] = 'No rule: every approved teacher';
$string['color_warning'] = 'Gold (Warning)';
$string['color_primary'] = 'Main colour (Primary)';
$string['color_success'] = 'Green (Success)';
$string['color_info'] = 'Blue (Info)';
$string['color_error'] = 'Red (Error)';
$string['color_secondary'] = 'Grey (Secondary)';
$string['err_badgename'] = 'Give the badge a name.';
$string['err_rating'] = 'Enter a rating from 0 to 5.';
$string['err_percent'] = 'Enter a percentage from 0 to 100.';
$string['err_negative'] = 'Enter 0 or more.';
$string['privacy:metadata:badgeuser'] = 'The badges a teacher wears, and a reviewer\'s choice per badge.';
$string['privacy:metadata:badgeuser:userid'] = 'The teacher.';
$string['privacy:metadata:badgeuser:mode'] = 'The reviewer\'s choice (by the rules, always, never).';
$string['privacy:metadata:badgeuser:awarded'] = 'Whether the teacher wears the badge.';

// Teacher sign-up steps and the profile page (teacher_input).
$string['signup_sent'] = 'Your account was created and your teacher application was sent for review.';
$string['signup_laststep'] = 'Send for review';
$string['signup_review_note'] = 'When you send it, the academy reviews your profile. You will get a message with the decision; once approved, students can find and book you.';
$string['signup_filesagain'] = 'Please choose your video and certificate files again (the page had to reload).';
$string['signup_agree'] = 'I agree to the';
$string['signup_terms'] = 'terms and conditions';
$string['signup_send'] = 'Create my account and send for review';
$string['form_headline_ph'] = 'One line under your name, e.g. "IELTS coach — 8 years of experience"';
$string['form_bio_ph'] = 'Introduce yourself to students: your experience and how you teach.';
$string['form_qualtitle_ph'] = 'e.g. BA in English';
$string['currentfile'] = 'Current file: {$a}';
$string['filelimit'] = 'Up to {$a}';
$string['err_form'] = 'Please correct the highlighted parts.';
$string['err_toolong'] = 'At most {$a} characters.';
$string['err_choosevideo'] = 'Choose a video file to upload, or paste a link.';
$string['err_qualyear'] = 'Enter a year between 1950 and next year.';
$string['err_filetype'] = 'This file type is not accepted. Accepted: {$a}';
$string['err_filesize'] = 'The file is too large (at most {$a}).';
$string['savedforreview'] = 'Saved. Your new video / qualifications are shown after the academy reviews them.';
$string['nocertificate'] = 'No certificate attached.';
$string['isnew'] = 'New — to review';

// Review page.
$string['tab_updates'] = 'New changes';
$string['act_approveupdates'] = 'Approve the changes';
$string['act_rejectupdates'] = 'Reject the changes';
$string['decision_application'] = 'Approve the teacher, or reject the application with the reason the teacher reads (they can update it and apply again).';
$string['decision_updates'] = 'This approved teacher changed their intro video or qualifications. Approve shows them to students; Reject (with the reason) keeps the old ones out — the teacher stays approved.';
$string['decision_approved'] = 'This teacher is approved. To stop them (not listed, not bookable), write the reason and press Stop.';
$string['decision_suspended'] = 'This teacher is stopped. Reinstate them to list them again.';
$string['msg_updates_subject'] = 'Teacher profile changes to review: {$a}';
$string['msg_updates_body'] = '{$a->name} changed their intro video or qualifications. Review them: {$a->url}';
$string['msg_updatesrejected_subject'] = 'Your profile changes were not approved';
$string['msg_updatesrejected_body'] = 'Hello {$a->name}, the changes to your teacher profile were not approved: {$a->note}. {$a->url}';

// Badges tab: the teachers of a badge.
$string['badge_manage'] = 'Teachers';
$string['badgeteachers_help'] = 'For each approved teacher choose how they get this badge: by its rules, always, or never. This is how a manual badge is given.';
$string['noapprovedteachers'] = 'No approved teachers yet.';
