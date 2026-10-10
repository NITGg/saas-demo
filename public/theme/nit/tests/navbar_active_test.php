<?php
namespace theme_nit;

use theme_nit\output\core_renderer;

/**
 * The navbar inline links: which one is lit as "the page you are on".
 *
 * @package    theme_nit
 * @covers     \theme_nit\output\core_renderer::navbar_active_flags
 */
final class navbar_active_test extends \basic_testcase {

    /** Lit flags of $links on $here. */
    private function lit(string $here, array $links): array {
        $urls = array_map(static fn(string $u) => new \moodle_url($u), $links);
        return array_keys(array_filter(core_renderer::navbar_active_flags(new \moodle_url($here), $urls)));
    }

    public function test_link_stays_lit_when_the_page_adds_parameters(): void {
        // The catalogue sets its url as ?id=0&sub=0; the navbar link has none.
        $links = ['/local/nit_category/index.php', '/local/nit_pages/page.php?slug=about'];
        $this->assertSame([0], $this->lit('/local/nit_category/index.php?id=0&sub=0', $links));
        $this->assertSame([0], $this->lit('/local/nit_category/index.php', $links));
    }

    public function test_most_specific_link_wins(): void {
        $links = ['/local/nit_pages/page.php', '/local/nit_pages/page.php?slug=about',
            '/local/nit_pages/page.php?slug=contact'];
        $this->assertSame([1], $this->lit('/local/nit_pages/page.php?slug=about', $links));
        $this->assertSame([0], $this->lit('/local/nit_pages/page.php?slug=faq', $links));
    }

    public function test_index_php_and_folder_are_the_same_page(): void {
        $this->assertSame([0], $this->lit('/local/nit_category/index.php', ['/local/nit_category/']));
        $this->assertSame([0], $this->lit('/index.php', ['/']));
    }

    public function test_other_pages_light_nothing(): void {
        $links = ['/', '/local/nit_category/index.php', '/local/nit_pages/page.php?slug=about'];
        $this->assertSame([], $this->lit('/course/view.php?id=4', $links));
        $this->assertSame([], $this->lit('/local/nit_pages/page.php?slug=contact', $links));
        $this->assertSame([false], core_renderer::navbar_active_flags(null, [new \moodle_url('/')]));
        $this->assertSame([false], core_renderer::navbar_active_flags(new \moodle_url('/'), [null]));
    }
}
