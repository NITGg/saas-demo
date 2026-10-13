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

namespace local_nit_assistant\llm;

use local_nit_assistant\config;

/**
 * "Test the AI connection" for admins: one tiny request with the academy's key and
 * chat model, reporting exactly what Anthropic answered (a wrong key, a model the
 * key can't use, the server unable to reach api.anthropic.com…).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class check {
    /**
     * Run the check.
     *
     * @return array ['ok' => true, 'model', 'reply', 'ms'] or ['ok' => false, 'error']
     */
    public static function run(): array {
        if (config::apikey() === null) {
            return ['ok' => false, 'error' => get_string('error_nokey', 'local_nit_assistant')];
        }
        $started = microtime(true);
        try {
            // The same request a visitor's chat sends (prompt, context and tools), so any part of it
            // Anthropic refuses shows up here — not only the key.
            $res = \core\di::get(client::class)->call([
                'model' => config::chat_model(),
                'system' => \local_nit_assistant\knowledge\prompt::system('sales', format_string(get_site()->fullname)),
                'volatile' => \local_nit_assistant\knowledge\prompt::volatile(time(), 'en', false, null) . "\n"
                    . 'This is a connection test: reply with the single word OK and do not use any tool.',
                'messages' => [['role' => 'user', 'text' => 'Connection test']],
                'tools' => (new \local_nit_assistant\tools\registry())->definitions('sales'),
                'maxtokens' => 256,
                'effort' => 'low',
            ]);
        } catch (\Throwable $e) {
            config::record_error($e);
            $detail = $e instanceof \moodle_exception && !empty($e->debuginfo) ? $e->debuginfo : $e->getMessage();
            return ['ok' => false, 'error' => \core_text::substr((string) $detail, 0, 500)];
        }
        return ['ok' => true, 'model' => $res->model, 'reply' => \core_text::substr(trim($res->text), 0, 40),
            'ms' => (int) round((microtime(true) - $started) * 1000)];
    }
}
