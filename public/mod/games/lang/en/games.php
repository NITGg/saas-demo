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
 * English strings for the Game activity.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Game';
$string['modulename'] = 'Game';
$string['modulenameplural'] = 'Games';
$string['modulename_help'] = 'The Game activity puts one game from the Games Corner into a course.

Pick the game when you add the activity. Children play it on the activity page exactly as they would in the corner - the same game, the same sounds, the same badges - and the points they collect count towards their standing across the whole site.

What the activity adds is the course\'s half of the picture: who in this class has played it, how they got on, and, if you want one, a completion rule such as "play three rounds".';
$string['pluginadministration'] = 'Game activity administration';

// Capabilities.
$string['games:addinstance'] = 'Add a new Game activity';
$string['games:view'] = 'View a Game activity';
$string['games:play'] = 'Play the game and have the round recorded';
$string['games:viewreports'] = 'See what the class has played';
$string['games:managecontent'] = 'Write the game content of a Game activity';

// The settings form.
$string['gamesname'] = 'Activity name';
$string['thegame'] = 'The game';
$string['choosegame'] = 'Game';
$string['choosegame_help'] = 'Which game of the Games Corner this activity plays. Only finished games that are switched on are listed - an administrator can switch one off in Site administration > Plugins > Local plugins > Game control, and it then disappears from here.';
$string['nogamesavailable'] = 'There are no playable games right now. Every game is either still being built or has been switched off in Game control.';
$string['errorpickagame'] = 'Choose a game for this activity to play.';
$string['showhublink'] = 'Offer the whole Games Corner';
$string['showhublink_help'] = 'Puts a link to the Games Corner under the game, so a child who has finished can go and play the rest. Turn it off to keep the class on the one game you set.';

// Completion.
$string['completionplays'] = 'Rounds to finish:';
$string['completionplaysgroup'] = 'Rounds played';
$string['completionscore'] = 'Score to reach in one round:';
$string['completionscoregroup'] = 'Score reached';
$string['completiondetail:plays'] = 'Finish {$a} rounds';
$string['completiondetail:score'] = 'Score {$a} in a single round';

// The activity page.
$string['backtocourse'] = 'Back to the course';
$string['gotohub'] = 'See all the games';
$string['yourstanding'] = 'You have played this {$a->plays} times. Your best round here: {$a->bestscore}.';
$string['errormissinggame'] = 'This activity was set to a game that no longer exists on this site. Edit the activity and pick another one.';
$string['errorgameoff'] = '"{$a}" has been switched off by an administrator, so it cannot be played right now.';

// The report.
$string['report'] = 'Who has played';
$string['reportfor'] = 'Who has played: {$a}';
$string['viewreport'] = 'See who has played';
$string['backtoactivity'] = 'Back to the game';
$string['reportsummary'] = 'Playing {$a->game}. {$a->played} of {$a->total} students have played it at least once.';
$string['noplayers'] = 'Nobody is enrolled who can play this activity yet.';
$string['notenrolledheading'] = 'Played, but not on the roster';
$string['notenrolledintro'] = 'These rounds were played by somebody who is not currently enrolled with permission to play - unenrolled since, or a teacher trying the activity out. They are listed so the rounds are not invisible; they are not counted in the figure above.';
$string['neverplayed'] = 'Not yet';
$string['colplays'] = 'Rounds';
$string['colpoints'] = 'Correct answers';
$string['colbest'] = 'Best round';
$string['colstreak'] = 'Longest streak';
$string['collastplayed'] = 'Last played';

// Course index.
$string['noinstances'] = 'There are no Game activities in this course.';

// Privacy.
$string['privacy:metadata:play'] = 'What one learner has done in one Game activity. Their points and badges across the whole site belong to the Games Corner and are recorded separately.';
$string['privacy:metadata:play:userid'] = 'The learner who played.';
$string['privacy:metadata:play:plays'] = 'How many rounds they finished in this activity.';
$string['privacy:metadata:play:points'] = 'How many correct answers they gave in this activity.';
$string['privacy:metadata:play:bestscore'] = 'Their best single round in this activity.';
$string['privacy:metadata:play:beststreak'] = 'Their longest run of correct answers in this activity.';
$string['privacy:metadata:play:timemodified'] = 'When their last round in this activity ended.';
$string['privacy:metadata:content'] = 'Game content a teacher wrote for one Game activity.';
$string['privacy:metadata:content:usermodified'] = 'Who last edited the content row.';
$string['privacy:metadata:content:timemodified'] = 'When the content row was last edited.';

// The activity's own content.
$string['gamecontent'] = 'Game content';
$string['editgamecontent'] = 'Edit game content';
$string['gamecontentintro'] = 'Write the questions, words and other material {$a} is played with in this activity. Students in this course get this content; the Games Corner and other activities are not affected.';
$string['yourcontent'] = 'This activity\'s content';
$string['contentmode'] = 'Game content';
$string['contentmode_help'] = 'Where the game\'s questions, words, colours... come from.

* **Default content**: the content the site administrator set for this game in Game control.
* **My content only**: the content you write for this activity. Until you have written some (in the language the student is using), the default content is used so the game always works.
* **My content + default**: your content first, then the default content.

Write the content from "Edit game content" on the activity page.';
$string['contentmode_default'] = 'Default content';
$string['contentmode_own'] = 'My content only';
$string['contentmode_both'] = 'My content + default';
$string['contentmode_desc'] = 'Default content: what the administrator set for this game. My content only: what you write below (the default is used until you have written some in the student\'s language). My content + default: yours first, then the default.';
$string['contentchangenote'] = 'Changing the game to one that uses a different kind of content (for example from a question game to the colour game) deletes this activity\'s own content.';
$string['copydefaults'] = 'Copy the default content ({$a} rows)';
$string['contentcopied'] = '{$a} rows copied from the default content. You can now edit or delete them.';
$string['clearcontent'] = 'Delete all my content';
$string['confirmclearcontent'] = 'Delete all of this activity\'s own content? The game will use the default content until you add more.';
$string['nocontentyet'] = 'This activity has no content of its own yet. Add rows one by one, or copy the default content and edit it.';
$string['status_default'] = 'Students play the default content ({$a} rows in this language).';
$string['status_fallback'] = 'You have no content in this language yet, so students play the default content ({$a} rows).';
$string['status_own'] = 'Students play {$a->total} rows in this language, {$a->own} of them yours.';
$string['toofewrows'] = 'This game needs at least {$a->minimum} rows to play a proper round; it has {$a->count} in this language. Add more rows or choose "My content + default".';
