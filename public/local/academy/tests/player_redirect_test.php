<?php
namespace local_academy;

/**
 * Unit tests for {@see \local_academy\player::redirect_for_view()} — a lesson's own page
 * opened on its own goes to the course player, so a lesson always opens in one frame.
 *
 * @package    local_academy
 * @covers     \local_academy\player::redirect_for_view
 */
final class player_redirect_test extends \advanced_testcase {

    /** The local URL redirect_for_view() gives, or null. */
    private function to(\cm_info $cm, ?array $query = null, string $method = 'GET', string $dest = 'document',
            ?string $pagetype = null): ?string {
        $url = player::redirect_for_view($cm, $pagetype ?? 'mod-' . $cm->modname . '-view',
            $query ?? ['id' => $cm->id], $method, $dest);
        return $url ? $url->out_as_local_url(false) : null;
    }

    /** A page activity in a course (or in the given course), as the admin sees it. */
    private function cm(?int $courseid = null): \cm_info {
        $gen = $this->getDataGenerator();
        $courseid = $courseid ?? (int) $gen->create_course()->id;
        $page = $gen->create_module('page', ['course' => $courseid]);
        return get_fast_modinfo($courseid)->get_cm($page->cmid);
    }

    public function test_a_lesson_page_opens_in_the_player(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cm = $this->cm();
        $this->assertSame('/local/academy/player.php?cmid=' . $cm->id, $this->to($cm));
        // Browsers that send no Sec-Fetch-Dest.
        $this->assertSame('/local/academy/player.php?cmid=' . $cm->id, $this->to($cm, null, 'GET', ''));
    }

    public function test_pages_that_stay(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cm = $this->cm();
        $this->assertNull($this->to($cm, null, 'POST'), 'a form posting back');
        $this->assertNull($this->to($cm, null, 'GET', 'iframe'), 'inside a frame (the player itself)');
        $this->assertNull($this->to($cm, ['id' => $cm->id, 'action' => 'grading']), 'the page with its own options');
        $this->assertNull($this->to($cm, ['id' => $cm->id, 'nitplayer' => 1]), 'the player\'s frame');
        $this->assertNull($this->to($cm, null, 'GET', 'document', 'mod-page-edit'), 'another page of the activity');
    }

    public function test_private_lessons_and_the_front_page_have_no_player(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $cm = $this->cm();
        set_config('lessons_courseid', $cm->course, 'local_nit_lessons');
        $this->assertNull($this->to($cm), 'local_nit_lessons\' course');
        $this->assertNull($this->to($this->cm(SITEID)), 'front page');
    }

    public function test_a_lesson_the_user_cannot_see_stays(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $this->assertNull($this->to(get_fast_modinfo($course)->get_cm($page->cmid)));
    }
}
