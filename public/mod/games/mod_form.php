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
 * The settings form for a Game activity.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use local_games\registry;

/**
 * Game activity settings form.
 */
class mod_games_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('gamesname', 'mod_games'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // -- Which game ---------------------------------------------------
        $mform->addElement('header', 'gamehdr', get_string('thegame', 'mod_games'));
        $mform->setExpanded('gamehdr');

        $options = self::game_options();

        if (!$options) {
            // Every game switched off in Game control. Saying so beats an empty
            // menu the teacher cannot get past and cannot explain.
            $mform->addElement('static', 'nogames', get_string('choosegame', 'mod_games'),
                html_writer::div(get_string('nogamesavailable', 'mod_games'), 'alert alert-warning mb-0'));
        } else {
            $mform->addElement('select', 'gameid', get_string('choosegame', 'mod_games'), $options);
            $mform->addHelpButton('gameid', 'choosegame', 'mod_games');
            $mform->addRule('gameid', null, 'required', null, 'client');
        }

        $mform->addElement('advcheckbox', 'showhublink', get_string('showhublink', 'mod_games'));
        $mform->addHelpButton('showhublink', 'showhublink', 'mod_games');
        $mform->setDefault('showhublink', 1);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * The games a teacher may pick from, grouped by the corner's own sections.
     *
     * Only playable games are listed: one that is still being built, or that an
     * admin has switched off in Game control, would give the class a card that
     * refuses to open.
     *
     * @return array menu options, game slug => label
     */
    protected static function game_options(): array {
        $bysection = [];

        foreach (registry::get_games() as $id => $game) {
            if ($game['status'] !== registry::STATUS_LIVE) {
                continue;
            }
            $section = get_string('cat_' . $game['category'], 'local_games');
            $bysection[$section][$id] = $game['emoji'] . '  ' . registry::name($id);
        }

        // A flat menu with the section as a prefix, rather than optgroups: the
        // activity form's select does not render groups, and the section is
        // worth keeping because "Numbers" and "Questions" is most of what a
        // teacher is choosing between.
        $options = [];
        foreach ($bysection as $section => $games) {
            foreach ($games as $id => $label) {
                $options[$id] = $section . ' — ' . $label;
            }
        }

        return $options;
    }

    /**
     * Server-side validation.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['gameid']) || !registry::is_live($data['gameid'])) {
            $errors['gameid'] = get_string('errorpickagame', 'mod_games');
        }

        return $errors;
    }

    /**
     * Add this module's completion rules to the completion section.
     *
     * @return string[] the element names added
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $playsel = 'completionplays' . $suffix;
        $group = [
            $mform->createElement('checkbox', $playsel . 'enabled', '',
                get_string('completionplays', 'mod_games')),
            $mform->createElement('text', $playsel, '', ['size' => 3]),
        ];
        $mform->setType($playsel, PARAM_INT);
        $mform->addGroup($group, $playsel . 'group',
            get_string('completionplaysgroup', 'mod_games'), [' '], false);
        $mform->hideIf($playsel, $playsel . 'enabled', 'notchecked');
        $mform->setDefault($playsel, 1);

        $scoreel = 'completionscore' . $suffix;
        $group = [
            $mform->createElement('checkbox', $scoreel . 'enabled', '',
                get_string('completionscore', 'mod_games')),
            $mform->createElement('text', $scoreel, '', ['size' => 3]),
        ];
        $mform->setType($scoreel, PARAM_INT);
        $mform->addGroup($group, $scoreel . 'group',
            get_string('completionscoregroup', 'mod_games'), [' '], false);
        $mform->hideIf($scoreel, $scoreel . 'enabled', 'notchecked');
        $mform->setDefault($scoreel, 10);

        return [$playsel . 'group', $scoreel . 'group'];
    }

    /**
     * Whether any of this module's completion rules is switched on.
     *
     * @param array $data submitted values
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();

        return (!empty($data['completionplays' . $suffix . 'enabled'])
                && $data['completionplays' . $suffix] > 0)
            || (!empty($data['completionscore' . $suffix . 'enabled'])
                && $data['completionscore' . $suffix] > 0);
    }

    /**
     * Turn the two checkbox-plus-number pairs into the numbers we store.
     *
     * @param stdClass $data submitted values
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (empty($data->completionunlocked)) {
            return;
        }

        $suffix = $this->get_suffix();
        $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;

        foreach (['completionplays', 'completionscore'] as $rule) {
            $element = $rule . $suffix;
            if (!$autocompletion || empty($data->{$element . 'enabled'})) {
                $data->$element = 0;
            }
        }
    }

    /**
     * Put the stored numbers back into the checkbox-plus-number pairs.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $suffix = $this->get_suffix();

        foreach (['completionplays', 'completionscore'] as $rule) {
            $element = $rule . $suffix;
            $value = (int) ($defaultvalues[$element] ?? 0);
            $defaultvalues[$element . 'enabled'] = $value > 0 ? 1 : 0;
            if ($value === 0) {
                // Unticking the rule must not also wipe the number the teacher
                // typed the last time they used it, so the box keeps a sensible
                // default rather than showing 0.
                $defaultvalues[$element] = $rule === 'completionscore' ? 10 : 1;
            }
        }
    }
}
