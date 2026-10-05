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

namespace local_nit_finance\local;

use moodle_url;

/**
 * Template data for the student-facing finance pages.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class output {

    /**
     * A wallet balance card.
     *
     * @param string $type student | teacher
     * @param int $userid
     * @return array
     */
    public static function balance_card(string $type, int $userid): array {
        $balance = wallets::balance($type, $userid);
        return [
            'label' => get_string('wallet_' . $type, 'local_nit_finance'),
            'amount' => money::format($balance),
            'teacher' => $type === wallets::TEACHER,
            'negative' => $balance < 0,
        ];
    }

    /**
     * The "have a code?" form.
     *
     * @param moodle_url $action where the form posts (handles action=redeem)
     * @return array
     */
    public static function redeem_form(moodle_url $action): array {
        $params = [];
        foreach ($action->params() as $name => $value) {
            $params[] = ['name' => $name, 'value' => $value];
        }
        return [
            'action' => $action->out_omit_querystring(),
            'params' => $params,
            'sesskey' => sesskey(),
        ];
    }

    /**
     * The online top-up form. When no payment gateway is switched on it is shown
     * disabled with a note (and, for admins, links to switch Kashier on).
     *
     * @param string $return local page to come back to after paying ('' = wallet)
     * @return array|null null only when local_payments is not installed
     */
    public static function topup_form(string $return = ''): ?array {
        if (!class_exists('\local_payments\manager')
                || !method_exists('\local_payments\manager', 'create_wallet_topup_checkout')) {
            return null;
        }
        $presets = [];
        foreach ([50, 100, 200, 500] as $pounds) {
            $presets[] = ['value' => $pounds, 'label' => money::format($pounds * 100)];
        }
        $available = self::online_payment_available();
        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        return [
            'action' => (new moodle_url('/local/nit_finance/topup.php'))->out(false),
            'sesskey' => sesskey(),
            'return' => $return,
            'presets' => $presets,
            'min' => \local_payments\manager::WALLET_TOPUP_MIN,
            'max' => \local_payments\manager::WALLET_TOPUP_MAX,
            'available' => $available,
            'settingsurl' => !$available && $isadmin
                ? (new moodle_url('/admin/settings.php', ['section' => 'paymentprovider_kashier']))->out(false) : '',
            'providersurl' => !$available && $isadmin
                ? (new moodle_url('/local/payments/admin/providers.php'))->out(false) : '',
        ];
    }

    /**
     * Is an online payment gateway set up for EGP (local_payments)?
     *
     * @return bool
     */
    public static function online_payment_available(): bool {
        if (!class_exists('\local_payments\manager')
                || !method_exists('\local_payments\manager', 'create_wallet_topup_checkout')) {
            return false;
        }
        try {
            return (bool) \local_payments\manager::get_available_providers('EG', 'EGP');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Purchase rows.
     *
     * @param \stdClass[] $purchases from purchases::for_user()
     * @return array
     */
    public static function purchases(array $purchases): array {
        $out = [];
        foreach ($purchases as $p) {
            $out[] = [
                'name' => $p->item->name,
                'coursename' => $p->item->type === catalog::CM ? $p->item->coursename : '',
                'typename' => get_string('itemtype_' . $p->item->type, 'local_nit_finance'),
                'amount' => money::format($p->amount_minor),
                'method' => get_string('method_' . $p->method, 'local_nit_finance'),
                'date' => userdate($p->timecreated, get_string('strftimedatefullshort', 'langconfig')),
                'url' => $p->item->url,
            ];
        }
        return $out;
    }

    /**
     * "Or buy single lessons": the course's lessons sold on their own, for the
     * whole-course checkout page. '' when the course sells none.
     *
     * @param int $courseid
     * @param int $userid
     * @return string HTML
     */
    public static function lessons_for_sale(int $courseid, int $userid): string {
        $state = access::course_state($userid, $courseid);
        if (!$state['prices'] || $state['ownscourse']) {
            return '';
        }
        $modinfo = get_fast_modinfo($courseid);
        $items = '';
        foreach ($modinfo->get_cms() as $cm) {
            $price = $state['prices'][$cm->id] ?? 0;
            if ($price <= 0 || !$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            $owned = !empty($state['owned'][$cm->id]);
            $action = $owned
                ? \html_writer::span(get_string('owned', 'local_nit_finance'), 'nitfin-chip nitfin-chip--owned')
                : \html_writer::link(new moodle_url('/local/nit_finance/buy.php', ['cmid' => $cm->id]),
                    get_string('buy', 'local_nit_finance'), ['class' => 'nitfin-btn nitfin-btn--small']);
            $items .= \html_writer::tag('li',
                \html_writer::span(format_string($cm->name, true, ['context' => $cm->context]), 'nitfin-purchase__main nitfin-purchase__name')
                . \html_writer::span(money::format($price), 'nitfin-purchase__meta')
                . $action,
                ['class' => 'nitfin-purchase']);
        }
        if ($items === '') {
            return '';
        }
        return \html_writer::div(
            \html_writer::tag('h2', get_string('lessonsforsale', 'local_nit_finance'), ['class' => 'nitfin-section__title'])
            . \html_writer::tag('ul', $items, ['class' => 'nitfin-purchases']),
            'nitfin-forsale nit-brand-18 nitfin-section');
    }

    /**
     * "Free previews" block for the whole-course checkout page: the video lessons
     * whose first minutes this user can watch before buying.
     *
     * @param int $courseid
     * @param int $userid
     * @return string HTML, '' when there is none
     */
    public static function free_previews(int $courseid, int $userid): string {
        $previews = preview::course_seconds($courseid);
        if (!$previews) {
            return '';
        }
        $state = access::course_state($userid, $courseid);
        $items = '';
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if (!isset($previews[$cm->id]) || !$cm->visible || $cm->deletioninprogress) {
                continue;
            }
            $seconds = preview::for_user($userid, $cm, $state, $previews[$cm->id]);
            if ($seconds <= 0) {
                continue;
            }
            $items .= \html_writer::tag('li',
                \html_writer::span(format_string($cm->name, true, ['context' => $cm->context]),
                    'nitfin-purchase__main nitfin-purchase__name')
                . \html_writer::span(preview::label($seconds), 'nitfin-purchase__meta')
                . \html_writer::link(preview::url((int) $cm->id), get_string('previewwatch', 'local_nit_finance'),
                    ['class' => 'nitfin-btn nitfin-btn--outline nitfin-btn--small']),
                ['class' => 'nitfin-purchase']);
        }
        if ($items === '') {
            return '';
        }
        return \html_writer::div(
            \html_writer::tag('h2', get_string('freepreviews', 'local_nit_finance'), ['class' => 'nitfin-section__title'])
            . \html_writer::tag('ul', $items, ['class' => 'nitfin-purchases']),
            'nitfin-forsale nit-brand-18 nitfin-section');
    }

    /**
     * Ledger rows.
     *
     * @param \stdClass[] $lines from wallets::history()
     * @return array
     */
    public static function history(array $lines): array {
        $out = [];
        foreach ($lines as $l) {
            $amount = (int) $l->amount_minor;
            $out[] = [
                'date' => userdate($l->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'kind' => get_string('kind_' . $l->kind, 'local_nit_finance'),
                'note' => format_string($l->note),
                'amount' => ($amount > 0 ? '+' : '') . money::format($amount),
                'positive' => $amount > 0,
                'balance' => money::format((int) $l->balance_after_minor),
            ];
        }
        return $out;
    }
}
