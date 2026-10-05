<?php
namespace theme_nit;

/**
 * Unit tests for the footer texts in both languages:
 * {@see theme_nit_footer_translate_defaults()} and the footer view-model.
 *
 * @package    theme_nit
 * @covers     ::theme_nit_footer_translate_defaults
 * @covers     ::theme_nit_footer_context
 */
final class footer_translate_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/theme/nit/lib.php');
    }

    public function test_old_arabic_only_defaults_get_english_and_admin_texts_are_kept(): void {
        // What earlier versions saved: Arabic only, plus a page the admin added.
        set_config('footer_pages', json_encode([
            ['name' => 'الرئيسية', 'url' => '/', 'show' => 'all'],
            ['name' => 'صفحة من الأدمن', 'url' => '/about', 'show' => 'all'],
        ], JSON_UNESCAPED_UNICODE), 'theme_nit');
        set_config('footer_description', 'تم صنع هذه المنصة بهدف تهيئة الطالب لـ كامل جوانب الثانوية العامة و ما بعدها', 'theme_nit');
        set_config('footer_copyright', 'حقوق كتبها الأدمن', 'theme_nit');

        theme_nit_footer_translate_defaults();

        $rows = json_decode(get_config('theme_nit', 'footer_pages'), true);
        $this->assertSame('{mlang en}Home{mlang}{mlang ar}الرئيسية{mlang}', $rows[0]['name']);
        $this->assertSame('صفحة من الأدمن', $rows[1]['name']);
        $this->assertStringStartsWith('{mlang en}This platform', get_config('theme_nit', 'footer_description'));
        $this->assertSame('حقوق كتبها الأدمن', get_config('theme_nit', 'footer_copyright'));
    }

    public function test_social_labels_follow_the_page_language(): void {
        set_config('footer_facebook', 'https://facebook.com/x', 'theme_nit');
        force_current_language('en');
        $this->assertSame('Facebook', theme_nit_footer_context()['socials'][0]['label']);
        force_current_language('ar');
        $this->assertSame('فيسبوك', theme_nit_footer_context()['socials'][0]['label']);
    }
}
