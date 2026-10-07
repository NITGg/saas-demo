<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_pages;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for static pages management in local_nit_pages.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_pages\page_manager
 */
final class pages_test extends \advanced_testcase {

    public function test_clean_slug(): void {
        $this->assertSame('about-us', page_manager::clean_slug('About Us!'));
        $this->assertSame('عن-المنصة', page_manager::clean_slug('عن المنصة...'));
        $this->assertSame('terms-and-conditions', page_manager::clean_slug('terms---and---conditions'));
    }

    public function test_default_pages_cannot_be_deleted(): void {
        $this->resetAfterTest();

        page_manager::ensure_default_pages();

        $about = page_manager::get_page_by_default_key('about');
        $this->assertNotNull($about);
        $this->assertEquals(1, $about->is_default);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_cannot_delete_default', 'local_nit_pages'));
        page_manager::delete_page($about->id);
    }

    public function test_custom_page_crud(): void {
        $this->resetAfterTest();

        $page = page_manager::save_page([
            'title_ar' => 'صفحة تجريبية',
            'title_en' => 'Test Page',
            'slug_ar'  => 'تجربة',
            'slug_en'  => 'test-slug',
            'status'   => page_manager::STATUS_PUBLISHED,
        ]);

        $this->assertNotEmpty($page->id);
        $this->assertSame('تجربة', $page->slug_ar);
        $this->assertSame('test-slug', $page->slug_en);

        $lookup = page_manager::get_page_by_slug('test-slug');
        $this->assertNotNull($lookup);
        $this->assertEquals($page->id, $lookup->id);

        $lookupar = page_manager::get_page_by_slug('تجربة');
        $this->assertNotNull($lookupar);
        $this->assertEquals($page->id, $lookupar->id);

        $deleted = page_manager::delete_page($page->id);
        $this->assertTrue($deleted);
        $this->assertNull(page_manager::get_page_by_id($page->id));
    }

    public function test_visibility_rules(): void {
        $this->resetAfterTest();

        $admin = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $adminrole = $this->getDataGenerator()->create_role();
        assign_capability('local/nit_pages:manage', CAP_ALLOW, $adminrole, $sysctx);
        role_assign($adminrole, $admin->id, $sysctx->id);

        $pubpage = (object) [
            'status' => page_manager::STATUS_PUBLISHED,
            'loggedin_only' => 0,
        ];
        $this->assertTrue(page_manager::can_view($pubpage, 0));
        $this->assertTrue(page_manager::can_view($pubpage, $user->id));

        $draftpage = (object) [
            'status' => page_manager::STATUS_DRAFT,
            'loggedin_only' => 0,
        ];
        $this->assertFalse(page_manager::can_view($draftpage, 0));
        $this->assertFalse(page_manager::can_view($draftpage, $user->id));
        $this->assertTrue(page_manager::can_view($draftpage, $admin->id));

        $memberpage = (object) [
            'status' => page_manager::STATUS_PUBLISHED,
            'loggedin_only' => 1,
        ];
        $this->assertFalse(page_manager::can_view($memberpage, 0));
        $this->assertTrue(page_manager::can_view($memberpage, $user->id));
    }

    public function test_legal_fallback_to_pages(): void {
        $this->resetAfterTest();

        page_manager::ensure_default_pages();

        $terms = page_manager::get_page_by_default_key('terms');
        $this->assertNotNull($terms);

        $htmlar = page_manager::get_page_html($terms, 'ar');
        $this->assertNotEmpty($htmlar);
    }
}
