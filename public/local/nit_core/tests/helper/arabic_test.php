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

namespace local_nit_core\helper;

/**
 * Arabic search folding (BUG-056), in PHP and in SQL.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\helper\arabic
 */
final class arabic_test extends \advanced_testcase {

    /** Pairs typed differently that must match. */
    const SAME = [['أحمد', 'احمد'], ['إسلام', 'اسلام'], ['آية', 'ايه'], ['مدرسة', 'مدرسه'], ['على', 'علي'],
        ['مسؤول', 'مسوول'], ['قراءة', 'قراءه'], ['مُحَمَّد', 'محمد'], ['جمـــيل', 'جميل'], ['الصف ٣', 'الصف 3'],
        ['Physics', 'physics']];

    public function test_fold_makes_spellings_equal(): void {
        foreach (self::SAME as [$a, $b]) {
            $this->assertSame(arabic::fold($b), arabic::fold($a), "$a / $b");
        }
        $this->assertNotSame(arabic::fold('حلم'), arabic::fold('علم')); // Different letters stay different.
    }

    public function test_sql_fold_matches_like_fold(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (self::SAME as [$stored, $typed]) {
            $id = $DB->insert_record('config_plugins', (object) ['plugin' => 'test_arabic', 'name' => 'n' . random_int(1, 1e9),
                'value' => 'كتاب ' . $stored . ' الجديد']);
            $found = $DB->record_exists_sql('SELECT 1 FROM {config_plugins} WHERE id = :id AND '
                . $DB->sql_like(arabic::sql_fold('value'), ':q', false),
                ['id' => $id, 'q' => '%' . $DB->sql_like_escape(arabic::fold($typed)) . '%']);
            $this->assertTrue($found, "stored $stored, typed $typed");
        }
    }
}
