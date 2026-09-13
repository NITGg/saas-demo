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
            \local_academysessions\session_manager::end_session($session->id);
            mtrace("Session {$session->id} '{$session->title}' has ended.");
        }
    }
}
