<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Globally-unique Jitsi room name for an activity. On the shared SaaS Jitsi server
 * EVERY academy would otherwise generate `academy_jitsi_<cmid>_<hash(jitsiid.cmid)>`,
 * and cm/jitsi ids repeat across academies — so two tenants could land in the SAME
 * room. Salt the hash with this site's wwwroot so rooms are unique per academy.
 *
 * @param stdClass $jitsi the jitsi instance row (needs ->id)
 * @param cm_info|stdClass $cm the course module (needs ->id)
 * @return string
 */
function jitsi_room_name($jitsi, $cm): string {
    global $CFG;
    $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: $CFG->wwwroot;
    // Recoverable academy slug: the Jibri finalize script parses it out of the room
    // name to notify THIS academy's record_notify.php (rooms are shared across
    // tenants on one Jitsi server). Prefer the provisioned slug; else the first
    // label of the host (test.academy2026.nitg-eg.com -> test). Slugs are
    // [a-z0-9-] (no underscore), so "_" stays a safe field separator.
    $slug = (string) get_config('theme_nit', 'academyslug');
    if ($slug === '') {
        $slug = explode('.', $host)[0];
    }
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug)) ?: 'academy';
    $hash = substr(md5($host . '|' . $jitsi->id . '|' . $cm->id), 0, 8);
    return 'nit_' . $slug . '_' . $cm->id . '_' . $hash;
}

/**
 * Activity "More" menu: the room's attendance log, for its hosts.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $jitsinode
 */
function jitsi_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $jitsinode) {
    global $USER;
    $cm = $settingsnav->get_page()->cm;
    if (!$cm || !\mod_jitsi\local\presence::is_moderator($cm, (int) $USER->id)) {
        return;
    }
    $jitsinode->add(get_string('attendancelog', 'jitsi'), new moodle_url('/mod/jitsi/attendance.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING, null, 'jitsi_attendancelog', new pix_icon('i/report', ''));
}

/**
 * The names of a room's teachers, to pin on a student's screen: the session's teacher
 * for a lesson room; for a course room, the users who host it (mod/jitsi:moderate).
 *
 * @param cm_info|stdClass $cm
 * @param stdClass|null $session the linked live session
 * @return string[]
 */
function jitsi_teacher_names($cm, $session): array {
    if ($session) {
        $teacher = core_user::get_user((int) $session->teacherid);
        return $teacher ? [fullname($teacher)] : [];
    }
    $fields = 'u.id, ' . \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $users = get_users_by_capability(context_module::instance($cm->id), 'mod/jitsi:moderate', $fields);
    // The course's own teachers (enrolled) first, then admins / managers: a student's
    // screen pins the first of these who is in the call.
    $coursecontext = context_course::instance($cm->course);
    usort($users, fn($a, $b) => (int) is_enrolled($coursecontext, $b) <=> (int) is_enrolled($coursecontext, $a));
    return array_values(array_unique(array_map('fullname', $users)));
}

/**
 * Does the room page show its "Session recordings" tab? The host always; a student of a
 * linked session once it is over (local_academysessions\recordings::visible_to_students()),
 * of a standalone room always.
 *
 * @param bool $ishost
 * @param stdClass|null $session the linked live session
 * @return bool
 */
function jitsi_show_recordings_tab(bool $ishost, $session): bool {
    if ($ishost || !class_exists('\local_academysessions\recordings')) {
        return true;
    }
    return \local_academysessions\recordings::visible_to_students($session ?: null);
}

/**
 * The step before the call (view.php without join=1): opening the activity only shows the
 * room, so someone browsing the course never lands in the meeting or starts its recording.
 *
 * @param cm_info|stdClass $cm
 * @param bool $autorecord the recording starts when this user joins
 * @return string HTML
 */
function jitsi_prejoin_html($cm, bool $autorecord): string {
    $text = get_string('prejoin_text', 'jitsi');
    if ($autorecord) {
        $text .= ' ' . get_string('prejoin_autorecord', 'jitsi');
    }
    return html_writer::div(
        html_writer::div(get_string('prejoin_title', 'jitsi'), '', ['style' => 'font-size:20px;font-weight:600;'])
        . html_writer::div($text, '', ['style' => 'max-width:480px;opacity:.85;'])
        . html_writer::link(new moodle_url('/mod/jitsi/view.php', ['id' => $cm->id, 'join' => 1]),
            get_string('joinsession', 'jitsi'),
            ['class' => 'btn btn-primary btn-lg', 'style' => 'border-radius:24px;padding:10px 36px;']),
        '', ['id' => 'jitsi-prejoin', 'style' => 'height:100%;display:flex;flex-direction:column;align-items:center;'
            . 'justify-content:center;gap:16px;text-align:center;padding:24px;background:#1b1f24;color:#fff;']);
}

/**
 * The Jitsi live-session activity is a Professional-tier feature. Returns true when
 * the academy's licence includes 'jitsi' (local_license already defines this feature
 * and grants it to the professional tier; has_feature() is true when enforcement is
 * off, so unlicensed/dev academies keep working).
 *
 * @return bool
 */
function jitsi_feature_enabled(): bool {
    if (!class_exists('\local_license\license')) {
        return true; // licence plugin absent → don't block.
    }
    return \local_license\license::has_feature('jitsi');
}

function jitsi_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

function jitsi_add_instance($data, $mform = null) {
    global $DB;
    if (!jitsi_feature_enabled()) {
        throw new \moodle_exception('featurelocked', 'jitsi');
    }
    $data->timemodified = time();
    return $DB->insert_record('jitsi', $data);
}

function jitsi_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('jitsi', $data);
}

function jitsi_delete_instance($id) {
    global $DB;
    if (!$DB->get_record('jitsi', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('jitsi_presence', ['jitsiid' => $id]);
    $DB->delete_records('jitsi', ['id' => $id]);
    return true;
}

function jitsi_get_coursemodule_info($coursemodule) {
    global $DB;
    $jitsi = $DB->get_record('jitsi', ['id' => $coursemodule->instance], 'id, name, intro, introformat');
    if (!$jitsi) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $jitsi->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('jitsi', $jitsi, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Dynamically hide Jitsi activity from students not enrolled in the linked session.
 */
function jitsi_cm_info_dynamic(cm_info $cm) {
    global $DB, $USER;

    if (has_capability('mod/jitsi:moderate', $cm->context)) {
        return;
    }

    $session = $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]);
    if (!$session) {
        return;
    }

    $allowed = $DB->record_exists('academy_session_students', [
        'sessionid' => $session->id,
        'userid'    => $USER->id,
    ]);

    if (!$allowed) {
        $cm->set_user_visible(false);
        $cm->set_available(false);
        return;
    }

    $now          = time();
    $visible_from = $session->start_time - 1800;

    if ($now < $visible_from) {
        $cm->set_available(false, false, '');
    }
}
