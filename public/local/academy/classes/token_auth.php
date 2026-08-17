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

namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Web-service token authentication for the academy mobile endpoints.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_auth {
    /**
     * Validate a web-service token with the same gates Moodle's own web-service
     * stack enforces — existence, expiry (validuntil), IP restriction, the
     * external service being enabled, and the account being a confirmed,
     * non-suspended, non-guest local user. Returns the user only when ALL pass,
     * so an expired / IP-locked / disabled-service / suspended-user token is
     * rejected instead of authenticating as the full user.
     *
     * @param string $token the raw web-service token
     * @return \stdClass|null the validated user, or null when the token is invalid
     */
    public static function validate(string $token): ?\stdClass {
        global $DB, $CFG;

        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $now = time();

        $tokenrec = $DB->get_record('external_tokens', ['token' => $token]);
        if (!$tokenrec) {
            return null;
        }
        // Token expiry.
        if (!empty($tokenrec->validuntil) && $tokenrec->validuntil < $now) {
            return null;
        }
        // IP restriction.
        if (!empty($tokenrec->iprestriction)
                && !address_in_subnet(getremoteaddr(), $tokenrec->iprestriction)) {
            return null;
        }
        // The token's external service must exist and be enabled.
        $service = $DB->get_record('external_services', ['id' => $tokenrec->externalserviceid]);
        if (!$service || empty($service->enabled)) {
            return null;
        }
        // The account must be a live, confirmed, non-suspended local user.
        $user = $DB->get_record('user', ['id' => $tokenrec->userid, 'deleted' => 0]);
        if (!$user
                || $user->mnethostid != $CFG->mnet_localhost_id
                || !empty($user->suspended)
                || empty($user->confirmed)
                || isguestuser($user)) {
            return null;
        }

        // Record access, mirroring core web-service behaviour.
        $DB->set_field('external_tokens', 'lastaccess', $now, ['id' => $tokenrec->id]);

        return $user;
    }
}
