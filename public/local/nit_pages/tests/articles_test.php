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
 * Unit tests for articles management in local_nit_pages.
 *
 * @package    local_nit_pages
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_pages\article_manager
 */
final class articles_test extends \advanced_testcase {

    public function test_article_crud_and_unique_slug(): void {
        $this->resetAfterTest();
        global $USER;

        $art1 = article_manager::save_article([
            'title_ar' => 'مقال أول',
            'title_en' => 'First Article',
            'slug'     => 'first-article',
            'status'   => article_manager::STATUS_PUBLISHED,
        ], (int)$USER->id);

        $this->assertSame('first-article', $art1->slug);

        // Save second article with duplicate slug to verify collision handling.
        $art2 = article_manager::save_article([
            'title_ar' => 'مقال مكرر',
            'title_en' => 'Duplicate Article',
            'slug'     => 'first-article',
            'status'   => article_manager::STATUS_PUBLISHED,
        ], (int)$USER->id);

        $this->assertSame('first-article-2', $art2->slug);

        $fetched = article_manager::get_article_by_slug('first-article');
        $this->assertNotNull($fetched);
        $this->assertEquals($art1->id, $fetched->id);

        $deleted = article_manager::delete_article($art1->id);
        $this->assertTrue($deleted);
        $this->assertNull(article_manager::get_article_by_id($art1->id));
    }

    public function test_articles_search_and_pagination(): void {
        $this->resetAfterTest();
        global $USER;

        for ($i = 1; $i <= 12; $i++) {
            article_manager::save_article([
                'title_ar'    => "مقال تجريبي رقم $i",
                'title_en'    => "Test Article number $i",
                'summary_ar'  => ($i === 5) ? 'كلمة مفتاحية نادرة' : 'ملخص عام',
                'summary_en'  => ($i === 5) ? 'Rare keyword in summary' : 'General summary',
                'status'      => article_manager::STATUS_PUBLISHED,
                'published_at'=> time() - (12 - $i) * 60,
            ], (int)$USER->id);
        }

        // Test pagination 9 per page.
        $page0 = article_manager::get_articles(0, 9);
        $this->assertCount(9, $page0['articles']);
        $this->assertEquals(12, $page0['total']);
        $this->assertEquals(2, $page0['pages']);

        $page1 = article_manager::get_articles(1, 9);
        $this->assertCount(3, $page1['articles']);

        // Test search by keyword.
        $searchres = article_manager::get_articles(0, 9, 'نادرة');
        $this->assertCount(1, $searchres['articles']);
        $this->assertSame('مقال تجريبي رقم 5', $searchres['articles'][0]->title_ar);
    }

    public function test_article_visibility(): void {
        $this->resetAfterTest();
        global $USER;

        $admin = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $sysctx = \context_system::instance();
        $adminrole = $this->getDataGenerator()->create_role();
        assign_capability('local/nit_pages:manage', CAP_ALLOW, $adminrole, $sysctx);
        role_assign($adminrole, $admin->id, $sysctx->id);

        $draft = (object) [
            'status' => article_manager::STATUS_DRAFT,
            'published_at' => time() - 100,
        ];
        $this->assertFalse(article_manager::can_view($draft, 0));
        $this->assertFalse(article_manager::can_view($draft, $user->id));
        $this->assertTrue(article_manager::can_view($draft, $admin->id));

        $future = (object) [
            'status' => article_manager::STATUS_PUBLISHED,
            'published_at' => time() + 3600,
        ];
        $this->assertFalse(article_manager::can_view($future, 0));
        $this->assertFalse(article_manager::can_view($future, $user->id));
        $this->assertTrue(article_manager::can_view($future, $admin->id));

        $published = (object) [
            'status' => article_manager::STATUS_PUBLISHED,
            'published_at' => time() - 3600,
        ];
        $this->assertTrue(article_manager::can_view($published, 0));
        $this->assertTrue(article_manager::can_view($published, $user->id));
    }

    public function test_status_toggle(): void {
        $this->resetAfterTest();
        global $USER;

        $art = article_manager::save_article([
            'title_ar' => 'مقال للتبديل',
            'title_en' => 'Toggle Article',
            'status'   => article_manager::STATUS_DRAFT,
        ], (int)$USER->id);

        $newstatus = article_manager::toggle_status($art->id);
        $this->assertSame(article_manager::STATUS_PUBLISHED, $newstatus);

        $newstatus2 = article_manager::toggle_status($art->id);
        $this->assertSame(article_manager::STATUS_DRAFT, $newstatus2);
    }
}
