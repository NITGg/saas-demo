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

namespace local_nit_tutors\local;

/**
 * The "Tutor" role: what an approved teacher holds at site level.
 *
 * Why not editingteacher / teacher: those are course roles — given at site level they would let
 * the tutor edit, or read the grades of, every course on the site. A tutor teaching private
 * lessons has no course, so the role only carries local/nit_lessons:teach (take bookings, run
 * lessons). A tutor who also teaches a course is enrolled in it as editingteacher as before.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_role {

    /** Role shortname. */
    const SHORTNAME = 'nittutor';

    /** What the role allows. */
    const CAPABILITIES = ['local/nit_lessons:teach'];

    /**
     * The role id, creating the role first when it is missing.
     *
     * @return int
     */
    public static function ensure(): int {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role('Tutor', self::SHORTNAME,
                'A teacher approved by the academy to give private live lessons (local_nit_tutors).', '');
        }
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $system = \context_system::instance();
        foreach (self::CAPABILITIES as $cap) {
            if (get_capability_info($cap)) {
                assign_capability($cap, CAP_ALLOW, $roleid, $system->id, true);
            }
        }
        return $roleid;
    }

    /**
     * Give the user the role at site level (no-op when they hold it).
     *
     * @param int $userid
     */
    public static function grant(int $userid): void {
        role_assign(self::ensure(), $userid, \context_system::instance()->id, 'local_nit_tutors');
    }

    /**
     * Take the role away.
     *
     * @param int $userid
     */
    public static function revoke(int $userid): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if ($roleid) {
            role_unassign($roleid, $userid, \context_system::instance()->id, 'local_nit_tutors');
        }
    }

    /**
     * Does the user hold the role?
     *
     * @param int $userid
     * @return bool
     */
    public static function holds(int $userid): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :uid AND r.shortname = :sn AND ra.contextid = :ctx",
            ['uid' => $userid, 'sn' => self::SHORTNAME, 'ctx' => \context_system::instance()->id]);
    }
}
