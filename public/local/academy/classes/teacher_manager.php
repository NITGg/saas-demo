<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher (instructor) directory for the app.
 *
 * Faithful to the old academy's local_academy teacher API — same function names
 * (get_all_teachers / browse_teachers / get_teacher) and the same response keys —
 * so the existing web/mobile clients work unchanged.
 *
 * Difference from the old academy: this build is Moodle-native (courses model,
 * not the Flex tutoring engine), so it has no teacher-profile/subjects/hours
 * tables. Those fields are still present in every response for contract parity,
 * but with empty/default values (subjects=[], hours=[], years=[], busy_times=[],
 * rating=0, approved=1, available=1, headline/experience=''). The Moodle-native
 * fields (userid, fullname, email, phone, bio, photourl) carry real data, plus a
 * `courses` list — a superset that never breaks the old shape.
 *
 * A "teacher" = any non-deleted user holding a role with the 'teacher' or
 * 'editingteacher' archetype in at least one course. No custom tables, no core
 * modifications.
 */
class teacher_manager {

    /** Caller's web-service token, so returned image URLs are directly loadable. */
    private static $token = '';

    /** Set the caller's token so photo/image URLs are returned token-embedded. */
    public static function set_token(string $token): void {
        self::$token = $token;
    }

    /** User columns needed to build a name + user_picture + contact safely. */
    private static function user_fields(): string {
        return 'u.id, u.firstname, u.lastname, u.middlename, u.alternatename,
                u.firstnamephonetic, u.lastnamephonetic, u.picture, u.imagealt, u.email, u.phone1,
                u.description, u.descriptionformat, u.city, u.country';
    }

    /** Same columns without the "u." alias, for get_record_select. */
    private static function user_fields_plain(): string {
        return str_replace('u.', '', self::user_fields());
    }

    /** Is this user a teacher anywhere? (archetype-based, so admins are excluded.) */
    public static function is_teacher(int $userid): bool {
        global $DB;
        if (empty($userid) || isguestuser($userid)) {
            return false;
        }
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :uid AND r.archetype IN ('teacher', 'editingteacher')";
        return $DB->record_exists_sql($sql, ['uid' => $userid]);
    }

    /**
     * Admin: list all teachers with optional filters and pagination.
     * Matches the old academy shape: { total, page, perpage, teachers[] }.
     *
     * @param array $filters search, courseid, categoryid, page, perpage
     *                       (subject/year/approved/available accepted but no-ops here)
     */
    public static function get_all_teachers(array $filters = []): array {
        global $DB;

        $where  = ['u.deleted = 0', "r.archetype IN ('teacher', 'editingteacher')"];
        $params = [];

        if (!empty($filters['courseid'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                 JOIN {context} ctx ON ctx.id = raf.contextid AND ctx.contextlevel = 50
                                 JOIN {role} rf ON rf.id = raf.roleid
                                                AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                WHERE raf.userid = u.id AND ctx.instanceid = :courseid)';
            $params['courseid'] = (int) $filters['courseid'];
        }
        if (!empty($filters['categoryid'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                 JOIN {context} ctx ON ctx.id = raf.contextid AND ctx.contextlevel = 50
                                 JOIN {course} c ON c.id = ctx.instanceid AND c.category = :categoryid
                                 JOIN {role} rf ON rf.id = raf.roleid
                                                AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                WHERE raf.userid = u.id)';
            $params['categoryid'] = (int) $filters['categoryid'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where[] = '(' . $DB->sql_like('u.firstname', ':sq1', false)
                     . ' OR ' . $DB->sql_like('u.lastname', ':sq2', false)
                     . ' OR ' . $DB->sql_like('u.email', ':sq3', false) . ')';
            $params['sq1'] = $q;
            $params['sq2'] = $q;
            $params['sq3'] = $q;
        }

        $whereclause = implode(' AND ', $where);
        $basesql = "FROM {user} u
                    JOIN {role_assignments} ra ON ra.userid = u.id
                    JOIN {role} r ON r.id = ra.roleid
                   WHERE $whereclause";

        $total   = (int) $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $basesql", $params);
        $page    = max(0, (int) ($filters['page'] ?? 0));
        $perpage = min(200, max(1, (int) ($filters['perpage'] ?? 20)));

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT " . self::user_fields() . " $basesql
              ORDER BY u.lastname ASC, u.firstname ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        $teachers = [];
        foreach ($rows as $u) {
            // Admin listing keeps email (matches old get_all_teachers).
            $teachers[] = self::format_profile($u, true, false);
        }

        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'teachers' => $teachers];
    }

    /**
     * Public: browse instructors. Matches the old academy: returns a bare array
     * with email dropped. The $subject filter is accepted for signature parity
     * (the courses model has no subjects, so it does not filter).
     */
    public static function browse_teachers(string $subject = ''): array {
        global $DB;
        $sql = "SELECT DISTINCT " . self::user_fields() . "
                  FROM {user} u
                  JOIN {role_assignments} ra ON ra.userid = u.id
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE u.deleted = 0 AND u.suspended = 0
                   AND r.archetype IN ('teacher', 'editingteacher')
              ORDER BY u.lastname ASC, u.firstname ASC";
        $rows = $DB->get_records_sql($sql);

        $out = [];
        foreach ($rows as $u) {
            $out[] = self::format_profile($u, false, false);
        }
        return $out;
    }

    /** Public: a single instructor's profile + the courses they teach (email dropped). */
    public static function get_teacher(int $teacherid): array {
        global $DB;
        $u = $DB->get_record_select(
            'user',
            'id = :id AND deleted = 0',
            ['id' => $teacherid],
            self::user_fields_plain()
        );
        if (!$u || !self::is_teacher($teacherid)) {
            throw new \moodle_exception('err_teachernotfound', 'local_academy');
        }
        return self::format_profile($u, false, true);
    }

    /** Courses a teacher teaches (visible courses only). */
    public static function get_teacher_courses(int $teacherid): array {
        global $DB;

        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.summaryformat
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {role} r ON r.id = ra.roleid
                                AND r.archetype IN ('teacher', 'editingteacher')
                 WHERE ra.userid = :uid AND c.id <> :site AND c.visible = 1
              ORDER BY c.fullname ASC";
        $rows = $DB->get_records_sql($sql, ['uid' => $teacherid, 'site' => SITEID]);

        $courses = [];
        foreach ($rows as $c) {
            $context = \context_course::instance($c->id);
            $summary = '';
            if (!empty($c->summary)) {
                $summary = trim(html_to_text(
                    format_text($c->summary, $c->summaryformat, ['context' => $context, 'noclean' => true]),
                    0,
                    false
                ));
            }
            $courses[] = [
                'id'        => (int) $c->id,
                'fullname'  => format_string($c->fullname, true, ['context' => $context]),
                'shortname' => $c->shortname,
                'summary'   => $summary,
                'imageurl'  => self::course_image_url($c->id, $context),
                'url'       => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            ];
        }
        return $courses;
    }

    // ── helpers ──

    /**
     * Build the teacher view-model in the old academy's exact shape.
     *
     * @param \stdClass $u          user row (see user_fields)
     * @param bool $withemail       include the email field (admin listing) — public views drop it
     * @param bool $withcourses     append the `courses` list (single-teacher view)
     */
    private static function format_profile(\stdClass $u, bool $withemail, bool $withcourses): array {
        $bio = '';
        if (!empty($u->description)) {
            $bio = trim(html_to_text(
                format_text($u->description, $u->descriptionformat ?? FORMAT_HTML,
                    ['context' => \context_system::instance(), 'noclean' => true]),
                0,
                false
            ));
        }

        // Old academy shape — Flex-only fields kept as defaults for contract parity.
        $out = [
            'userid'     => (int) $u->id,
            'fullname'   => fullname($u),
            'email'      => $u->email ?? '',
            'phone'      => $u->phone1 ?? '',
            'headline'   => '',
            'bio'        => $bio,
            'experience' => '',
            'photourl'   => self::picture_url($u),
            'rating'     => 0,
            'approved'   => 1,
            'available'  => 1,
            'subjects'   => [],
            'years'      => [],
            'hours'      => [],
            'busy_times' => [],
            // Superset (never in the old shape, additive): quick course info.
            'coursecount' => self::course_count((int) $u->id),
        ];
        if (!$withemail) {
            unset($out['email']);
        }
        if ($withcourses) {
            $out['courses'] = self::get_teacher_courses((int) $u->id);
        }
        return $out;
    }

    /** How many visible courses this teacher teaches. */
    private static function course_count(int $userid): int {
        global $DB;
        $sql = "SELECT COUNT(DISTINCT ctx.instanceid)
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
                  JOIN {role} r ON r.id = ra.roleid AND r.archetype IN ('teacher', 'editingteacher')
                  JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1
                 WHERE ra.userid = :uid";
        return (int) $DB->count_records_sql($sql, ['uid' => $userid]);
    }

    /** Absolute profile-picture URL (real photo if uploaded, else the default). */
    private static function picture_url(\stdClass $u): string {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $up = new \user_picture($u);
        $up->size = 100;
        return ws_files::tokenize($up->get_url($page)->out(false), self::$token);
    }

    /** Course overview image URL, or '' if none. */
    private static function course_image_url(int $courseid, \context $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return ws_files::tokenize(\moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                    null, $file->get_filepath(), $file->get_filename()
                )->out(false), self::$token);
            }
        }
        return '';
    }
}
