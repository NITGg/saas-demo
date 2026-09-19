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

namespace theme_nit\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Registry of the ten selectable academy-homepage templates (T1..T10).
 *
 * A "template" is a set of section seed-HTML files under
 * theme/nit/blocks/templates/<id>/ (hero, categories, courses, about,
 * subscriptions, coupons, gallery, contact, footer). {@see template_applier}
 * pastes them into the Site-home nit_section blocks. The front-page engine and
 * data helpers are template-agnostic — nothing here forks them.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class homepage_templates {

    /** Config key (theme_nit) storing the applied template id. */
    const CONFIG_KEY = 'homepage_template';

    /** Default template when none has been chosen. */
    const DEFAULT_ID = 't1';

    /**
     * The ordered sections that make up a homepage, with where each block lives
     * and how to recognise the block that already renders it.
     *
     * region/weight are used only when CREATING a missing block; an existing
     * block keeps its own region/weight (except the footer, which is always
     * forced to the bottom). `signatures` are substrings matched against a
     * block's HTML to find the block that currently plays this role — the first
     * one that hits wins, so legacy "my courses" blocks are reused for the
     * catalog "courses" slot.
     *
     * @return array<int, array{key:string,file:string,region:string,weight:int,signatures:string[]}>
     */
    public static function sections(): array {
        return [
            ['key' => 'hero',          'file' => 'hero.html',          'region' => 'fullwidth-top',    'weight' => 0, 'signatures' => ['data-nit-section="hero"']],
            ['key' => 'categories',    'file' => 'categories.html',    'region' => 'fullwidth-top',    'weight' => 1, 'signatures' => ['data-nit-categories']],
            ['key' => 'courses',       'file' => 'courses.html',       'region' => 'fullwidth-top',    'weight' => 2, 'signatures' => ['data-nit-courses', 'data-nit-my-courses']],
            ['key' => 'about',         'file' => 'about.html',         'region' => 'fullwidth-top',    'weight' => 3, 'signatures' => ['data-nit-section="about"']],
            ['key' => 'subscriptions', 'file' => 'subscriptions.html', 'region' => 'fullwidth-top',    'weight' => 4, 'signatures' => ['data-nit-subs']],
            ['key' => 'coupons',       'file' => 'coupons.html',       'region' => 'fullwidth-top',    'weight' => 5, 'signatures' => ['data-nit-coupons']],
            ['key' => 'gallery',       'file' => 'gallery.html',       'region' => 'fullwidth-top',    'weight' => 6, 'signatures' => ['data-nit-section="gallery"']],
            ['key' => 'contact',       'file' => 'contact.html',       'region' => 'fullwidth-top',    'weight' => 7, 'signatures' => ['data-nit-section="contact"']],
            ['key' => 'footer',        'file' => 'footer.html',        'region' => 'fullwidth-bottom', 'weight' => 0, 'signatures' => ['data-nit-section="footer"']],
        ];
    }

    /**
     * All ten templates, in display order. `accent` is an illustrative swatch of
     * the template's signature look for the picker — the real accent is always
     * the academy's brand colour (the templates wire accent to --nit-brand-*).
     *
     * @return array<string, array{name:array{en:string,ar:string},font:string,accent:string,dark:bool,blurb:array{en:string,ar:string}}>
     */
    public static function all(): array {
        return [
            't1'  => ['name' => ['en' => 'Modern Minimal',  'ar' => 'بسيط عصري'],     'font' => 'Manrope',            'accent' => '#0E7C66', 'dark' => false, 'blurb' => ['en' => 'Whitespace, thin type, one clean accent.',        'ar' => 'مساحات بيضاء وخطوط رفيعة ولون مميّز واحد.']],
            't2'  => ['name' => ['en' => 'Bold Gradient',   'ar' => 'تدرّج جريء'],     'font' => 'Plus Jakarta Sans', 'accent' => '#6D28D9', 'dark' => false, 'blurb' => ['en' => 'Big gradient bands and confident type.',           'ar' => 'تدرّجات لونية كبيرة وخطوط واثقة.']],
            't3'  => ['name' => ['en' => 'Academic Classic', 'ar' => 'أكاديمي كلاسيكي'], 'font' => 'Almarai',          'accent' => '#C9A227', 'dark' => false, 'blurb' => ['en' => 'Navy and cream with serif headings.',              'ar' => 'كحلي وكريمي مع عناوين رسمية.']],
            't4'  => ['name' => ['en' => 'Dark Premium',    'ar' => 'داكن فاخر'],      'font' => 'Space Grotesk',     'accent' => '#22D3EE', 'dark' => true,  'blurb' => ['en' => 'Near-black, glass surfaces, glowing accent.',      'ar' => 'أسود تقريبًا بأسطح زجاجية ولمسة متوهّجة.']],
            't5'  => ['name' => ['en' => 'Warm Editorial',  'ar' => 'تحريري دافئ'],    'font' => 'Alexandria',        'accent' => '#B9482B', 'dark' => false, 'blurb' => ['en' => 'Warm cream, rust accent, editorial serif.',       'ar' => 'كريمي دافئ بلمسة صدئة وطابع تحريري.']],
            't6'  => ['name' => ['en' => 'Soft Glass',      'ar' => 'زجاج ناعم'],      'font' => 'Public Sans',       'accent' => '#3B82F6', 'dark' => false, 'blurb' => ['en' => 'Frosted translucent cards and soft washes.',      'ar' => 'بطاقات زجاجية شفافة وتدرّجات ناعمة.']],
            't7'  => ['name' => ['en' => 'Corporate Trust', 'ar' => 'ثقة مؤسسية'],     'font' => 'Baloo Bhaijaan 2',  'accent' => '#2563EB', 'dark' => false, 'blurb' => ['en' => 'Clean, trustworthy, corporate blue.',            'ar' => 'نظيف وموثوق بلون أزرق مؤسسي.']],
            't8'  => ['name' => ['en' => 'Playful Rounded', 'ar' => 'مرِح مستدير'],     'font' => 'Archivo',           'accent' => '#FF7A59', 'dark' => false, 'blurb' => ['en' => 'Big rounded shapes, ink borders, pastels.',       'ar' => 'أشكال مستديرة كبيرة وحدود واضحة وألوان باستيل.']],
            't9'  => ['name' => ['en' => 'Elegant Mono',    'ar' => 'أحادي أنيق'],     'font' => 'Syne',              'accent' => '#D6001C', 'dark' => false, 'blurb' => ['en' => 'Monochrome, square corners, hairline rules.',     'ar' => 'أحادي اللون بزوايا قائمة وخطوط رفيعة.']],
            't10' => ['name' => ['en' => 'Vibrant Duotone', 'ar' => 'ثنائي نابض'],     'font' => 'Syne / Changa',     'accent' => '#D6006E', 'dark' => false, 'blurb' => ['en' => 'Two brand-driven colours, bold duotone photos.', 'ar' => 'لونان من هوية الأكاديمية وصور ثنائية جريئة.']],
        ];
    }

    /** Whether an id is a known template. */
    public static function exists(string $id): bool {
        return array_key_exists($id, self::all());
    }

    /** Absolute path to a template's folder. */
    public static function dir(string $id): string {
        global $CFG;
        return $CFG->dirroot . '/theme/nit/blocks/templates/' . $id;
    }

    /** The id of the currently-applied template (falls back to the default). */
    public static function current(): string {
        $id = (string) get_config('theme_nit', self::CONFIG_KEY);
        return self::exists($id) ? $id : self::DEFAULT_ID;
    }

    /** The display name of a template in the current language (en/ar aware). */
    public static function name(string $id): string {
        $all = self::all();
        if (!isset($all[$id])) {
            return $id;
        }
        return current_language() === 'ar' ? $all[$id]['name']['ar'] : $all[$id]['name']['en'];
    }
}
