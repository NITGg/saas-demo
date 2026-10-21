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

namespace local_nit_lessons;

use local_nit_lessons\local\push_defaults;

/**
 * Lesson notifications reach the phone: the FCM push output is on for lessonupdate and
 * instantoffer, on a new site (db/messages.php) and on an existing one (upgrade).
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_lessons\local\push_defaults
 */
final class push_defaults_test extends \advanced_testcase {
    /** @var string */
    const LESSON = 'message_provider_local_nit_lessons_lessonupdate_enabled';
    /** @var string */
    const OFFER = 'message_provider_local_nit_lessons_instantoffer_enabled';

    public function test_add_keeps_existing_outputs_and_respects_none(): void {
        $this->assertSame('popup,email,fcm', push_defaults::add('popup,email', ['fcm']));
        $this->assertSame('popup,fcm,airnotifier', push_defaults::add('popup,fcm', ['fcm', 'airnotifier']));
        $this->assertNull(push_defaults::add('popup,fcm', ['fcm']));
        $this->assertNull(push_defaults::add('none', ['fcm']), 'A user who turned it off stays off.');
        $this->assertSame('fcm', push_defaults::add('', ['fcm']));
    }

    public function test_new_site_has_push_on_by_default(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->record_exists('message_processors', ['name' => 'fcm'])) {
            $this->markTestSkipped('message_fcm is not installed.');
        }
        foreach ([self::LESSON, self::OFFER] as $name) {
            $this->assertContains('fcm', explode(',', (string) get_config('message', $name)), $name);
        }
    }

    public function test_upgrade_turns_push_on_for_existing_site_and_users(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->record_exists('message_processors', ['name' => 'fcm'])) {
            $this->markTestSkipped('message_fcm is not installed.');
        }
        // As installed before: bell (+ email), no push.
        set_config(self::LESSON, 'popup,email', 'message');
        set_config(self::OFFER, 'popup', 'message');
        set_config('fcm_provider_local_nit_lessons_lessonupdate_locked', 1, 'message');
        $gen = $this->getDataGenerator();
        $custom = $gen->create_user();
        $off = $gen->create_user();
        set_user_preference(self::LESSON, 'popup', $custom);
        set_user_preference(self::LESSON, 'none', $off);

        push_defaults::enable();

        $this->assertContains('fcm', explode(',', (string) get_config('message', self::LESSON)));
        $this->assertStringStartsWith('popup,email', (string) get_config('message', self::LESSON));
        $this->assertContains('fcm', explode(',', (string) get_config('message', self::OFFER)));
        $this->assertSame('0', (string) get_config('message', 'fcm_provider_local_nit_lessons_lessonupdate_locked'));
        $pref = fn($user) => $DB->get_field('user_preferences', 'value', ['userid' => $user->id, 'name' => self::LESSON]);
        $this->assertContains('fcm', explode(',', (string) $pref($custom)));
        $this->assertSame('none', $pref($off));

        // Again: nothing doubles.
        push_defaults::enable();
        $list = explode(',', (string) get_config('message', self::LESSON));
        $this->assertCount(count(array_unique($list)), $list);
    }
}
