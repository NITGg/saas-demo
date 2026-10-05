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

namespace local_nit_mlang;

/**
 * Custom user profile fields whose *value* is a display string in two languages.
 *
 * The rest of this plugin answers "which form fields hold translatable content"
 * from a registry of field names, because those fields are the same on every
 * site. Custom profile fields are not: which ones exist, and which of them hold
 * prose rather than a phone number or a national ID, is data an administrator
 * typed into Site administration -> Users -> User profile fields.
 *
 * So the choice is made per *category*, which is the grouping the admin already
 * maintains and the one the profile form already draws as a heading. Tick
 * "Instructor Fields" and every text field and text area in it is edited in one
 * input per language and stored as `{mlang}` markup; "Additional details" — the
 * passport number, the national ID — stays a single box, because a passport
 * number does not have an Arabic spelling.
 *
 * Only `text` and `textarea` are eligible. A menu, a checkbox, a date or an
 * uploaded file has no free text to translate: a menu's *options* are translated
 * on the field definition itself, not per user.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profilefields {

    /** @var string Config setting holding the chosen category ids, comma separated. */
    const SETTING = 'profilecategories';

    /** @var string Config setting: whether text areas in those categories count too. */
    const SETTING_TEXTAREAS = 'profiletextareas';

    /** @var string The prefix core gives every custom profile field on a form. */
    const PREFIX = 'profile_field_';

    /**
     * Profile field datatypes that can hold translatable free text.
     *
     * `text` is the "Text input" field type and is always in scope. `textarea` is
     * a rich text editor, which gets a language tab strip rather than stacked
     * boxes - a visibly different control - so it is opt-in per site through
     * SETTING_TEXTAREAS rather than arriving with the category.
     *
     * @var string[]
     */
    const DATATYPES = ['text', 'textarea'];

    /**
     * Pages that carry a profile form, for somebody without the edit capability.
     *
     * Filling in your own bilingual profile field is not authoring site content,
     * so `local/nit_mlang:edit` does not gate it (see output_callbacks). That
     * exemption is deliberately narrow: it applies on the screens where a person
     * edits a profile and nowhere else, so an ordinary student never carries the
     * enhancer around the rest of the site.
     *
     * Glob patterns, matched against `$PAGE->pagetype`.
     *
     * @var string[]
     */
    const PAGETYPES = [
        'user-edit',
        'user-editadvanced',
        'admin-user',
        'local-profilefields-*',
    ];

    /**
     * Every profile field category on the site, for the settings menu.
     *
     * @return array category id => name as the profile page prints it
     */
    public static function categories(): array {
        global $DB;

        try {
            $records = $DB->get_records('user_info_category', null, 'sortorder ASC', 'id, name');

            $categories = [];
            foreach ($records as $record) {
                $categories[(int) $record->id] = format_string($record->name);
            }
            return $categories;
        } catch (\Throwable $e) {
            // Table or filters not ready yet (initial install): an empty menu is
            // the honest answer, and the settings page still renders.
            return [];
        }
    }

    /**
     * The category ids an administrator has ticked.
     *
     * @return int[]
     */
    public static function selected(): array {
        $raw = (string) get_config('local_nit_mlang', self::SETTING);
        if ($raw === '') {
            return [];
        }
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return array_values(array_unique($ids));
    }

    /**
     * The form input names to enhance, split by the widget core draws for them.
     *
     * A `text` field is an `<input type="text">` named `profile_field_x`; a
     * `textarea` field is an editor whose form element is also named
     * `profile_field_x` (its textarea submits as `profile_field_x[text]`, which
     * the JS strips before matching). Both names are therefore the same shape —
     * only the widget differs, and the two lists keep them apart.
     *
     * @return array{text: string[], editor: string[]}
     */
    public static function inputs(): array {
        global $DB;

        $empty = ['text' => [], 'editor' => []];

        $categoryids = self::selected();
        if (!$categoryids) {
            return $empty;
        }

        // "Text input" is always in scope; a text area is a different control and
        // is only in scope when the site has asked for it.
        $datatypes = ['text'];
        if (get_config('local_nit_mlang', self::SETTING_TEXTAREAS)) {
            $datatypes[] = 'textarea';
        }

        try {
            [$catsql, $catparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
            [$typesql, $typeparams] = $DB->get_in_or_equal($datatypes, SQL_PARAMS_NAMED, 'type');
            $records = $DB->get_records_select(
                'user_info_field',
                "categoryid $catsql AND datatype $typesql",
                array_merge($catparams, $typeparams),
                'sortorder ASC',
                'id, shortname, datatype'
            );
        } catch (\Throwable $e) {
            return $empty;
        }

        $inputs = $empty;
        foreach ($records as $record) {
            $key = $record->datatype === 'textarea' ? 'editor' : 'text';
            $inputs[$key][] = self::PREFIX . $record->shortname;
        }
        return $inputs;
    }

    /**
     * Is this page one where a profile is edited?
     *
     * @param string $pagetype the current `$PAGE->pagetype`
     * @return bool
     */
    public static function is_profile_page(string $pagetype): bool {
        foreach (self::PAGETYPES as $glob) {
            $pattern = '/^' . str_replace('\*', '.*', preg_quote($glob, '/')) . '$/';
            if (preg_match($pattern, $pagetype)) {
                return true;
            }
        }
        return false;
    }
}
