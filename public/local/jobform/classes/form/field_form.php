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

namespace local_jobform\form;

use local_jobform\field_types;
use local_jobform\mlang;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add / edit a single Job Form field.
 *
 * Reused by both the admin template editor (local_jobform) and — through the
 * same field shape — the per-activity editor. The caller decides where to save.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // The field's own id (0 = new). Named 'fieldid' so it never collides with
        // the activity's course-module 'id' when this form is used inside mod_jobform.
        $mform->addElement('hidden', 'fieldid');
        $mform->setType('fieldid', PARAM_INT);
        $mform->setDefault('fieldid', 0);

        // Field label — one input per language; combined into a {mlang} value on save.
        $mform->addElement('text', 'name_en', get_string('fieldname_en', 'local_jobform'), ['size' => 50]);
        $mform->setType('name_en', PARAM_TEXT);
        $mform->addRule('name_en', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'name_ar', get_string('fieldname_ar', 'local_jobform'),
            ['size' => 50, 'dir' => 'rtl']);
        $mform->setType('name_ar', PARAM_TEXT);
        $mform->addHelpButton('name_ar', 'fieldname_ar', 'local_jobform');

        // Optional group — pick from the groups defined on this template / activity.
        $groups = $this->_customdata['groups'] ?? [0 => get_string('nogroup', 'local_jobform')];
        $mform->addElement('select', 'groupid', get_string('fieldgroup', 'local_jobform'), $groups);
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', (int) ($this->_customdata['defaultgroupid'] ?? 0));
        $mform->addHelpButton('groupid', 'fieldgroup', 'local_jobform');

        // Field type.
        $mform->addElement('select', 'type', get_string('fieldtype', 'local_jobform'), field_types::menu());
        $mform->setDefault('type', field_types::TYPE_TEXT);

        // Required flag.
        $mform->addElement('advcheckbox', 'required', get_string('fieldrequired', 'local_jobform'));
        $mform->setDefault('required', 0);

        // --- Dropdown-only settings ---------------------------------------
        // One repeatable row PER option: its English + Arabic value and a delete
        // button grouped under a single "Option N" label, so each row clearly
        // reads as one option. Shown only for the dropdown type.
        $mform->addElement('static', 'optionshdr', get_string('fieldoptions', 'local_jobform'),
            get_string('fieldoptions_help', 'local_jobform'));
        $mform->hideIf('optionshdr', 'type', 'neq', field_types::TYPE_SELECT);

        $optionrow = [
            $mform->createElement('text', 'option_en', '',
                ['size' => 26, 'placeholder' => get_string('optionenglish', 'local_jobform')]),
            $mform->createElement('text', 'option_ar', '',
                ['size' => 26, 'dir' => 'rtl', 'placeholder' => get_string('optionarabic', 'local_jobform')]),
            $mform->createElement('submit', 'option_delete', get_string('deleteoption', 'local_jobform'),
                ['class' => 'btn-outline-danger'], false),
        ];
        $optionelements = [
            $mform->createElement('group', 'optiongroup', get_string('optionn', 'local_jobform'),
                $optionrow, '  ', false),
        ];
        $optionoptions = [
            'option_en'   => ['type' => PARAM_TEXT],
            'option_ar'   => ['type' => PARAM_TEXT],
            'optiongroup' => ['hideif' => ['type', 'neq', field_types::TYPE_SELECT]],
        ];
        $optioncount = max(1, (int) ($this->_customdata['optioncount'] ?? 3));
        $this->repeat_elements($optionelements, $optioncount, $optionoptions,
            'option_repeats', 'option_add', 1, get_string('addoption', 'local_jobform'),
            true, 'option_delete');
        $mform->hideIf('option_add', 'type', 'neq', field_types::TYPE_SELECT);

        $mform->addElement('advcheckbox', 'multiple', get_string('fieldmultiple', 'local_jobform'));
        $mform->setDefault('multiple', 0);
        $mform->hideIf('multiple', 'type', 'neq', field_types::TYPE_SELECT);

        // --- Fixed-value-only setting -------------------------------------
        // The admin-set value the student can only read (bilingual, like labels).
        $mform->addElement('text', 'fixedvalue_en', get_string('fieldfixedvalue_en', 'local_jobform'),
            ['size' => 50]);
        $mform->setType('fixedvalue_en', PARAM_TEXT);
        $mform->addHelpButton('fixedvalue_en', 'fieldfixedvalue', 'local_jobform');
        $mform->hideIf('fixedvalue_en', 'type', 'neq', field_types::TYPE_FIXED);

        $mform->addElement('text', 'fixedvalue_ar', get_string('fieldfixedvalue_ar', 'local_jobform'),
            ['size' => 50, 'dir' => 'rtl']);
        $mform->setType('fixedvalue_ar', PARAM_TEXT);
        $mform->hideIf('fixedvalue_ar', 'type', 'neq', field_types::TYPE_FIXED);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['type'] === field_types::TYPE_SELECT) {
            $filled = array_filter(array_map('trim', $data['option_en'] ?? []));
            if (count($filled) < 1) {
                $errors['option_en[0]'] = get_string('erroroptionsrequired', 'local_jobform');
            }
        }
        if ($data['type'] === field_types::TYPE_FIXED && trim($data['fixedvalue_en'] ?? '') === '') {
            $errors['fixedvalue_en'] = get_string('errorfixedvaluerequired', 'local_jobform');
        }

        return $errors;
    }

    /**
     * Collapse the per-language inputs into the stored {mlang} values.
     *
     * Downstream code (template_manager / instance_manager) still reads
     * $data->name and $data->groupname, so it needs no changes.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->name = mlang::build(['en' => $data->name_en ?? '', 'ar' => $data->name_ar ?? '']);
            $data->options = self::build_options($data->option_en ?? [], $data->option_ar ?? []);
            $data->fixedvalue = mlang::build([
                'en' => $data->fixedvalue_en ?? '', 'ar' => $data->fixedvalue_ar ?? '']);
        }
        return $data;
    }

    /**
     * Pair the repeated English and Arabic option inputs (by index) into {mlang}
     * options, returned as a newline-separated list for field_types::encode_config().
     *
     * @param array $en option_en[] values
     * @param array $ar option_ar[] values
     * @return string
     */
    protected static function build_options(array $en, array $ar): string {
        $keys = array_unique(array_merge(array_keys($en), array_keys($ar)));
        sort($keys, SORT_NUMERIC);
        $built = [];
        foreach ($keys as $k) {
            $option = mlang::build([
                'en' => $en[$k] ?? '',
                'ar' => $ar[$k] ?? '',
            ]);
            if ($option !== '') {
                $built[] = $option;
            }
        }
        return implode("\n", $built);
    }

    /**
     * Prime the form from a stored field record (decoding configdata + mlang).
     *
     * @param object $field
     * @return void
     */
    public function set_field_data(object $field): void {
        $config = field_types::decode_config($field->configdata ?? null);
        $name = mlang::parse($field->name ?? '');
        $fixed = mlang::parse($config['fixedvalue']);

        $data = [
            'fieldid'       => $field->id,
            'name_en'       => $name['en'],
            'name_ar'       => $name['ar'],
            'groupid'       => $field->groupid ?? 0,
            'type'          => $field->type,
            'required'      => $field->required,
            'multiple'      => $config['multiple'] ? 1 : 0,
            'fixedvalue_en' => $fixed['en'],
            'fixedvalue_ar' => $fixed['ar'],
        ];

        // Split each stored option into the repeated group's English / Arabic inputs.
        // Flat "name[i]" keys are how grouped repeat_elements read their defaults.
        foreach (array_values($config['options']) as $i => $option) {
            $parts = mlang::parse($option);
            $data['option_en[' . $i . ']'] = $parts['en'];
            $data['option_ar[' . $i . ']'] = $parts['ar'];
        }

        $this->set_data($data);
    }
}
