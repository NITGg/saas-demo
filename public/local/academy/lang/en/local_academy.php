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
$string['err_teachernotfound']  = 'Teacher not found.';

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
