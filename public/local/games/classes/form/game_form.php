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

namespace local_games\form;

use local_games\registry;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * What an administrator may change about one game's card.
 *
 * The name and description are single fields. They are translatable, so
 * local_nit_mlang draws one input per installed language over each of them and
 * composes the stored value itself - the same treatment a course name gets, and
 * the reason this form carries no language selector of its own.
 *
 * Leaving a name empty is a real answer: it means "use the name the corner ships
 * with", so the shipped text is offered as a hint rather than filled in.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game_form extends \moodleform {

    /** @var string The submit-name prefix local_nit_mlang splits per language. */
    const MLANG_GROUP = content_form::MLANG_GROUP;

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        /** @var string $gameid */
        $gameid = $this->_customdata['gameid'];
        $key = registry::key($gameid);

        $mform->addElement('hidden', 'id', $gameid);
        $mform->setType('id', PARAM_ALPHANUMEXT);

        // Both fields carry the value that is actually in use, whether that is
        // the admin's or the one the corner ships with. An empty box with the
        // real name printed underneath it made the admin retype what was already
        // there to change one word of it.
        //
        // Both are submitted under the gametext[...] prefix, which is what
        // local_nit_mlang splits into one input per installed language. `name`
        // happens to be on that plugin's site-wide list of display strings and
        // `description` is not - which is why the two behaved differently until
        // they were named the same way.
        foreach (['name', 'description'] as $field) {
            $element = self::MLANG_GROUP . '[' . $field . ']';
            $mform->addElement('text', $element,
                get_string('game' . $field, 'local_games'), ['size' => 60]);
            $mform->setType($element, PARAM_TEXT);
            $mform->addHelpButton($element, 'game' . $field, 'local_games');
        }

        // The card face: an uploaded picture, or the shipped emoji when empty.
        $mform->addElement('filemanager', 'logo', get_string('gamelogo', 'local_games'), null,
            \local_games\logo::options());
        $mform->addHelpButton('logo', 'gamelogo', 'local_games');

        $mform->addElement('advcheckbox', 'enabled', get_string('gameenabled', 'local_games'));
        $mform->addHelpButton('enabled', 'gameenabled', 'local_games');
        $mform->setDefault('enabled', 1);

        $default = registry::get_defaults()[$gameid] ?? ['level' => 1];
        $levels = [0 => get_string('leveldefault', 'local_games', str_repeat('⭐', (int) $default['level']))];
        for ($star = 1; $star <= 3; $star++) {
            $levels[$star] = str_repeat('⭐', $star);
        }
        $mform->addElement('select', 'level', get_string('gamelevel', 'local_games'), $levels);
        $mform->addHelpButton('level', 'gamelevel', 'local_games');
        $mform->setDefault('level', 0);

        // Where the card sits is not a number typed here any more: it is the
        // arrows on the catalogue, where the admin can see what they are
        // moving it past.

        $this->add_action_buttons();
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

        $group = (array) ($data[self::MLANG_GROUP] ?? []);
        $name = trim((string) ($group['name'] ?? ''));

        // What is stored is the composed multilingual value, which is longer than
        // anything typed into one language's input - so the length that matters
        // is the composed one.
        if (\core_text::strlen($name) > 1000) {
            $errors[self::MLANG_GROUP . '[name]'] = get_string('errornametoolong', 'local_games');
        }

        return $errors;
    }

    /**
     * The values in force, ready for the form.
     *
     * Where the admin has set nothing, the fields are filled with what the
     * corner ships - so the form always shows what is on the card right now, and
     * changing one word does not mean retyping the rest.
     *
     * @param string $gameid game slug
     * @param \stdClass|null $override the local_games_game row, or null
     * @return array
     */
    public static function to_data(string $gameid, ?\stdClass $override): array {
        $name = trim((string) ($override->name ?? ''));
        $description = trim((string) ($override->description ?? ''));

        return [
            self::MLANG_GROUP => [
                'name'        => $name !== '' ? $name : registry::shipped_name($gameid),
                'description' => $description !== '' ? $description : registry::shipped_description($gameid),
            ],
            'enabled' => $override !== null ? (int) $override->enabled : 1,
            'level'   => $override !== null ? (int) $override->level : 0,
        ];
    }

    /**
     * Turn the submitted form into what should be stored.
     *
     * A value left exactly as the corner ships it is stored as empty rather than
     * as a copy of itself, and so is one cleared altogether. Both mean the same
     * thing - follow the language pack - which is also how a card is put back:
     * empty the field and save.
     *
     * @param \stdClass $data submitted values
     * @param string $gameid game slug
     * @return array ready for {@see \local_games\admin\manager::save_game()}
     */
    public static function to_record(\stdClass $data, string $gameid): array {
        $group = (array) ($data->{self::MLANG_GROUP} ?? []);

        $name = trim((string) ($group['name'] ?? ''));
        $description = trim((string) ($group['description'] ?? ''));

        return [
            'name'        => $name === registry::shipped_name($gameid) ? '' : $name,
            'description' => $description === registry::shipped_description($gameid) ? '' : $description,
            'enabled'     => empty($data->enabled) ? 0 : 1,
            'level'       => (int) ($data->level ?? 0),
        ];
    }
}
