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
 * Instance configuration form for block_nit_section.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Config form: content + layout controls.
 */
class block_nit_section_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        // ---- Content -------------------------------------------------------.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('advcheckbox', 'config_showtitle', get_string('showtitle', 'block_nit_section'));
        $mform->setDefault('config_showtitle', 0);

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_nit_section'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->hideIf('config_title', 'config_showtitle', 'notchecked');

        // Content mode: Visual (TinyMCE) for simple rich text, or Raw HTML for
        // pasted markup that must survive untouched (templates, scripts, exact
        // layout). Only the field for the chosen mode is shown.
        $mform->addElement('select', 'config_mode', get_string('mode', 'block_nit_section'), [
            'visual' => get_string('mode_visual', 'block_nit_section'),
            'html' => get_string('mode_html', 'block_nit_section'),
        ]);
        $mform->setDefault('config_mode', 'visual');
        $mform->addHelpButton('config_mode', 'mode', 'block_nit_section');

        // Visual editor (uses the user's WYSIWYG editor, e.g. TinyMCE).
        $editoroptions = [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
            'context' => $this->block->context,
        ];
        $mform->addElement('editor', 'config_visualtext', get_string('content', 'block_nit_section'), null, $editoroptions);
        $mform->setType('config_visualtext', PARAM_RAW);
        $mform->hideIf('config_visualtext', 'config_mode', 'neq', 'visual');

        // Raw HTML code area — stored and rendered verbatim.
        $mform->addElement(
            'textarea',
            'config_htmltext',
            get_string('contentraw', 'block_nit_section'),
            ['rows' => 18, 'wrap' => 'off', 'spellcheck' => 'false',
                'style' => 'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre;']
        );
        $mform->setType('config_htmltext', PARAM_RAW);
        $mform->addHelpButton('config_htmltext', 'contentraw', 'block_nit_section');
        $mform->hideIf('config_htmltext', 'config_mode', 'neq', 'html');

        // ---- Layout --------------------------------------------------------.
        $mform->addElement('header', 'layoutheader', get_string('layoutheading', 'block_nit_section'));
        $mform->setExpanded('layoutheader', true);

        $mform->addElement('select', 'config_width', get_string('width', 'block_nit_section'), [
            'full' => get_string('width_full', 'block_nit_section'),
            'twothirds' => get_string('width_twothirds', 'block_nit_section'),
            'half' => get_string('width_half', 'block_nit_section'),
            'third' => get_string('width_third', 'block_nit_section'),
            'quarter' => get_string('width_quarter', 'block_nit_section'),
        ]);
        $mform->setDefault('config_width', 'full');
        $mform->addHelpButton('config_width', 'width', 'block_nit_section');

        $mform->addElement('select', 'config_align', get_string('align', 'block_nit_section'), [
            'stretch' => get_string('align_stretch', 'block_nit_section'),
            'start' => get_string('align_start', 'block_nit_section'),
            'center' => get_string('align_center', 'block_nit_section'),
            'end' => get_string('align_end', 'block_nit_section'),
        ]);
        $mform->setDefault('config_align', 'stretch');
        $mform->addHelpButton('config_align', 'align', 'block_nit_section');

        // Spacing: numeric value + a shared unit. Blank = keep the theme default.
        $mform->addElement('text', 'config_margintop', get_string('margintop', 'block_nit_section'), ['size' => 5]);
        $mform->setType('config_margintop', PARAM_TEXT);
        $mform->addHelpButton('config_margintop', 'margintop', 'block_nit_section');

        $mform->addElement('text', 'config_marginbottom', get_string('marginbottom', 'block_nit_section'), ['size' => 5]);
        $mform->setType('config_marginbottom', PARAM_TEXT);
        $mform->addHelpButton('config_marginbottom', 'marginbottom', 'block_nit_section');

        $mform->addElement('select', 'config_spacingunit', get_string('spacingunit', 'block_nit_section'), [
            'px' => 'px',
            'rem' => 'rem',
            'em' => 'em',
            '%' => '%',
            'vh' => 'vh',
            'vw' => 'vw',
        ]);
        $mform->setDefault('config_spacingunit', 'px');

        $mform->addElement('advcheckbox', 'config_attachprev', get_string('attachprev', 'block_nit_section'));
        $mform->setDefault('config_attachprev', 0);
        $mform->addHelpButton('config_attachprev', 'attachprev', 'block_nit_section');

        $mform->addElement('advcheckbox', 'config_plain', get_string('plain', 'block_nit_section'));
        $mform->setDefault('config_plain', 0);
        $mform->addHelpButton('config_plain', 'plain', 'block_nit_section');
    }

    /**
     * Reject non-numeric spacing values (blank is allowed = theme default).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['config_margintop', 'config_marginbottom'] as $field) {
            if (isset($data[$field]) && trim((string) $data[$field]) !== '' && !is_numeric($data[$field])) {
                $errors[$field] = get_string('spacingnumeric', 'block_nit_section');
            }
        }
        return $errors;
    }

    /**
     * Prepare the Visual editor's draft file area on load.
     *
     * @param stdClass $defaults
     */
    public function set_data($defaults) {
        // Legacy instances (created before the Visual/Raw-HTML mode toggle) stored
        // their markup in config->text with no mode/visualtext/htmltext. The page
        // still renders it via get_content()'s legacy fallback, but the form has no
        // field bound to it — so the editor opens empty. Surface that content in the
        // Raw HTML field (verbatim, no WYSIWYG cleaning); the next save migrates the
        // instance to mode='html' + htmltext and drops the orphan text field.
        $config = $this->block->config ?? null;
        if ($config && empty($config->mode) && empty($config->visualtext)
                && empty($config->htmltext) && !empty($config->text)) {
            $this->block->config->mode = 'html';
            $this->block->config->htmltext = $config->text;
        }

        if (!empty($this->block->config->visualtext)) {
            $text = $this->block->config->visualtext;
            $draftid = file_get_submitted_draft_itemid('config_visualtext');
            $defaults->config_visualtext['text'] = file_prepare_draft_area(
                $draftid,
                $this->block->context->id,
                'block_nit_section',
                'content',
                0,
                ['subdirs' => true],
                $text
            );
            $defaults->config_visualtext['itemid'] = $draftid;
            $defaults->config_visualtext['format'] = $this->block->config->format ?? FORMAT_HTML;

            unset($this->block->config->visualtext);
            parent::set_data($defaults);
            $this->block->config->visualtext = $text;
            return;
        }
        parent::set_data($defaults);
    }

    /**
     * Show the config form immediately when the block is added.
     *
     * @return bool
     */
    public static function display_form_when_adding(): bool {
        return true;
    }
}
