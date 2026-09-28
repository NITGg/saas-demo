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
 * Strings for local_nit_ai.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI video assistant';

// Capabilities.
$string['nit_ai:use'] = 'Ask the video assistant questions';
$string['nit_ai:manage'] = 'Upload and approve video transcripts';

// Activity form.
$string['formheader'] = 'AI assistant';
$string['enabled'] = 'Enable the AI assistant for this video';
$string['enabled_help'] = 'Students get a chat panel beside the player and can ask about the lesson. It only appears once a transcript has been uploaded and approved.';
$string['transcriptfile'] = 'Transcript file';
$string['transcriptfile_help'] = 'A .vtt, .srt, .json or .txt transcript of this video. Timestamps are strongly preferred: with them the assistant knows where the student is and can link back to the moment something was explained. Uploading a new file resets the approval.';
$string['hasonscreen'] = 'The transcript includes on-screen text (code, slides)';
$string['hasonscreen_help'] = 'Tick this if the transcript covers what is written on screen and not just what is said. If it is speech only, the assistant tells students it can hear the lesson but not see it, instead of guessing.';

// Review panel.
$string['reviewtitle'] = 'AI assistant — transcript review';
$string['reviewintro'] = 'This is what we read from the file. Check it, then approve.';
$string['detectedformat'] = 'Format';
$string['detectedsegments'] = 'Segments';
$string['detectedtimestamps'] = 'Timestamps';
$string['detectedlanguage'] = 'Language';
$string['detectedlast'] = 'Last timestamp';
$string['videolength'] = 'Video length';
$string['lengthunknown'] = 'not reported yet';
$string['lengthpassed'] = 'Transcript covers the whole video';
$string['lengthfailed'] = 'Transcript does not match the video length';
$string['lengthskipped'] = 'Length check skipped';
$string['approve'] = 'Approve and enable';
$string['approved'] = 'Approved — students can use the assistant';
$string['notapproved'] = 'Waiting for your approval — students cannot see the assistant yet';
$string['approvedblocked'] = 'Approved, but the assistant is still not running — see what is blocking it below.';
$string['notenabled'] = 'The assistant is switched off for this activity in its settings.';
$string['notranscript'] = 'No transcript uploaded yet. Add one in the activity settings to switch the assistant on.';
$string['problemsfound'] = 'Check these before approving';
$string['langar'] = 'Arabic';
$string['langen'] = 'English';
$string['langmixed'] = 'Arabic and English';
$string['langunknown'] = 'Could not tell';

// Checks.
$string['check_stale'] = 'The video has been replaced since this transcript was uploaded. The assistant is switched off until a matching transcript is uploaded.';
$string['check_notimestamps'] = 'No timestamps were found. The assistant will still answer questions, but it cannot tell where the student is or link back to a moment in the video.';
$string['check_empty'] = 'No readable text was found in the file.';
$string['check_noprovider'] = 'No AI provider is set up on this site yet, so the assistant cannot answer. An administrator needs to configure one under Site administration > AI.';
$string['check_placementoff'] = 'The AI video assistant is switched off for the whole site. Turn it on under Site administration > AI > AI placements.';
$string['check_contextoff'] = 'AI tools are switched off for this course or activity, so the assistant cannot run here.';
$string['check_lengthmismatch'] = 'The transcript ends at {$a->transcript} but the video runs {$a->video}. This usually means the transcript is truncated, or belongs to a different video.';

// Chat.
$string['chattitle'] = 'Ask about this lesson';
$string['chatintro'] = 'Ask anything about this video. I answer from what is actually said in it.';
$string['chatplaceholder'] = 'Ask a question…';
$string['chatsend'] = 'Send';
$string['chatthinking'] = 'Thinking…';
$string['chatopen'] = 'Open the assistant';
$string['chatclose'] = 'Close the assistant';
$string['chatdisclaimer'] = 'AI generated. This conversation is not saved — it starts fresh each time you open the page.';
$string['jumpto'] = 'Jump to {$a}';

// Errors.
$string['err_emptyquestion'] = 'Type a question first.';
$string['err_aifailed'] = 'The assistant could not answer right now. Please try again.';
$string['err_unavailable'] = 'The assistant is not available for this video.';

$string['privacy:metadata'] = 'The AI video assistant does not store conversations. Questions are sent to the site\'s configured AI provider to be answered and are not kept afterwards.';

// Quiz generator.
$string['nit_ai:generatequiz'] = 'Generate a quiz from a video transcript';
$string['quizgen_formlabel'] = 'Quiz from this video';
$string['quizgen_button'] = 'Generate a quiz with AI';
$string['quizgen_buttonnote'] = 'Opens in a new tab and works from the saved transcript. If you have just changed the file above, save this form first.';
$string['quizgen_paneltitle'] = 'AI quiz';
$string['quizgen_lastquiz'] = 'Last generated:';
$string['quizgen_lastquizstale'] = 'That quiz was written from an older transcript of this video. Generate again if the video has changed.';
$string['quizgen_title'] = 'Generate a quiz from this video';
$string['quizgen_intro'] = 'The transcript of "{$a->video}" runs {$a->length} and was read as {$a->parts} parts. Every part that teaches something gets at least one question, so the number of questions follows the video rather than a setting.';
$string['quizgen_quizname'] = 'Quiz name';
$string['quizgen_quizsuffix'] = 'quiz';
$string['quizgen_language'] = 'Write the questions in';
$string['quizgen_language_help'] = 'Defaults to the language detected in the transcript. Technical terms stay in English where the video says them in English.';
$string['quizgen_types'] = 'Question types';
$string['quizgen_types_help'] = 'Only types that mark themselves. Multiple choice carries the difficult questions; true/false is quick and suits the easy level.';
$string['quizgen_type_multichoice'] = 'Multiple choice (one correct answer)';
$string['quizgen_type_truefalse'] = 'True or false';
$string['quizgen_startbutton'] = 'Generate';
$string['quizgen_level_easy'] = 'Easy';
$string['quizgen_level_medium'] = 'Medium';
$string['quizgen_level_hard'] = 'Hard';
$string['quizgen_part'] = 'part {$a}';
$string['quizgen_working'] = 'Writing questions from the transcript';
$string['quizgen_workingnote'] = 'Leave this page open. Each part is saved as it is written, so a slow provider costs time and not work.';
$string['quizgen_slice'] = 'Part {a} of {b}';
$string['quizgen_fillinggaps'] = 'Going back for the parts nothing was asked about';
$string['quizgen_questionssofar'] = 'Questions so far';
$string['quizgen_coverage'] = 'Video covered';
$string['quizgen_atlimit'] = 'This run has reached the limit set for the site, so generation stopped here.';
$string['quizgen_reviewintro'] = 'Nothing here is a question yet. Untick anything you do not want, then create the quiz.';
$string['quizgen_coveragesummary'] = '{$a->total} questions, reaching {$a->percent}% of the video that can be examined.';
$string['quizgen_gapstitle'] = 'No question was written about these parts';
$string['quizgen_skippedtitle'] = 'Reported as having nothing to examine';
$string['quizgen_keep'] = 'Keep';
$string['quizgen_marks'] = 'marks each';
$string['quizgen_createbutton'] = 'Create the quiz';
$string['quizgen_createdhidden'] = 'The quiz is created hidden, in this video\'s section, with one page per level. Show it once you are happy with it.';
$string['quizgen_created'] = 'Quiz created with {$a} questions. It is hidden until you show it.';
$string['quizgen_categoryinfo'] = 'Written by the AI quiz generator from this video\'s transcript.';
$string['quizgen_quizintro'] = 'Questions about the video in this section.';
$string['quizgen_seenat'] = 'This is covered at {$a} in the video.';

// Quiz generator checks and errors.
$string['quizgen_check_notapproved'] = 'The transcript has not been approved yet. Approve it first — questions are only as good as the text they are written from.';
$string['quizgen_check_placementoff'] = 'The AI quiz generator is switched off for the whole site. Turn it on under Site administration > AI > AI placements.';
$string['quizgen_err_unreadable'] = 'The AI replied in a format we could not read. Nothing was added for this part.';
$string['quizgen_err_norun'] = 'That generation run no longer exists. Start again from the activity.';
$string['quizgen_err_alreadybuilt'] = 'A quiz has already been created from this run.';
$string['quizgen_err_notypes'] = 'Choose at least one question type.';
$string['quizgen_err_nothingtobuild'] = 'There are no questions to build a quiz from.';
$string['quizgen_err_nothingkept'] = 'You unticked every question, so there was nothing to create.';
$string['quizgen_err_nobank'] = 'This course has no question bank the questions could be written into.';
$string['quizgen_err_importfailed'] = 'The questions could not be written into the question bank. Nothing was created.';

// Site settings.
$string['setting_quizgenheading'] = 'AI quiz generator';
$string['setting_quizgenheading_desc'] = 'Limits on generating a quiz from a video transcript. How many questions a video produces is meant to follow how much it teaches; these are guards on spending, not targets.';
$string['setting_maxquestions'] = 'Most questions per run';
$string['setting_maxquestions_desc'] = 'Generation stops once a run holds this many questions, however much of the video is left.';
$string['setting_maxgappasses'] = 'Gap-filling passes';
$string['setting_maxgappasses_desc'] = 'After the first pass, how many extra requests may be spent going back for parts of the video nothing was asked about.';
$string['quizgen_span'] = 'Read from a transcript of {$a->parts} parts ending at {$a->end}, for a video that runs {$a->video}.';
