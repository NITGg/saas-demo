<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_nit_devices;

/**
 * The device limit.
 *
 * @package    local_nit_devices
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_devices\manager
 */
final class manager_test extends \advanced_testcase {

    /** @var \stdClass the mobile service */
    private \stdClass $service;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        set_config('enabled', 1, 'local_nit_devices');
        set_config('maxdevices', 2, 'local_nit_devices');
        set_config('policy', manager::POLICY_BLOCK, 'local_nit_devices');
        $DB->set_field('external_services', 'enabled', 1, ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        $this->service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
    }

    public function test_nothing_is_recorded_while_off(): void {
        set_config('enabled', 0, 'local_nit_devices');
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(manager::register((int) $user->id, manager::WEB, 'a'));
        $this->assertSame([], manager::devices((int) $user->id));
    }

    public function test_block_refuses_a_new_device_but_not_a_known_one(): void {
        $user = $this->getDataGenerator()->create_user();
        manager::register((int) $user->id, manager::WEB, 'browser1');
        manager::register((int) $user->id, manager::MOBILE, 'phone1');
        // The same browser signing in again is not a new device.
        manager::register((int) $user->id, manager::WEB, 'browser1');
        $this->assertCount(2, manager::devices((int) $user->id));

        $this->expectException(device_limit_exception::class);
        manager::register((int) $user->id, manager::MOBILE, 'phone2');
    }

    public function test_replace_signs_the_oldest_device_out(): void {
        global $DB;
        set_config('policy', manager::POLICY_REPLACE, 'local_nit_devices');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $old = manager::issue_token($user, $this->service, 'phone-old');
        $DB->set_field('local_nit_devices', 'lastseen', time() - DAYSECS, ['deviceid' => 'phone-old']);
        manager::issue_token($user, $this->service, 'phone-mid');
        manager::issue_token($user, $this->service, 'phone-new');

        $ids = array_column(manager::devices((int) $user->id), 'deviceid');
        $this->assertEqualsCanonicalizing(['phone-mid', 'phone-new'], $ids);
        $this->assertFalse($DB->record_exists('external_tokens', ['id' => $old->id]), 'the oldest phone is signed out');
    }

    public function test_every_install_gets_its_own_token_and_keeps_it(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $a = manager::issue_token($user, $this->service, 'phone-a', 'Galaxy A51', 'android');
        $b = manager::issue_token($user, $this->service, 'phone-b');
        $this->assertNotSame($a->token, $b->token, 'core would give both phones the same token');
        $this->assertSame($a->token, manager::issue_token($user, $this->service, 'phone-a')->token, 'same install, same token');

        $device = $this->devicenamed((int) $user->id, 'phone-a');
        $this->assertSame('Galaxy A51', $device->name);
        $this->assertSame((int) $a->id, (int) $device->tokenid);
    }

    public function test_removing_a_device_deletes_its_token_only(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $a = manager::issue_token($user, $this->service, 'phone-a');
        $b = manager::issue_token($user, $this->service, 'phone-b');

        manager::revoke($this->devicenamed((int) $user->id, 'phone-a'));
        $this->assertFalse($DB->record_exists('external_tokens', ['id' => $a->id]));
        $this->assertTrue($DB->record_exists('external_tokens', ['id' => $b->id]));

        $this->assertSame(1, manager::reset((int) $user->id));
        $this->assertFalse($DB->record_exists('external_tokens', ['id' => $b->id]));
        $this->assertSame([], manager::devices((int) $user->id));
    }

    public function test_admins_and_teachers_have_no_limit(): void {
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $course = $gen->create_course();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->assertTrue(manager::applies_to((int) $student->id));
        $this->assertFalse(manager::applies_to((int) $teacher->id));
        $this->assertFalse(manager::applies_to((int) get_admin()->id));

        foreach (['a', 'b', 'c'] as $id) {
            manager::register((int) $teacher->id, manager::WEB, $id);
        }
        $this->assertCount(3, manager::devices((int) $teacher->id), 'recorded, never refused');
    }

    public function test_old_app_phones_count_as_one_device(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        manager::register((int) $user->id, manager::WEB, 'browser1');
        // The old app signs in through core: its token is bound to the "legacy-app" device.
        $token = \core_external\util::generate_token_for_current_user($this->service);
        \core_external\util::log_token_request($token);
        $legacy = $this->devicenamed((int) $user->id, manager::LEGACY_ID);
        $this->assertSame((int) $token->id, (int) $legacy->tokenid);

        // A second old-app phone gets the same token from core: still one device.
        \core_external\util::log_token_request($token);
        $this->assertCount(2, manager::devices((int) $user->id));
        $this->assertTrue($DB->record_exists('external_tokens', ['id' => $token->id]));
    }

    public function test_describe_agent(): void {
        $this->assertSame(['name' => 'Chrome — Windows', 'platform' => 'Windows'], manager::describe_agent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'));
        $this->assertSame(['name' => 'Edge — Windows', 'platform' => 'Windows'], manager::describe_agent(
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0'));
        $this->assertSame(['name' => 'Safari — iPhone', 'platform' => 'iPhone'], manager::describe_agent(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Browser', manager::describe_agent('curl/8.0')['name']);
    }

    /**
     * One device of the user by its device id.
     *
     * @param int $userid
     * @param string $deviceid
     * @return \stdClass
     */
    private function devicenamed(int $userid, string $deviceid): \stdClass {
        global $DB;
        return $DB->get_record('local_nit_devices', ['userid' => $userid, 'deviceid' => $deviceid], '*', MUST_EXIST);
    }
}
