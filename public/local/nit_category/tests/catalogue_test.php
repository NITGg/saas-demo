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

namespace local_nit_category;

/**
 * Tests for the shared course catalogue (web page + mobile API).
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_category\catalogue
 */
final class catalogue_test extends \advanced_testcase {

    /**
     * Give a course an active default price (local_payments).
     *
     * @param int $courseid
     * @param float $price
     */
    protected function price(int $courseid, float $price): void {
        global $DB;
        $DB->insert_record('local_payments_course_prices', (object) [
            'courseid' => $courseid, 'country' => '*', 'currency' => 'EGP', 'price' => $price,
            'is_default' => 1, 'is_active' => 1, 'priority' => 0, 'created_by' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    public function test_normalise_filters_falls_back_on_unknown_values(): void {
        $f = catalogue::normalise_filters('bogus', 'cheap', 'Expert', 9);
        $this->assertSame('recommended', $f['sort']);
        $this->assertSame('all', $f['price']);
        $this->assertSame('', $f['level']);       // No "level" field → never a level filter.
        $this->assertSame(0, $f['rating']);

        $f = catalogue::normalise_filters('az', 'paid', '', 0);
        $this->assertSame('az', $f['sort']);
        $this->assertSame('paid', $f['price']);
    }

    public function test_course_state_free_paid_and_enrolled(): void {
        $this->resetAfterTest();
        if (!catalogue::checkout_available()) {
            $this->markTestSkipped('local_payments / local_nit_commerce not installed');
        }
        $gen = $this->getDataGenerator();
        $free = $gen->create_course();
        $paid = $gen->create_course();
        $this->price((int) $paid->id, 150);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $free->id, 'student');

        $s = catalogue::course_state((int) $free->id, (int) $user->id);
        $this->assertTrue($s['enrolled']);
        $this->assertTrue($s['free']);
        $this->assertFalse($s['haspricing']);

        $s = catalogue::course_state((int) $paid->id, (int) $user->id);
        $this->assertFalse($s['enrolled']);
        $this->assertFalse($s['free']);
        $this->assertTrue($s['haspricing']);
        $this->assertEquals(150.0, $s['price']);
        $this->assertSame('EGP', $s['currency']);

        // Anonymous (userid 0) is never enrolled / covered.
        $s = catalogue::course_state((int) $free->id, 0);
        $this->assertFalse($s['enrolled']);
        $this->assertFalse($s['covered']);
    }

    public function test_search_filters_pages_and_searches(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category();
        $sub = $gen->create_category(['parent' => $cat->id]);
        $a = $gen->create_course(['category' => $cat->id, 'fullname' => 'Physics A']);
        $b = $gen->create_course(['category' => $sub->id, 'fullname' => 'Chemistry B']);
        $c = $gen->create_course(['category' => $sub->id, 'fullname' => 'Physics C']);
        if (catalogue::checkout_available()) {
            $this->price((int) $c->id, 99);
        }

        // Recursive by default: the subcategory's courses are included.
        $r = catalogue::search(['categoryid' => $cat->id], 0, 50, 0);
        $this->assertSame(3, $r['total']);

        // Not recursive: only the direct course.
        $r = catalogue::search(['categoryid' => $cat->id, 'recursive' => 0], 0, 50, 0);
        $this->assertSame([(int) $a->id], array_column($r['courses'], 'id'));

        // Text search.
        $r = catalogue::search(['categoryid' => $cat->id, 'q' => 'physics'], 0, 50, 0);
        $this->assertEqualsCanonicalizing([(int) $a->id, (int) $c->id], array_column($r['courses'], 'id'));

        // Paging keeps the total.
        $r = catalogue::search(['categoryid' => $cat->id, 'sort' => 'az'], 1, 2, 0);
        $this->assertSame(3, $r['total']);
        $this->assertCount(1, $r['courses']);
        $this->assertSame('Physics C', $r['courses'][0]['fullname']);

        if (catalogue::checkout_available()) {
            $r = catalogue::search(['categoryid' => $cat->id, 'price' => 'paid'], 0, 50, 0);
            $this->assertSame([(int) $c->id], array_column($r['courses'], 'id'));
            $this->assertSame(9900, $r['courses'][0]['price_minor']);
            $r = catalogue::search(['categoryid' => $cat->id, 'price' => 'free'], 0, 50, 0);
            $this->assertEqualsCanonicalizing([(int) $a->id, (int) $b->id], array_column($r['courses'], 'id'));
        }
    }

    public function test_public_search_hides_hidden_courses_and_categories(): void {
        $this->resetAfterTest();
        $this->setAdminUser(); // An admin token used as the shared pre-login token.
        $gen = $this->getDataGenerator();
        $open = $gen->create_category();
        $hiddencat = $gen->create_category(['visible' => 0]);
        $shown = $gen->create_course(['category' => $open->id]);
        $gen->create_course(['category' => $open->id, 'visible' => 0]);
        $gen->create_course(['category' => $hiddencat->id]);

        $r = catalogue::search(['categoryid' => $open->id], 0, 50, 0, true);
        $this->assertSame([(int) $shown->id], array_column($r['courses'], 'id'));

        $ids = array_column(catalogue::category_tree(true), 'id');
        $this->assertContains((int) $open->id, $ids);
        $this->assertNotContains((int) $hiddencat->id, $ids);

        $this->expectException(\moodle_exception::class);
        catalogue::search(['categoryid' => $hiddencat->id], 0, 50, 0, true);
    }

    public function test_teachers_lists_each_teacher_once(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $t = $gen->create_user(['firstname' => 'Mona', 'lastname' => 'Adel']);
        $gen->enrol_user($t->id, $course->id, 'editingteacher');
        $gen->enrol_user($t->id, $course->id, 'teacher');
        $s = $gen->create_user();
        $gen->enrol_user($s->id, $course->id, 'student');

        $out = catalogue::teachers([(int) $course->id]);
        $this->assertCount(1, $out[(int) $course->id]);
        $this->assertSame('Mona Adel', $out[(int) $course->id][0]['fullname']);
    }

    public function test_root_nodes_drop_empty_subtrees(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $parent = $gen->create_category();
        $full = $gen->create_category(['parent' => $parent->id]);
        $gen->create_category(['parent' => $parent->id]); // Empty: must be dropped.
        $gen->create_course(['category' => $full->id]);

        $cat = \core_course_category::get($parent->id);
        $nodes = catalogue::root_nodes($cat, $cat->get_children(), null,
            catalogue::normalise_filters('recommended', 'all', '', 0));
        $this->assertCount(1, $nodes);
        $this->assertSame((int) $full->id, (int) $nodes[0]['cat']->id);
        $this->assertSame(1, catalogue::count_tree($nodes[0]));
    }

    public function test_normalise_query(): void {
        $this->assertSame('', catalogue::normalise_query("  \t "));
        $this->assertSame('math basics', catalogue::normalise_query("  Math \n  Basics "));
        $this->assertSame(100, \core_text::strlen(catalogue::normalise_query(str_repeat('ر', 150))));
    }

    public function test_root_nodes_keep_only_courses_matching_the_search(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $parent = $gen->create_category();
        $a = $gen->create_category(['parent' => $parent->id]);
        $b = $gen->create_category(['parent' => $parent->id]);
        $gen->create_course(['category' => $a->id, 'fullname' => 'Mathematics One']);
        $gen->create_course(['category' => $a->id, 'fullname' => 'History']);
        $gen->create_course(['category' => $b->id, 'fullname' => 'Arabic', 'summary' => 'النحو والصرف']);

        $cat = \core_course_category::get($parent->id);
        $filters = catalogue::normalise_filters('recommended', 'all', '', 0);
        $courses = static fn(array $nodes): array => array_merge(...array_map(
            static fn($n) => array_map(static fn($c) => $c->fullname, $n['courses']), $nodes));

        // Name match: only that course, and the category left without a match is dropped.
        $nodes = catalogue::root_nodes($cat, $cat->get_children(), null,
            $filters + ['q' => catalogue::normalise_query('MATH')]);
        $this->assertSame(['Mathematics One'], $courses($nodes));
        $this->assertSame((int) $a->id, (int) $nodes[0]['cat']->id);

        // Summary match, Arabic text.
        $nodes = catalogue::root_nodes($cat, $cat->get_children(), null, $filters + ['q' => 'الصرف']);
        $this->assertSame(['Arabic'], $courses($nodes));

        // No search text: everything.
        $this->assertCount(3, $courses(catalogue::root_nodes($cat, $cat->get_children(), null, $filters + ['q' => ''])));
    }

    public function test_suggest_lists_the_first_matches_and_links_to_the_catalogue(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $cat = $gen->create_category(['name' => 'Year 1']);
        for ($i = 1; $i <= 8; $i++) {
            $gen->create_course(['category' => $cat->id, 'fullname' => "Physics $i"]);
        }
        $gen->create_course(['category' => $cat->id, 'fullname' => 'Chemistry']);
        $gen->create_course(['category' => $cat->id, 'fullname' => 'Physics hidden', 'visible' => 0]);
        $this->setUser($gen->create_user());

        $out = catalogue::suggest('  physics ', 6);
        $this->assertSame('physics', $out['q']);
        $this->assertSame(8, $out['total']); // The hidden course is not offered to a student.
        $this->assertCount(6, $out['courses']);
        $this->assertSame('Year 1', $out['courses'][0]['category']);
        $this->assertStringContainsString('/course/view.php?id=', $out['courses'][0]['url']);
        $this->assertStringContainsString('/local/nit_category/index.php?q=physics', $out['moreurl']);

        // One letter is too short to search.
        $this->assertSame(0, catalogue::suggest('p')['total']);
    }
}
