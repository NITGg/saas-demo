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
 * Strings for aiplacement_nit_quizgen.
 *
 * @package    aiplacement_nit_quizgen
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Named with the NIT prefix so it reads as ours in a table of Moodle's own placements.
$string['pluginname'] = 'NIT — AI quiz generator';
$string['plugindescription'] = 'Writes a quiz from an approved video transcript: questions at three levels, covering the whole video, reviewed by a teacher before anyone sees them.';

$string['privacy:metadata'] = 'The NIT AI quiz generator placement stores no personal data. It registers the feature and its on/off switch; the generator itself lives in the local_nit_ai plugin.';
