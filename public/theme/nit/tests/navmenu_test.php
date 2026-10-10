<?php
namespace theme_nit;

/**
 * The navbar menus (Site pages → Navbar menus): which links each user sees.
 *
 * @package    theme_nit
 * @covers     ::theme_nit_navmenu_links
 * @covers     ::theme_nit_user_menu_items
 * @covers     \theme_nit\admin_setting_navlinks
 */
final class navmenu_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/theme/nit/lib.php');
    }

    /** A teacher: an editing-teacher role in a course. */
    private function teacher(): \stdClass {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $gen->create_course()->id, 'editingteacher');
        return $user;
    }

    public function test_menus_keep_moodle_links_until_saved(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(theme_nit_navmenu_links('gear'));
        $this->assertNull(theme_nit_navmenu_links('user'));
        // The installed default ('') is "not saved" too.
        set_config('navmenu_gear', '', 'theme_nit');
        $this->assertNull(theme_nit_navmenu_links('gear'));
        // An emptied list (saved from an empty editor) never leaves the menu blank,
        // and the editor opens with today's links again.
        set_config('navmenu_gear', '[]', 'theme_nit');
        $this->assertNull(theme_nit_navmenu_links('gear'));
        $html = (new admin_setting_navlinks('gear', 'Gear', ''))->output_html('[]');
        $this->assertStringContainsString('value="/my/"', $html);
    }

    public function test_links_follow_who_sees_it(): void {
        set_config('navmenu_gear', json_encode([
            ['name' => 'All', 'url' => '/a.php', 'show' => 'all'],
            ['name' => 'Students', 'url' => '/s.php', 'show' => 'student'],
            ['name' => 'Teachers', 'url' => '/t.php', 'show' => 'teacher'],
            ['name' => 'Admins', 'url' => '/m.php', 'show' => 'admin'],
            ['name' => 'Broken', 'url' => 'javascript:alert(1)', 'show' => 'all'],
        ]), 'theme_nit');
        $names = fn() => array_column(theme_nit_navmenu_links('gear'), 'name');

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(['All', 'Students'], $names());

        $this->setUser($this->teacher());
        $this->assertSame(['All', 'Teachers'], $names());

        $this->setAdminUser();
        $this->assertSame(['All', 'Admins'], $names());

        // Visitors get nothing (the menus are for signed-in users).
        $this->setUser(null);
        $this->assertSame([], $names());
    }

    public function test_editor_starts_from_the_current_gear_menu(): void {
        global $CFG;
        $rows = theme_nit_navmenu_merge_current(
            [['name' => 'Home', 'url' => '/', 'show' => 'all']],
            [
                ['text' => 'Home', 'url' => $CFG->wwwroot . '/?redirect=0', 'haschildren' => false],
                ['text' => 'Library', 'url' => $CFG->wwwroot . '/local/library/index.php', 'haschildren' => false],
                ['text' => 'More', 'haschildren' => true, 'children' => [
                    ['text' => 'Reports', 'url' => $CFG->wwwroot . '/local/x/manage_reports.php'],
                ]],
                ['text' => 'Elsewhere', 'url' => 'https://example.com/', 'haschildren' => false],
            ]);
        $this->assertSame([
            ['name' => 'Home', 'url' => '/', 'show' => 'all'],
            ['name' => 'Library', 'url' => '/local/library/index.php', 'show' => 'all'],
            ['name' => 'Reports', 'url' => '/local/x/manage_reports.php', 'show' => 'admin'],
        ], $rows);
    }

    public function test_user_menu_keeps_language_and_logout(): void {
        $moodle = [
            (object) ['itemtype' => 'link', 'url' => new \moodle_url('/user/profile.php'), 'title' => 'Profile'],
            (object) ['itemtype' => 'submenu-link', 'title' => 'Language', 'submenuid' => 'x'],
            (object) ['itemtype' => 'link', 'url' => new \moodle_url('/login/logout.php'), 'title' => 'Log out'],
        ];
        $items = theme_nit_user_menu_items([['name' => 'Wallet', 'url' => 'https://x/w']], $moodle);
        $this->assertSame(['Wallet', 'Language', 'Log out'], array_column(array_map('get_object_vars', $items), 'title'));
        $this->assertTrue($items[0]->divider);

        // An emptied list still leaves the user a way to log out.
        $items = theme_nit_user_menu_items([], $moodle);
        $this->assertSame(['Language', 'Log out'], array_column(array_map('get_object_vars', $items), 'title'));
    }

    public function test_setting_keeps_order_and_ticked_roles(): void {
        $setting = new admin_setting_navlinks('gear', 'Gear', '');
        // As the editor posts it: one key per row, in the order the rows were arranged,
        // plus the always-posted empty marker.
        $this->assertSame('', $setting->write_setting([
            'name' => ['_' => '', 'r2' => 'C', 'r0' => 'A', 'n1' => 'B'],
            'url' => ['_' => '', 'r2' => '/c', 'r0' => '/a', 'n1' => '/b'],
            'show' => ['r2' => ['manager', 'admin'], 'r0' => ['student'], 'n1' => ['teacher', 'guest', 'x']],
        ]));
        $rows = json_decode(get_config('theme_nit', 'navmenu_gear'), true);
        $this->assertSame(['C', 'A', 'B'], array_column($rows, 'name'));
        // Stored in the checkbox order; unknown roles (and "visitors" off the bar) dropped.
        $this->assertSame([['admin', 'manager'], ['student'], ['teacher']], array_column($rows, 'show'));
    }

    public function test_setting_refuses_a_row_with_no_role_and_bad_links(): void {
        $setting = new admin_setting_navlinks('gear', 'Gear', '');
        $none = get_string('navmenu_show_none', 'theme_nit');
        $this->assertSame($none, $setting->write_setting(['name' => ['r0' => 'A'], 'url' => ['r0' => '/a'],
            'show' => ['r0' => ['guest']]]));
        $this->assertSame($none, $setting->write_setting(['name' => ['r0' => 'A'], 'url' => ['r0' => '/a']]));
        $this->assertNotSame('', $setting->write_setting(['name' => ['r0' => 'A'], 'url' => ['r0' => 'ftp://a'],
            'show' => ['r0' => ['admin']]]));
    }

    public function test_old_single_choices_read_as_roles(): void {
        $this->assertSame(['admin', 'manager', 'teacher', 'student'], theme_nit_navmenu_roles('all', 'gear'));
        $this->assertSame(['admin', 'manager', 'teacher', 'student', 'guest'], theme_nit_navmenu_roles('all', 'bar'));
        $this->assertSame(['admin', 'manager', 'teacher', 'student'], theme_nit_navmenu_roles('user', 'bar'));
        $this->assertSame(['admin', 'manager'], theme_nit_navmenu_roles('admin', 'gear'));
        $this->assertSame(['guest'], theme_nit_navmenu_roles('guest', 'bar'));
        $this->assertSame([], theme_nit_navmenu_roles('guest', 'gear'));
    }

    public function test_a_manager_sees_manager_links_but_not_admin_only_ones(): void {
        global $DB;
        set_config('navmenu_gear', json_encode([
            ['name' => 'Admins', 'url' => '/a.php', 'show' => ['admin']],
            ['name' => 'Managers', 'url' => '/m.php', 'show' => ['manager']],
            ['name' => 'Both', 'url' => '/b.php', 'show' => ['admin', 'manager']],
        ]), 'theme_nit');
        $names = fn() => array_column(theme_nit_navmenu_links('gear'), 'name');

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']),
            $manager->id, \context_system::instance()->id);
        $this->setUser($manager);
        $this->assertSame(['Managers', 'Both'], $names());

        $this->setAdminUser();
        $this->assertSame(['Admins', 'Both'], $names());
    }

    public function test_bar_menu_shows_inline_links_and_supports_guests(): void {
        set_config('navmenu_bar', json_encode([
            ['name' => 'All', 'url' => '/a.php', 'show' => 'all'],
            ['name' => 'Visitors', 'url' => '/v.php', 'show' => 'guest'],
            ['name' => 'Users', 'url' => '/u.php', 'show' => 'user'],
        ]), 'theme_nit');
        $names = fn() => array_column(theme_nit_navmenu_links('bar'), 'name');

        // Signed-in user:
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(['All', 'Users'], $names());

        // Guest/visitor:
        $this->setUser(null);
        $this->assertSame(['All', 'Visitors'], $names());
    }
}
