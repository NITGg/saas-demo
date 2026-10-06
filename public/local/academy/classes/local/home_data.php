<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_academy\local;

/**
 * Data for the home page HTML blocks (served as JSON by local/academy/homedata.php).
 *
 * The blocks themselves are plain HTML in nit_section blocks
 * (theme/nit/blocks/templates/bassthalk); their scripts fetch this data.
 *
 * Years are course categories: a year filter offers every visible category
 * ({id, name}), and a course carries `years` — the ids of its category and of
 * that category's parents — so choosing a parent category also shows the
 * courses of its sub-categories.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home_data {

    /** Most courses the "كورسات مختارة" carousel carries (every visible course, filtered by year on the page). */
    public const SELECTED_LIMIT = 200;

    /** Most courses the suggested-lessons slider carries. */
    public const LESSONS_LIMIT = 30;

    /**
     * "كورسات مختارة": the years for the filter and every visible course (the
     * page filters them by year). The "is-special" courses go to the suggested
     * lessons section instead ({@see lessons()}).
     *
     * @return array{years:array<int, array{id:int, name:string}>, courses:array<int, array>}
     */
    public static function selected(): array {
        return [
            'years' => self::year_options(),
            'courses' => self::selected_courses(),
        ];
    }

    /**
     * Every visible course, in the site's course order, as card data.
     *
     * @return array<int, array{id:int, fullname:string, url:string, image:string, year:string, years:int[], teachers:int, lessons:int}>
     */
    public static function selected_courses(): array {
        global $DB;

        $courses = $DB->get_records_select('course', 'visible = 1 AND id <> :siteid', ['siteid' => SITEID],
            'sortorder', '*', 0, self::SELECTED_LIMIT);

        $years = self::years_by_category();
        $teacherroles = self::teacher_roles();
        $out = [];
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            $year = $years[(int) $course->category] ?? null;
            $out[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'image' => $image ? (string) $image : '',
                'year' => $year ? $year['name'] : '',
                'years' => $year ? $year['ids'] : [],
                'teachers' => $teacherroles ? (int) count_role_users($teacherroles, $context) : 0,
                'lessons' => $DB->count_records_select('course_modules',
                    'course = :course AND visible = 1 AND deletioninprogress = 0', ['course' => $course->id]),
            ];
        }
        return $out;
    }

    /**
     * The subjects section of the home page (bassthalk.com's subject cards): the
     * years for the filter and one card per subject of a year — the visible
     * courses with the same category (year) and the same course field "Subject",
     * with the number of their teachers and courses. A card opens the subject
     * page (local/academy/subject.php). Courses without a subject are left out.
     *
     * @return array{years:array<int, array{id:int, name:string}>, subjects:array<int, array>}
     */
    public static function subjects(): array {
        global $DB;

        $courses = $DB->get_records_select('course', 'visible = 1 AND id <> :siteid', ['siteid' => SITEID],
            'sortorder', 'id, category');
        $subjectof = course_fields::subjects_of(array_keys($courses));
        $options = course_fields::subject_options();
        $years = self::years_by_category();

        // Year (in the category tree's order) → subject (in the list's order) → courses.
        $groups = [];
        foreach ($courses as $course) {
            $subject = $subjectof[(int) $course->id] ?? 0;
            $catid = (int) $course->category;
            if (!isset($options[$subject - 1], $years[$catid])) {
                continue;
            }
            $groups[$catid][$subject][] = (int) $course->id;
        }
        $order = array_flip(array_keys($years));
        uksort($groups, static fn(int $a, int $b): int => $order[$a] <=> $order[$b]);

        $out = [];
        foreach ($groups as $catid => $bysubject) {
            ksort($bysubject);
            foreach ($bysubject as $subject => $courseids) {
                $out[] = [
                    'fullname' => format_string($options[$subject - 1], true, ['escape' => false]),
                    'url' => subject_page::url($catid, $subject)->out(false),
                    'year' => self::short_year($years[$catid]['leaf']),
                    'years' => $years[$catid]['ids'],
                    'teachers' => count(self::teacher_ids($courseids)),
                    'courses' => count($courseids),
                ];
            }
        }
        return ['years' => self::year_options(), 'subjects' => $out];
    }

    /**
     * The teachers of these courses (users with a teacher / editing-teacher role,
     * not site admins), in name order.
     *
     * @param int[] $courseids
     * @return int[] user ids
     */
    public static function teacher_ids(array $courseids): array {
        global $DB;
        $roleids = self::teacher_roles();
        if (!$courseids || !$roleids) {
            return [];
        }
        [$rsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'r');
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE ra.roleid $rsql AND ctx.instanceid $csql
           ORDER BY u.firstname, u.lastname, u.id",
            $params + $cparams + ['ctxlevel' => CONTEXT_COURSE]
        );
        // Site admins are enrolled as teacher in every course they create
        // (\local_academy\observer::course_created) — they are not teachers.
        return array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => !is_siteadmin($id)));
    }

    /**
     * Ids of the visible courses whose "is-special" field is ticked.
     *
     * @return int[]
     */
    public static function special_course_ids(): array {
        global $DB;
        $fieldids = self::course_field_ids([course_fields::SPECIAL]);
        if (empty($fieldids[course_fields::SPECIAL])) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT c.id
               FROM {course} c
               JOIN {customfield_data} d ON d.instanceid = c.id AND d.fieldid = :fieldid
              WHERE d.intvalue = 1 AND c.visible = 1 AND c.id <> :siteid",
            ['fieldid' => $fieldids[course_fields::SPECIAL], 'siteid' => SITEID]));
    }

    /**
     * "المدرسين عندنا": the filter lists, the visitor's own study details (to
     * pre-select them) and every teacher with the year / study system / division
     * of each course they teach — the page filters these without reloading.
     *
     * A teacher = a user (not a site admin) with a teacher or editing-teacher role in a visible course.
     *
     * @return array{years:array, systems:array, me:?array, teachers:array<int, array>}
     */
    public static function teachers(): array {
        global $DB, $PAGE;

        $structure = academic_structure::get();
        $roleids = self::teacher_roles();
        $teachers = [];
        if ($roleids) {
            [$rsql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
            $params += ['ctxlevel' => CONTEXT_COURSE, 'siteid' => SITEID];
            $rs = $DB->get_recordset_sql(
                "SELECT DISTINCT ra.userid, c.id AS courseid, c.category
                   FROM {role_assignments} ra
                   JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
                   JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1 AND c.id <> :siteid
                   JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
                  WHERE ra.roleid $rsql",
                $params
            );
            $courses = [];
            $categories = [];
            foreach ($rs as $row) {
                // Site admins are enrolled as teacher in every course they create
                // (\local_academy\observer::course_created) — they are not teachers.
                if (is_siteadmin((int) $row->userid)) {
                    continue;
                }
                $courses[(int) $row->userid][] = (int) $row->courseid;
                $categories[(int) $row->courseid] = (int) $row->category;
            }
            $rs->close();

            if ($courses) {
                $answers = self::course_answers(array_keys($categories));
                $years = self::years_by_category();
                [$usql, $uparams] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
                $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
                $users = $DB->get_records_sql("SELECT $fields FROM {user} u WHERE u.id $usql", $uparams);
                // Approved learner rating (local_nit_reviews); the card hides it at 0 reviews.
                $ratings = class_exists('\local_nit_reviews\api')
                    ? \local_nit_reviews\api::get_teacher_aggregates(array_keys($users)) : [];
                foreach ($users as $user) {
                    $picture = new \user_picture($user);
                    $picture->size = 512;
                    $values = user_fields::values((int) $user->id);
                    $teachers[] = [
                        'id' => (int) $user->id,
                        'name' => fullname($user),
                        'photo' => $picture->get_url($PAGE)->out(false),
                        'title' => format_string($values['teachertitle'] ?? '', true, ['escape' => false]), // May carry {mlang}.
                        'url' => (new \moodle_url('/local/academy/teacher.php', ['id' => $user->id]))->out(false),
                        'rating' => isset($ratings[$user->id]) ? format_float($ratings[$user->id]->avg, 1) : '',
                        'ratingcount' => isset($ratings[$user->id]) ? (int) $ratings[$user->id]->count : 0,
                        'courses' => array_map(static fn(int $cid): array => [
                            'years' => $years[$categories[$cid]]['ids'] ?? [],
                            'system' => $answers[$cid]['system'],
                            'division' => $answers[$cid]['division'],
                        ], $courses[(int) $user->id]),
                    ];
                }
                \core_collator::asort_array_of_arrays_by_key($teachers, 'name');
                $teachers = array_values($teachers);
            }
        }

        return ['years' => self::year_options(), 'systems' => self::system_options($structure), 'me' => self::me(), 'teachers' => $teachers];
    }

    /**
     * "المحاضرات المقترحة": every "is-special" course, newest first, for every
     * visitor (whatever their year, division or enrolments). While no course is
     * ticked: the newest visible courses — for a student whose year is set, the
     * courses of that year (its category or a sub-category), and of their
     * division when the course has one.
     *
     * @return array{all:string, courses:array<int, array>}
     */
    public static function lessons(): array {
        global $DB;

        $special = self::special_course_ids();
        if ($special) {
            $courses = $DB->get_records_list('course', 'id', $special, 'timecreated DESC, id DESC');
        } else {
            $courses = $DB->get_records_select('course', 'visible = 1 AND id <> :siteid', ['siteid' => SITEID],
                'timecreated DESC, id DESC', '*', 0, 200);
        }
        $answers = $courses ? self::course_answers(array_keys($courses)) : [];
        $years = self::years_by_category();
        $me = self::me();

        $out = [];
        foreach ($courses as $course) {
            $year = $years[(int) $course->category] ?? null;
            $answer = $answers[(int) $course->id];
            if (!$special && $me && $me['year']) {
                if (!$year || !in_array($me['year'], $year['ids'], true)) {
                    continue;
                }
                if ($answer['division'] !== '' && $me['division'] !== '' && $answer['division'] !== $me['division']) {
                    continue;
                }
            }
            $out[] = self::course_card($course, $year ? $year['leaf'] : '');
            if (count($out) >= self::LESSONS_LIMIT) {
                break;
            }
        }
        return ['all' => (new \moodle_url('/course/index.php'))->out(false), 'courses' => $out];
    }

    /**
     * One course as the bassthalk course card draws it (the home page's
     * "المحاضرات المقترحة" slider and the teacher page): picture, name, short
     * year, price label, plain-text description, dates and the two links.
     *
     * @param \stdClass $course a course record
     * @param string $yearleaf the name of the course's own category (its year)
     * @return array{id:int, fullname:string, url:string, enrolurl:string, image:string, year:string, price:string, summary:string, created:int, modified:int, enrolled:bool}
     */
    public static function course_card(\stdClass $course, string $yearleaf): array {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/theme/nit/lib.php');

        $context = \context_course::instance($course->id);
        $image = \core_course\external\course_summary_exporter::get_course_image($course);
        $summary = trim(html_to_text(format_text($course->summary, $course->summaryformat,
            ['context' => $context]), 0, false));
        return [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'enrolurl' => (new \moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
            // No course picture → Moodle's generated pattern (the same default the
            // course cards on the dashboard show).
            'image' => $image ? (string) $image : $OUTPUT->get_generated_url_for_course($context),
            'year' => self::short_year($yearleaf),
            'price' => self::price_label(theme_nit_course_price((int) $course->id)),
            'summary' => \core_text::substr($summary, 0, 400),
            'created' => (int) $course->timecreated,
            'modified' => (int) $course->timemodified,
            // The viewer already joined / bought it: cards show "enter" but no "subscribe".
            'enrolled' => isloggedin() && !isguestuser() && is_enrolled($context, null, '', true),
        ];
    }

    /**
     * The context of the ONE web course card (local_academy/course_card) for a card of
     * course_card(): every page that draws a course card goes through here (the home
     * slider via homedata.php, the teacher page, the subject page), so they all show
     * the same card in the same states.
     *
     * @param array $card one course_card() entry (extra keys such as yearid are kept)
     * @return array the card plus free, createdtext, modifiedtext and yearid
     */
    public static function card_view(array $card): array {
        if (strpos($card['image'], '/course/generated/') !== false) {
            // Moodle's generated pattern is served behind the log-in, so a visitor gets a
            // broken picture; the template draws its own placeholder instead.
            $card['image'] = '';
        }
        $dateformat = get_string('strftimedaydate', 'langconfig');
        $card['free'] = $card['price'] === self::price_label('');
        $card['createdtext'] = userdate($card['created'], $dateformat);
        $card['modifiedtext'] = userdate($card['modified'], $dateformat);
        $card += ['yearid' => 0];
        return $card;
    }

    /**
     * One course card as HTML (local_academy/course_card), for the home slider.
     *
     * @param array $card one course_card() entry
     * @return string
     */
    public static function render_card(array $card): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('local_academy/course_card', self::card_view($card));
    }

    /**
     * "الصف الثاني الثانوي" → "٢ ث" (the short form bassthalk prints on cards;
     * likewise "١ ع" for a preparatory year), "Second Year of Secondary School"
     * → "2 Sec" / "… Middle School" → "2 Prep"; any other text is returned as it is.
     *
     * @param string $year a category name, already filtered for display
     * @return string
     */
    public static function short_year(string $year): string {
        $rules = [
            // [ordinal words => digit], [stage words => letter]
            [['الأول' => '١', 'الاول' => '١', 'الثاني' => '٢', 'الثانى' => '٢', 'الثالث' => '٣'],
                ['ثانو' => 'ث', 'اعداد' => 'ع', 'إعداد' => 'ع']],
            [['first' => '1', 'second' => '2', 'third' => '3'],
                ['secondary' => 'Sec', 'middle' => 'Prep', 'preparatory' => 'Prep']],
        ];
        $lower = \core_text::strtolower($year);
        foreach ($rules as [$digits, $stages]) {
            foreach ($digits as $word => $digit) {
                // A whole word: "second" is also the start of "secondary".
                if (!preg_match('/(?<!\p{L})' . preg_quote($word, '/') . '(?!\p{L})/u', $lower)) {
                    continue;
                }
                foreach ($stages as $stage => $letter) {
                    if (\core_text::strpos($lower, $stage) !== false) {
                        return $digit . ' ' . $letter;
                    }
                }
            }
        }
        return $year;
    }

    /**
     * "175.00 EGP" → "175 جنيه" (Arabic) / "175 EGP"; '' (free) → "مجاني" / "Free".
     *
     * @param string $price as theme_nit_course_price() returns it
     * @return string
     */
    public static function price_label(string $price): string {
        $ar = (strpos(current_language(), 'ar') === 0);
        if ($price === '') {
            return $ar ? 'مجاني' : 'Free';
        }
        [$amount, $currency] = array_pad(explode(' ', $price, 2), 2, '');
        $amount = preg_replace('/\.00$/', '', str_replace(',', '', $amount));
        return trim($amount . ' ' . ($ar && strtoupper($currency) === 'EGP' ? 'جنيه' : $currency));
    }

    /**
     * The logged-in student's own year (category id) / study system / division,
     * or null for a visitor.
     *
     * @return array{year:int, system:string, division:string}|null
     */
    private static function me(): ?array {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $values = user_fields::values((int) $USER->id);
        return [
            'year' => academic_structure::year_category($values[academic_structure::USER_YEAR]),
            'system' => $values[academic_structure::USER_SYSTEM],
            'division' => $values[academic_structure::USER_DIVISION],
        ];
    }

    /**
     * The study systems and divisions for the division filter: `name` is the
     * stored text (what course and student answers hold, so what filters match),
     * `label` the same text for display in the page language (names may carry
     * {mlang} markup).
     *
     * @param array $structure academic_structure::get()
     * @return array<int, array{name:string, label:string, divisions:array<int, array{name:string, label:string}>}>
     */
    private static function system_options(array $structure): array {
        $label = static fn(string $text): string => format_string($text, true, ['escape' => false]);
        return array_map(static fn(array $system): array => [
            'name' => $system['name'],
            'label' => $label($system['name']),
            'divisions' => array_map(static fn(string $d): array => ['name' => $d, 'label' => $label($d)], $system['divisions']),
        ], $structure['systems']);
    }

    /**
     * The year filter's options: every visible category, by tree order.
     *
     * @return array<int, array{id:int, name:string}>
     */
    private static function year_options(): array {
        return array_map(static fn(array $y): array => ['id' => $y['id'], 'name' => $y['name']], academic_structure::years());
    }

    /**
     * Category id => its year data (path ids, full name, own name).
     *
     * @return array<int, array{ids:int[], name:string, leaf:string}>
     */
    public static function years_by_category(): array {
        $out = [];
        foreach (academic_structure::years() as $year) {
            $parts = explode(academic_structure::PATH_SEPARATOR, $year['name']);
            $out[$year['id']] = ['ids' => $year['ids'], 'name' => $year['name'], 'leaf' => (string) end($parts)];
        }
        return $out;
    }

    /**
     * Role ids of the teacher and editing-teacher archetypes.
     *
     * @return int[]
     */
    public static function teacher_roles(): array {
        return array_keys(get_archetype_roles('editingteacher') + get_archetype_roles('teacher'));
    }

    /**
     * Each course's Study system / Division answers, as text.
     *
     * @param int[] $courseids
     * @return array<int, array{system:string, division:string}>
     */
    public static function course_answers(array $courseids): array {
        global $DB;
        $keys = ['system' => academic_structure::COURSE_SYSTEM, 'division' => academic_structure::COURSE_DIVISION];
        $fieldids = self::course_field_ids(array_values($keys));
        $out = array_fill_keys($courseids, ['system' => '', 'division' => '']);
        if (!$courseids) {
            return $out;
        }
        [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        foreach ($keys as $key => $shortname) {
            if (empty($fieldids[$shortname])) {
                continue;
            }
            $config = json_decode((string) $DB->get_field('customfield_field', 'configdata', ['id' => $fieldids[$shortname]]), true) ?: [];
            $options = array_map('trim', preg_split('/\R/u', (string) ($config['options'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));
            $params = $cparams + ['fieldid' => $fieldids[$shortname]];
            foreach ($DB->get_records_sql("SELECT instanceid, intvalue FROM {customfield_data}
                                            WHERE fieldid = :fieldid AND instanceid $csql", $params) as $row) {
                $out[(int) $row->instanceid][$key] = $options[(int) $row->intvalue - 1] ?? '';
            }
        }
        return $out;
    }

    /**
     * Course custom field ids by short name.
     *
     * @param string[] $shortnames
     * @return array<string, int>
     */
    private static function course_field_ids(array $shortnames): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT f.shortname, f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'core_course' AND c.area = 'course' AND f.shortname $insql",
            $params
        ));
    }
}
