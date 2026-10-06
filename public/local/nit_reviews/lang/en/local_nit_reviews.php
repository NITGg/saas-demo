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
 * Strings for local_nit_reviews.
 *
 * @package    local_nit_reviews
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'NIT Reviews';
$string['nit_reviews:rate'] = 'Rate a course and its teachers';
$string['nit_reviews:moderate'] = 'Approve, reject and delete reviews';

// Rate page.
$string['ratecourse'] = 'Rate this course';
$string['rateteacher'] = 'Rate the teacher';
$string['ratecourseandteachers'] = 'Rate the course and its teachers';
$string['yourrating'] = 'Your rating';
$string['yourreview'] = 'Your comment (optional)';
$string['submitreview'] = 'Submit review';
$string['updatereview'] = 'Update review';
$string['reviewsaved'] = 'Thanks — your review was saved.';
$string['reviewsaved_pending'] = 'Thanks — your review was saved. Your comment will show once it is approved.';
$string['mustenrol'] = 'You need to be enrolled in this course to review it.';
$string['cannotrateteacher'] = 'You can rate a teacher only in a course of theirs you are enrolled in, or after a lesson with them.';
$string['commentneedsapproval'] = 'Stars show at once; a comment shows after the platform approves it.';
$string['mine_pending'] = 'Your review is waiting for approval.';
$string['mine_approved'] = 'Your review is published.';
$string['mine_rejected'] = 'Your review was not approved. You can edit it and send it again.';
$string['mine_rejected_reason'] = 'Your review was not approved: {$a}. You can edit it and send it again.';
$string['back'] = 'Back';
$string['type_course'] = 'Course';
$string['type_teacher'] = 'Teacher';
$string['privatelessons'] = 'Private lessons';

// Course reviews page (for teachers).
$string['coursereviews'] = 'Reviews';
$string['coursereviews_desc'] = 'Student reviews and feedback for this course and its teachers.';
$string['averagerating'] = 'Average rating';
$string['totalreviews'] = 'Total reviews';
$string['stardistribution'] = 'Star distribution';
$string['allratings'] = 'All ratings';
$string['stars_count'] = '{$a} stars';
$string['one_star'] = '1 star';
$string['filterbystars'] = 'Filter by stars';
$string['filterbytarget'] = 'Review type';
$string['target_course'] = 'Course review';
$string['target_teacher'] = 'Teacher review';
$string['target_teacher_name'] = 'Teacher review: {$a}';
$string['gotomoderation'] = 'Review moderation (Approve / Reject)';
$string['viewpubliccourse'] = 'Preview student course page';
$string['teacherreviews'] = 'Teacher reviews';

// Display.
$string['rating'] = 'Rating';
$string['nreviews'] = '{$a} reviews';
$string['ratingand'] = '{$a->rating} ({$a->count})';

// Moderation page.
$string['moderatereviews'] = 'Review moderation';
$string['status_pending'] = 'Pending';
$string['status_approved'] = 'Approved';
$string['status_rejected'] = 'Rejected';
$string['allstatuses'] = 'All';
$string['reviewtype'] = 'Type';
$string['alltypes'] = 'Courses and teachers';
$string['allcourses'] = 'All courses';
$string['searchplaceholder'] = 'Student name or comment';
$string['noreviews'] = 'No reviews here.';
$string['author'] = 'Student';
$string['reviewof'] = 'Review of';
$string['comment'] = 'Comment';
$string['nocomment'] = 'Stars only';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['confirmdelete'] = 'Delete this review for good?';
$string['rejectreason'] = 'Rejection reason (optional, shown to the student)';
$string['rejectreason_help'] = 'Used by every Reject button on this page';
$string['approveselected'] = 'Approve selected';
$string['rejectselected'] = 'Reject selected';
$string['nothingselected'] = 'Tick at least one review first.';
$string['moderation_done'] = '{$a} review(s) updated.';
$string['moderation_failed'] = '{$a} could not be changed.';
$string['eventreviewmoderated'] = 'Review moderated';

// Notifications.
$string['messageprovider:reviewpending'] = 'A review waits for approval';
$string['messageprovider:reviewmoderated'] = 'Your review was approved or rejected';
$string['msg_pending_subject'] = 'New review to approve: {$a->target}';
$string['msg_pending_body'] = '{$a->author} rated {$a->target} {$a->rating}/5 with a comment. It shows once you approve it.';
$string['msg_approved_subject'] = 'Your review was published';
$string['msg_approved_body'] = 'Your review of {$a->target} was approved and is now public.';
$string['msg_rejected_subject'] = 'Your review was not approved';
$string['msg_rejected_body'] = 'Your review of {$a->target} was not approved. You can edit it and send it again.';
$string['msg_rejected_reason_subject'] = 'Your review was not approved';
$string['msg_rejected_reason_body'] = 'Your review of {$a->target} was not approved: {$a->reason}. You can edit it and send it again.';

// Privacy.
$string['privacy:metadata:local_nit_reviews'] = 'Star ratings and comments the user wrote about courses and teachers.';
$string['privacy:metadata:local_nit_reviews:courseid'] = 'The course being reviewed, or the course of the teacher being reviewed.';
$string['privacy:metadata:local_nit_reviews:teacherid'] = 'The teacher being reviewed, if any.';
$string['privacy:metadata:local_nit_reviews:userid'] = 'The user who wrote the review.';
$string['privacy:metadata:local_nit_reviews:rating'] = 'The star rating (1–5).';
$string['privacy:metadata:local_nit_reviews:review'] = 'The written comment.';
$string['privacy:metadata:local_nit_reviews:status'] = 'Whether the review is pending, approved or rejected.';
$string['privacy:metadata:local_nit_reviews:rejectreason'] = 'Why a moderator rejected it.';
$string['privacy:metadata:local_nit_reviews:reviewedby'] = 'The moderator who approved or rejected it.';
$string['privacy:metadata:local_nit_reviews:timecreated'] = 'When the review was first written.';
$string['privacy:metadata:local_nit_reviews:timemodified'] = 'When the review was last updated.';

// Errors (mobile API and pages).
$string['err_coursenotfound'] = 'This course was not found.';
$string['err_cannotrate'] = 'You can only review courses you are enrolled in.';
$string['err_cannotrateteacher'] = 'You can rate a teacher only in a course of theirs you are enrolled in, or after a lesson with them.';
$string['err_invalidrating'] = 'Choose a rating from 1 to 5 stars.';
$string['err_reviewtoolong'] = 'The review is too long (at most {$a} characters).';
$string['err_reasontoolong'] = 'The reason is too long (at most {$a} characters).';
$string['err_reviewnotfound'] = 'This review was not found.';
$string['err_toomanycourses'] = 'Too many courses in one request (at most {$a}).';
$string['err_teachernotfound'] = 'This teacher was not found.';
