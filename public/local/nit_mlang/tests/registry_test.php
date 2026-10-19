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

namespace local_nit_mlang;

/**
 * Which text inputs get per-language boxes.
 *
 * The lists are applied in the browser by amd/src/fields.js; {@see self::splits()}
 * reproduces its rule (allowed by text_fields() and not excluded by a
 * `pagetypeglob|fieldglob` in text_excludes()) so the shipped lists can be checked here.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(registry::class)]
final class registry_test extends \advanced_testcase {

    /**
     * A question's answer inputs hold values the student's response is compared
     * with (a Calculated formula, a Numerical number, a Short-answer word). Wrapped in
     * {mlang} they stop matching, and a Calculated formula even reports {mlang ar}
     * as a wildcard, so they must stay single on every question-editing page.
     *
     * @param string $qtype question type plugin name
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('question_types')]
    public function test_question_answer_inputs_are_not_split(string $qtype): void {
        $this->resetAfterTest();
        $pagetype = 'question-type-' . $qtype;

        $this->assertFalse(self::splits($pagetype, 'answer[0]'));
        $this->assertFalse(self::splits($pagetype, 'answer[12]'));
        // The question's name is a display string and keeps its language boxes.
        $this->assertTrue(self::splits($pagetype, 'name'));
    }

    /**
     * Question types whose edit form has `answer[n]` text inputs.
     *
     * @return array
     */
    public static function question_types(): array {
        return [
            'calculated'       => ['calculated'],
            'calculatedsimple' => ['calculatedsimple'],
            'calculatedmulti'  => ['calculatedmulti'],
            'numerical'        => ['numerical'],
            'shortanswer'      => ['shortanswer'],
        ];
    }

    public function test_answer_inputs_elsewhere_are_still_split(): void {
        $this->resetAfterTest();
        // The exclusion is scoped to question editing, not every answer[n] on the site.
        $this->assertTrue(self::splits('mod-lesson-editpage', 'answer[0]'));
        // A field that was never on the allow list stays single everywhere.
        $this->assertFalse(self::splits('mod-lesson-editpage', 'tolerance[0]'));
    }

    public function test_admin_can_still_add_exclusions(): void {
        $this->resetAfterTest();
        $this->assertTrue(self::splits('mod-lesson-editpage', 'answer[0]'));

        set_config('extraexcludes', "# comment\nmod-lesson-*|answer[*]", 'local_nit_mlang');
        $this->assertFalse(self::splits('mod-lesson-editpage', 'answer[0]'));
    }

    /**
     * Does fields.js give this text input per-language boxes on this page?
     *
     * @param string $pagetype the page's $PAGE->pagetype
     * @param string $fieldname the input's name attribute
     * @return bool
     */
    private static function splits(string $pagetype, string $fieldname): bool {
        $allowed = false;
        foreach (registry::text_fields() as $glob) {
            $allowed = $allowed || self::glob($glob, $fieldname);
        }
        if (!$allowed) {
            return false;
        }
        foreach (registry::text_excludes() as $rule) {
            $parts = explode('|', $rule);
            $fieldglob = count($parts) > 1 ? $parts[1] : '*';
            if (self::glob(trim($parts[0]), $pagetype) && self::glob(trim($fieldglob), $fieldname)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Same glob as fields.js globToRe(): `*` matches anything, everything else is literal.
     *
     * @param string $glob pattern
     * @param string $value value to test
     * @return bool
     */
    private static function glob(string $glob, string $value): bool {
        $re = str_replace('\*', '.*', preg_quote($glob, '~'));
        return (bool) preg_match('~^' . $re . '$~u', $value);
    }
}
