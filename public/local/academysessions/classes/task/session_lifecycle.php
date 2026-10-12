<?php
namespace local_academysessions\task;

defined('MOODLE_INTERNAL') || die();

class session_lifecycle extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('lifecycle_task', 'local_academysessions');
    }

    public function execute() {
        global $DB;
        $now = time();

        // Course sessions follow their course: a student enrolled since the last run is
        // on the list (reminders, monitoring), one who left is off it.
        $course = $DB->get_records_select('academy_live_sessions',
            "audience = :audience AND status IN ('scheduled', 'live')",
            ['audience' => \local_academysessions\session_manager::AUDIENCE_COURSE]);
        foreach ($course as $session) {
            \local_academysessions\session_manager::sync_course_students($session);
        }

        $scheduled = $DB->get_records_select('academy_live_sessions',
            "status = 'scheduled' AND start_time <= :now",
            array('now' => $now)
        );
        foreach ($scheduled as $session) {
            $session->status = 'live';
            $session->timemodified = $now;
            $DB->update_record('academy_live_sessions', $session);
            mtrace("Session {$session->id} '{$session->title}' is now live.");
        }

        $live = $DB->get_records_select('academy_live_sessions',
            "status = 'live' AND (start_time + (duration * 60)) <= :now",
            array('now' => $now)
        );
        foreach ($live as $session) {
            \local_academysessions\session_manager::end_session($session->id, 'schedule');
            mtrace("Session {$session->id} '{$session->title}' has ended.");
        }
    }
}
