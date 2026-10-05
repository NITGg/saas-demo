<?php
namespace block_nit_section;

/**
 * Unit tests for {@see \block_nit_section::site_relative_urls()} — template HTML
 * written for a site at the domain root, served from a sub-folder.
 *
 * @package    block_nit_section
 * @covers     \block_nit_section::site_relative_urls
 */
final class site_relative_urls_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/nit_section/block_nit_section.php');
    }

    public function test_sub_folder_site_gets_its_folder_in_front_of_root_relative_urls(): void {
        global $CFG;
        $CFG->wwwroot = 'https://academy2026.nitg-eg.com/bassthalk';
        $html = '<img src="/theme/nit/pix/bassthalk_hero.svg"><a href="/local/academy/start.php">'
            . "<script>fetch('/local/academy/homedata.php?section=teachers')</script>"
            . "<div style=\"background:url('/theme/a.png')\"></div>";
        $this->assertSame('<img src="/bassthalk/theme/nit/pix/bassthalk_hero.svg"><a href="/bassthalk/local/academy/start.php">'
            . "<script>fetch('/bassthalk/local/academy/homedata.php?section=teachers')</script>"
            . "<div style=\"background:url('/bassthalk/theme/a.png')\"></div>",
            \block_nit_section::site_relative_urls($html));
    }

    public function test_external_protocol_relative_and_already_prefixed_urls_are_left_alone(): void {
        global $CFG;
        $CFG->wwwroot = 'https://academy2026.nitg-eg.com/bassthalk';
        $html = '<a href="https://x.com/a"></a><img src="//cdn.x/a.png"><img src="/bassthalk/a.png"><a href="#top"></a>';
        $this->assertSame($html, \block_nit_section::site_relative_urls($html));
        // A path that only starts with the folder name is not inside it.
        $this->assertSame('<a href="/bassthalk/bassthalkfoo">', \block_nit_section::site_relative_urls('<a href="/bassthalkfoo">'));
    }

    public function test_site_at_the_domain_root_is_unchanged(): void {
        global $CFG;
        $CFG->wwwroot = 'http://localhost:8082';
        $html = '<img src="/theme/nit/pix/bassthalk_hero.svg">';
        $this->assertSame($html, \block_nit_section::site_relative_urls($html));
    }
}
