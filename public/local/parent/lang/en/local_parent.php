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

$string['pluginname'] = 'Parent dashboard';
$string['cachedef_failures'] = 'Wrong phone pairs on the parent dashboard';

// Settings.
$string['settingsheading'] = 'Parent dashboard';
$string['defaultcountrycode'] = 'Default country code';
$string['defaultcountrycode_desc'] = 'Digits put in front of a local number that has no country code, so 010… and +2010… match. Egypt = 20.';
$string['parentphonefield'] = 'Parent phone field';
$string['parentphonefield_desc'] = 'Shortname of the student profile field holding the parent\'s phone. The parent dashboard checks the parent\'s number against it and against the father and mother phone fields.';

// Student sign-up field.
$string['parentphonelabel'] = 'Parent / guardian phone';

// Dashboard — hero and form.
$string['dashboard'] = 'Parent dashboard';
$string['title_a'] = 'Parent';
$string['title_b'] = 'dashboard';
$string['intro'] = 'Follow your child every week — what they watched and the marks they got — so you can be sure of their progress and be part of their learning journey.';
$string['childphone'] = 'Your child\'s phone';
$string['yourphone'] = 'Your phone';
$string['start'] = 'Start following';
$string['err_invalidphone'] = 'Please enter a valid phone number (e.g. 01012345678). Letters are not allowed.';
$string['err_nomatch'] = 'These numbers do not match. Check your child\'s phone and the parent phone they registered.';
$string['err_toomany'] = 'Too many wrong attempts. Please try again in 15 minutes.';

// Dashboard — results.
$string['courselist'] = 'Your child\'s courses';
$string['togglelist'] = 'Collapse / expand the course list';
$string['choosecourse'] = 'Choose a course first to see your child\'s statistics and results';
$string['nocourses'] = 'Your child is not enrolled in any course yet';
$string['stats_a'] = 'Statistics of';
$string['stats_b'] = 'your child!';
$string['showcontent'] = 'Show content';
$string['hidecontent'] = 'Hide content';
$string['nocontent'] = 'No content has been published in this course yet';
$string['emptyweek'] = 'No content yet';
$string['startedon'] = 'Started on:';
$string['col_videos'] = 'Videos';
$string['col_homework'] = 'Homework';
$string['col_exams'] = 'Exams';
$string['studentlevel'] = 'Student level';

// Items, named by their order in the lecture.
$string['label_videos'] = 'Video {$a}';
$string['label_homework'] = 'Homework {$a}';
$string['label_exams'] = 'Exam {$a}';
$string['ord1'] = '1';
$string['ord2'] = '2';
$string['ord3'] = '3';
$string['ord4'] = '4';
$string['ord5'] = '5';
$string['ord6'] = '6';
$string['ord7'] = '7';
$string['ord8'] = '8';
$string['ord9'] = '9';
$string['ord10'] = '10';
$string['done_videos'] = 'Watched';
$string['done_videos_after'] = 'of the video';
$string['done_homework'] = 'Homework result';
$string['done_exams'] = 'Exam result';
$string['pending_videos'] = 'Watching';
$string['pending_homework'] = 'Handed in, waiting for marking';
$string['pending_exams'] = 'Taken, waiting for the result';
$string['absent_videos'] = 'Not watched';
$string['absent_homework'] = 'Not handed in';
$string['absent_exams'] = 'Not taken';
$string['none_videos'] = 'No videos yet';
$string['none_homework'] = 'No homework yet';
$string['none_exams'] = 'No exams yet';

// Privacy.
$string['privacy:metadata'] = 'The parent dashboard stores no personal data; it shows a student\'s existing data when their phone and their parent\'s phone match.';
