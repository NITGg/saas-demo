<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\knowledge;

use local_nit_assistant\tools\sales\helper;

/**
 * The academy's help content as plain text — CMS pages and articles
 * (local_nit_pages) and the notes the academy writes for the assistant in its
 * settings — with a small word-match search that understands common Arabic
 * spelling variants. Page texts are cached for a few minutes per language.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class help_index {
    /** Articles read per search (newest first). */
    private const ARTICLES = 50;

    /** Characters of each result returned. */
    public const EXCERPT = 700;

    /**
     * The best matches for a question.
     *
     * @param string $query
     * @param string $lang ar | en
     * @param bool $visitor only public pages
     * @param int $limit
     * @return array list of ['title', 'source', 'url'|null, 'text']
     */
    public static function search(string $query, string $lang, bool $visitor, int $limit = 3): array {
        $words = self::words($query);
        if (!$words) {
            return [];
        }
        $docs = self::documents($lang, $visitor);
        $norm = array_map(static fn($d): array => [self::normalise($d['title']), self::normalise($d['text'])], $docs);
        // Rare words decide (a word found in every page, like "policy", says little).
        $weight = [];
        foreach ($words as $w) {
            $df = count(array_filter($norm, static fn($n) => str_contains($n[0], $w) || str_contains($n[1], $w)));
            $weight[$w] = $df ? log(1 + count($docs) / $df) : 0.0;
        }
        $scored = [];
        foreach ($docs as $i => $doc) {
            [$title, $text] = $norm[$i];
            $score = 0.0;
            foreach ($words as $w) {
                $score += $weight[$w] * ((str_contains($title, $w) ? 2 : 0) + min(3, substr_count($text, $w)));
            }
            if ($doc['source'] === 'notes') {
                $score *= 1.5; // Written by the academy for the assistant.
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'doc' => $doc, 'at' => self::first_match($text, $words)];
            }
        }
        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_map(static fn($s): array => [
            'title' => $s['doc']['title'],
            'source' => $s['doc']['source'],
            'url' => $s['doc']['url'],
            'text' => self::excerpt($s['doc']['text'], $s['at']),
        ], array_slice($scored, 0, $limit));
    }

    /**
     * Every searchable document.
     *
     * @param string $lang
     * @param bool $visitor
     * @return array list of ['title', 'source', 'url', 'text']
     */
    public static function documents(string $lang, bool $visitor): array {
        $docs = [];
        $notes = trim((string) get_config('local_nit_assistant', 'notes'));
        if ($notes !== '') {
            foreach (preg_split('/\n\s*\n/u', helper::ml($notes, $lang)) as $para) {
                if (trim($para) !== '') {
                    $docs[] = ['title' => 'Academy notes', 'source' => 'notes', 'url' => null, 'text' => trim($para)];
                }
            }
        }
        $cache = \cache::make('local_nit_assistant', 'help');
        $key = "pages_{$lang}_" . ($visitor ? 'public' : 'all');
        $pages = $cache->get($key);
        if ($pages === false) {
            $pages = self::pages($lang, $visitor);
            $cache->set($key, $pages);
        }
        return array_merge($docs, $pages, self::articles($lang));
    }

    /**
     * Published CMS pages as text.
     *
     * @param string $lang
     * @param bool $visitor
     * @return array
     */
    private static function pages(string $lang, bool $visitor): array {
        if (!class_exists('\local_nit_pages\page_manager')) {
            return [];
        }
        $out = [];
        foreach (\local_nit_pages\page_manager::get_all_pages(true, $visitor) as $page) {
            $title = trim((string) ($page->{"title_{$lang}"} ?? '')) ?: trim((string) ($page->title_en ?? $page->title_ar ?? ''));
            $slug = trim((string) ($page->{"slug_{$lang}"} ?? '')) ?: (string) ($page->default_key ?? $page->slug_en ?? '');
            try {
                $text = helper::plain(\local_nit_pages\page_manager::get_page_html($page, $lang), 20000);
            } catch (\Throwable $e) {
                continue;
            }
            if ($text === '' && $title === '') {
                continue;
            }
            $out[] = [
                'title' => $title,
                'source' => 'page',
                'url' => $slug !== '' ? helper::url('/local/nit_pages/page.php', ['p' => $slug]) : null,
                'text' => $text,
            ];
        }
        return $out;
    }

    /**
     * Published articles as text.
     *
     * @param string $lang
     * @return array
     */
    private static function articles(string $lang): array {
        if (!class_exists('\local_nit_pages\article_manager')) {
            return [];
        }
        $other = $lang === 'ar' ? 'en' : 'ar';
        $out = [];
        foreach (\local_nit_pages\article_manager::get_articles(0, self::ARTICLES, '', true)['articles'] as $a) {
            $pick = static fn(string $field): string => trim((string) ($a->{"{$field}_{$lang}"} ?? ''))
                ?: trim((string) ($a->{"{$field}_{$other}"} ?? ''));
            $out[] = [
                'title' => $pick('title'),
                'source' => 'article',
                'url' => helper::url('/local/nit_pages/article.php', ['a' => $a->slug]),
                'text' => helper::plain($pick('summary') . "\n" . $pick('body'), 20000),
            ];
        }
        return $out;
    }

    /**
     * Search words: lower case, spelling-normalised, without short and common words.
     *
     * @param string $query
     * @return string[]
     */
    public static function words(string $query): array {
        static $stop = ['the', 'and', 'for', 'what', 'how', 'can', 'you', 'your', 'is', 'are', 'do', 'does', 'من', 'في',
            'على', 'عن', 'ما', 'هل', 'كيف', 'هو', 'هي', 'انا', 'ان', 'او', 'مع', 'الى', 'ده', 'دي', 'ايه', 'عايز'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::normalise($query), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, static fn($w) => \core_text::strlen($w) >= 2 && !in_array($w, $stop, true));
        // Arabic: also match without the article "ال".
        $words = array_map(static fn($w) => preg_replace('/^ال(?=\p{L}{3,})/u', '', $w), $words);
        return array_values(array_unique($words));
    }

    /**
     * Lower case; Arabic letter variants and diacritics unified.
     *
     * @param string $text
     * @return string
     */
    public static function normalise(string $text): string {
        $text = \core_text::strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text);
        return strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    }

    /**
     * Character position (in the normalised text) of the first word found.
     *
     * @param string $text normalised
     * @param string[] $words
     * @return int
     */
    private static function first_match(string $text, array $words): int {
        $best = null;
        foreach ($words as $w) {
            $p = \core_text::strpos($text, $w);
            if ($p !== false && ($best === null || $p < $best)) {
                $best = $p;
            }
        }
        return (int) $best;
    }

    /**
     * About EXCERPT characters around a position.
     *
     * @param string $text
     * @param int $at
     * @return string
     */
    private static function excerpt(string $text, int $at): string {
        $len = \core_text::strlen($text);
        if ($len <= self::EXCERPT) {
            return $text;
        }
        $start = max(0, min($at - 150, $len - self::EXCERPT));
        $part = \core_text::substr($text, $start, self::EXCERPT);
        return ($start > 0 ? '…' : '') . trim($part) . ($start + self::EXCERPT < $len ? '…' : '');
    }
}
