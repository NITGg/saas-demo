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
 * English strings for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Parent accounts';

// Capability.
$string['parent:view'] = 'View a linked child\'s progress';

// The parent role.
$string['parentrole'] = 'Parent';
$string['parentroledesc'] = 'A parent or guardian who can follow their linked child\'s marks, quiz activity and events. Assigned automatically in each child\'s context when the accounts are linked by phone number.';

// Settings.
$string['settingsheading'] = 'Parent accounts';
$string['defaultcountrycode'] = 'Default country code';
$string['defaultcountrycode_desc'] = 'Digits prefixed to a local number that has no country code, so 010… and +2010… match. Egypt = 20.';
$string['parentphonefield'] = 'Parent-phone profile field';
$string['parentphonefield_desc'] = 'The shortname of the student custom profile field that holds the parent\'s phone number (filled at student registration).';

// Student sign-up field.
$string['parentphonelabel'] = 'Parent / guardian phone';

// Parent phone-gate page.
$string['parentsignup'] = 'Create a parent account';
$string['parentsignupintro'] = 'Enter the phone number your child registered as their parent\'s number. We only let you continue if a student has already listed it.';
$string['yourphone'] = 'Your phone number';
$string['continuetosignup'] = 'Continue';

// Parent dashboard.
$string['dashboard'] = 'Parent dashboard';
$string['marks'] = 'Marks';
$string['quizactivity'] = 'Quiz activity';
$string['col_course'] = 'Course';
$string['col_grade'] = 'Grade';
$string['col_quiz'] = 'Quiz';
$string['col_score'] = 'Score';
$string['col_taken'] = 'Taken';
$string['col_duration'] = 'Duration';
$string['noquizzes'] = 'No quiz attempts yet.';
$string['nomarks'] = 'No marks yet.';

// Linking / flow messages.
$string['err_noparentnumber'] = 'No student has listed this number. Ask your child to add it to their profile first.';
$string['err_invalidphone'] = 'Please enter a valid phone number (e.g. 01012345678). Letters and special characters are not allowed.';
$string['parentsignupbadge'] = 'Creating a parent account (linked to: {$a})';
$string['parentsignupprompt'] = 'Are you a parent or guardian?';
$string['parentsignuplink'] = 'Create a parent account here';
$string['parentphonehint'] = 'Optional: enter your parent\'s phone number so they can follow your progress and grades.';
$string['mychildren'] = 'My children';
$string['linkedchildren'] = 'Linked children';
$string['nochildren'] = 'No children are linked to your account yet.';

// Notifications to parents.
$string['messageprovider:child_activity'] = 'Updates about linked child (grades, quizzes, and activities)';

$string['notif_quiz_subject'] = 'Quiz submitted: {$a->child} completed {$a->quiz}';
$string['notif_quiz_body'] = 'Hello,

Your child {$a->child} has completed the quiz "{$a->quiz}" in the course "{$a->course}".
Score: {$a->score}

You can view details and progress on your Parent Dashboard:
{$a->url}';

$string['notif_grade_subject'] = 'New grade for {$a->child}: {$a->item}';
$string['notif_grade_body'] = 'Hello,

A new grade has been posted for your child {$a->child} for "{$a->item}" in "{$a->course}".
Grade: {$a->grade}

View your child\'s full progress on your Parent Dashboard:
{$a->url}';

$string['notif_course_completed_subject'] = 'Congratulations! {$a->child} completed {$a->course}';
$string['notif_course_completed_body'] = 'Hello,

Great news! Your child {$a->child} has successfully completed the course "{$a->course}".

View certificates and summary on your Parent Dashboard:
{$a->url}';

$string['notif_course_enrolled_subject'] = '{$a->child} enrolled in a new course: {$a->course}';
$string['notif_course_enrolled_body'] = 'Hello,

Your child {$a->child} has enrolled in the course "{$a->course}".

Follow their progress anytime on your Parent Dashboard:
{$a->url}';

