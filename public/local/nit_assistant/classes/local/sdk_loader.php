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

/**
 * Autoloads the bundled Anthropic SDK (thirdpartylibs.xml) for its three
 * namespaces only. PSR HTTP interfaces and Guzzle come from Moodle core, so the
 * plugin never ships a second copy of them.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sdk_loader {
    /** @var bool */
    private static bool $registered = false;

    /** @var array<string,string> Namespace prefix → directory under vendor/. */
    private const MAP = [
        'Anthropic\\'        => 'anthropic-ai/sdk/src/',
        'Http\\Discovery\\'  => 'php-http/discovery/src/',
        'StandardWebhooks\\' => 'standard-webhooks/libraries/php/src/',
    ];

    /**
     * Register the autoloader once.
     *
     * @return void
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        $base = dirname(__DIR__, 2) . '/vendor/';
        spl_autoload_register(static function (string $class) use ($base): void {
            foreach (self::MAP as $prefix => $dir) {
                if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                    $file = $base . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($file)) {
                        require_once($file);
                    }
                    return;
                }
            }
        });
        require_once($base . 'anthropic-ai/sdk/src/Version.php'); // Composer "files" entry.
        self::$registered = true;
    }
}
