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
$string['applications'] = 'Applications';
$string['applicationfields'] = 'Application fields';
$string['settings'] = 'Settings';
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
$string['supertutor'] = 'Super Tutor';
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
$string['form_headline_help'] = 'One line students see under your name, e.g. "IELTS coach — 8 years of experience".';
$string['form_bio'] = 'About you';
$string['form_subjects'] = 'Subjects';
$string['form_subjects_none'] = 'No subject chosen';
$string['form_video'] = 'Intro video';
$string['form_video_help'] = 'A short video (1–3 minutes) introducing yourself and how you teach. Upload a file or paste a YouTube / Vimeo link. Students see it after the academy approves it.';
$string['form_videotype'] = 'Video';
$string['form_videotype_none'] = 'No video';
$string['form_videotype_upload'] = 'Upload a video file';
$string['form_videotype_link'] = 'YouTube or Vimeo link';
$string['form_videofile'] = 'Video file';
$string['form_videourl'] = 'Video link';
$string['form_videostate'] = 'Video review';
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
$string['qual_unverified'] = 'Not verified yet';

// Join as a teacher.
$string['apply_title'] = 'Join as a teacher';
$string['apply_lead'] = 'Teach private live lessons on the academy.';
$string['apply_step1'] = 'Create your account.';
$string['apply_step2'] = 'Complete your teacher profile: about you, subjects, intro video and qualifications.';
$string['apply_step3'] = 'Send it for review — once approved, students can find and book you.';
$string['apply_haveaccount'] = 'Already have an account?';
$string['apply_asstudent'] = 'Sign up as a student instead';
$string['apply_phone'] = 'Phone number';
$string['apply_agree'] = 'I agree to the <a href="{$a}" target="_blank">terms and conditions</a>';
$string['apply_create'] = 'Create account and continue';

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
$string['tab_videos'] = 'New videos';
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
$string['act_changes'] = 'Ask for changes';
$string['act_reject'] = 'Reject';
$string['act_suspend'] = 'Suspend';
$string['act_videook'] = 'Approve video';
$string['act_videono'] = 'Refuse video';
$string['act_verify'] = 'Mark verified';
$string['act_unverify'] = 'Unverify';
$string['novideo'] = 'No intro video.';
$string['noquals'] = 'No qualifications.';
$string['price'] = 'Price';
$string['priceflex'] = '{$a} Flex';
$string['badgemode_auto'] = 'By the rules';
$string['badgemode_on'] = 'Always on';
$string['badgemode_off'] = 'Always off';
$string['badge_stats'] = 'Rating {$a->rating} from {$a->reviews} ratings · {$a->lessons} completed lessons · {$a->absence}% teacher no-shows';
$string['badge_rules'] = 'Rules: rating ≥ {$a->rating}, at least {$a->reviews} ratings, at least {$a->lessons} completed lessons, no-shows ≤ {$a->absence}%';
$string['badge_meets'] = 'Meets the rules.';
$string['badge_notmeets'] = 'Does not meet the rules yet.';

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
$string['set_h_apply'] = 'Applications';
$string['set_requireapproval'] = 'Approval required';
$string['set_requireapproval_desc'] = 'Only teachers whose application is approved are listed in the teacher search and can be booked. Off: every teacher who takes bookings is listed.';
$string['set_requirevideo'] = 'Intro video required';
$string['set_minqualifications'] = 'Qualifications required';
$string['set_videomaxmb'] = 'Max video upload (MB)';
$string['set_videomaxmb_desc'] = 'Also limited by the server\'s upload limit.';
$string['set_allowreapply'] = 'Allow applying again after a rejection';
$string['set_h_badge'] = 'Super Tutor badge';
$string['set_h_badge_desc'] = 'An approved teacher who meets every rule gets the badge; it is checked daily and taken away when the rules are no longer met. A reviewer can pin it on or off for one teacher.';
$string['set_badge_minrating'] = 'Minimum average rating';
$string['set_badge_minreviews'] = 'Minimum number of ratings';
$string['set_badge_minlessons'] = 'Minimum completed lessons';
$string['set_badge_maxabsence'] = 'Maximum teacher no-shows (%)';

// Messages.
$string['msg_submitted_subject'] = 'New teacher application: {$a}';
$string['msg_submitted_body'] = '{$a->name} sent a teacher application. Review it: {$a->url}';
$string['msg_video_subject'] = 'New intro video to review: {$a}';
$string['msg_video_body'] = '{$a->name} changed their intro video. Review it: {$a->url}';
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
$string['supertutoronly'] = 'Super Tutors only';
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
$string['privacy:metadata:tutor:badge'] = 'Whether the teacher has the Super Tutor badge.';
$string['privacy:metadata:qual'] = 'A teacher\'s qualifications.';
$string['privacy:metadata:qual:userid'] = 'The teacher.';
$string['privacy:metadata:qual:title'] = 'The qualification.';
$string['privacy:metadata:qual:institution'] = 'The institution.';
$string['privacy:metadata:qual:year'] = 'The year.';
$string['privacy:metadata:data'] = 'A teacher\'s answers to the application fields.';
$string['privacy:metadata:data:userid'] = 'The teacher.';
$string['privacy:metadata:data:value'] = 'The answer.';
$string['privacy:metadata:files'] = 'The intro video, certificates and file answers.';
