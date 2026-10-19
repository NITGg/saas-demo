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
 * Library of interface functions and constants for mod_games.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the list of features this module supports.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function games_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        // The corner deliberately keeps no grade: the design doc says a wrong
        // answer is never a failure, and a column in the gradebook would make
        // it one.
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_INTERACTIVECONTENT,
        default => null,
    };
}

/**
 * Add a new Game activity instance.
 *
 * @param stdClass $data form data
 * @param mod_games_mod_form|null $mform
 * @return int new instance id
 */
function games_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->intro = $data->intro ?? '';
    $data->introformat = $data->introformat ?? FORMAT_HTML;
    $data->showhublink = !empty($data->showhublink) ? 1 : 0;
    $data->completionplays = max(0, (int) ($data->completionplays ?? 0));
    $data->completionscore = max(0, (int) ($data->completionscore ?? 0));

    return $DB->insert_record('games', $data);
}

/**
 * Update a Game activity instance.
 *
 * @param stdClass $data form data
 * @param mod_games_mod_form|null $mform
 * @return bool
 */
function games_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $data->showhublink = !empty($data->showhublink) ? 1 : 0;
    $data->completionplays = max(0, (int) ($data->completionplays ?? 0));
    $data->completionscore = max(0, (int) ($data->completionscore ?? 0));

    return $DB->update_record('games', $data);
}

/**
 * Delete a Game activity instance and everything played in it.
 *
 * @param int $id instance id
 * @return bool
 */
function games_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('games', ['id' => $id])) {
        return false;
    }

    // Only this activity's record of the rounds goes. The points and badges the
    // same rounds earned belong to the child's standing in the corner, not to
    // the course, and deleting an activity must not take those with it.
    $DB->delete_records('games_play', ['gamesid' => $id]);
    $DB->delete_records('games', ['id' => $id]);

    return true;
}

/**
 * What the course page needs to know about one Game activity.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 */
function games_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, gameid, completionplays, completionscore';
    if (!$game = $DB->get_record('games', ['id' => $coursemodule->instance], $fields)) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $game->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('games', $game, $coursemodule->id, false);
    }

    // Custom completion rules have to travel with the module info or the course
    // page cannot describe them without loading every instance.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionplays'] = (int) $game->completionplays;
        $info->customdata['customcompletionrules']['completionscore'] = (int) $game->completionscore;
    }

    return $info;
}

/**
 * Sentences describing this activity's active custom completion rules.
 *
 * @param cm_info|stdClass $cm
 * @return string[]
 */
function mod_games_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules'])
            || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $value) {
        if ((int) $value <= 0) {
            continue;
        }
        if ($key === 'completionplays') {
            $descriptions[] = get_string('completiondetail:plays', 'mod_games', $value);
        } else if ($key === 'completionscore') {
            $descriptions[] = get_string('completiondetail:score', 'mod_games', $value);
        }
    }

    return $descriptions;
}
