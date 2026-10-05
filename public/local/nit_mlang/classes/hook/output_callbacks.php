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

namespace local_nit_mlang\hook;

use core\hook\output\before_standard_head_html_generation;
use local_nit_mlang\langs;
use local_nit_mlang\profilefields;
use local_nit_mlang\registry;

/**
 * Output hook callbacks — the "where" of the multilang field editor.
 *
 * There is no core hook that lets a plugin walk a `moodleform` definition, so the
 * enhancement is applied in the browser: this callback only decides whether the
 * current page/user should get it, and hands the JS module the language list and
 * the field registry. The module itself matches fields (including ones a modal
 * loads later, via a MutationObserver) and swaps in the per-language editor.
 *
 * @package    local_nit_mlang
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {

    /**
     * Load the per-language field editor on this page, when it applies.
     *
     * @param before_standard_head_html_generation $hook the output hook
     * @return void
     */
    public static function load_field_editor(before_standard_head_html_generation $hook): void {
        global $PAGE, $CFG;

        // Never during install/upgrade (no capabilities yet) or for logged-out
        // visitors (nobody edits content from the login or signup page).
        if (!isset($PAGE) || during_initial_install() || !empty($CFG->upgraderunning) || !isloggedin() || isguestuser()) {
            return;
        }
        if (!self::setting('enabled')) {
            return;
        }

        // Nothing to translate between when a single language pack is installed.
        $languages = langs::get();
        if (count($languages) < 2) {
            return;
        }

        try {
            $context = $PAGE->context ?: \context_system::instance();
        } catch (\Throwable $e) {
            $context = \context_system::instance();
        }
        $pagetype = (string) $PAGE->pagetype;

        // Custom profile fields sitting in a category an administrator marked as
        // translatable. These hold a person's own data rather than site content,
        // so they are collected separately from the site-wide registry and reach
        // people the registry never would.
        $profile = profilefields::inputs();

        if (has_capability('local/nit_mlang:edit', $context)) {
            // Content authors: the whole registry, plus any translatable profile
            // field (an administrator editing somebody's profile is on this path).
            $textfields   = array_merge(registry::text_fields(), $profile['text']);
            $editors      = self::setting('editors');
            $forceeditors = $profile['editor'];
            $inlineitems  = registry::inline_items();
        } else {
            // Everybody else: nothing but their own profile, and only on a screen
            // where a profile is edited — a student filling in a forum subject
            // must still get the ordinary single field.
            if ((!$profile['text'] && !$profile['editor']) || !profilefields::is_profile_page($pagetype)) {
                return;
            }
            $textfields   = $profile['text'];
            $editors      = false;
            $forceeditors = $profile['editor'];
            $inlineitems  = [];
        }

        $PAGE->requires->js_call_amd('local_nit_mlang/fields', 'init', [[
            'pagetype'       => $pagetype,
            'langs'          => $languages,
            'textfields'     => $textfields,
            'textexcludes'   => registry::text_excludes(),
            'editorexcludes' => registry::editor_excludes(),
            'editors'        => $editors,
            // Text fields whose two language boxes sit side by side rather than one
            // under the other. Only the custom profile fields: that is the form the
            // layout was asked for, and every other text field on the site keeps the
            // arrangement it already had.
            'inlinefields'   => $profile['text'],
            // Editors that get a language tab strip whether or not the global
            // "Include rich text editors" switch is on. That switch is off on this
            // site because a hand-authored HTML block is a single bilingual
            // document that breaks when it is split in two; an instructor's
            // Biography is ordinary prose and has the opposite need.
            'forceeditors'   => $forceeditors,
            // Inline-rename controls (the pencil beside a section or activity name
            // on the course page) that get the same per-language boxes. They are
            // not form fields, so they are keyed by component|itemtype rather than
            // by field name, and are only ever handed to content authors.
            'inlineitems'    => $inlineitems,
            'strings'        => [
                'translations' => get_string('translations', 'local_nit_mlang'),
                // The inline widget reuses core's own wording so it reads like the
                // single-box control it replaces.
                'instructions' => get_string('edittitleinstructions'),
                'save'         => get_string('save'),
                'cancel'       => get_string('cancel'),
            ],
        ]]);
    }

    /**
     * Read an on/off setting, treating "never saved" as on.
     *
     * Both switches ship enabled; this keeps that true in the window between the
     * plugin being installed and an administrator first opening its settings page.
     *
     * @param string $name setting name
     * @return bool
     */
    protected static function setting(string $name): bool {
        $value = get_config('local_nit_mlang', $name);
        return $value === false ? true : (bool) $value;
    }
}
