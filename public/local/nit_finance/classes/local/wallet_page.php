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

/**
 * "My wallet" (local_nit_finance/wallet_page): balance, code redemption, top-up,
 * purchases and history; teachers also see their earnings wallet. Shown as the
 * "محفظتي" tab of the student hub (local/nit_lessons/student.php); wallet.php keeps
 * the form actions.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wallet_page {

    /**
     * The address that shows the wallet: the hub tab, or wallet.php itself when the
     * hub is not installed.
     *
     * @return \moodle_url
     */
    public static function url(): \moodle_url {
        global $CFG;
        if (file_exists($CFG->dirroot . '/local/nit_lessons/student.php')) {
            return new \moodle_url('/local/nit_lessons/student.php', ['tab' => 'wallet']);
        }
        return new \moodle_url('/local/nit_finance/wallet.php');
    }

    /**
     * Template context for local_nit_finance/wallet_page.
     *
     * @param int $userid
     * @param string $return where the online top-up comes back to ('' = its default)
     * @return array
     */
    public static function context(int $userid, string $return = ''): array {
        global $DB;
        $isteacher = $DB->record_exists('nit_wallet', ['ownertype' => wallets::TEACHER, 'userid' => $userid])
            || (class_exists('\local_academy\teacher_manager') && \local_academy\teacher_manager::is_teacher($userid));

        $context = [
            'balances' => [output::balance_card(wallets::STUDENT, $userid)],
            // The forms post to wallet.php, which acts and comes back to url().
            'redeem' => output::redeem_form(new \moodle_url('/local/nit_finance/wallet.php')),
            'topup' => output::topup_form($return),
            'purchases' => output::purchases(purchases::for_user($userid)),
            'history' => output::history(wallets::history(wallets::STUDENT, $userid)),
            'teacherhistory' => [],
            'isteacher' => $isteacher,
        ];
        if ($isteacher) {
            $context['balances'][] = output::balance_card(wallets::TEACHER, $userid);
            $context['teacherhistory'] = output::history(wallets::history(wallets::TEACHER, $userid));
        }
        $context['haspurchases'] = !empty($context['purchases']);
        $context['hashistory'] = !empty($context['history']);
        $context['hasteacherhistory'] = !empty($context['teacherhistory']);
        return $context;
    }
}
