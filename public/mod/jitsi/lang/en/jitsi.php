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
$string['lobby_enabled_help']    = 'When on, students can open the room only while a teacher of the course (or an admin) is in it, and then wait in the meeting\'s waiting room until the teacher lets them in from inside the meeting. Teachers and admins go straight in. Applies to the rooms of a course (scheduled or not), not to private lessons.';

// Session time (the activity's settings).
$string['schedule_header']       = 'Session time';
$string['sessionstart']          = 'Starts at';
$string['sessionstart_help']     = 'Turn on to schedule the session. Every student of the course (also those who enrol later) gets a reminder before it, the room opens to them 30 minutes before the start, and the session shows on the live monitoring wall and in the lessons report. The activity stays on the course page all the time, so its recording can be watched after the session. Leave off for a room that is always open.';
$string['sessionstart_past']     = 'This time is already over. Check the day and the hour.';
$string['sessionend']            = 'Ends at';
$string['sessionend_help']       = 'After this time nobody new comes in, unless the teacher is still in the session: it stays open, and attendance keeps counting, until the teacher leaves or ends it for everyone; it then closes within a few minutes.';
$string['sessionend_beforestart'] = 'The end must be after the start.';
$string['sessionend_toolong']    = 'A session is at most 24 hours long.';
$string['sessiontime']           = 'Session time';
$string['sessiontime_text']      = '{$a->start} – {$a->end}';

// After leaving the call (view.php).
$string['ended_byteacher'] = 'You ended the session for everyone.';
$string['ended_byhost']    = 'The teacher ended the session.';
$string['ended_left']      = 'You left the session.';
$string['ended_ending']    = 'Ending the session…';
$string['ended_failed']    = 'The session could not be marked as ended. Try again from the room.';
$string['ended_recnote']   = 'If the session was recorded, the recording appears under "Session recordings" a few minutes after it ends.';
$string['ended_recfailed'] = 'Could not load the recordings right now.';

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
