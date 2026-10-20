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

namespace mod_games\form;

use mod_games\content;

defined('MOODLE_INTERNAL') || die();

/**
 * Add or edit one row of a Game activity's own content.
 *
 * The corner's row form, pointed at the activity: the same fields per game, the
 * same one-input-per-language translatable fields and the same validation. Only
 * where the row is stored and who may store it differ.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_form extends \local_games\form\content_form {

    /** @var \stdClass|null The games record, once loaded. */
    protected $game = null;

    /** @var \cm_info|\stdClass|null The course module, once loaded. */
    protected $cm = null;

    /**
     * The course module id this form is editing, however it was opened.
     *
     * @return int
     */
    protected function cmid(): int {
        return (int) ($this->_customdata['cmid'] ?? $this->optional_param('cmid', 0, PARAM_INT));
    }

    /**
     * The activity this form edits.
     *
     * @return \stdClass the games record
     */
    protected function game(): \stdClass {
        global $DB;

        if ($this->game === null) {
            [, $this->cm] = get_course_and_cm_from_cmid($this->cmid(), 'games');
            $this->game = $DB->get_record('games', ['id' => $this->cm->instance], '*', MUST_EXIST);
        }

        return $this->game;
    }

    /**
     * The game slug comes from the activity, never from the request.
     *
     * @return string
     */
    protected function gameid(): string {
        return $this->game()->gameid;
    }

    /**
     * Form definition: the corner's, plus the course module id.
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;
        $mform->addElement('hidden', 'cmid', $this->cmid());
        $mform->setType('cmid', PARAM_INT);
    }

    /**
     * The activity's context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $this->game();
        return \context_module::instance($this->cm->id);
    }

    /**
     * Who may open it.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/games:managecontent', $this->get_context_for_dynamic_submission());
    }

    /**
     * Store what the dialogue submitted.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $game = $this->game();

        $rowid = content::save_row((int) ($data->rowid ?? 0), $game, self::to_row($data, $game->gameid));

        return ['cmid' => $this->cmid(), 'rowid' => $rowid];
    }

    /**
     * Fill the dialogue in when it opens.
     */
    public function set_data_for_dynamic_submission(): void {
        $game = $this->game();
        $rowid = $this->optional_param('rowid', 0, PARAM_INT);

        $data = ['id' => $game->gameid, 'cmid' => $this->cmid(), 'rowid' => $rowid];

        if ($rowid > 0) {
            $row = content::get_row($rowid, (int) $game->id);
            if (!$row) {
                throw new \moodle_exception('errorunknownrow', 'local_games');
            }
            $data += self::to_data($row->data, $game->gameid);
        }

        $this->set_data($data);
    }

    /**
     * Where the dialogue's "open in a page" fallback goes.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/games/content_edit.php', [
            'cmid'  => $this->cmid(),
            'rowid' => $this->optional_param('rowid', 0, PARAM_INT),
        ]);
    }
}
