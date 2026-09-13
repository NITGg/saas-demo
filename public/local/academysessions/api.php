<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');

header('Content-Type: application/json');

$function = optional_param('function', '', PARAM_RAW);
$token = optional_param('token', '', PARAM_TEXT);

$userid = 0;
if (!empty($token)) {
    $api = new webservice();
    try {
        $result = json_decode(json_encode($api->authenticate_user($token)), true);
        $userid = $result['user']['id'];
    } catch (Exception $e) {
        echo json_encode(['status' => 'fail', 'error' => 'Invalid token']);
        exit;
    }
}

if (empty($userid)) {
    echo json_encode(['status' => 'fail', 'error' => 'Authentication required']);
    exit;
}

use local_academysessions\session_manager;

if ($function == 'create_session') {
    $courseid = required_param('courseid', PARAM_INT);
    $title = required_param('title', PARAM_TEXT);
    $starttime = required_param('starttime', PARAM_INT);
    $studentids_raw = required_param('studentids', PARAM_TEXT);
    $meetinglink = optional_param('meetinglink', '', PARAM_URL);
    $duration = optional_param('duration', 50, PARAM_INT);
    $googlemeetid = optional_param('googlemeetid', 0, PARAM_INT);
    $jitsiid      = optional_param('jitsiid', 0, PARAM_INT);

    $context = context_course::instance($courseid);
    require_capability('local/academysessions:managesessions', $context);

    $studentids = array_map('intval', explode(',', $studentids_raw));

    $sessionid = session_manager::create_session($courseid, $userid, $title, $starttime, $studentids, $meetinglink, $duration, $googlemeetid ?: null, $jitsiid ?: null);
    echo json_encode(['status' => 'success', 'data' => ['sessionid' => $sessionid]]);

} else if ($function == 'get_teacher_sessions') {
    $courseid = optional_param('courseid', 0, PARAM_INT);

    if ($courseid) {
        $sessions = session_manager::get_sessions_for_course($courseid);
    } else {
        $sessions = session_manager::get_sessions_for_teacher($userid);
    }

    $data = array();
    foreach ($sessions as $s) {
        $students = session_manager::get_allowed_students($s->id);
        $s->students = array_values($students);
        $s->attendance_count = count(session_manager::get_attendance_report($s->id));
        $data[] = $s;
    }
    echo json_encode(['status' => 'success', 'data' => array_values($data)]);

} else if ($function == 'get_student_sessions') {
    $sessions = session_manager::get_upcoming_sessions_for_student($userid);
    $data = array();
    foreach ($sessions as $s) {
        $s->link_visible = session_manager::is_link_visible($s);
        if (!$s->link_visible) {
            $s->meeting_link = '';
        }
        $data[] = $s;
    }
    echo json_encode(['status' => 'success', 'data' => array_values($data)]);

} else if ($function == 'join_session') {
    $sessionid = required_param('sessionid', PARAM_INT);

    $session = session_manager::get_session($sessionid);
    if (!session_manager::is_student_allowed($sessionid, $userid)) {
        echo json_encode(['status' => 'fail', 'error' => 'Not allowed']);
        exit;
    }
    if (!session_manager::is_link_visible($session)) {
        echo json_encode(['status' => 'fail', 'error' => 'Session not available yet']);
        exit;
    }

    session_manager::record_attendance($sessionid, $userid);
    echo json_encode(['status' => 'success', 'data' => ['meeting_link' => $session->meeting_link]]);

} else if ($function == 'end_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = session_manager::get_session($sessionid);

    $context = context_course::instance($session->courseid);
    require_capability('local/academysessions:managesessions', $context);

    session_manager::end_session($sessionid);
    echo json_encode(['status' => 'success']);

} else if ($function == 'get_attendance') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = session_manager::get_session($sessionid);

    $context = context_course::instance($session->courseid);
    require_capability('local/academysessions:viewattendance', $context);

    $report = session_manager::get_attendance_report($sessionid);
    echo json_encode(['status' => 'success', 'data' => array_values($report)]);

} else if ($function == 'delete_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = session_manager::get_session($sessionid);

    $context = context_course::instance($session->courseid);
    require_capability('local/academysessions:managesessions', $context);

    session_manager::delete_session($sessionid);
    echo json_encode(['status' => 'success']);

} else if ($function == 'update_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = session_manager::get_session($sessionid);

    $context = context_course::instance($session->courseid);
    require_capability('local/academysessions:managesessions', $context);

    $data = array();
    $title = optional_param('title', '', PARAM_TEXT);
    $starttime = optional_param('starttime', 0, PARAM_INT);
    $meetinglink = optional_param('meetinglink', '', PARAM_URL);
    $duration = optional_param('duration', 0, PARAM_INT);

    if (!empty($title)) $data['title'] = $title;
    if ($starttime > 0) $data['start_time'] = $starttime;
    if (!empty($meetinglink)) $data['meeting_link'] = $meetinglink;
    if ($duration > 0) $data['duration'] = $duration;

    session_manager::update_session($sessionid, $data);
    echo json_encode(['status' => 'success']);

} else {
    echo json_encode(['status' => 'fail', 'error' => 'Unknown function']);
}
