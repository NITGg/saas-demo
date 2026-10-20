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

namespace local_nit_lessons\api;

use local_nit_lessons\local\notifier;
use local_nit_lessons\local\realtime;
use local_nit_lessons\service\instant_service;

/**
 * Public facade for instant lessons ("Get a tutor now"), shared by the web page (instant.php)
 * and the mobile API (api.php). Every call names the acting user; ownership is checked below.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class instant {
    /** @var string[] Actions callable through instant.php / api.php (prefix instant_). */
    const ACTIONS = ['subjects', 'request', 'status', 'cancel', 'presence', 'online', 'offline', 'poll', 'accept',
        'decline', 'watch', 'reliability'];

    /**
     * Run one action for a user.
     *
     * @param int $userid
     * @param string $action one of ACTIONS
     * @param array $p subject, note, requestid, photo (an uploaded file from $_FILES, or null)
     * @return array
     */
    public static function act(int $userid, string $action, array $p): array {
        $s = new instant_service();
        switch ($action) {
            case 'subjects':
                $settings = new \local_nit_lessons\service\settings_service();
                $all = array_map(fn($x) => ['subject' => $x, 'label' => format_string($x)],
                    \local_nit_lessons\service\teacher_service::site_subjects());
                return ['enabled' => $s->enabled(), 'subjects' => $s->enabled() ? $s->online_subjects($userid) : [],
                    'all' => $all, 'open' => $s->open_request($userid),
                    'trial' => $s->trial_eligible($userid),
                    'trialflex' => $settings->get('instant_trial_flex'),
                    'quick' => $settings->get('instant_quick_minutes') > 0,
                    'quickminutes' => $settings->get('instant_quick_minutes'),
                    'quickflex' => $s->price(0, 'quick'),
                    'minutes' => $settings->get('instant_minutes'),
                    'peak' => $s->is_peak(time()),
                    'ws' => realtime::client_config($userid), // Null when realtime is off.
                    'bookurl' => (new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'book']))->out(false)];
            case 'request':
                return $s->request($userid, (string) ($p['subject'] ?? ''), (string) ($p['note'] ?? ''), null,
                    $p['photo'] ?? null, (string) ($p['mode'] ?? '') === 'quick' ? 'quick' : 'normal');
            case 'reliability':
                return $s->reliability((int) ($p['teacherid'] ?? 0) ?: $userid);
            case 'watch':
                return $s->watch($userid, (string) ($p['subject'] ?? ''));
            case 'status':
                return $s->status($userid, (int) ($p['requestid'] ?? 0));
            case 'cancel':
                return $s->cancel($userid, (int) ($p['requestid'] ?? 0));
            case 'presence':
                return $s->presence($userid) + ['ws' => realtime::client_config($userid)];
            case 'online':
                return $s->set_online($userid, true);
            case 'offline':
                return $s->set_online($userid, false);
            case 'poll':
                return $s->heartbeat($userid);
            case 'accept':
                $lesson = $s->accept($userid, (int) ($p['requestid'] ?? 0));
                notifier::send('started', $lesson, $userid);
                return ['lesson' => $lesson,
                    'joinurl' => (new \moodle_url('/local/nit_lessons/join.php', ['id' => $lesson['id']]))->out(false)];
            case 'decline':
                $s->decline($userid, (int) ($p['requestid'] ?? 0));
                return ['ok' => true];
        }
        throw new \local_nit_lessons\exception\lesson_exception('err_badaction');
    }
}
