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

/**
 * XML sitemap of public NIT content, for search engines.
 *
 * Lists the front page plus every visible course and visible category, so a
 * crawler can discover the whole catalogue from one URL — regardless of the
 * client-side rendering on the Site home. Public, session-less, and cached for
 * an hour. Submit this URL to Google Search Console, and/or reference it from a
 * robots.txt "Sitemap:" line:
 *
 *   https://<yoursite>/theme/nit/sitemap.php
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public, cacheable, no session needed.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

$cache = \cache::make('theme_nit', 'frontpage');
$entry = $cache->get('sitemap');

if (is_array($entry) && ($entry['expires'] ?? 0) > time() && isset($entry['xml'])) {
    $xml = $entry['xml'];
} else {
    // The front page.
    $urls = [[
        'loc'        => $CFG->wwwroot . '/',
        'changefreq' => 'daily',
        'priority'   => '1.0',
    ]];

    // Every visible course (excluding the site "course").
    $courses = $DB->get_records_select(
        'course',
        'id <> :site AND visible = 1',
        ['site' => SITEID],
        'sortorder ASC',
        'id, timemodified'
    );
    foreach ($courses as $c) {
        $urls[] = [
            'loc'        => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            'lastmod'    => date('Y-m-d', (int) $c->timemodified),
            'changefreq' => 'weekly',
            'priority'   => '0.8',
        ];
    }

    // Every visible category.
    $categories = $DB->get_records('course_categories', ['visible' => 1], 'id ASC', 'id, timemodified');
    foreach ($categories as $cat) {
        $urls[] = [
            'loc'        => (new moodle_url('/course/index.php', ['categoryid' => $cat->id]))->out(false),
            'lastmod'    => date('Y-m-d', (int) $cat->timemodified),
            'changefreq' => 'weekly',
            'priority'   => '0.6',
        ];
    }

    // Build the <urlset>. loc is XML-escaped (moodle_url::out(false) leaves raw
    // "&", which is invalid in XML).
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
    $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) {
        $line = '  <url><loc>' . htmlspecialchars($u['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>';
        if (!empty($u['lastmod'])) {
            $line .= '<lastmod>' . $u['lastmod'] . '</lastmod>';
        }
        if (!empty($u['changefreq'])) {
            $line .= '<changefreq>' . $u['changefreq'] . '</changefreq>';
        }
        if (!empty($u['priority'])) {
            $line .= '<priority>' . $u['priority'] . '</priority>';
        }
        $line .= '</url>';
        $lines[] = $line;
    }
    $lines[] = '</urlset>';
    $xml = implode("\n", $lines) . "\n";

    // Cache for an hour so crawler hits don't re-query every time.
    $cache->set('sitemap', ['expires' => time() + 3600, 'xml' => $xml]);
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $xml;
