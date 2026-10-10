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
 * The category image + icon form.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_category\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/local/nit_category/lib.php");

/**
 * The two visual identifiers core never gave a course category.
 *
 * They are deliberately separate things: the IMAGE is the big picture on cards and the
 * category hero, the ICON is the small glyph printed next to the category NAME.
 */
class image_form extends moodleform {

    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        // ---- Image -------------------------------------------------------------
        $mform->addElement('header', 'imageheader', get_string('categoryimage', 'local_nit_category'));
        $mform->setExpanded('imageheader');

        $mform->addElement(
            'filemanager',
            'categoryimage_filemanager',
            get_string('categoryimagefile', 'local_nit_category'),
            null,
            \local_nit_category_image_options()
        );
        $mform->addHelpButton('categoryimage_filemanager', 'categoryimage', 'local_nit_category');

        // ---- Icon --------------------------------------------------------------
        $mform->addElement('header', 'iconheader', get_string('categoryicon', 'local_nit_category'));
        $mform->setExpanded('iconheader');

        // Emoji first: it is the zero-effort option, and most categories will stop here.
        $mform->addElement(
            'text',
            'iconemoji',
            get_string('categoryiconemoji', 'local_nit_category'),
            ['size' => 6, 'maxlength' => 8]
        );
        $mform->setType('iconemoji', PARAM_TEXT);
        $mform->addHelpButton('iconemoji', 'categoryiconemoji', 'local_nit_category');

        $mform->addElement(
            'filemanager',
            'categoryicon_filemanager',
            get_string('categoryiconfile', 'local_nit_category'),
            null,
            \local_nit_category_icon_options()
        );
        $mform->addHelpButton('categoryicon_filemanager', 'categoryiconfile', 'local_nit_category');

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
