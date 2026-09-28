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
 * English strings for local_parent.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Parent accounts';

// Capability.
$string['parent:view'] = 'View a linked child\'s progress';

// The parent role.
$string['parentrole'] = 'Parent';
$string['parentroledesc'] = 'A parent or guardian who can follow their linked child\'s marks, quiz activity and events. Assigned automatically in each child\'s context when the accounts are linked by phone number.';

// Settings.
$string['settingsheading'] = 'Parent accounts';
$string['defaultcountrycode'] = 'Default country code';
$string['defaultcountrycode_desc'] = 'Digits prefixed to a local number that has no country code, so 010… and +2010… match. Egypt = 20.';
$string['parentphonefield'] = 'Parent-phone profile field';
$string['parentphonefield_desc'] = 'The shortname of the student custom profile field that holds the parent\'s phone number (filled at student registration).';

// Student sign-up field.
$string['parentphonelabel'] = 'Parent / guardian phone';

// Linking / flow messages.
$string['err_noparentnumber'] = 'No student has listed this number. Ask your child to add it to their profile first.';
$string['linkedchildren'] = 'Linked children';
$string['nochildren'] = 'No children are linked to your account yet.';
