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

namespace local_nit_assistant\local;

use local_nit_assistant\chat\access;
use local_nit_assistant\chat\conversation;
use local_nit_assistant\chat\service;
use local_nit_assistant\config;

/**
 * The chat button on every page (hook before_footer_html_generation; no theme
 * or core change). Not shown inside the mobile app's WebView, in chrome-free
 * layouts, or on admin pages.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class launcher {
    /** Layouts without the chat. */
    private const SKIP_LAYOUTS = ['embedded', 'popup', 'frametop', 'print', 'redirect', 'maintenance', 'secure', 'admin'];

    /** Strings the widget needs. */
    private const STRINGS = ['chat_title', 'chat_open', 'chat_close', 'chat_send', 'chat_placeholder', 'chat_new',
        'chat_ai_note', 'chat_team', 'chat_you', 'chat_waiting', 'chat_human', 'chat_error_busy', 'chat_error_full',
        'chat_error_ratelimited', 'chat_error_generic', 'chat_error_session', 'msg_fallback', 'chat_thinking'];

    /**
     * Hook: add the widget.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;
        try {
            if (!self::should_show($PAGE->pagelayout)) {
                return;
            }
            $hook->add_html(self::html());
            $PAGE->requires->js(new \moodle_url('/local/nit_assistant/js/chat.js'));
        } catch (\Throwable $e) {
            debugging('local_nit_assistant: launcher failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Should this page show the chat?
     *
     * @param string $layout
     * @return bool
     */
    public static function should_show(string $layout): bool {
        global $CFG, $SESSION;
        if (during_initial_install() || !empty($CFG->upgraderunning) || in_array($layout, self::SKIP_LAYOUTS, true)) {
            return false;
        }
        if (\core_useragent::is_moodle_app() || !empty($SESSION->theme_nit_appembed)) {
            return false;
        }
        return access::userid() !== null && config::usable();
    }

    /**
     * The widget's root element; chat.js builds the rest.
     *
     * @return string
     */
    public static function html(): string {
        $lang = conversation::lang(current_language());
        $strings = [];
        foreach (self::STRINGS as $id) {
            $strings[$id] = get_string($id, 'local_nit_assistant');
        }
        $data = [
            'chat' => (new \moodle_url('/local/nit_assistant/chat.php'))->out(false),
            'api' => (new \moodle_url('/local/nit_assistant/api.php'))->out(false),
            'greeting' => config::greeting($lang),
            'maxlength' => service::MAXLEN,
            'signedin' => isloggedin() && !isguestuser(),
            'rtl' => right_to_left(),
            'strings' => $strings,
        ];
        return \html_writer::div('', 'nit-asst', [
            'id' => 'nit-assistant',
            'data-config' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'dir' => right_to_left() ? 'rtl' : 'ltr',
        ]);
    }
}
