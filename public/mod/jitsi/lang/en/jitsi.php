<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Jitsi Session';
$string['modulenameplural'] = 'Jitsi Sessions';
$string['pluginname'] = 'Jitsi Session';
$string['pluginadministration'] = 'Jitsi Session administration';
$string['jitsi:addinstance'] = 'Add a new Jitsi session';
$string['jitsi:view'] = 'View Jitsi session';
$string['jitsi:moderate'] = 'Moderate Jitsi session';
$string['sessionname'] = 'Session name';
$string['nonewmodules'] = 'No Jitsi sessions';
$string['privacy:metadata'] = 'The Jitsi Session module does not store any personal data.';

// View page strings.
$string['notallowed']      = 'You are not enrolled in this session.';
$string['sessionopening']  = 'The session will open in {$a} minute(s).';
$string['waitingforteacher'] = 'Waiting for the teacher to start the meeting.';
$string['teacheraway'] = 'The teacher is not in the call right now. You will see them here when they are back.';
$string['sessionended']    = 'This session has ended.';
$string['roomunavailable'] = 'This room is not available to you.';
$string['youarehost']      = 'You are the host';
$string['featurelocked']   = 'Live sessions (Jitsi) are available on the Professional plan. Please upgrade your academy to use this activity.';
$string['recording']       = 'Recording';
$string['recordings']      = 'Session recordings';
$string['norecordings']    = 'No recordings yet. When the host records a session it appears here after processing.';
$string['recordingprocessing'] = 'Processing…';
$string['tab_video']       = 'Video Session';
$string['tab_whiteboard']  = 'Whiteboard';
$string['recordings']      = 'Session Recordings';
$string['norecordings']    = 'No recordings available yet.';
$string['recording']       = 'Recording';
$string['recprocessing']   = 'Recording is being processed, please check back later.';
$string['recnotavailable'] = 'Recording is not available at this time.';
$string['recstorage']      = 'Storage location';

// Security options.
$string['security_header']       = 'Security Options';
$string['roompassword']          = 'Room password';
$string['roompassword_help']     = 'If set, participants must enter this password to join the session. Leave blank for no password.';
$string['lobby_enabled']         = 'Students wait for the teacher to let them in';
$string['lobby_enabled_help']    = 'When on, students can open the room only while a teacher of the course (or an admin) is in it, and then wait in the meeting\'s waiting room until the teacher lets them in from inside the meeting. Teachers and admins go straight in. Applies to rooms of a course, not to scheduled live sessions.';

// The room's attendance log.
$string['attendancelog'] = 'Attendance log';
$string['task_closestalepresence'] = 'Close room attendance of people who left without saying so';
$string['log_day'] = 'Day';
$string['log_download'] = 'Download CSV';
$string['log_empty'] = 'Nobody has been in this room yet.';
$string['log_people'] = 'People';
$string['log_students'] = 'Students';
$string['log_hosts'] = 'Teachers and admins';
$string['log_visits'] = 'Times in';
$string['log_role'] = 'Role';
$string['log_host'] = 'Teacher / admin';
$string['log_student'] = 'Student';
$string['log_joined'] = 'Joined';
$string['log_left'] = 'Left';
$string['log_leftat'] = 'Left at';
$string['log_minutes'] = 'Minutes';
$string['log_inside'] = 'In the call';
$string['log_stillin'] = 'still in';
$string['log_summary'] = 'First in {$a->first} · {$a->visits}× · {$a->minutes} min';

// The step before entering the room (view.php without join=1).
$string['joinsession'] = 'Join the session';
$string['prejoin_title'] = 'The session room is ready';
$string['prejoin_text'] = 'You enter the room, and your camera and microphone are used, only after you press "Join the session".';
$string['prejoin_autorecord'] = 'The session recording starts automatically when you join.';
$string['waitingadmit'] = 'You asked to join. Waiting for the teacher to let you in…';
// The video tab while a student waits for the teacher (view.php).
$string['waiting_title'] = 'The teacher has not started the session yet.';
$string['waiting_next_join'] = 'You will go in automatically when they join.';
$string['waiting_next_button'] = 'The "Join the session" button appears here when they join.';
$string['waiting_next_admit'] = 'Then the teacher lets you in.';
$string['waiting_title_join'] = 'The teacher is not in the session right now.';

// The room's own controls (templates/dock.mustache).
$string['dock_blur'] = 'Blur my background';
$string['dock_camera'] = 'Camera';
$string['dock_chat'] = 'Chat';
$string['dock_device_camera'] = 'Camera';
$string['dock_device_mic'] = 'Microphone';
$string['dock_device_speaker'] = 'Speaker';
$string['dock_endall'] = 'End the session for everyone';
$string['dock_fullscreen'] = 'Full screen';
$string['dock_hand'] = 'Raise hand';
$string['dock_leave'] = 'Leave';
$string['dock_live'] = 'Live';
$string['dock_mic'] = 'Microphone';
$string['dock_muteall'] = 'Mute all';
$string['dock_noise'] = 'Noise suppression';
$string['dock_people'] = 'Participants';
$string['dock_rec'] = 'Recording';
$string['dock_record'] = 'Record';
$string['dock_screen'] = 'Share screen';
$string['dock_settings'] = 'Settings';
$string['dock_tile'] = 'Grid view';
