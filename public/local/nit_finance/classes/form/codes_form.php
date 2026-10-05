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

namespace local_nit_finance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_finance\local\catalog;
use local_nit_finance\local\codes;
use local_nit_finance\local\money;

/**
 * Admin: make codes for a lesson, a course or wallet credit.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class codes_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $s = fn(string $key) => get_string($key, 'local_nit_finance');

        $mform->addElement('header', 'generate', $s('codes_generate'));
        $mform->addElement('select', 'itemtype', $s('codes_type'), [
            catalog::CM => $s('itemtype_cm'),
            catalog::COURSE => $s('itemtype_course'),
            catalog::WALLET => $s('itemtype_wallet'),
        ]);

        $mform->addElement('autocomplete', 'cmid', $s('codes_cm'), ['' => ''] + self::lesson_options());
        $mform->hideIf('cmid', 'itemtype', 'neq', catalog::CM);

        $mform->addElement('autocomplete', 'courseid', $s('codes_course'), ['' => ''] + self::course_options());
        $mform->hideIf('courseid', 'itemtype', 'neq', catalog::COURSE);

        $mform->addElement('text', 'amount', $s('codes_amount'), ['size' => 8]);
        $mform->setType('amount', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('amount', 'codes_amount', 'local_nit_finance');

        $mform->addElement('text', 'count', $s('codes_count'), ['size' => 4]);
        $mform->setType('count', PARAM_INT);
        $mform->setDefault('count', 1);

        $mform->addElement('date_selector', 'timeexpires', $s('codes_expires'), ['optional' => true]);

        $mform->addElement('text', 'note', $s('codes_note'), ['size' => 40, 'maxlength' => 255]);
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(false, $s('codes_generate'));
    }

    /**
     * Activities of all courses, labelled "Course › Activity (price)".
     *
     * @return array<int, string>
     */
    private static function lesson_options(): array {
        global $DB;
        $prices = $DB->get_records_menu('nit_item_price', ['itemtype' => catalog::CM], '', 'itemid, price_minor');
        $rows = $DB->get_records_sql(
            "SELECT cm.id, cm.instance, m.name AS modname, c.fullname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               JOIN {course} c ON c.id = cm.course
              WHERE cm.deletioninprogress = 0 AND m.name <> 'label' AND c.id <> :site
           ORDER BY c.fullname, cm.section, cm.id",
            ['site' => SITEID]);
        $names = [];
        foreach ($rows as $row) {
            $names[$row->modname][$row->instance] = null;
        }
        foreach ($names as $modname => $ids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($ids));
            $names[$modname] = $DB->get_records_select_menu($modname, "id $insql", $params, '', 'id, name');
        }
        $out = [];
        foreach ($rows as $row) {
            $label = format_string($row->fullname) . ' › ' . format_string($names[$row->modname][$row->instance] ?? $row->modname);
            if (!empty($prices[$row->id])) {
                $label .= ' (' . money::format((int) $prices[$row->id]) . ')';
            }
            $out[(int) $row->id] = $label;
        }
        return $out;
    }

    /**
     * All courses.
     *
     * @return array<int, string>
     */
    private static function course_options(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records_select_menu('course', 'id <> ?', [SITEID], 'fullname', 'id, fullname') as $id => $name) {
            $out[(int) $id] = format_string($name);
        }
        return $out;
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $type = $data['itemtype'] ?? '';
        if ($type === catalog::CM && empty($data['cmid'])) {
            $errors['cmid'] = get_string('err_chooseitem', 'local_nit_finance');
        }
        if ($type === catalog::COURSE && empty($data['courseid'])) {
            $errors['courseid'] = get_string('err_chooseitem', 'local_nit_finance');
        }
        $amount = trim((string) ($data['amount'] ?? ''));
        $minor = $amount === '' ? null : money::to_minor($amount);
        if ($amount !== '' && $minor === null) {
            $errors['amount'] = get_string('err_badprice', 'local_nit_finance');
        } else if ($amount === '' && $type !== catalog::CM) {
            $errors['amount'] = get_string('err_badprice', 'local_nit_finance');
        } else if ($type === catalog::WALLET && (int) $minor <= 0) {
            $errors['amount'] = get_string('err_amountpositive', 'local_nit_finance');
        }
        $count = (int) ($data['count'] ?? 0);
        if ($count < 1 || $count > codes::MAX_BATCH) {
            $errors['count'] = get_string('err_codecount', 'local_nit_finance', codes::MAX_BATCH);
        }
        return $errors;
    }
}
