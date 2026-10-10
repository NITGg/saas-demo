<?php
namespace theme_nit;

use theme_nit\local\hook_callbacks;

/**
 * Which requests render chrome-free inside the course player frame.
 *
 * @package    theme_nit
 * @covers     \theme_nit\local\hook_callbacks::in_player
 */
final class player_embed_test extends \advanced_testcase {

    protected function tearDown(): void {
        unset($_GET['nitplayer'], $_SERVER['HTTP_SEC_FETCH_DEST'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    public function test_the_parameter_marks_a_player_page(): void {
        $this->assertFalse(hook_callbacks::in_player());
        $_GET['nitplayer'] = 1;
        $this->assertTrue(hook_callbacks::in_player());
    }

    public function test_a_redirect_inside_the_frame_stays_a_player_page(): void {
        global $CFG;
        // The quiz "Attempt" form posts from view.php?…&nitplayer=1 and Moodle
        // redirects to attempt.php without the parameter.
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/quiz/view.php?id=61&nitplayer=1';
        $this->assertTrue(hook_callbacks::in_player());
    }

    public function test_other_frames_and_pages_are_not(): void {
        global $CFG;
        // A top-level page whose referrer happens to be a player page.
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/quiz/view.php?id=61&nitplayer=1';
        $this->assertFalse(hook_callbacks::in_player());
        // A frame opened from a page that is not the player.
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/quiz/view.php?id=61';
        $this->assertFalse(hook_callbacks::in_player());
        // Another site claiming to be the player.
        $_SERVER['HTTP_REFERER'] = 'https://evil.example/mod/quiz/view.php?nitplayer=1';
        $this->assertFalse(hook_callbacks::in_player());
    }
}
