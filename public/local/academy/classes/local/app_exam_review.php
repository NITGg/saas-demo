<?php
namespace local_academy\local;

/**
 * Marks a quiz attempt taken in the Android app's exam mode on the attempt review page,
 * so teachers can tell it apart from an attempt taken in Safe Exam Browser.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class app_exam_review {

    /**
     * mod/quiz/review.php?attempt=N → an info notice when N is an exam-mode attempt.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        if ($PAGE->pagetype !== 'mod-quiz-review') {
            return;
        }
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if ($attemptid && \local_academy\quiz_manager::is_app_exam_attempt($attemptid)) {
            \core\notification::info(get_string('appexamattempt', 'local_academy'));
        }
    }
}
