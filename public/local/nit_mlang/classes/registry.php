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
 * Which form fields are translatable, site-wide.
 *
 * A field is translatable when whatever is typed into it is later printed through
 * `format_string()` / `format_text()`, because that is where the multilang filter
 * runs. Moodle has no per-element flag for this, so this class is the registry:
 *
 *  - Plain text inputs are an ALLOW list. Most `<input type="text">` in Moodle hold
 *    identifiers, e-mails, numbers or URLs, and only a well-known set of names
 *    ("name", "fullname", "title", "itemname", ...) are display strings.
 *  - Rich-text editors are a DENY list. Essentially every editor in Moodle holds
 *    `format_text()`-rendered content, so they are all translatable except a few
 *    that hold code/templates.
 *
 * Both lists are glob patterns (`*` matches anything) against the field's HTML
 * `name` attribute, and both can be extended or trimmed by an administrator in
 * Site administration -> Plugins -> Local plugins -> Multilingual fields, so a new
 * field never needs a code change.
 *
 * Exclusions are written as `pagetypeglob|fieldglob` so the same field name can be
 * translatable on one page and an identifier on another (e.g. `shortname` is a
 * course display name on course/edit.php but a code on a custom-field form).
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {

    /**
     * Text-input names that hold a display string somewhere in the site.
     *
     * Covers, in order: every activity/resource/section/group/grouping/cohort/
     * badge/scale/role/enrolment-method/calendar-event/question(-category)/
     * competency/custom-field/repository/block "Name"; repeated-group variants;
     * course and grade-category full names; course, outcome and framework short
     * names; book chapter, lesson page and policy titles; block "Block title";
     * forum post subject; grade item name; choice options; quiz/section headings;
     * question answers; and the site name pair on the front-page settings screen.
     *
     * @var string[]
     */
    const TEXT_FIELDS = [
        'name',
        'name[*]',
        '*[name]',
        'fullname',
        'fullname[*]',
        'shortname',
        'title',
        'title[*]',
        'config_title',
        'itemname',
        'subject',
        'displayname',
        'heading',
        'sectionheading',
        'option[*]',
        'answer[*]',
        'answer[*][text]',
        's__fullname',
        's__shortname',
    ];

    /**
     * Places where one of the names above is an identifier, not a display string.
     *
     * Format: `pagetypeglob|fieldglob`.
     *
     * @var string[]
     */
    const TEXT_EXCLUDES = [
        // mod_data field names are referenced as [[name]] inside its templates.
        'mod-data-*|*',
        // Role shortnames are code (editingteacher, student, ...).
        'admin-roles-*|shortname',
        // Custom-field and user-profile-field shortnames are code.
        // The `name` beside them is not: on admin-user-profile-index it is the
        // profile field's (and category's) display label, printed through
        // format_string() everywhere it appears, so it keeps the per-language
        // inputs like every other display name.
        '*customfield*|shortname',
        'admin-user-profile*|shortname',
        // Technical admin screens whose "name" is printed raw, not format_string()'d.
        'admin-webservice-*|*',
        'admin-mnet-*|*',
        'admin-oauth2-*|*',
        'admin-tool-customlang-*|*',
        'admin-tool-task-*|*',
        // Already bilingual by design (its own en/ar pair of inputs).
        'local-jobform-*|*',
    ];

    /**
     * Editors that hold code or templates rather than prose.
     *
     * Format: `pagetypeglob|fieldglob`.
     *
     * @var string[]
     */
    const EDITOR_EXCLUDES = [
        'mod-data-*|*',
        'admin-tool-customlang-*|*',
        'local-jobform-*|*',
    ];

    /**
     * Inline-rename controls that edit a display string.
     *
     * The pencil next to a section or activity name on the course page is not a
     * form field: it is a `core/inplace_editable` that swaps the name for a single
     * input and saves on Enter. Which of those hold a translatable value is keyed
     * by the editable's `component|itemtype` pair (both accept `*`). Section names
     * are owned by the course format (`format_topics`, `format_weeks`, ...), hence
     * the wildcard; activity names by core_course.
     *
     * @var string[]
     */
    const INLINE_ITEMS = [
        'format_*|sectionname',
        'format_*|sectionnamenl',
        'core_course|activityname',
    ];

    /**
     * Inline-rename controls (`component|itemtype` globs) that get per-language boxes.
     *
     * @return string[]
     */
    public static function inline_items(): array {
        return self::INLINE_ITEMS;
    }

    /**
     * The effective allow list for text inputs (defaults + admin additions).
     *
     * @return string[]
     */
    public static function text_fields(): array {
        return self::merge(self::TEXT_FIELDS, 'extratextfields');
    }

    /**
     * The effective exclusion list for text inputs (defaults + admin additions).
     *
     * @return string[]
     */
    public static function text_excludes(): array {
        return self::merge(self::TEXT_EXCLUDES, 'extraexcludes');
    }

    /**
     * The effective exclusion list for editors (defaults + admin additions).
     *
     * @return string[]
     */
    public static function editor_excludes(): array {
        return self::merge(self::EDITOR_EXCLUDES, 'extraexcludes');
    }

    /**
     * Merge a shipped default list with the newline-separated admin setting.
     *
     * @param string[] $defaults shipped patterns
     * @param string $settingname name of the local_nit_mlang config setting
     * @return string[] unique, trimmed patterns
     */
    protected static function merge(array $defaults, string $settingname): array {
        $extra = (string) get_config('local_nit_mlang', $settingname);
        $lines = preg_split('/\R/u', $extra) ?: [];
        $lines = array_filter(array_map('trim', $lines), function ($line) {
            return $line !== '' && strpos($line, '#') !== 0;
        });
        return array_values(array_unique(array_merge($defaults, $lines)));
    }
}
