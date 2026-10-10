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

namespace local_academy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * "Prevent messaging between students" (Site administration → Messaging →
 * Messaging settings).
 *
 * Moodle decides who may message whom only by shared courses / site-wide
 * messaging, with no hook to add a rule. Every message the web drawer and the
 * mobile app send goes through a core_message_* web service, so the rule is
 * applied in the official override_webservice_execution callback (lib.php):
 *
 *  - sending (send_instant_messages, send_messages_to_conversation) and contact
 *    requests between two students are refused;
 *  - the member info the drawer / app read gets canmessage = false for such a
 *    pair, so they show "You are unable to message this user" instead of a
 *    message box.
 *
 * "Staff" = site admins and anyone holding moodle/site:messageanyuser at the
 * site level or in at least one course (teachers, non-editing teachers,
 * managers — also category managers). A pair is blocked only when neither side
 * is staff, so student ↔ teacher and admin ↔ anyone stay open. Group
 * conversations are left alone: they exist only when a teacher turns on
 * "Group messaging" for a course group.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_messaging {

    /** Config key (plugin local_academy). */
    const CONFIG = 'blockstudentmessaging';

    /** The capability that marks a user as staff for this rule. */
    const STAFFCAP = 'moodle/site:messageanyuser';

    /** Web services that send a message or create a contact request. */
    const SENDING = [
        'core_message_send_instant_messages',
        'core_message_send_messages_to_conversation',
        'core_message_create_contact_request',
    ];

    /** Web services whose result carries members with a canmessage flag. */
    const READING = [
        'core_message_get_member_info',
        'core_message_get_conversation',
        'core_message_get_conversations',
        'core_message_get_conversation_between_users',
        'core_message_get_conversation_members',
        'core_message_message_search_users',
        'core_message_get_user_contacts',
        'core_message_get_contact_requests',
    ];

    /** @var bool[] userid => is staff, for this request. */
    private static $staff = [];

    /** Is the rule switched on? */
    public static function enabled(): bool {
        global $CFG;
        return !empty($CFG->messaging) && !empty(get_config('local_academy', self::CONFIG));
    }

    /** Forget cached staff lookups (tests, role changes within a request). */
    public static function reset_cache(): void {
        self::$staff = [];
    }

    /**
     * Is this user staff (allowed to message and be messaged by students)?
     *
     * @param int $userid
     * @return bool
     */
    public static function is_staff(int $userid): bool {
        if (!array_key_exists($userid, self::$staff)) {
            self::$staff[$userid] = is_siteadmin($userid)
                || has_capability(self::STAFFCAP, \context_system::instance(), $userid)
                || !empty(get_user_capability_course(self::STAFFCAP, $userid, false, '', '', 1));
        }
        return self::$staff[$userid];
    }

    /**
     * Does the rule stop $senderid from messaging $recipientid?
     *
     * @param int $senderid
     * @param int $recipientid
     * @return bool
     */
    public static function is_blocked(int $senderid, int $recipientid): bool {
        if ($senderid == $recipientid || !self::enabled()) {
            return false;
        }
        return !self::is_staff($senderid) && !self::is_staff($recipientid);
    }

    /**
     * override_webservice_execution: apply the rule to the core messaging services.
     *
     * @param \stdClass $function external function info (classname, methodname, name)
     * @param array $params validated parameters, in order
     * @return mixed the result, or false to let core run the function as usual
     */
    public static function override_webservice($function, array $params) {
        global $USER;
        $name = $function->name;
        if ((!in_array($name, self::SENDING) && !in_array($name, self::READING))
                || !self::enabled() || empty($USER->id) || self::is_staff((int) $USER->id)) {
            return false;
        }
        $userid = (int) $USER->id;
        $real = [$function->classname, $function->methodname];

        switch ($name) {
            case 'core_message_send_instant_messages':
                return self::send_instant_messages($real, $params, $userid);

            case 'core_message_send_messages_to_conversation':
                $otherid = self::other_member((int) $params[0], $userid);
                if ($otherid && self::is_blocked($userid, $otherid)) {
                    throw new \moodle_exception('studentmessaging_blocked', 'local_academy');
                }
                return false;

            case 'core_message_create_contact_request':
                // Params: userid (the requester, checked by core to be $USER), requesteduserid.
                if (self::is_blocked((int) $params[0], (int) $params[1])) {
                    throw new \moodle_exception('studentmessaging_blocked', 'local_academy');
                }
                return false;
        }

        // A read service: run it, then close the message box for blocked members.
        $result = call_user_func_array($real, $params);
        self::mark_members($result, $userid);
        return $result;
    }

    /**
     * Send the allowed messages through core; the blocked ones get a per-message
     * error in the same place (the service's own way of reporting a failed send).
     */
    private static function send_instant_messages(callable $real, array $params, int $userid): array {
        $messages = $params[0];
        $allowed = [];
        foreach ($messages as $i => $message) {
            if (!self::is_blocked($userid, (int) $message['touserid'])) {
                $allowed[$i] = $message;
            }
        }
        if (count($allowed) == count($messages)) {
            return call_user_func_array($real, $params);
        }

        $sent = $allowed ? array_values(call_user_func($real, array_values($allowed))) : [];
        $results = [];
        $next = 0;
        foreach ($messages as $i => $message) {
            if (isset($allowed[$i])) {
                $results[] = $sent[$next++];
                continue;
            }
            $result = [
                'msgid' => -1,
                'errormessage' => get_string('studentmessaging_blocked', 'local_academy'),
                'cantsendtouser' => (string) $message['touserid'],
            ];
            if (isset($message['clientmsgid'])) {
                $result['clientmsgid'] = $message['clientmsgid'];
            }
            $results[] = $result;
        }
        return $results;
    }

    /** The other member of an individual conversation, or 0 (group / self / unknown). */
    private static function other_member(int $conversationid, int $userid): int {
        global $DB;
        $type = $DB->get_field('message_conversations', 'type', ['id' => $conversationid]);
        if ($type != \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL) {
            return 0;
        }
        $other = $DB->get_field_select('message_conversation_members', 'userid',
            'conversationid = :cid AND userid <> :uid', ['cid' => $conversationid, 'uid' => $userid], IGNORE_MULTIPLE);
        return (int) $other;
    }

    /**
     * Walk a service result and set canmessage = false (and no "add contact"
     * prompt) on every member the user may not message.
     *
     * @param mixed $node array / object / scalar, changed in place
     * @param int $userid the viewer
     */
    private static function mark_members(&$node, int $userid): void {
        if (is_object($node)) {
            if (isset($node->id) && property_exists($node, 'canmessage') && self::is_blocked($userid, (int) $node->id)) {
                $node->canmessage = false;
                if (property_exists($node, 'canmessageevenifblocked')) {
                    $node->canmessageevenifblocked = false;
                }
                if (property_exists($node, 'requirescontact')) {
                    $node->requirescontact = false;
                }
            }
            foreach (get_object_vars($node) as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    self::mark_members($node->$key, $userid);
                }
            }
        } else if (is_array($node)) {
            if (isset($node['id']) && array_key_exists('canmessage', $node) && self::is_blocked($userid, (int) $node['id'])) {
                $node['canmessage'] = false;
                if (array_key_exists('canmessageevenifblocked', $node)) {
                    $node['canmessageevenifblocked'] = false;
                }
                if (array_key_exists('requirescontact', $node)) {
                    $node['requirescontact'] = false;
                }
            }
            foreach ($node as $key => &$value) {
                if (is_array($value) || is_object($value)) {
                    self::mark_members($value, $userid);
                }
            }
            unset($value);
        }
    }
}
