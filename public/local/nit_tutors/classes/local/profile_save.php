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

use local_nit_lessons\service\teacher_service;

/**
 * Saving the teacher profile: the teacher's data (teacher_input) and the lessons card
 * (local_nit_lessons\local\profile_section::card() — bookings, price, hours; its headline and
 * subjects are part of the teacher's data). One door for the web page (application.php) and the
 * app (api.php → save_application).
 *
 * Everything is checked before anything is saved. An approved teacher's data waits for review
 * ({@see pending}); bookings, price and hours always save at once.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_save {

    /**
     * Can the teacher send the application for review now (a draft, sent back, or rejected with
     * re-applying allowed)?
     *
     * @param int $userid
     * @return bool
     */
    public static function can_submit(int $userid): bool {
        $status = application::status($userid);
        return application::editable($userid)
            && in_array($status, ['', application::DRAFT, application::CHANGES, application::REJECTED], true)
            && ($status !== application::REJECTED || application::setting_int('allowreapply', 1));
    }

    /**
     * Check and save.
     *
     * @param int $userid
     * @param array $in teacher_input values (headline and subjects are the card's when there is a card)
     * @param array $files teacher_input files
     * @param array|null $card profile_section::card(), null = leave bookings, price and hours alone
     * @param bool $send also send the application for review (ignored when it cannot be sent)
     * @return array {errors: [field => message] (nothing saved when not empty, except 'general' after a
     *     failed send), waits: bool (a change waits for review), submitted: bool}
     */
    public static function save(int $userid, array $in, array $files, ?array $card, bool $send): array {
        $out = ['errors' => [], 'waits' => false, 'submitted' => false];
        if (!application::editable($userid)) {
            throw new \moodle_exception('err_noteditable', 'local_nit_tutors');
        }
        if ($card !== null) {
            $in['headline'] = $card['headline'];
            $in['subjects'] = $card['subjects'];
        }
        $send = $send && self::can_submit($userid);
        $errors = teacher_input::validate($in, $files, $userid, $send) + ($card['errors'] ?? []);
        // Taking bookings needs subjects — the ones students see: the new ones, or while a change
        // waits for review, the approved ones.
        $service = new teacher_service();
        $livesubjects = pending::applies($userid) ? $service->profile($userid)['subjects'] : (array) $in['subjects'];
        if ($card !== null && $card['available'] && !array_filter($livesubjects) && !isset($errors['subjects'])) {
            $errors['subjects'] = get_string('err_subjectsrequired', 'local_nit_lessons');
        }
        if ($errors) {
            $out['errors'] = array_map('strval', $errors);
            return $out;
        }

        $out['waits'] = teacher_input::save($userid, $in, $files);
        if ($card !== null) {
            // Bookings, price and hours save at once, next to the headline and subjects now live.
            $live = $service->profile($userid);
            try {
                $service->save($userid, $card['available'], $live['headline'], $live['subjects'], $card['hours'],
                    $card['lessonflex']);
            } catch (\moodle_exception $e) {
                $out['errors']['general'] = $e->getMessage();
                return $out;
            }
        }
        if ($send) {
            try {
                application::submit($userid);
                $out['submitted'] = true;
            } catch (\moodle_exception $e) {
                $out['errors']['general'] = $e->getMessage();
            }
        }
        return $out;
    }
}
