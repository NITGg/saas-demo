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

namespace local_academy;

use local_academy\local\admin_order;

/**
 * The order of Site administration → Plugins → Local plugins.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\admin_order
 */
final class admin_order_test extends \advanced_testcase {

    /** A root with a 'localplugins' category holding pages named $names, in that order. */
    private function root(array $names): \admin_root {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $root = new \admin_root(true);
        $root->add('root', new \admin_category('localplugins', 'Local plugins'));
        foreach ($names as $name) {
            $root->add('localplugins', new \admin_externalpage($name, $name, new \moodle_url('/' . $name . '.php')));
        }
        return $root;
    }

    /** The names under 'localplugins'. */
    private function names(\admin_root $root): array {
        return array_map(static fn($c) => $c->name, $root->locate('localplugins')->get_children());
    }

    public function test_listed_pages_lead_in_order_and_the_rest_keep_theirs(): void {
        $root = $this->root(['x', 'c', 'y', 'a', 'z', 'b']);
        admin_order::apply($root, 'localplugins', ['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c', 'x', 'y', 'z'], $this->names($root));
    }

    public function test_each_plugin_calling_it_while_loading_ends_in_the_same_order(): void {
        // The plugins load in any order (core sorts them by the display name of the
        // current language); each calls apply() after adding its own page.
        $root = $this->root(['x']);
        foreach (['c', 'y', 'a', 'b'] as $name) {
            $root->add('localplugins', new \admin_externalpage($name, $name, new \moodle_url('/' . $name . '.php')));
            admin_order::apply($root, 'localplugins', ['a', 'b', 'c']);
        }
        $root->add('localplugins', new \admin_externalpage('z', 'z', new \moodle_url('/z.php'))); // Loaded after them all.
        $this->assertSame(['a', 'b', 'c', 'x', 'y', 'z'], $this->names($root));
    }

    public function test_missing_parent_or_pages_are_ignored(): void {
        $root = $this->root(['x', 'y']);
        admin_order::apply($root, 'nosuchcategory');
        admin_order::apply($root, 'localplugins', ['gone', 'y']);
        $this->assertSame(['y', 'x'], $this->names($root));
    }
}
