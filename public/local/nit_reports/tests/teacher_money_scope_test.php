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

namespace local_nit_reports;

/**
 * BUG-043 (QA run 1): a category manager sees a teacher's money only from the
 * courses of their category — never the teacher's whole earnings, payouts or wallet.
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_reports\scope
 * @covers     \local_nit_reports\report\teachers
 * @covers     \local_nit_reports\report\teacher_dues
 */
final class teacher_money_scope_test extends \advanced_testcase {

    /** @var \stdClass teaches in both categories */
    private $teacher;
    /** @var \stdClass manager of category A only */
    private $manager;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('nit_earning')) {
            $this->markTestSkipped('local_nit_finance is not installed.');
        }
        $gen = $this->getDataGenerator();
        $cata = $gen->create_category();
        $catb = $gen->create_category();
        $coursea = $gen->create_course(['category' => $cata->id]);
        $courseb = $gen->create_course(['category' => $catb->id]);
        $this->teacher = $gen->create_user(['lastname' => 'Moneyteacher']);
        $gen->enrol_user($this->teacher->id, $coursea->id, 'editingteacher');
        $gen->enrol_user($this->teacher->id, $courseb->id, 'editingteacher');
        $this->manager = $gen->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $this->manager->id, \context_coursecat::instance($cata->id));

        $cma = (int) $gen->create_module('page', ['course' => $coursea->id])->cmid;
        $cmb = (int) $gen->create_module('page', ['course' => $courseb->id])->cmid;
        $tid = (int) $this->teacher->id;
        $this->earning($tid, 'cm', $cma, 5000);        // 50 in category A.
        $this->earning($tid, 'cm', $cmb, 70000);       // 700 in category B.
        $this->earning($tid, 'lesson', 12345, 2500);   // 25 from a private lesson (no course).
        $now = time();
        $DB->insert_record('nit_withdrawal', (object) ['teacherid' => $tid, 'amount_minor' => 20000, 'method' => 'bank',
            'status' => 'paid', 'processedby' => 0, 'timeprocessed' => $now, 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('nit_withdrawal', (object) ['teacherid' => $tid, 'amount_minor' => 3000, 'method' => 'bank',
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now]);
        $DB->insert_record('nit_wallet', (object) ['ownertype' => 'teacher', 'userid' => $tid, 'balance_minor' => 123400,
            'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * An earning row as local_nit_finance writes it (teacher gets 100% to keep sums plain).
     *
     * @param int $teacherid
     * @param string $source cm | lesson
     * @param int $lessonid cmid or nit_lesson id
     * @param int $amount teacher amount, minor units
     */
    private function earning(int $teacherid, string $source, int $lessonid, int $amount): void {
        global $DB;
        $DB->insert_record('nit_earning', (object) ['lessonid' => $lessonid, 'source' => $source,
            'teacherid' => $teacherid, 'studentid' => 0, 'purchaseid' => 0, 'flex_value_minor' => $amount,
            'teacher_amount_minor' => $amount, 'platform_amount_minor' => 0, 'teacher_percent' => 100,
            'platform_percent' => 0, 'status' => 'active', 'timecreated' => time(), 'timemodified' => time()]);
    }

    /**
     * A report as a user sees it: columns, the teacher's row, the cards.
     *
     * @param string $class report class
     * @param \stdClass $user
     * @return array{columns:array, row:array, cards:array}
     */
    private function view(string $class, \stdClass $user): array {
        $this->setUser($user);
        $_GET = ['q' => 'Moneyteacher'];
        $report = new $class(filters::from_request(), scope::for_user((int) $user->id));
        $rows = $report->rows(0, 0)['rows'];
        $cards = array_column($report->summary(), 'value', 'id');
        $_GET = [];
        $this->assertCount(1, $rows);
        return ['columns' => array_keys($report->columns()), 'row' => $rows[0], 'cards' => $cards];
    }

    public function test_category_manager_sees_teacher_dues_of_their_category_only(): void {
        $v = $this->view(report\teacher_dues::class, $this->manager);

        $this->assertSame(data::minor(5000), $v['row']['earned']);
        $this->assertSame(data::minor(5000), $v['row']['activities']);
        $this->assertSame('1', $v['row']['operations']);
        // Payouts, withdrawal requests, the wallet and private lessons belong to no course: hidden.
        foreach (['paid', 'lastpaid', 'requested', 'balance', 'lessons', 'unpaid'] as $col) {
            $this->assertNotContains($col, $v['columns'], $col);
            $this->assertArrayNotHasKey($col, $v['row'], $col);
        }
        $this->assertSame(['col_earned' => data::minor(5000)], $v['cards']);
    }

    public function test_category_manager_sees_teachers_earnings_of_their_category_only(): void {
        $v = $this->view(report\teachers::class, $this->manager);

        $this->assertSame(data::minor(5000), $v['row']['earned']);
        foreach (['paid', 'wallet', 'pending'] as $col) {
            $this->assertNotContains($col, $v['columns'], $col);
            $this->assertArrayNotHasKey($col, $v['row'], $col);
        }
        $this->assertSame(data::minor(5000), $v['cards']['card_earned']);
        $this->assertArrayNotHasKey('card_paidout', $v['cards']);
    }

    public function test_site_admin_still_sees_all_of_the_teachers_money(): void {
        $dues = $this->view(report\teacher_dues::class, get_admin());
        $this->assertSame(data::minor(77500), $dues['row']['earned']);
        $this->assertSame(data::minor(2500), $dues['row']['lessons']);
        $this->assertSame(data::minor(20000), $dues['row']['paid']);
        $this->assertSame(data::minor(3000), $dues['row']['requested']);
        $this->assertSame(data::minor(123400), $dues['row']['balance']);

        $teachers = $this->view(report\teachers::class, get_admin());
        $this->assertSame(data::minor(77500), $teachers['row']['earned']);
        $this->assertSame(data::minor(123400), $teachers['row']['wallet']);
        $this->assertSame(data::minor(20000), $teachers['cards']['card_paidout']);
    }

    public function test_teacher_still_sees_their_own_whole_line(): void {
        $v = $this->view(report\teacher_dues::class, $this->teacher);

        $this->assertSame(data::minor(77500), $v['row']['earned']);
        $this->assertSame(data::minor(123400), $v['row']['balance']);
        $this->assertSame(data::minor(3000), $v['row']['requested']);
    }
}
