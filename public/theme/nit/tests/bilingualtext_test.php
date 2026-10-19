<?php
namespace theme_nit;

/**
 * The English / Arabic text settings (footer description and copyright line).
 *
 * @package    theme_nit
 * @covers     \theme_nit\admin_setting_bilingualtext
 * @covers     \theme_nit\admin_setting_footerpages
 */
final class bilingualtext_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/theme/nit/lib.php');
    }

    public function test_two_sides_are_stored_as_one_mlang_text(): void {
        $setting = new admin_setting_bilingualtext('theme_nit/footer_copyright', 'Copyright', '', '');
        $this->assertSame('', $setting->write_setting(['en' => ' All rights © {year} ', 'ar' => 'كل الحقوق <b>محفوظة</b>']));
        $this->assertSame('{mlang en}All rights © {year}{mlang}{mlang ar}كل الحقوق محفوظة{mlang}',
            get_config('theme_nit', 'footer_copyright'));

        // One side only → plain text (shown in both languages); none → empty (hidden).
        $setting->write_setting(['en' => '', 'ar' => 'عربي']);
        $this->assertSame('عربي', get_config('theme_nit', 'footer_copyright'));
        $setting->write_setting(['en' => '', 'ar' => '']);
        $this->assertSame('', get_config('theme_nit', 'footer_copyright'));
    }

    public function test_editor_shows_each_language_in_its_own_field(): void {
        $setting = new admin_setting_bilingualtext('theme_nit/footer_description', 'Description', '', '', 3);
        $html = $setting->output_html('{mlang en}Hello{mlang}{mlang ar}مرحبا{mlang}');
        $this->assertMatchesRegularExpression('~<textarea [^>]*name="[^"]*\[en\]"[^>]*>Hello</textarea>~', $html);
        $this->assertMatchesRegularExpression('~<textarea [^>]*name="[^"]*\[ar\]"[^>]*>مرحبا</textarea>~', $html);

        // A one-line setting is an input; an old plain text opens in both fields.
        $html = (new admin_setting_bilingualtext('theme_nit/footer_copyright', 'C', '', ''))->output_html('Plain');
        $this->assertSame(2, substr_count($html, 'value="Plain"'));
    }

    public function test_footer_pages_name_is_edited_in_english_and_arabic(): void {
        $setting = new admin_setting_footerpages('theme_nit/footer_pages', 'Pages', '', '');
        $this->assertSame('', $setting->write_setting([
            'name_en' => ['_' => '', 'r0' => 'Home'], 'name_ar' => ['_' => '', 'r0' => 'الرئيسية'],
            'url' => ['_' => '', 'r0' => '/'], 'show' => ['r0' => 'all'],
        ]));
        $rows = json_decode(get_config('theme_nit', 'footer_pages'), true);
        $this->assertSame('{mlang en}Home{mlang}{mlang ar}الرئيسية{mlang}', $rows[0]['name']);
    }
}
