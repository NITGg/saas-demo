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

namespace local_academy\local;

/**
 * Cleans a Database activity template (mod_data) without breaking it.
 *
 * Moodle prints these templates raw, so a teacher's <script> runs for every student.
 * HTMLPurifier cannot clean them: a template is a fragment (the list header opens a
 * <table> that the footer closes) full of [[field]] / ##tag## placeholders that the
 * purifier mangles in links and drops with id attributes.
 *
 * So the template is read as a stream of tags and text and written back out:
 * known-safe elements and attributes only, every attribute re-quoted, every other
 * "<" escaped. Nothing is restructured, so fragments and placeholders stay as they are.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_sanitizer {

    /** Elements kept (with their allowed attributes). */
    const ELEMENTS = ['a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'blockquote', 'br', 'button',
        'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt', 'em',
        'figcaption', 'figure', 'font', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img', 'input',
        'ins', 'kbd', 'label', 'li', 'main', 'mark', 'nav', 'ol', 'optgroup', 'option', 'p', 'pre', 'q', 's', 'samp',
        'section', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td',
        'textarea', 'tfoot', 'th', 'thead', 'time', 'tr', 'tt', 'u', 'ul', 'var', 'wbr'];

    /** Elements dropped together with everything inside them. */
    const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg',
        'math', 'noscript', 'noembed', 'noframes', 'xmp', 'template', 'title', 'plaintext'];

    /** Attributes kept on any allowed element (plus aria-*). */
    const ATTRIBUTES = ['abbr', 'align', 'alt', 'border', 'cellpadding', 'cellspacing', 'checked', 'class', 'color',
        'cols', 'colspan', 'datetime', 'dir', 'disabled', 'face', 'for', 'headers', 'height', 'href', 'id', 'lang',
        'maxlength', 'multiple', 'name', 'open', 'placeholder', 'readonly', 'rel', 'reversed', 'role', 'rows', 'rowspan',
        'scope', 'selected', 'size', 'span', 'src', 'start', 'style', 'tabindex', 'target', 'title', 'type', 'valign',
        'value', 'width', 'data-bs-toggle', 'data-bs-target', 'data-bs-parent'];

    /** Placeholders allowed inside attribute values (all others may carry quotes or markup). */
    const ATTRIBUTE_PLACEHOLDERS = '~^(##(id|moreurl|approvalstatusclass)##|\[\[[^\[\]]+#id\]\])$~i';

    /**
     * The template with only safe markup left.
     *
     * @param string $html
     * @return string
     */
    public static function clean(string $html): string {
        $tokens = preg_split('~(<!--.*?(?:-->|$)|<[a-zA-Z/!?](?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~s', $html, -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $out = '';
        $skip = null; // Inside an element dropped with its content: [name, depth].
        foreach ($tokens as $token) {
            if ($token[0] !== '<' || !preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)(.*?)(/?)>$~s', $token, $m)) {
                if ($skip === null && strpos($token, '<!--') !== 0) {
                    // Text; anything that could still open a tag is escaped.
                    $out .= preg_replace('~<(?=[a-zA-Z/!?])~', '&lt;', $token);
                }
                continue;
            }
            [, $closing, $name, $attributes, $selfclosing] = $m;
            $name = strtolower($name);
            if ($skip !== null) {
                if ($name === $skip[0]) {
                    $skip[1] += $closing ? -1 : 1;
                    if ($skip[1] === 0) {
                        $skip = null;
                    }
                }
                continue;
            }
            if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                if (!$closing && !$selfclosing) {
                    $skip = [$name, 1];
                }
                continue;
            }
            if (!in_array($name, self::ELEMENTS, true)) {
                continue;
            }
            $out .= $closing ? "</{$name}>" : '<' . $name . self::attributes($attributes) . ($selfclosing ? '/' : '') . '>';
        }
        return $out;
    }

    /**
     * The allowed attributes, re-quoted.
     *
     * @param string $source the text after the tag name
     * @return string
     */
    private static function attributes(string $source): string {
        preg_match_all('~([^\s"\'>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~', $source, $matches, PREG_SET_ORDER);
        $out = '';
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (!in_array($name, self::ATTRIBUTES, true) && !preg_match('~^aria-[a-z]+$~', $name)) {
                continue;
            }
            if (!isset($match[2])) {
                $out .= ' ' . $name; // No value (checked, disabled …).
                continue;
            }
            $value = html_entity_decode($match[2] !== '' ? $match[2] : ($match[3] ?? '') . ($match[4] ?? ''),
                ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = self::attribute_value($name, $value);
            if ($value === null) {
                continue;
            }
            $out .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return $out;
    }

    /**
     * A safe attribute value, or null to drop the attribute.
     *
     * @param string $name
     * @param string $value decoded
     * @return string|null
     */
    private static function attribute_value(string $name, string $value): ?string {
        // Placeholders are filled in after this, with text that may hold quotes or markup.
        $value = preg_replace_callback('~##[a-z]+##|\[\[[^\[\]]*\]\]~i',
            fn(array $p): string => preg_match(self::ATTRIBUTE_PLACEHOLDERS, $p[0]) ? $p[0] : '', $value);
        $squashed = strtolower(preg_replace('~[\x00-\x20\x7f]+~', '', $value));
        if ($name === 'href' || $name === 'src') {
            if (preg_match('~^([a-z][a-z0-9+.\-]*):~', $squashed, $scheme)
                    && !in_array($scheme[1], ['http', 'https', 'mailto', 'tel'], true)) {
                return null;
            }
        }
        if ($name === 'style' && preg_match('~expression|javascript|vbscript|url\(|@import|behavio|binding|\\\\~', $squashed)) {
            return null;
        }
        if ($name === 'data-bs-toggle' && !in_array($squashed, ['collapse', 'tab', 'pill', 'dropdown'], true)) {
            return null;
        }
        return $value;
    }
}
