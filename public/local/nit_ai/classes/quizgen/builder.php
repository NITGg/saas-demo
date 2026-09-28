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

namespace local_nit_ai\quizgen;

use core_question\local\bank\question_bank_helper;
use local_nit_ai\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Turns reviewed questions into a real quiz, using nothing but core's own doors.
 *
 * There is no NIT quiz. What comes out of here is an ordinary mod_quiz activity
 * whose questions sit in the course question bank, so everything a teacher
 * already knows keeps working: editing a question, changing a mark, reordering,
 * the gradebook, the reports, the mobile app, backup and restore.
 *
 * The questions are written through qformat_xml — the same importer behind
 * "Import questions" — rather than by inserting rows. Question storage spans
 * four tables and moves between Moodle releases; the importer is core's problem
 * to keep working, and ours to hand well-formed input to.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class builder {

    /** @var array Marks per level: a hard question is worth three easy ones. */
    public const MARKS = ['easy' => 1.0, 'medium' => 2.0, 'hard' => 3.0];

    /**
     * Build the quiz.
     *
     * @param \stdClass $run the reviewed generation run
     * @param object $cm the video course module the questions came from
     * @param \stdClass $course
     * @param string $quizname
     * @return array ['cmid' => int, 'url' => \moodle_url, 'counts' => [level => int]]
     * @throws \moodle_exception when there is nothing to build or core refuses
     */
    public static function build(\stdClass $run, object $cm, \stdClass $course, string $quizname): array {
        $questions = run::questions($run);
        if (!$questions) {
            throw new \moodle_exception('quizgen_err_nothingtobuild', 'local_nit_ai');
        }

        $bank = self::bank($course);
        $bankcontext = \context_module::instance($bank->id);
        require_capability('moodle/question:add', $bankcontext);
        require_capability('moodle/course:manageactivities', \context_course::instance($course->id));

        $bylevel = self::by_level($questions);
        $categories = self::categories($bankcontext, $quizname, array_keys($bylevel));

        // Questions first: a quiz with no questions is a mess to clean up, and
        // this is the half that can still fail on a question the importer
        // dislikes.
        $imported = [];
        foreach ($bylevel as $level => $items) {
            $ids = self::import($items, $categories[$level], $bankcontext, $course);
            if (!$ids) {
                throw new \moodle_exception('quizgen_err_importfailed', 'local_nit_ai');
            }
            self::tag($ids, $level, $bankcontext);
            $imported[$level] = $ids;
        }

        $quizcm = self::create_quiz($course, $cm, $quizname);
        $quiz = self::quiz_record($quizcm);

        self::fill_quiz($quiz, $imported);

        \mod_quiz\quiz_settings::create((int) $quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        rebuild_course_cache((int) $course->id, true);

        return [
            'cmid'   => (int) $quizcm->id,
            'url'    => new \moodle_url('/mod/quiz/view.php', ['id' => (int) $quizcm->id]),
            'counts' => array_map('count', $imported),
        ];
    }

    /**
     * The question bank to write into, created on first use.
     *
     * In Moodle 5 a question bank is an activity, so "the course's bank" is a
     * question with more than one answer. An existing bank the teacher already
     * uses is the right home; only when there is none do we make one, and we
     * make the same one core's own "create a default bank" button makes.
     *
     * The system bank is accepted but never created for this: it is invisible on
     * the course page and exists to hold categories migrated from before 5.0.
     * Questions a teacher will want to find again do not belong there.
     *
     * @param \stdClass $course
     * @return \cm_info
     * @throws \moodle_exception
     */
    protected static function bank(\stdClass $course): \cm_info {
        $ordinary = self::existing_bank($course, false);
        if ($ordinary) {
            return $ordinary;
        }

        try {
            return question_bank_helper::create_default_open_instance(
                $course,
                question_bank_helper::get_bank_name_string(
                    'defaultbank',
                    'core_question',
                    ['coursename' => $course->fullname]
                )
            );
        } catch (\Throwable $e) {
            // Question banks disabled as an activity, or this user not allowed
            // to add one. Either way there may still be a bank to write into.
            debugging('local_nit_ai: could not create a question bank: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $system = self::existing_bank($course, true);
        if ($system) {
            return $system;
        }

        throw new \moodle_exception('quizgen_err_nobank', 'local_nit_ai');
    }

    /**
     * A bank already in this course that this user may add questions to.
     *
     * @param \stdClass $course
     * @param bool $system true to look for the hidden system bank, false for an
     *                     ordinary one a teacher can see on the course page
     * @return \cm_info|null
     */
    protected static function existing_bank(\stdClass $course, bool $system): ?\cm_info {
        global $DB;

        $modname = question_bank_helper::get_default_question_bank_activity_name();
        $banks = get_fast_modinfo($course)->get_instances_of($modname);

        if (!$banks) {
            return null;
        }

        $types = $DB->get_records_list(
            $modname,
            'id',
            array_map(static fn($bank) => (int) $bank->instance, $banks),
            '',
            'id, type'
        );

        foreach ($banks as $bank) {
            $issystem = ($types[$bank->instance]->type ?? '') === question_bank_helper::TYPE_SYSTEM;

            if ($issystem !== $system) {
                continue;
            }
            if (has_capability('moodle/question:add', \context_module::instance($bank->id))) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Group the questions by level, in quiz order, dropping empty levels.
     *
     * @param array $questions
     * @return array level => questions
     */
    protected static function by_level(array $questions): array {
        $bylevel = [];

        foreach (generator::LEVELS as $level) {
            $items = array_values(array_filter(
                $questions,
                static fn($question) => ($question['level'] ?? '') === $level
            ));
            if ($items) {
                $bylevel[$level] = $items;
            }
        }

        return $bylevel;
    }

    /**
     * One category named after the video, with a child per level.
     *
     * The children are what make the questions reusable afterwards: "twelve
     * random medium questions from lesson 3" is then a thing a teacher can build
     * in the quiz UI without our help.
     *
     * @param \context $bankcontext
     * @param string $name
     * @param array $levels
     * @return array level => category record
     * @throws \moodle_exception
     */
    protected static function categories(\context $bankcontext, string $name, array $levels): array {
        global $DB;

        $manager = new \core_question\category_manager();

        // Asked for with create-if-missing rather than read: a bank is not
        // guaranteed to have a top category. The system bank in particular is
        // created with none at all — add_moduleinfo skips the default category
        // for it — so reading would fail on exactly the course that has never
        // had a bank of its own.
        $top = question_get_top_category($bankcontext->id, true);

        if (!$top) {
            throw new \moodle_exception('quizgen_err_nobank', 'local_nit_ai');
        }

        $parentid = $manager->add_category(
            $top->id . ',' . $bankcontext->id,
            self::unique_category_name($bankcontext, $name),
            get_string('quizgen_categoryinfo', 'local_nit_ai'),
            FORMAT_HTML,
        );

        $categories = [];
        foreach ($levels as $level) {
            $childid = $manager->add_category(
                $parentid . ',' . $bankcontext->id,
                get_string('quizgen_level_' . $level, 'local_nit_ai'),
                '',
                FORMAT_HTML,
            );
            $categories[$level] = $DB->get_record('question_categories', ['id' => $childid], '*', MUST_EXIST);
        }

        return $categories;
    }

    /**
     * A category name nobody else in this bank is using.
     *
     * Regenerating from the same video is a normal thing to do — the transcript
     * was corrected, or the first pass was thin — and the old questions may still
     * be in use by a quiz somebody has attempted. So a second run sits beside the
     * first rather than merging into it.
     *
     * @param \context $bankcontext
     * @param string $name
     * @return string
     */
    protected static function unique_category_name(\context $bankcontext, string $name): string {
        global $DB;

        $name = \core_text::substr(trim($name), 0, 200);
        $candidate = $name;
        $suffix = 1;

        while ($DB->record_exists('question_categories', ['contextid' => $bankcontext->id, 'name' => $candidate])) {
            $suffix++;
            $candidate = $name . ' (' . $suffix . ')';
        }

        return $candidate;
    }

    /**
     * Write one level's questions into its category.
     *
     * @param array $items
     * @param \stdClass $category
     * @param \context $bankcontext
     * @param \stdClass $course
     * @return array question ids, in the order they were given
     */
    protected static function import(array $items, \stdClass $category, \context $bankcontext, \stdClass $course): array {
        $format = new xml_importer();
        $format->set_source(self::xml($items));
        $format->setCategory($category);
        $format->setContexts([$bankcontext]);
        $format->setCourse($course);
        $format->setMatchgrades('error');
        $format->setCatfromfile(false);
        $format->setContextfromfile(false);
        $format->setStoponerror(true);
        $format->set_display_progress(false);

        // The importer talks to a page: it echoes what it is doing and what it
        // could not read. Here there is no page, so its voice goes to the log
        // instead of into the middle of an AJAX reply.
        ob_start();
        $ok = $format->importprocess();
        $chatter = trim(html_to_text(ob_get_clean(), 0, false));

        if ($chatter !== '') {
            debugging('local_nit_ai: question import said: ' . $chatter, DEBUG_DEVELOPER);
        }

        return $ok ? array_map('intval', $format->questionids) : [];
    }

    /**
     * Tag a level's questions with that level.
     *
     * The category already says it, but a tag travels with the question when it
     * is moved or copied, and it is what the quiz's own random-question filter
     * can search on.
     *
     * @param array $questionids
     * @param string $level
     * @param \context $bankcontext
     * @return void
     */
    protected static function tag(array $questionids, string $level, \context $bankcontext): void {
        foreach ($questionids as $questionid) {
            \core_tag_tag::set_item_tags('core_question', 'question', $questionid, $bankcontext, [$level]);
        }
    }

    /**
     * The Moodle XML for a set of questions.
     *
     * @param array $items
     * @return string
     */
    protected static function xml(array $items): string {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<quiz>'];

        foreach ($items as $index => $item) {
            $out[] = $item['type'] === 'truefalse'
                ? self::truefalse_xml($item, $index + 1)
                : self::multichoice_xml($item, $index + 1);
        }

        $out[] = '</quiz>';

        return implode("\n", $out);
    }

    /**
     * One multiple choice question.
     *
     * @param array $item
     * @param int $number position within its level, used for the name
     * @return string
     */
    protected static function multichoice_xml(array $item, int $number): string {
        $out = [
            '  <question type="multichoice">',
            self::name_xml($item, $number),
            self::text_xml('questiontext', $item['stem']),
            self::text_xml('generalfeedback', self::feedback($item)),
            '    <defaultgrade>1</defaultgrade>',
            '    <penalty>0.3333333</penalty>',
            '    <hidden>0</hidden>',
            '    <single>true</single>',
            '    <shuffleanswers>true</shuffleanswers>',
            '    <answernumbering>abc</answernumbering>',
        ];

        foreach ($item['options'] as $index => $option) {
            $out[] = self::answer_xml($option, $index === (int) $item['correct'] ? 100 : 0);
        }

        $out[] = '  </question>';

        return implode("\n", $out);
    }

    /**
     * One true/false question.
     *
     * The importer matches on the literal words true and false, so those are
     * what goes in the file whatever language the question is written in.
     *
     * @param array $item
     * @param int $number
     * @return string
     */
    protected static function truefalse_xml(array $item, int $number): string {
        $istrue = (int) $item['correct'] === 0;

        return implode("\n", [
            '  <question type="truefalse">',
            self::name_xml($item, $number),
            self::text_xml('questiontext', $item['stem']),
            self::text_xml('generalfeedback', self::feedback($item)),
            '    <defaultgrade>1</defaultgrade>',
            '    <penalty>1</penalty>',
            '    <hidden>0</hidden>',
            self::answer_xml('true', $istrue ? 100 : 0),
            self::answer_xml('false', $istrue ? 0 : 100),
            '  </question>',
        ]);
    }

    /**
     * The question's name in the bank: something a teacher can scan down a list.
     *
     * @param array $item
     * @param int $number
     * @return string
     */
    protected static function name_xml(array $item, int $number): string {
        $stem = \core_text::substr(preg_replace('/\s+/u', ' ', $item['stem']), 0, 80);
        $prefix = $item['time'] >= 0 ? '[' . helper::timecode((int) $item['time']) . '] ' : $number . '. ';

        return "    <name><text>" . self::escape($prefix . $stem) . "</text></name>";
    }

    /**
     * One answer.
     *
     * @param string $text
     * @param int $fraction 100 for the right one, 0 for the rest
     * @return string
     */
    protected static function answer_xml(string $text, int $fraction): string {
        return '    <answer fraction="' . $fraction . '" format="html">'
            . '<text>' . self::escape($text) . '</text>'
            . '<feedback format="html"><text></text></feedback>'
            . '</answer>';
    }

    /**
     * A tagged text element.
     *
     * @param string $tag
     * @param string $text
     * @return string
     */
    protected static function text_xml(string $tag, string $text): string {
        return '    <' . $tag . ' format="html"><text>' . self::escape($text) . '</text></' . $tag . '>';
    }

    /**
     * What the student reads after answering: why, and where to go and watch it.
     *
     * The timecode is the whole reason the block ids were carried this far. It
     * turns a wrong answer into a place in the video rather than a mark.
     *
     * @param array $item
     * @return string
     */
    protected static function feedback(array $item): string {
        $parts = [];

        if ($item['explanation'] !== '') {
            $parts[] = $item['explanation'];
        }
        if ((int) $item['time'] >= 0) {
            $parts[] = get_string('quizgen_seenat', 'local_nit_ai', helper::timecode((int) $item['time']));
        }

        return implode(' ', $parts);
    }

    /**
     * Text safe to drop inside an XML element.
     *
     * @param string $text
     * @return string
     */
    protected static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Create the quiz activity itself, hidden, in the video's own section.
     *
     * Hidden is not caution for its own sake: these questions have been read once,
     * by one person, minutes ago. Showing the quiz is a second decision, and it
     * belongs to whoever teaches the course.
     *
     * @param \stdClass $course
     * @param object $cm the video
     * @param string $name
     * @return \stdClass the new course module record
     * @throws \moodle_exception
     */
    protected static function create_quiz(\stdClass $course, object $cm, string $name): \stdClass {
        global $DB;

        $sectionnum = (int) ($cm->sectionnum ?? 0);

        // The helper hands back five things, of which the form data is the last.
        // The rest — module, context, section, cm — the add form needs and we do
        // not.
        [, , , , $moduleinfo] = prepare_new_moduleinfo_data($course, 'quiz', $sectionnum);

        $moduleinfo->name = \core_text::substr($name, 0, 250);
        $moduleinfo->visible = 0;
        $moduleinfo->visibleoncoursepage = 1;

        // Fill the editor prepare_new_moduleinfo_data already set up rather than
        // replacing it: its draft item id is real, and a made-up one would send
        // the intro's file handling looking at an area that does not exist.
        if (isset($moduleinfo->introeditor) && is_array($moduleinfo->introeditor)) {
            $moduleinfo->introeditor['text'] = get_string('quizgen_quizintro', 'local_nit_ai');
            $moduleinfo->introeditor['format'] = FORMAT_HTML;
        }

        self::apply_quiz_defaults($moduleinfo);

        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        $quizcm = $DB->get_record('course_modules', ['id' => (int) $moduleinfo->coursemodule], '*', MUST_EXIST);

        self::place_after_video($quizcm, $cm, $course);

        return $quizcm;
    }

    /**
     * Fill in every quiz setting the add form would have submitted.
     *
     * quiz_add_instance is written for a form, not for a caller: it reads
     * settings straight off the object and hands the same object to the access
     * rules and to the calendar. A quiz built from a half-filled object is a
     * quiz with warnings in the log and blanks in its settings, so the whole set
     * is written here, taking the administrator's own defaults wherever there is
     * one — a generated quiz should be the quiz this site would have made.
     *
     * @param \stdClass $moduleinfo
     * @return void
     */
    protected static function apply_quiz_defaults(\stdClass $moduleinfo): void {
        $config = get_config('quiz');

        $moduleinfo->timeopen = 0;
        $moduleinfo->timeclose = 0;
        $moduleinfo->timelimit = (int) ($config->timelimit ?? 0);
        $moduleinfo->overduehandling = $config->overduehandling ?: 'autosubmit';
        $moduleinfo->graceperiod = (int) ($config->graceperiod ?? 86400);
        $moduleinfo->preferredbehaviour = $config->preferredbehaviour ?: 'deferredfeedback';
        $moduleinfo->canredoquestions = (int) ($config->canredoquestions ?? 0);
        $moduleinfo->attempts = (int) ($config->attempts ?? 0);
        $moduleinfo->attemptonlast = (int) ($config->attemptonlast ?? 0);
        $moduleinfo->grademethod = (int) ($config->grademethod ?? 1);
        $moduleinfo->grade = (float) ($config->maximumgrade ?? 10);
        $moduleinfo->sumgrades = 0;
        $moduleinfo->decimalpoints = (int) ($config->decimalpoints ?? 2);
        $moduleinfo->questiondecimalpoints = (int) ($config->questiondecimalpoints ?? -1);
        $moduleinfo->showuserpicture = (int) ($config->showuserpicture ?? 0);
        $moduleinfo->showblocks = (int) ($config->showblocks ?? 0);
        $moduleinfo->delay1 = (int) ($config->delay1 ?? 0);
        $moduleinfo->delay2 = (int) ($config->delay2 ?? 0);
        $moduleinfo->shuffleanswers = (int) ($config->shuffleanswers ?? 1);
        $moduleinfo->navmethod = $config->navmethod ?: 'free';
        $moduleinfo->quizpassword = '';
        $moduleinfo->subnet = (string) ($config->subnet ?? '');
        $moduleinfo->browsersecurity = $config->browsersecurity ?: '-';
        $moduleinfo->precreateattempts = (int) ($config->precreateattempts ?? 0);

        // One page per level. Set after the defaults so nothing puts it back.
        $moduleinfo->questionsperpage = 0;

        self::apply_review_defaults($moduleinfo, $config);
    }

    /**
     * Turn the site's review-option bitmasks back into the checkboxes the quiz
     * form would have submitted, which is the only shape quiz_process_options
     * reads.
     *
     * @param \stdClass $moduleinfo
     * @param \stdClass $config
     * @return void
     */
    protected static function apply_review_defaults(\stdClass $moduleinfo, \stdClass $config): void {
        $whens = [
            'during'      => \mod_quiz\question\display_options::DURING,
            'immediately' => \mod_quiz\question\display_options::IMMEDIATELY_AFTER,
            'open'        => \mod_quiz\question\display_options::LATER_WHILE_OPEN,
            'closed'      => \mod_quiz\question\display_options::AFTER_CLOSE,
        ];

        // The names are listed rather than read from mod_quiz\admin\review_setting
        // because that class extends admin_setting, which is only loaded on
        // administration pages. They mirror review_setting::fields().
        $fields = [
            'attempt', 'correctness', 'maxmarks', 'marks',
            'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback',
        ];

        $allon = array_sum($whens);

        foreach ($fields as $field) {
            $mask = (int) ($config->{'review' . $field} ?? $allon);

            foreach ($whens as $whenname => $when) {
                $moduleinfo->{$field . $whenname} = ($mask & $when) ? 1 : 0;
            }
        }
    }

    /**
     * Move the quiz directly below the video it belongs to.
     *
     * Cosmetic, and treated as such: a quiz at the end of the section is still a
     * correct quiz, so a failure here is logged and swallowed rather than losing
     * everything that was just built.
     *
     * @param \stdClass $quizcm
     * @param object $cm the video
     * @param \stdClass $course
     * @return void
     */
    protected static function place_after_video(\stdClass $quizcm, object $cm, \stdClass $course): void {
        global $DB;

        try {
            $section = $DB->get_record('course_sections', [
                'course'  => (int) $course->id,
                'section' => (int) ($cm->sectionnum ?? 0),
            ], '*', MUST_EXIST);

            $sequence = array_map('intval', array_filter(explode(',', (string) $section->sequence)));
            $position = array_search((int) $cm->id, $sequence, true);

            if ($position === false) {
                return;
            }

            // moveto_module puts the module before the one it is given; the
            // module after the video is therefore where we want to land.
            $next = $sequence[$position + 1] ?? null;
            $beforemod = ($next && $next !== (int) $quizcm->id)
                ? $DB->get_record('course_modules', ['id' => $next])
                : null;

            if ($beforemod) {
                moveto_module($quizcm, $section, $beforemod);
            }
        } catch (\Throwable $e) {
            debugging('local_nit_ai: could not place the quiz next to its video: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The quiz row, with the cmid attached the way quiz functions expect.
     *
     * @param \stdClass $quizcm
     * @return \stdClass
     */
    protected static function quiz_record(\stdClass $quizcm): \stdClass {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => (int) $quizcm->instance], '*', MUST_EXIST);
        $quiz->cmid = (int) $quizcm->id;

        return $quiz;
    }

    /**
     * Put the questions in the quiz: one page per level, marks by level, and a
     * heading over each.
     *
     * @param \stdClass $quiz
     * @param array $imported level => question ids
     * @return void
     */
    protected static function fill_quiz(\stdClass $quiz, array $imported): void {
        global $DB;

        $page = 0;
        $firstslot = 1;
        $sections = [];

        foreach ($imported as $level => $questionids) {
            $page++;
            $sections[] = [
                'firstslot' => $firstslot,
                'heading'   => get_string('quizgen_level_' . $level, 'local_nit_ai'),
            ];

            foreach ($questionids as $questionid) {
                quiz_add_quiz_question($questionid, $quiz, $page, self::MARKS[$level]);
            }

            $firstslot += count($questionids);
        }

        // quiz_add_instance already made a first section covering slot 1; reuse
        // it rather than leaving an empty unnamed one above ours.
        $existing = $DB->get_record('quiz_sections', ['quizid' => (int) $quiz->id, 'firstslot' => 1]);

        foreach ($sections as $index => $section) {
            $record = (object) [
                'quizid'           => (int) $quiz->id,
                'firstslot'        => $section['firstslot'],
                'heading'          => $section['heading'],
                'shufflequestions' => 0,
            ];

            if ($index === 0 && $existing) {
                $record->id = $existing->id;
                $DB->update_record('quiz_sections', $record);
            } else {
                $DB->insert_record('quiz_sections', $record);
            }
        }
    }
}

