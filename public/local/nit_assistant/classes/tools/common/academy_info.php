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

namespace local_nit_assistant\tools\common;

use local_nit_assistant\tools\context;
use local_nit_assistant\tools\tool;

/**
 * get_academy_info — the academy's name, description, languages and public
 * contact options (from the site and theme settings). Public data only.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class academy_info extends tool {
    /**
     * {@inheritDoc}
     */
    public function name(): string {
        return 'get_academy_info';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string {
        return 'The academy\'s name, short description, site languages and public contact options '
            . '(WhatsApp, phone, email, site URL). '
            . 'Use it for "who are you", "how do I contact you" and before giving any contact detail — never invent one.';
    }

    /**
     * {@inheritDoc}
     */
    public function modes(): array {
        return ['sales', 'student', 'teacher'];
    }

    /**
     * {@inheritDoc}
     */
    public function schema(): array {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    /**
     * {@inheritDoc}
     */
    public function run(context $ctx, array $input): array {
        global $CFG, $SITE;
        $setting = static fn(string $plugin, string $name): string => trim((string) (get_config($plugin, $name) ?: ''));
        $html = format_text($SITE->summary ?? '', FORMAT_HTML, ['context' => \context_system::instance()]);
        $summary = trim(html_to_text($html, 0, false));
        $whatsapp = $setting('theme_nit', 'support_phone') ?: $setting('local_multitopics', 'whatsapp_phone');
        return self::ok([
            'name' => format_string($SITE->fullname, true, ['context' => \context_system::instance()]),
            'description' => \core_text::substr($summary, 0, 1200),
            'languages' => array_keys(get_string_manager()->get_list_of_translations()),
            'contact' => array_filter([
                'whatsapp' => $whatsapp,
                'phone' => $setting('theme_nit', 'contact_phone'),
                'email' => $setting('theme_nit', 'support_email') ?: trim((string) ($CFG->supportemail ?? '')),
                'website' => $CFG->wwwroot,
            ]),
        ]);
    }
}
