<?php
namespace local_academy;

use local_academy\local\site_defaults;

/**
 * Unit tests for {@see \local_academy\local\site_defaults} — the site settings a
 * fresh server needs to behave like dev.
 *
 * @package    local_academy
 * @covers     \local_academy\local\site_defaults
 */
final class site_defaults_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_apply_turns_a_fresh_server_into_the_academy_setup(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filterlib.php');
        // A fresh Moodle: multilang2 off, dashboard as home page, log-in forced.
        filter_set_global_state('multilang2', TEXTFILTER_DISABLED);
        filter_set_applies_to_strings('multilang2', false);
        set_config('filterall', 0);
        set_config('defaulthomepage', HOMEPAGE_MY);
        set_config('forcelogin', 1);
        set_config('enablemyhome', 0);
        set_config('timezone', 'Europe/London');

        site_defaults::apply();

        $this->assertEquals(TEXTFILTER_ON,
            $DB->get_field('filter_active', 'active', ['filter' => 'multilang2', 'contextid' => SYSCONTEXTID]));
        $this->assertContains('multilang2', explode(',', get_config('core', 'stringfilters')));
        $this->assertEquals(1, get_config('core', 'filterall'));
        $this->assertEquals(HOMEPAGE_SITE, get_config('core', 'defaulthomepage'));
        $this->assertEquals(0, get_config('core', 'forcelogin'));
        $this->assertEquals(1, get_config('core', 'enablemyhome'));
        // Lesson times are shown and checked in Cairo time.
        $this->assertSame('Africa/Cairo', get_config('core', 'timezone'));
        $this->assertSame('Africa/Cairo', \core_date::get_user_timezone($this->getDataGenerator()->create_user()));
        // {mlang} text now renders as one language.
        \filter_manager::reset_caches();
        $this->assertSame('Cairo', format_string('{mlang ar}القاهرة{mlang}{mlang en}Cairo{mlang}'));
    }

    public function test_email_confirmation_turns_on_moodle_email_self_registration(): void {
        set_config('registerauth', '');
        set_config('auth', '');
        $this->assertFalse(\local_academy\local\registration::confirmation_required());

        site_defaults::email_confirmation();

        $this->assertSame('email', get_config('core', 'registerauth'));
        $this->assertTrue(is_enabled_auth('email'));
        $this->assertTrue(\local_academy\local\registration::confirmation_required());
    }
}
