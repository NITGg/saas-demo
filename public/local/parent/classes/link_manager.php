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

namespace local_parent;

defined('MOODLE_INTERNAL') || die();

/**
 * The parent<->child link, keyed on the parent's phone number.
 *
 * The link table is the single registry: a student names a parent phone (a
 * pending row); the parent signs up with that phone and every matching row is
 * linked and given the parent role in the child's user context. Two students
 * naming one number simply yield two rows sharing the number, so the parent
 * lists both children.
 *
 * @package    local_parent
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_manager {

    /** @var string DB table. */
    const TABLE = 'local_parent_link';

    /**
     * Normalize a phone number so 010…, 0020…, +2010… and 2010… all match.
     *
     * Heuristic (good enough for a single default country): keep digits only,
     * drop an international 00 prefix, strip a local trunk 0, and ensure the
     * country code is present. See docs/parent-accounts.md §7.5.
     *
     * @param string $raw the number as typed
     * @return string digits only with country code, or '' if there was nothing usable
     */
    public static function normalize_phone(string $raw): string {
        $cc = (string) (get_config('local_parent', 'countrycode') ?: '20');
        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '' || $d === null) {
            return '';
        }
        // International 00 prefix → drop it (00 20 10… → 2010…).
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
        }
        // Already starts with the country code and is long enough to be a full number.
        if ($cc !== '' && strpos($d, $cc) === 0 && strlen($d) >= strlen($cc) + 7) {
            return $d;
        }
        // Otherwise treat it as a local number: strip the trunk 0 and prepend cc.
        $d = ltrim($d, '0');
        return $cc . $d;
    }

    /**
     * Record (or update) the parent phone a student named for themselves.
     *
     * Upserts one link row for (phone, student). If a parent account already
     * exists for that phone, the row is linked immediately and the role assigned
     * (covers the "parent signed up first" order).
     *
     * @param int $studentid
     * @param string $rawphone the parent number as the student typed it
     */
    public static function record_parent_phone(int $studentid, string $rawphone): void {
        global $DB;

        $phone = self::normalize_phone($rawphone);
        if ($studentid <= 0 || $phone === '') {
            return;
        }
        $now = time();

        // Does a parent account already back this number?
        $parentid = $DB->get_field_select(self::TABLE, 'parentid',
            'parentphone = :p AND parentid IS NOT NULL', ['p' => $phone], IGNORE_MULTIPLE) ?: null;

        $existing = $DB->get_record(self::TABLE, ['parentphone' => $phone, 'studentid' => $studentid]);
        if ($existing) {
            $existing->parentid     = $parentid ?: $existing->parentid;
            $existing->status       = $existing->parentid ? 'linked' : 'pending';
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'parentphone'  => $phone,
                'studentid'    => $studentid,
                'parentid'     => $parentid,
                'status'       => $parentid ? 'linked' : 'pending',
                'verified'     => 0,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        if ($parentid) {
            self::assign_role((int) $parentid, $studentid);
        }
    }

    /**
     * Whether any student has named this phone as their parent's — the
     * eligibility check the parent sign-up runs before creating the account.
     *
     * @param string $rawphone
     * @return bool
     */
    public static function phone_exists(string $rawphone): bool {
        global $DB;
        $phone = self::normalize_phone($rawphone);
        if ($phone === '') {
            return false;
        }
        return $DB->record_exists(self::TABLE, ['parentphone' => $phone]);
    }

    /**
     * Link a (just-created) parent account to every student that named its
     * phone: fill parentid, flip to linked, and assign the parent role in each
     * child's user context.
     *
     * @param int $parentid the parent user id
     * @param string $rawphone the parent's own phone (as entered at sign-up)
     * @return int number of children linked
     */
    public static function link_parent(int $parentid, string $rawphone): int {
        global $DB;

        $phone = self::normalize_phone($rawphone);
        if ($parentid <= 0 || $phone === '') {
            return 0;
        }
        $now = time();
        $rows = $DB->get_records(self::TABLE, ['parentphone' => $phone]);
        $count = 0;
        foreach ($rows as $row) {
            $row->parentid     = $parentid;
            $row->status       = 'linked';
            $row->timemodified = $now;
            $DB->update_record(self::TABLE, $row);
            self::assign_role($parentid, (int) $row->studentid);
            $count++;
        }
        return $count;
    }

    /**
     * The children linked to a parent.
     *
     * @param int $parentid
     * @return int[] child user ids
     */
    public static function list_children(int $parentid): array {
        global $DB;
        if ($parentid <= 0) {
            return [];
        }
        return array_values($DB->get_fieldset_select(self::TABLE, 'studentid',
            'parentid = :pid AND status = :st', ['pid' => $parentid, 'st' => 'linked']));
    }

    /**
     * Whether a parent is linked to a given child — the guard every parent-facing
     * API must run before returning that child's data.
     *
     * @param int $parentid
     * @param int $studentid
     * @return bool
     */
    public static function is_linked(int $parentid, int $studentid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE,
            ['parentid' => $parentid, 'studentid' => $studentid, 'status' => 'linked']);
    }

    /**
     * Get or create the 'parent' role ID. Self-healing if configuration is missing.
     *
     * @return int
     */
    public static function get_parent_role_id(): int {
        global $DB;
        $roleid = (int) get_config('local_parent', 'roleid');
        if ($roleid && $DB->record_exists('role', ['id' => $roleid])) {
            return $roleid;
        }

        // Fall back to finding 'parent' role by shortname.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'parent']);
        if (!$roleid) {
            require_once(__DIR__ . '/../db/install.php');
            if (function_exists('xmldb_local_parent_install')) {
                xmldb_local_parent_install();
                $roleid = (int) get_config('local_parent', 'roleid');
            }
            if (!$roleid) {
                $roleid = (int) create_role(
                    get_string('parentrole', 'local_parent'),
                    'parent',
                    get_string('parentroledesc', 'local_parent')
                );
            }
        }

        if ($roleid) {
            set_config('roleid', $roleid, 'local_parent');
            self::ensure_role_capabilities($roleid);
        }

        return (int) $roleid;
    }

    /**
     * Ensure the parent role has the required capabilities in system context.
     *
     * @param int $roleid
     */
    public static function ensure_role_capabilities(int $roleid): void {
        if ($roleid <= 0) {
            return;
        }
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        $syscontext = \context_system::instance();
        $caps = [
            'local/parent:view',
            'moodle/user:viewdetails',
            'moodle/user:viewalldetails',
            'moodle/grade:viewall',
            'gradereport/user:view',
        ];
        foreach ($caps as $cap) {
            if (get_capability_info($cap)) {
                assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
            }
        }
        $syscontext->mark_dirty();
    }

    /**
     * Assign the parent role to a parent in a child's user context (idempotent).
     *
     * @param int $parentid
     * @param int $studentid
     */
    public static function assign_role(int $parentid, int $studentid): void {
        if ($parentid <= 0 || $studentid <= 0) {
            return;
        }
        $roleid = self::get_parent_role_id();
        if (!$roleid) {
            return;
        }
        $context = \context_user::instance($studentid, IGNORE_MISSING);
        if ($context) {
            global $DB;
            if (!$DB->record_exists('role_assignments', [
                'roleid'    => $roleid,
                'contextid' => $context->id,
                'userid'    => $parentid,
                'component' => 'local_parent',
            ])) {
                role_assign($roleid, $parentid, $context->id, 'local_parent');
                $context->mark_dirty();
            }
        }
    }

    /**
     * Remove the parent role from a child's context (used when a link is dropped).
     *
     * @param int $parentid
     * @param int $studentid
     */
    public static function unassign_role(int $parentid, int $studentid): void {
        $roleid = self::get_parent_role_id();
        if (!$roleid || $parentid <= 0 || $studentid <= 0) {
            return;
        }
        $context = \context_user::instance($studentid, IGNORE_MISSING);
        if ($context) {
            role_unassign($roleid, $parentid, $context->id, 'local_parent');
            $context->mark_dirty();
        }
    }
}
