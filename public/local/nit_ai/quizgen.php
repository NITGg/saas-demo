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

/**
 * Write a quiz from a video transcript, in three steps: choose, watch, review.
 *
 * The middle step is a page and not a spinner on a button because generating
 * questions for a long video is a dozen requests to a provider, and a teacher
 * watching a coverage number climb can tell a slow run from a stuck one.
 *
 * Nothing here writes a question. The review step is the only door to the
 * builder, and it cannot be skipped.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_ai\api;
use local_nit_ai\form\quizgen_options;
use local_nit_ai\helper;
use local_nit_ai\quizgen\builder;
use local_nit_ai\quizgen\generator;
use local_nit_ai\quizgen\planner;
use local_nit_ai\quizgen\run;

$cmid = required_param('cmid', PARAM_INT);
$runid = optional_param('run', 0, PARAM_INT);
$review = optional_param('review', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('', $cmid, 0, true, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/nit_ai:generatequiz', $context);

$pageurl = new moodle_url('/local/nit_ai/quizgen.php', ['cmid' => $cmid]);
$activityurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('quizgen_title', 'local_nit_ai'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('quizgen_title', 'local_nit_ai'));

// Every reason this cannot run, in one place and in the teacher's words. Checked
// on the way in as well as on every generated slice: the state can change under
// a run, and finding out at the end would waste the whole thing.
$blockers = generator::blockers($cm, $context);
if ($blockers) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('quizgen_title', 'local_nit_ai'));
    echo $OUTPUT->notification(implode(' ', $blockers), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->continue_button($activityurl);
    echo $OUTPUT->footer();
    exit;
}

$transcript = api::get($cmid);
$record = $runid ? run::get($runid) : null;

// A run belongs to one person and one video. Anything else is a stale tab or a
// guessed id, and either way this page has nothing to show for it.
if ($record && ((int) $record->cmid !== $cmid || (int) $record->userid !== (int) $USER->id)) {
    $record = null;
    $runid = 0;
}

// ── Create the quiz ─────────────────────────────────────────────────────────
if ($record && optional_param('create', 0, PARAM_BOOL)) {
    require_sesskey();

    if ($record->status !== run::STATUS_DRAFT) {
        redirect($activityurl, get_string('quizgen_err_alreadybuilt', 'local_nit_ai'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $keep = optional_param_array('keep', [], PARAM_INT);
    $quizname = trim(optional_param('quizname', '', PARAM_TEXT));
    if ($quizname === '') {
        $quizname = format_string($cm->name) . ' — ' . get_string('quizgen_quizsuffix', 'local_nit_ai');
    }

    $record = run::keep_only($record, $keep);

    if (!run::questions($record)) {
        redirect(new moodle_url($pageurl, ['run' => $runid, 'review' => 1]),
            get_string('quizgen_err_nothingkept', 'local_nit_ai'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    try {
        $built = builder::build($record, $cm, $course, $quizname);
    } catch (moodle_exception $e) {
        redirect(new moodle_url($pageurl, ['run' => $runid, 'review' => 1]), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    run::mark_created($record, $built['cmid']);

    redirect(
        $built['url'],
        get_string('quizgen_created', 'local_nit_ai', array_sum($built['counts'])),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Step 1: the choices ─────────────────────────────────────────────────────
if (!$record) {
    $language = in_array($transcript->lang, ['ar', 'en'], true) ? $transcript->lang : current_language();
    $language = $language === 'ar' ? 'ar' : 'en';

    $form = new quizgen_options($pageurl, [
        'cmid'     => $cmid,
        'quizname' => format_string($cm->name) . ' — ' . get_string('quizgen_quizsuffix', 'local_nit_ai'),
        'language' => $language,
    ]);

    if ($form->is_cancelled()) {
        redirect($activityurl);
    }

    if ($data = $form->get_data()) {
        $types = [];
        foreach (generator::TYPES as $type) {
            if (!empty($data->{'type_' . $type})) {
                $types[] = $type;
            }
        }

        $describe = \local_nit_ai\source::describe($cm);
        $record = run::start($cmid, (int) $course->id, $describe['ref'], [
            'language' => $data->language,
            'types'    => $types,
            'quizname' => trim($data->quizname),
        ]);

        redirect(new moodle_url($pageurl, ['run' => (int) $record->id]));
    }

    $blocks = planner::blocks($transcript);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('quizgen_title', 'local_nit_ai'));
    echo html_writer::tag('p', get_string('quizgen_intro', 'local_nit_ai', (object) [
        'video'  => format_string($cm->name),
        'parts'  => count($blocks),
        'length' => $transcript->sourcelength > 0
            ? helper::timecode((int) $transcript->sourcelength)
            : helper::timecode((int) $transcript->lasttimestamp),
    ]), ['class' => 'text-muted']);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// ── Step 2: watch it work ───────────────────────────────────────────────────
$blocks = planner::blocks($transcript);

if (!$review) {
    $PAGE->requires->js(new moodle_url('/local/nit_ai/js/quizgen.js'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('quizgen_title', 'local_nit_ai'));
    echo $OUTPUT->render_from_template('local_nit_ai/quizgen_progress', [
        'cmid'        => $cmid,
        'runid'       => (int) $record->id,
        'batches'     => count(planner::batches($blocks)),
        'maxgaps'     => (int) (get_config('local_nit_ai', 'quizgen_maxgappasses') ?: 3),
        'reviewurl'   => (new moodle_url($pageurl, ['run' => (int) $record->id, 'review' => 1]))->out(false),
        'cancelurl'   => $activityurl->out(false),
        'working'     => get_string('quizgen_working', 'local_nit_ai'),
        'workingnote' => get_string('quizgen_workingnote', 'local_nit_ai'),
        'strslice'    => get_string('quizgen_slice', 'local_nit_ai'),
        'strgaps'     => get_string('quizgen_fillinggaps', 'local_nit_ai'),
        'strquestions' => get_string('quizgen_questionssofar', 'local_nit_ai'),
        'strcoverage' => get_string('quizgen_coverage', 'local_nit_ai'),
        'strcancel'   => get_string('cancel'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

// ── Step 3: review, then create ─────────────────────────────────────────────
$held = run::held($record);
$coverage = planner::coverage($blocks, $held['questions'], $held['skipped']);
run::set_coverage($record, $coverage);

$options = run::options($record);
$levels = [];
$index = 0;

foreach (generator::LEVELS as $level) {
    $questions = [];

    foreach ($held['questions'] as $position => $question) {
        if (($question['level'] ?? '') !== $level) {
            continue;
        }

        $answers = [];
        foreach ($question['options'] as $key => $answer) {
            $answers[] = [
                'text'    => $question['type'] === 'truefalse'
                    ? get_string($key === 0 ? 'true' : 'false', 'qtype_truefalse')
                    : $answer,
                'correct' => $key === (int) $question['correct'],
            ];
        }

        $questions[] = [
            'position'    => $position,
            'number'      => ++$index,
            'stem'        => $question['stem'],
            'answers'     => $answers,
            'time'        => (int) $question['time'] >= 0 ? helper::timecode((int) $question['time']) : '',
            'explanation' => $question['explanation'],
        ];
    }

    if ($questions) {
        $levels[] = [
            'name'      => get_string('quizgen_level_' . $level, 'local_nit_ai'),
            'mark'      => format_float(builder::MARKS[$level], 0),
            'count'     => count($questions),
            'questions' => $questions,
        ];
    }
}

// Coverage is measured against the transcript, so it says nothing about the
// video unless the two actually match. A transcript that stops early reads as
// 100% covered while the second half of the lesson was never seen, which is the
// one failure this feature must not hide — so the span is stated, and a gap
// between it and the video is called out.
$transcriptend = (int) $transcript->lasttimestamp;
$videolength = (int) $transcript->sourcelength;
$spanmismatch = $transcript->hastimestamps && $videolength > 0
    && abs($videolength - $transcriptend) > api::LENGTH_TOLERANCE;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizgen_title', 'local_nit_ai'));
echo $OUTPUT->render_from_template('local_nit_ai/quizgen_review', [
    'strspan'      => get_string('quizgen_span', 'local_nit_ai', (object) [
        'parts' => count($blocks),
        'end'   => $transcript->hastimestamps
            ? helper::timecode($transcriptend)
            : get_string('lengthunknown', 'local_nit_ai'),
        'video' => $videolength > 0
            ? helper::timecode($videolength)
            : get_string('lengthunknown', 'local_nit_ai'),
    ]),
    'spanmismatch' => $spanmismatch,
    'strspanwarning' => $spanmismatch
        ? get_string('check_lengthmismatch', 'local_nit_ai', (object) [
            'transcript' => helper::timecode($transcriptend),
            'video'      => helper::timecode($videolength),
        ])
        : '',
    'actionurl'    => $pageurl->out(false),
    'cmid'         => $cmid,
    'runid'        => (int) $record->id,
    'sesskey'      => sesskey(),
    'quizname'     => $options['quizname'] ?? format_string($cm->name),
    'levels'       => $levels,
    'hasquestions' => !empty($levels),
    'total'        => count($held['questions']),
    'percent'      => (int) $coverage['percent'],
    'gaps'         => $coverage['gaps'],
    'hasgaps'      => !empty($coverage['gaps']),
    'skippedparts' => $coverage['skippedparts'],
    'hasskipped'   => !empty($coverage['skippedparts']),
    'cancelurl'    => $activityurl->out(false),
    'strnothing'   => get_string('quizgen_err_nothingtobuild', 'local_nit_ai'),
    'strreview'    => get_string('quizgen_reviewintro', 'local_nit_ai'),
    'strcoveragesummary' => get_string('quizgen_coveragesummary', 'local_nit_ai', (object) [
        'percent' => (int) $coverage['percent'],
        'total'   => count($held['questions']),
    ]),
    'strgapstitle' => get_string('quizgen_gapstitle', 'local_nit_ai'),
    'strskippedtitle' => get_string('quizgen_skippedtitle', 'local_nit_ai'),
    'strquizname'  => get_string('quizgen_quizname', 'local_nit_ai'),
    'strcreate'    => get_string('quizgen_createbutton', 'local_nit_ai'),
    'strhidden'    => get_string('quizgen_createdhidden', 'local_nit_ai'),
    'strcancel'    => get_string('cancel'),
    'strkeep'      => get_string('quizgen_keep', 'local_nit_ai'),
    'strmarks'     => get_string('quizgen_marks', 'local_nit_ai'),
]);
echo $OUTPUT->footer();
