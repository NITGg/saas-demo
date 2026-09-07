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
 * CLI: recompile the theme_nit CSS (both directions).
 *
 * Spawned detached by the inline front-page editor after a palette save
 * (editor::spawn_prewarm), so the ~7s SCSS build never blocks the Save request:
 * the browser gets its "ok" immediately while this process warms the compiled
 * stylesheet for later page loads / non-editing visitors. Also runnable by hand
 * for diagnostics:  php theme/nit/cli/prewarm_css.php
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

\theme_nit\local\editor::prewarm_theme_css();
