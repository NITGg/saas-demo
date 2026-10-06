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

namespace local_nit_notifications;

/**
 * Sending notifications and reading them back.
 *
 * A notification is saved first (local_nit_notif), then its recipients are
 * listed (local_nit_notif_rcpt, one row each, "queued"), then each one gets a
 * Moodle notification: the bell on the site and a push in the app (provider
 * local_nit_notifications/announcement), plus an email when the sender asked
 * for it. Small groups are delivered at once; a big group is handed to an adhoc
 * task that delivers it in batches (so cron must run). Every recipient row keeps
 * whether it was sent or failed and the core notification id, which tells
 * whether it was read.
 *
 * Automatic notifications (subscriptions, quizzes, live sessions…) go through
 * send_to_users() so they land in the same log.
 *
 * @package    local_nit_notifications
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sender {

    /** Notification types. */
    public const TYPES = ['general', 'courses', 'subscriptions', 'offers'];

    /** Recipient waiting. */
    public const RCPT_QUEUED = 0;
    /** Recipient got it. */
    public const RCPT_SENT = 1;
    /** Delivery failed. */
    public const RCPT_FAILED = 2;

    /** Up to this many recipients are delivered during the request; more go to cron. */
    public const SYNC_LIMIT = 300;

    /** Recipients per adhoc-task batch. */
    public const BATCH = 500;

    /** Longest title. */
    public const MAX_TITLE = 200;

    /** Longest body. */
    public const MAX_BODY = 4000;

    /**
     * Validate a hand-written notification against the sender's rights.
     *
     * @param array $data audience, courseid, type, title, body, url, email
     * @param int $userid 0 = current user
     * @return array<string,string> field => error string key (empty when valid)
     */
    public static function validate(array $data, int $userid = 0): array {
        $errors = [];
        $audience = (string) ($data['audience'] ?? '');
        if ($err = audience::validate($audience, (int) ($data['courseid'] ?? 0), $userid)) {
            $errors[audience::is_course_audience($audience) && $err !== 'err_audience' ? 'courseid' : 'audience'] = $err;
        }
        if (!in_array((string) ($data['type'] ?? ''), self::TYPES, true)) {
            $errors['type'] = 'err_type';
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'err_required';
        } else if (\core_text::strlen($title) > self::MAX_TITLE) {
            $errors['title'] = 'err_titletoolong';
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            $errors['body'] = 'err_required';
        } else if (\core_text::strlen($body) > self::MAX_BODY) {
            $errors['body'] = 'err_bodytoolong';
        }
        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '' && clean_param($url, PARAM_URL) === '') {
            $errors['url'] = 'err_url';
        }
        return $errors;
    }

    /**
     * Send a hand-written notification now (the compose page and the API).
     *
     * @param array $data audience, courseid, type, title, body, url, email
     * @param int $userid the sender, 0 = current user
     * @return \stdClass the saved notification (status done, or queued for cron)
     * @throws \moodle_exception err_* on invalid data, err_norecipients when nobody matches
     */
    public static function send(array $data, int $userid = 0): \stdClass {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        if ($errors = self::validate($data, $userid)) {
            $code = reset($errors);
            $a = ['err_titletoolong' => self::MAX_TITLE, 'err_bodytoolong' => self::MAX_BODY][$code] ?? null;
            throw new \moodle_exception($code, 'local_nit_notifications', '', $a);
        }
        $audience = (string) $data['audience'];
        $courseid = audience::is_course_audience($audience) ? (int) ($data['courseid'] ?? 0) : 0;
        $recipients = audience::resolve($audience, $courseid, $userid);
        if (!$recipients) {
            throw new \moodle_exception('err_norecipients', 'local_nit_notifications');
        }
        $notif = self::create([
            'senderid' => $userid,
            'source' => 'manual',
            'type' => (string) $data['type'],
            'audience' => $audience,
            'courseid' => $courseid,
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'url' => trim((string) ($data['url'] ?? '')),
            'email' => empty($data['email']) ? 0 : 1,
        ], $recipients);
        event\notification_sent::create_from_notif($notif)->trigger();
        return self::dispatch($notif);
    }

    /**
     * Send a notification to given users and keep it in the log — the entry point
     * for automatic notifications. Recipients that are not active are skipped.
     *
     * @param string $source what sent it, e.g. 'subscription_expiry'
     * @param string $type one of TYPES
     * @param int[] $userids
     * @param string $title
     * @param string $body plain text
     * @param int $courseid the course it is about (scopes it in managers' logs), 0 = none
     * @param string $url optional link
     * @return \stdClass|null the notification, null when nobody is left to send to
     */
    public static function send_to_users(string $source, string $type, array $userids, string $title, string $body,
            int $courseid = 0, string $url = ''): ?\stdClass {
        $recipients = audience::active(array_values(array_unique(array_map('intval', $userids))));
        if (!$recipients) {
            return null;
        }
        $notif = self::create([
            'senderid' => 0,
            'source' => $source,
            'type' => in_array($type, self::TYPES, true) ? $type : 'general',
            'audience' => audience::USERS,
            'courseid' => $courseid,
            'title' => \core_text::substr(trim($title), 0, 255),
            'body' => trim($body),
            'url' => $url,
            'email' => 0,
        ], $recipients);
        return self::dispatch($notif);
    }

    /**
     * Save the notification and its queued recipients.
     *
     * @param array $fields
     * @param int[] $recipients
     * @return \stdClass
     */
    private static function create(array $fields, array $recipients): \stdClass {
        global $DB;
        $notif = (object) ($fields + [
            'status' => 'queued',
            'total' => count($recipients),
            'sent' => 0,
            'failed' => 0,
            'timecreated' => time(),
            'timecompleted' => 0,
        ]);
        $transaction = $DB->start_delegated_transaction();
        $notif->id = $DB->insert_record('local_nit_notif', $notif);
        foreach (array_chunk($recipients, 1000) as $chunk) {
            $DB->insert_records('local_nit_notif_rcpt', array_map(fn($uid) => (object) [
                'notifid' => $notif->id, 'userid' => $uid, 'status' => self::RCPT_QUEUED, 'messageid' => 0, 'timesent' => 0,
            ], $chunk));
        }
        $transaction->allow_commit();
        return $notif;
    }

    /**
     * Deliver now when the group is small, else queue the adhoc task.
     *
     * @param \stdClass $notif
     * @return \stdClass the notification as it is now
     */
    private static function dispatch(\stdClass $notif): \stdClass {
        global $DB;
        if ((int) $notif->total <= self::SYNC_LIMIT) {
            self::deliver((int) $notif->id, self::SYNC_LIMIT);
        } else {
            $task = new task\deliver();
            $task->set_custom_data(['notifid' => (int) $notif->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        return $DB->get_record('local_nit_notif', ['id' => $notif->id]);
    }

    /**
     * Deliver up to $limit queued recipients of a notification; mark it done when
     * none are left.
     *
     * @param int $notifid
     * @param int $limit
     * @return int how many are still queued
     */
    public static function deliver(int $notifid, int $limit = self::BATCH): int {
        global $DB;
        $notif = $DB->get_record('local_nit_notif', ['id' => $notifid]);
        if (!$notif) {
            return 0;
        }
        if ($notif->status !== 'sending') {
            $DB->set_field('local_nit_notif', 'status', 'sending', ['id' => $notifid]);
        }
        $from = (int) $notif->senderid ? \core_user::get_user((int) $notif->senderid) : null;
        $from = $from && !$from->deleted ? $from : \core_user::get_noreply_user();

        $rows = $DB->get_records('local_nit_notif_rcpt', ['notifid' => $notifid, 'status' => self::RCPT_QUEUED],
            'id', 'id, userid', 0, $limit);
        foreach ($rows as $row) {
            $user = \core_user::get_user((int) $row->userid);
            $messageid = 0;
            if ($user && !$user->deleted && !$user->suspended) {
                $messageid = self::message($notif, $from, $user);
            }
            $DB->update_record('local_nit_notif_rcpt', (object) [
                'id' => $row->id,
                'status' => $messageid ? self::RCPT_SENT : self::RCPT_FAILED,
                'messageid' => $messageid,
                'timesent' => time(),
            ]);
        }

        // Recount from the rows, so retries and parallel runs stay right.
        $sent = $DB->count_records('local_nit_notif_rcpt', ['notifid' => $notifid, 'status' => self::RCPT_SENT]);
        $failed = $DB->count_records('local_nit_notif_rcpt', ['notifid' => $notifid, 'status' => self::RCPT_FAILED]);
        $left = $DB->count_records('local_nit_notif_rcpt', ['notifid' => $notifid, 'status' => self::RCPT_QUEUED]);
        $update = (object) ['id' => $notifid, 'sent' => $sent, 'failed' => $failed];
        if (!$left) {
            $update->status = 'done';
            $update->timecompleted = time();
        }
        $DB->update_record('local_nit_notif', $update);
        return $left;
    }

    /**
     * One Moodle notification (bell + push), and the email copy when asked.
     *
     * @param \stdClass $notif
     * @param \stdClass $from
     * @param \stdClass $to
     * @return int the core notification id, 0 on failure
     */
    private static function message(\stdClass $notif, \stdClass $from, \stdClass $to): int {
        $html = '<p>' . nl2br(s($notif->body)) . '</p>';
        $message = new \core\message\message();
        $message->component = 'local_nit_notifications';
        $message->name = 'announcement';
        $message->userfrom = $from;
        $message->userto = $to;
        $message->subject = $notif->title;
        $message->fullmessage = $notif->body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $notif->title;
        $message->notification = 1;
        if (!empty($notif->url)) {
            $message->contexturl = $notif->url;
            $message->contexturlname = $notif->title;
        }
        $message->courseid = (int) $notif->courseid > 1 ? (int) $notif->courseid : SITEID;
        $message->customdata = ['nitnotifid' => (int) $notif->id, 'type' => $notif->type];
        try {
            $id = (int) message_send($message);
        } catch (\Throwable $e) {
            debugging('local_nit_notifications: delivery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
        if ($id && !empty($notif->email)) {
            try {
                $text = $notif->body . (!empty($notif->url) ? "\n\n" . $notif->url : '');
                email_to_user($to, $from, $notif->title, $text,
                    $html . (!empty($notif->url) ? '<p><a href="' . s($notif->url) . '">' . s($notif->url) . '</a></p>' : ''));
            } catch (\Throwable $e) {
                debugging('local_nit_notifications: email failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        return $id;
    }

    // =========================================================================
    // The log.
    // =========================================================================

    /**
     * Whether the user may open a notification in the log: site-wide senders see
     * everything; a scoped manager sees what they sent and what went to their courses.
     *
     * @param \stdClass $notif
     * @param int $userid 0 = current user
     * @return bool
     */
    public static function can_view(\stdClass $notif, int $userid = 0): bool {
        global $USER;
        $userid = $userid ?: (int) $USER->id;
        $scope = audience::course_ids($userid);
        if ($scope === null) {
            return true;
        }
        return (int) $notif->senderid === $userid || in_array((int) $notif->courseid, $scope, true);
    }

    /**
     * One page of the log, newest first, limited to what the user may see.
     *
     * @param array $filters type, source ('manual'|'auto'|''), courseid, q (title/body), datefrom, dateto
     * @param int $page 0-based
     * @param int $perpage
     * @param int $userid 0 = current user
     * @return array{total:int, items:\stdClass[]} items carry sendername, coursename, readcount
     */
    public static function log(array $filters, int $page = 0, int $perpage = 30, int $userid = 0): array {
        global $DB, $USER;
        $userid = $userid ?: (int) $USER->id;
        $where = ["n.status <> 'draft'"];
        $params = [];
        $scope = audience::course_ids($userid);
        if ($scope !== null) {
            $cond = 'n.senderid = :me';
            $params['me'] = $userid;
            if ($scope) {
                [$insql, $inparams] = $DB->get_in_or_equal($scope, SQL_PARAMS_NAMED, 'sc');
                $cond .= " OR n.courseid $insql";
                $params += $inparams;
            }
            $where[] = "($cond)";
        }
        if (!empty($filters['type']) && in_array($filters['type'], self::TYPES, true)) {
            $where[] = 'n.type = :type';
            $params['type'] = $filters['type'];
        }
        if (($filters['source'] ?? '') === 'manual') {
            $where[] = "n.source = 'manual'";
        } else if (($filters['source'] ?? '') === 'auto') {
            $where[] = "n.source <> 'manual'";
        }
        if (!empty($filters['courseid'])) {
            $where[] = 'n.courseid = :courseid';
            $params['courseid'] = (int) $filters['courseid'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(' . $DB->sql_like('n.title', ':q1', false) . ' OR ' . $DB->sql_like('n.body', ':q2', false) . ')';
            $params['q1'] = $params['q2'] = '%' . $DB->sql_like_escape($q) . '%';
        }
        if (!empty($filters['datefrom'])) {
            $where[] = 'n.timecreated >= :datefrom';
            $params['datefrom'] = (int) $filters['datefrom'];
        }
        if (!empty($filters['dateto'])) {
            $where[] = 'n.timecreated <= :dateto';
            $params['dateto'] = (int) $filters['dateto'];
        }
        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {local_nit_notif} n WHERE $wheresql", $params);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, 'sender_', '', false)->selects;
        $items = $DB->get_records_sql(
            "SELECT n.*, c.fullname AS coursename, $namefields
               FROM {local_nit_notif} n
          LEFT JOIN {course} c ON c.id = n.courseid
          LEFT JOIN {user} u ON u.id = n.senderid
              WHERE $wheresql
           ORDER BY n.timecreated DESC, n.id DESC", $params, max(0, $page) * $perpage, $perpage);
        $reads = self::read_counts(array_keys($items));
        foreach ($items as $n) {
            $n->readcount = $reads[(int) $n->id] ?? 0;
            $n->sendername = '';
            if ((int) $n->senderid) {
                $sender = (object) ['id' => (int) $n->senderid];
                foreach (\core_user\fields::get_name_fields() as $f) {
                    $sender->$f = $n->{'sender_' . $f} ?? '';
                }
                $n->sendername = fullname($sender);
            }
        }
        return ['total' => $total, 'items' => array_values($items)];
    }

    /**
     * How many recipients read each notification.
     *
     * @param int[] $notifids
     * @return array<int,int>
     */
    public static function read_counts(array $notifids): array {
        global $DB;
        if (!$notifids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($notifids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT r.notifid, COUNT(1)
               FROM {local_nit_notif_rcpt} r
               JOIN {notifications} m ON m.id = r.messageid
              WHERE r.notifid $insql AND m.timeread IS NOT NULL
           GROUP BY r.notifid", $params));
    }

    /**
     * One page of a notification's recipients with delivery and read state.
     *
     * @param int $notifid
     * @param string $state '' | sent | failed | queued | read | unread
     * @param int $page
     * @param int $perpage
     * @return array{total:int, items:\stdClass[]} items: userid, fullname, email, status, timesent, timeread
     */
    public static function recipients(int $notifid, string $state = '', int $page = 0, int $perpage = 50): array {
        global $DB;
        $where = 'r.notifid = :notifid';
        $params = ['notifid' => $notifid];
        $states = ['sent' => self::RCPT_SENT, 'failed' => self::RCPT_FAILED, 'queued' => self::RCPT_QUEUED];
        if (isset($states[$state])) {
            $where .= ' AND r.status = :status';
            $params['status'] = $states[$state];
        } else if ($state === 'read') {
            $where .= ' AND m.timeread IS NOT NULL';
        } else if ($state === 'unread') {
            $where .= ' AND r.status = :sentstatus AND m.timeread IS NULL';
            $params['sentstatus'] = self::RCPT_SENT;
        }
        $from = "FROM {local_nit_notif_rcpt} r
                 JOIN {user} u ON u.id = r.userid
            LEFT JOIN {notifications} m ON m.id = r.messageid
                WHERE $where";
        $total = (int) $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql("SELECT r.id, r.userid, r.status, r.timesent, m.timeread, u.email, $namefields
                                      $from
                                   ORDER BY u.firstname, u.lastname, r.id",
            $params, max(0, $page) * $perpage, $perpage);
        foreach ($rows as $r) {
            $user = clone $r;
            $user->id = (int) $r->userid;
            $r->fullname = fullname($user);
        }
        return ['total' => $total, 'items' => array_values($rows)];
    }
}
