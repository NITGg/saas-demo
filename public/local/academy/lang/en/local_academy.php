<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy';
$string['academy:manageplatform'] = 'Manage the Academy platform';

// Welcome notification (sent on first login after email signup).
$string['messageprovider:welcome'] = 'Welcome message';
$string['welcome_subject'] = 'Welcome to {$a}!';
$string['welcome_small'] = 'Welcome to {$a}!';
$string['welcome_body'] = 'Hi {$a->name},

Welcome to {$a->site}! Your account is now active.

You can browse courses, enrol, and start learning right away. We are glad to have you with us.';

// Dispatcher / envelope.
$string['err_postrequired']     = 'This action requires POST';
$string['err_authrequired']     = 'Authentication required';
$string['err_invalidtoken']     = 'Invalid token';
$string['err_permissiondenied'] = 'Permission denied';
$string['err_unknownfunction']  = 'Unknown function';
$string['err_internal']         = 'An internal error occurred. Please try again later.';
$string['err_nopermission']     = 'You do not have permission to view this.';
$string['err_teachernotfound']  = 'Teacher not found.';
$string['err_siteunavailable']  = 'This academy is currently unavailable. Please contact the academy administration.';
$string['err_invalidjson']      = 'The parameter "{$a}" must be valid JSON.';
$string['err_requiredfield']    = 'The field "{$a}" is required.';
$string['err_invalidphone']     = 'Please enter a valid phone number (e.g. 01012345678).';
$string['err_invalidlanguage']  = 'Unknown language.';
$string['err_invalidoption']    = 'Invalid choice for "{$a}".';
$string['err_divisionmismatch'] = 'This division does not belong to the chosen study system.';
$string['err_invalidnationalid'] = 'The national ID is 14 digits.';
$string['err_coursenotfound']   = 'Course not found.';
$string['err_notenrolled']      = 'You are not enrolled in this course.';
$string['err_lessonlocked']     = 'Complete the previous lessons first.';
$string['err_lessonforsale']    = 'This lesson must be bought first.';
$string['err_coursenotfree']    = 'This course is not free.';
$string['err_enrolfailed']      = 'Could not enrol you in this course.';

// Password reset (OTP).
$string['err_invalidemail']     = 'Please enter a valid email address.';
$string['err_toomanyrequests']  = 'Too many code requests. Please wait a few minutes and try again.';
$string['err_otpexpired']       = 'This code has expired. Please request a new one.';
$string['err_otplocked']        = 'Too many incorrect attempts. Please request a new code.';
$string['err_otpinvalid']       = 'The code you entered is incorrect.';
$string['err_resetexpired']     = 'Your reset session has expired. Please start again.';
$string['err_weakpassword']     = 'The new password does not meet the requirements.';
$string['err_wrongpassword']    = 'Your current password is incorrect.';
$string['err_authnochange']     = 'This account cannot change its password here (it signs in with Google).';
$string['otp_subject']          = '{$a}: your password reset code';
$string['otp_body']             = 'Hi {$a->name},

Your password reset code for {$a->site} is: {$a->code}

It is valid for {$a->mins} minutes. If you did not request this, you can ignore this email.';

// Quiz manager.
$string['notenrolled'] = 'You are not enrolled in this course';

// Course player.
$string['player_heading'] = 'Course player';
$string['player_heading_desc'] = 'How learners move through a course in the T1 player (every lesson type opens in the same frame).';
$string['player_markcomplete'] = 'Show "Mark as complete"';
$string['player_markcomplete_desc'] = 'For lessons with manual completion, show a Mark as complete button in the player header (and move to the next lesson).';
$string['player_lockorder'] = 'Lock lessons in order';
$string['player_lockorder_desc'] = 'A lesson opens only after every earlier lesson with completion tracking is complete. Locked lessons show a padlock in the curriculum.';
$string['player_markcomplete_btn'] = 'Mark as complete';
$string['player_completed'] = 'Completed';
$string['player_locked'] = 'Complete the previous lessons first';
$string['player_lesson'] = 'Lesson';
$string['player_of'] = 'of';

// Study systems & divisions (Plugins → Local plugins).
$string['academic_page'] = 'Study systems & divisions';
$string['academic_structure'] = 'Study systems and divisions';
$string['academic_structure_desc'] = 'The options of the "Study system" and "Division" dropdowns — on the course settings page (group "custom fields") and on the student profile / registration form. Each study system has its own divisions: once a system is chosen, the division list shows only that system\'s divisions. Saving updates every dropdown; a course whose answer was removed from the list loses that answer.';
$string['academic_system'] = 'Study system';
$string['academic_divisions'] = 'Its divisions (one per line)';
$string['academic_divisions_hint'] = 'One division per line';
$string['academic_add'] = 'Add study system';
$string['academic_remove'] = 'Remove';
$string['academic_nosystems'] = 'Add at least one study system.';
$string['academic_nodivisions'] = 'The study system "{$a}" needs at least one division.';
$string['academic_years_categories'] = 'The <strong>Years</strong> are the course categories (all levels) — add, rename or reorder them on <a href="{$a}">Manage courses and categories</a>. A course\'s year is the category it is in; the student "Year" field lists the categories automatically.';

// Teacher page (local/academy/teacher.php).
$string['teacherpage_title'] = 'Teacher page: {$a}';
$string['teacherpage_notteacher'] = 'This teacher page is not available.';
$string['teacherpage_years'] = 'Years';
$string['teacherpage_courses'] = 'Courses';
$string['teacherpage_coursecount'] = 'Number of courses';
$string['teacherpage_yearcount'] = 'Number of years';
$string['teacherpage_studentcount'] = 'Number of students';
$string['teacherpage_about'] = 'About the teacher';
$string['teacherpage_heading1'] = 'Teacher\'s';
$string['teacherpage_heading2'] = 'courses';
$string['teacherpage_all'] = 'All';
$string['teacherpage_enter'] = 'Go to the course';
$string['teacherpage_subscribe'] = 'Subscribe to the course!';
$string['teacherpage_free'] = 'Join for free';
$string['teacherpage_more'] = '... show more';
$string['teacherpage_less'] = 'Show less';
$string['teacherpage_created'] = 'Created';
$string['teacherpage_modified'] = 'Last updated';
$string['teacherpage_empty'] = 'No courses for this year yet.';
$string['teacherpage_rating'] = 'Rating';
$string['teacherpage_nreviews'] = '{$a} reviews';
$string['teacherpage_ratebtn'] = 'Rate the teacher';
$string['teacherpage_reviews1'] = 'What students';
$string['teacherpage_reviews2'] = 'say';
$string['subjectpage_title'] = 'Subject page: {$a}';
$string['subjectpage_notfound'] = 'This subject page is not available.';
$string['subjectpage_teachers'] = 'Teachers';
$string['subjectpage_teachercount'] = 'Number of teachers';
$string['subjectpage_teachersh1'] = 'Teachers';
$string['subjectpage_heading2'] = 'of the subject';
$string['subjectpage_coursesh1'] = 'Courses';
$string['subjectpage_noteachers'] = 'No teachers shown here yet';
$string['subjectpage_noteachers_desc'] = 'As soon as teachers are added to this subject you will find them here. Meanwhile you can browse the available courses.';
$string['subjectpage_browse'] = 'Browse courses';

// Student registration (register.php + mobile register_student).
$string['reg_firstname'] = 'First name';
$string['reg_secondname'] = 'Second name';
$string['reg_thirdname'] = 'Third name';
$string['reg_lastname'] = 'Last name';
$string['reg_phone'] = 'Phone number';
$string['reg_grade'] = 'School year';
$string['reg_national'] = 'Student national ID';
$string['reg_fatherphone'] = "Father's phone";
$string['reg_motherphone'] = "Mother's phone";
$string['reg_school'] = 'School name';
$string['reg_guardianjob'] = "Guardian's job";
$string['reg_studysystem'] = 'Study system';
$string['reg_governorate'] = 'Governorate';
$string['reg_division'] = 'Division';
$string['reg_religion'] = 'Religious education';
$string['reg_gender'] = 'Gender';
$string['reg_email'] = 'Email';
$string['reg_password'] = 'Password';
$string['reg_required'] = 'This field is required.';
$string['reg_invalidchoice'] = 'Please choose one of the options.';
$string['reg_emailtaken'] = 'An account with this email already exists. Sign in instead.';
$string['reg_mustagree'] = 'You must accept the terms and conditions.';
$string['reg_success'] = 'Your account has been created. Welcome!';
$string['reg_failed'] = 'Please correct the highlighted fields.';
$string['reg_sessionexpired'] = 'Your session expired before the request was sent, so no account was created. Your answers are kept: type the password again and send the request.';
$string['err_registrationdisabled'] = 'Registration is closed on this academy.';
$string['err_invalidregistration'] = 'Please correct the highlighted fields.';
$string['err_toomanyregistrations'] = 'Too many accounts were created from this network. Please try again in an hour.';
$string['reg_passwordmismatch'] = 'The two passwords do not match.';
$string['registration_heading'] = 'Student registration';
$string['registration_enabled'] = 'Allow students to create an account';
$string['registration_enabled_desc'] = 'Opens the registration page (/local/academy/register.php) and the mobile app registration. New accounts are active at once and sign in with their email. Independent of Moodle\'s "Self registration" setting.';
