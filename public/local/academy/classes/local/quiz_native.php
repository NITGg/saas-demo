<?php
namespace local_academy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Native (app-rendered) form of every core quiz question type.
 *
 * Works on a question as it was set up inside a real attempt ($qa), so the app sees
 * the attempt's calculated numbers, shuffled choices and random picks. Three jobs per
 * type, kept side by side so they cannot drift apart:
 *
 *  - render()        question → JSON the app draws (choices, gaps, stems, drop zones…)
 *  - to_response()   the app's answer → the question engine's response array
 *  - from_response() the engine's response array → the app's answer (resume + review)
 *
 * The app answer formats are documented in docs/MOBILE_API.md §3.6. Grading is never
 * done here: responses go into the attempt's question usage and Moodle grades them.
 */
class quiz_native {

    /** Question types the app can render and answer without a webview. */
    public const TYPES = [
        'multichoice', 'truefalse', 'shortanswer', 'numerical', 'calculated', 'calculatedsimple',
        'calculatedmulti', 'essay', 'match', 'randomsamatch', 'gapselect', 'ddwtos', 'ddimageortext',
        'ddmarker', 'ordering', 'multianswer', 'description',
    ];

    /** @var callable(string $html, int $contextid, string $component, string $filearea, int $itemid, int $questionid): array */
    private $content;

    /** @var callable(int $contextid, string $component, string $filearea, int $itemid): ?array */
    private $image;

    /**
     * @param callable $content turns stored HTML into ['text' => string, 'images' => string[]]
     * @param callable $image   first image of a file area → ['url', 'width', 'height'] or null
     */
    public function __construct(callable $content, callable $image) {
        $this->content = $content;
        $this->image = $image;
    }

    /**
     * Can the app answer this question natively?
     *
     * Essays that only take file uploads are the exception: uploads need the webview.
     */
    public static function is_supported(\question_definition $question): bool {
        $type = $question->qtype->name();
        if (!in_array($type, self::TYPES, true)) {
            return false;
        }
        if ($type === 'essay') {
            return $question->responseformat !== 'noinline' && empty($question->attachmentsrequired);
        }
        return true;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    /**
     * Type-specific fields for one question in an attempt.
     */
    public function render(\question_attempt $qa): array {
        $question = $qa->get_question();
        switch ($question->qtype->name()) {
            case 'multichoice':
            case 'calculatedmulti':
                return $this->render_choice($question, $qa);
            case 'truefalse':
                return $this->render_truefalse($question);
            case 'shortanswer':
                return ['input' => 'text'];
            case 'numerical':
            case 'calculated':
            case 'calculatedsimple':
                return $this->render_numerical($question);
            case 'essay':
                return $this->render_essay($question);
            case 'match':
            case 'randomsamatch':
                return $this->render_match($question);
            case 'gapselect':
            case 'ddwtos':
                return $this->render_gaps($question);
            case 'ddimageortext':
                return $this->render_ddimageortext($question);
            case 'ddmarker':
                return $this->render_ddmarker($question);
            case 'ordering':
                return $this->render_ordering($question);
            case 'multianswer':
                return $this->render_multianswer($question, $qa);
            case 'description':
                return ['informational' => true];
        }
        return [];
    }

    private function render_choice(\question_definition $question, \question_attempt $qa): array {
        $options = [];
        foreach ($question->get_order($qa) as $answerid) {
            $c = $this->html($question->answers[$answerid]->answer, $question, 'question', 'answer', (int) $answerid);
            $options[] = ['id' => (int) $answerid, 'text' => $c['text'], 'images' => $c['images']];
        }
        return [
            'single'  => $question instanceof \qtype_multichoice_single_question,
            'options' => $options,
        ];
    }

    private function render_truefalse(\question_definition $question): array {
        return ['options' => [
            ['id' => (int) $question->trueanswerid, 'text' => get_string('true', 'qtype_truefalse'), 'images' => [], 'value' => true],
            ['id' => (int) $question->falseanswerid, 'text' => get_string('false', 'qtype_truefalse'), 'images' => [], 'value' => false],
        ]];
    }

    private function render_numerical(\question_definition $question): array {
        $units = $question->ap ? array_values(array_map('strval', $question->ap->get_unit_options())) : [];
        $display = (int) $question->unitdisplay;
        return [
            'input'      => 'number',
            // none: no units; text: typed in the same box as the number; select: pick from units[].
            'unit_input' => $display === \qtype_numerical::UNITNONE || !$units ? 'none'
                : ($question->has_separate_unit_field() ? 'select' : 'text'),
            'units'      => $display === \qtype_numerical::UNITNONE ? [] : $units,
            'units_left' => !empty($question->unitsleft),
        ];
    }

    private function render_essay(\question_definition $question): array {
        return [
            'input'                => 'textarea',
            'response_required'    => !empty($question->responserequired),
            'lines'                => (int) $question->responsefieldlines,
            'min_words'            => (int) $question->minwordlimit,
            'max_words'            => (int) $question->maxwordlimit,
            // Uploads are not taken by the API; an essay that needs them is supported:false.
            'attachments_allowed'  => (int) $question->attachments,
        ];
    }

    private function render_match(\question_definition $question): array {
        $random = $question->qtype->name() === 'randomsamatch';
        $stems = [];
        foreach ($question->get_stem_order() as $stemid) {
            // Random-matching stems are other questions' texts, so their files are not ours to serve.
            $c = $random
                ? $this->html($question->stems[$stemid], null, '', '', 0)
                : $this->html($question->stems[$stemid], $question, 'qtype_match', 'subquestion', (int) $stemid);
            $stems[] = ['id' => (int) $stemid, 'text' => $c['text'], 'images' => $c['images']];
        }
        $choices = [];
        foreach ($question->get_choice_order() as $choiceid) {
            $choices[] = ['id' => (int) $choiceid, 'text' => $this->plain($question->choices[$choiceid])];
        }
        return ['stems' => $stems, 'choices' => $choices];
    }

    /**
     * Gap types: the text as segments, and for each group the choices that fit its gaps.
     */
    private function render_gaps(\question_definition $question): array {
        $segments = [];
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $segments[] = ['type' => 'gap', 'place' => (int) $i, 'group' => (int) $question->places[$i]];
            }
            if (($text = $this->fragment($fragment)) !== '') {
                $segments[] = ['type' => 'text', 'text' => $text];
            }
        }
        return [
            'drag'     => $question->qtype->name() === 'ddwtos',
            'segments' => $segments,
            'groups'   => $this->groups($question),
        ];
    }

    private function render_ddimageortext(\question_definition $question): array {
        $drops = [];
        foreach ($question->places as $place => $drop) {
            $drops[] = [
                'place' => (int) $place,
                'group' => (int) $drop->group,
                'left'  => (int) $drop->xy[0],
                'top'   => (int) $drop->xy[1],
                'label' => $this->plain($drop->text),
            ];
        }
        $groups = $this->groups($question);
        foreach ($groups as $group => $choices) {
            $ordered = $question->get_ordered_choices($group);
            foreach ($choices as $i => $choice) {
                // A drag item is an image when its dragimage area holds a file; its text is then the alt text.
                $image = ($this->image)((int) $question->contextid, 'qtype_ddimageortext', 'dragimage',
                    (int) $ordered[$choice['value']]->id);
                $groups[$group][$i]['image'] = $image['url'] ?? null;
            }
        }
        return [
            'background' => ($this->image)((int) $question->contextid, 'qtype_ddimageortext', 'bgimage', (int) $question->id),
            'drops'      => $drops,
            'groups'     => $groups,
        ];
    }

    private function render_ddmarker(\question_definition $question): array {
        $markers = [];
        foreach ($question->get_ordered_choices(1) as $choice) {
            $markers[] = [
                'no'       => (int) $choice->no,
                'text'     => $this->plain($choice->text),
                'infinite' => (bool) $choice->infinite,
                // How many copies of this marker can be placed (0 = no limit).
                'limit'    => $choice->infinite ? 0 : max(1, (int) $choice->noofdrags),
            ];
        }
        // The drop zones are the answer, so they are never sent.
        return [
            'background' => ($this->image)((int) $question->contextid, 'qtype_ddmarker', 'bgimage', (int) $question->id),
            'markers'    => $markers,
        ];
    }

    private function render_ordering(\question_definition $question): array {
        $items = [];
        foreach ($question->currentresponse as $answerid) {
            $answer = $question->answers[$answerid];
            $c = $this->html($answer->answer, $question, 'question', 'answer', (int) $answerid);
            $items[] = ['key' => $answer->md5key, 'text' => $c['text'], 'images' => $c['images']];
        }
        return [
            'layout' => (int) $question->layouttype === 1 ? 'horizontal' : 'vertical',
            'items'  => $items,
        ];
    }

    private function render_multianswer(\question_definition $question, \question_attempt $qa): array {
        $segments = [];
        $parts = preg_split('/\{#(\d+)\}/', $question->questiontext, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $segments[] = ['type' => 'sub', 'index' => (int) $part];
            } else if (($text = $this->fragment($part)) !== '') {
                $segments[] = ['type' => 'text', 'text' => $text];
            }
        }
        $subs = [];
        foreach ($question->subquestions as $i => $sub) {
            $type = $sub->qtype->name();
            $row = ['index' => (int) $i, 'type' => $type, 'weight' => (float) $sub->defaultmark];
            if ($type === 'multichoice') {
                $row += $this->render_choice($sub, $qa);
                $row['layout'] = [0 => 'dropdown', 1 => 'vertical', 2 => 'horizontal'][(int) $sub->layout] ?? 'dropdown';
            } else {
                $row['input'] = $type === 'numerical' ? 'number' : 'text';
            }
            $subs[] = $row;
        }
        return ['segments' => $segments, 'subquestions' => $subs];
    }

    /**
     * Choices of every gap group, in the attempt's (shuffled) order.
     * `value` is what the app sends back for a gap.
     */
    private function groups(\question_definition $question): array {
        $groups = [];
        foreach ($question->choices as $group => $notused) {
            foreach ($question->get_ordered_choices($group) as $value => $choice) {
                $groups[(int) $group][] = [
                    'value'    => (int) $value,
                    'text'     => $this->plain($choice->text),
                    'infinite' => !empty($choice->infinite),
                ];
            }
        }
        return $groups;
    }

    // ── App answer → engine response ─────────────────────────────────────────────

    /**
     * Convert the app's answer into the response array the question engine stores.
     *
     * @param mixed $answer decoded JSON from the app (null clears the answer)
     * @throws \moodle_exception invalidquizanswer when the answer does not fit the question
     */
    public function to_response(\question_attempt $qa, $answer): array {
        $question = $qa->get_question();
        if ($answer === null) {
            // Blank every field: the engine skips a step with no data when it reads the
            // latest answer, so an empty response would leave the old answer in place.
            return array_fill_keys(array_keys($question->get_expected_data()), '');
        }
        return $this->response_for($question, $qa, $answer);
    }

    private function response_for(\question_definition $question, \question_attempt $qa, $answer): array {
        $type = $question->qtype->name();
        switch ($type) {
            case 'multichoice':
            case 'calculatedmulti':
                return $this->choice_response($question, $qa, $answer);

            case 'truefalse':
                if (is_bool($answer)) {
                    return ['answer' => $answer ? 1 : 0];
                }
                $id = $this->int($answer, $type);
                if ($id === (int) $question->trueanswerid) {
                    return ['answer' => 1];
                }
                if ($id === (int) $question->falseanswerid) {
                    return ['answer' => 0];
                }
                throw self::invalid($type, 'not one of the options');

            case 'shortanswer':
                return ['answer' => $this->text($answer, $type)];

            case 'numerical':
            case 'calculated':
            case 'calculatedsimple':
                return $this->numerical_response($question, $answer);

            case 'essay':
                $response = ['answer' => $this->text($answer, $type), 'answerformat' => FORMAT_PLAIN];
                return array_intersect_key($response, $question->get_expected_data());

            case 'match':
            case 'randomsamatch':
                return $this->match_response($question, $answer);

            case 'gapselect':
            case 'ddwtos':
            case 'ddimageortext':
                return $this->gaps_response($question, $answer);

            case 'ddmarker':
                return $this->marker_response($question, $answer);

            case 'ordering':
                return $this->ordering_response($question, $answer);

            case 'multianswer':
                return $this->multianswer_response($question, $qa, $answer);

            case 'description':
                return [];
        }
        throw self::invalid($type, 'this question type cannot be answered in the app');
    }

    private function choice_response(\question_definition $question, \question_attempt $qa, $answer): array {
        $order = array_map('intval', $question->get_order($qa));
        $single = $question instanceof \qtype_multichoice_single_question;
        $type = $question->qtype->name();
        if ($single) {
            $key = array_search($this->int($answer, $type), $order, true);
            if ($key === false) {
                throw self::invalid($type, 'not one of the options');
            }
            return ['answer' => $key];
        }
        $ids = array_map(fn($id) => $this->int($id, $type), is_array($answer) ? $answer : [$answer]);
        if (array_diff($ids, $order)) {
            throw self::invalid($type, 'not one of the options');
        }
        $response = [];
        foreach ($order as $key => $id) {
            $response['choice' . $key] = in_array($id, $ids, true) ? 1 : 0;
        }
        return $response;
    }

    private function numerical_response(\question_definition $question, $answer): array {
        $type = $question->qtype->name();
        $unit = null;
        if (is_array($answer)) {
            $unit = isset($answer['unit']) ? $this->text($answer['unit'], $type) : null;
            $answer = $answer['value'] ?? null;
        }
        $response = ['answer' => $this->text($answer, $type)];
        if ($question->has_separate_unit_field()) {
            if ($unit !== null && $unit !== '' && !in_array($unit, array_map('strval', $question->ap->get_unit_options()), true)) {
                throw self::invalid($type, 'unknown unit');
            }
            $response['unit'] = (string) $unit;
        }
        return $response;
    }

    private function match_response(\question_definition $question, $answer): array {
        $type = $question->qtype->name();
        $answer = $this->map($answer, $type);
        $stemorder = array_map('intval', $question->get_stem_order());
        $choiceorder = array_map('intval', $question->get_choice_order());
        $response = [];
        foreach ($answer as $stemid => $choiceid) {
            $key = array_search((int) $stemid, $stemorder, true);
            $position = array_search($this->int($choiceid, $type), $choiceorder, true);
            if ($key === false || $position === false) {
                throw self::invalid($type, 'unknown stem or choice');
            }
            $response['sub' . $key] = $position;
        }
        foreach ($stemorder as $key => $notused) {
            $response += ['sub' . $key => 0];
        }
        return $response;
    }

    private function gaps_response(\question_definition $question, $answer): array {
        $type = $question->qtype->name();
        $response = [];
        foreach ($this->map($answer, $type) as $place => $value) {
            if (!isset($question->places[$place])) {
                throw self::invalid($type, 'unknown gap ' . $place);
            }
            $group = is_object($question->places[$place]) ? $question->places[$place]->group : $question->places[$place];
            $value = $this->int($value, $type);
            if ($value !== 0 && !array_key_exists($value, $question->get_ordered_choices($group))) {
                throw self::invalid($type, 'choice ' . $value . ' does not fit gap ' . $place);
            }
            $response['p' . $place] = $value;
        }
        foreach ($question->places as $place => $notused) {
            $response += ['p' . $place => 0];
        }
        return $response;
    }

    private function marker_response(\question_definition $question, $answer): array {
        $type = $question->qtype->name();
        $markers = [];
        foreach ($question->get_ordered_choices(1) as $choice) {
            $markers[(int) $choice->no] = $choice;
        }
        $response = [];
        foreach ($this->map($answer, $type) as $no => $points) {
            if (!isset($markers[(int) $no]) || !is_array($points)) {
                throw self::invalid($type, 'unknown marker ' . $no);
            }
            $choice = $markers[(int) $no];
            if (!$choice->infinite && count($points) > max(1, (int) $choice->noofdrags)) {
                throw self::invalid($type, 'marker ' . $no . ' placed too many times');
            }
            $coords = [];
            foreach ($points as $point) {
                if (!is_array($point) || count($point) !== 2) {
                    throw self::invalid($type, 'a point is [x, y]');
                }
                $coords[] = $this->int($point[0], $type) . ',' . $this->int($point[1], $type);
            }
            $response['c' . (int) $no] = implode(';', $coords);
        }
        foreach ($markers as $no => $notused) {
            $response += ['c' . $no => ''];
        }
        return $response;
    }

    private function ordering_response(\question_definition $question, $answer): array {
        $type = $question->qtype->name();
        if (!is_array($answer)) {
            throw self::invalid($type, 'send the item keys in order');
        }
        $keys = array_map(fn($k) => $this->text($k, $type), array_values($answer));
        $current = [];
        foreach ($question->currentresponse as $answerid) {
            $current[] = $question->answers[$answerid]->md5key;
        }
        $sorted = $keys;
        sort($sorted);
        sort($current);
        if ($sorted !== $current) {
            throw self::invalid($type, 'every item must appear exactly once');
        }
        return [$question->get_response_fieldname() => implode(',', $keys)];
    }

    private function multianswer_response(\question_definition $question, \question_attempt $qa, $answer): array {
        $type = $question->qtype->name();
        $response = [];
        foreach ($this->map($answer, $type) as $index => $subanswer) {
            if (!isset($question->subquestions[$index])) {
                throw self::invalid($type, 'unknown part ' . $index);
            }
            if ($subanswer === null) {
                continue;
            }
            foreach ($this->response_for($question->subquestions[$index], $qa, $subanswer) as $name => $value) {
                $response['sub' . $index . '_' . $name] = $value;
            }
        }
        return $response;
    }

    // ── Engine response → app answer ─────────────────────────────────────────────

    /**
     * The app-format answer held in an engine response (null when nothing was answered).
     */
    public function from_response(\question_attempt $qa, array $response) {
        return $this->answer_from($qa->get_question(), $qa, $response);
    }

    private function answer_from(\question_definition $question, \question_attempt $qa, array $response) {
        switch ($question->qtype->name()) {
            case 'multichoice':
            case 'calculatedmulti':
                $order = array_map('intval', $question->get_order($qa));
                if ($question instanceof \qtype_multichoice_single_question) {
                    $key = $response['answer'] ?? '';
                    return ($key !== '' && $key !== null && $key >= 0 && isset($order[(int) $key])) ? $order[(int) $key] : null;
                }
                $ids = [];
                foreach ($order as $key => $id) {
                    if (!empty($response['choice' . $key])) {
                        $ids[] = $id;
                    }
                }
                return $ids ?: null;

            case 'truefalse':
                if (!isset($response['answer']) || $response['answer'] === '') {
                    return null;
                }
                return (int) ($response['answer'] ? $question->trueanswerid : $question->falseanswerid);

            case 'shortanswer':
            case 'essay':
                return isset($response['answer']) && $response['answer'] !== '' ? (string) $response['answer'] : null;

            case 'numerical':
            case 'calculated':
            case 'calculatedsimple':
                if (!isset($response['answer']) || $response['answer'] === '') {
                    return null;
                }
                return $question->has_separate_unit_field()
                    ? ['value' => (string) $response['answer'], 'unit' => (string) ($response['unit'] ?? '')]
                    : (string) $response['answer'];

            case 'match':
            case 'randomsamatch':
                $choiceorder = $question->get_choice_order();
                $out = [];
                foreach ($question->get_stem_order() as $key => $stemid) {
                    $position = (int) ($response['sub' . $key] ?? 0);
                    if ($position && isset($choiceorder[$position])) {
                        $out[(string) $stemid] = (int) $choiceorder[$position];
                    }
                }
                return $out ?: null;

            case 'gapselect':
            case 'ddwtos':
            case 'ddimageortext':
                $out = [];
                foreach ($question->places as $place => $notused) {
                    if (!empty($response['p' . $place])) {
                        $out[(string) $place] = (int) $response['p' . $place];
                    }
                }
                return $out ?: null;

            case 'ddmarker':
                $out = [];
                foreach ($question->get_ordered_choices(1) as $choice) {
                    $value = trim((string) ($response['c' . $choice->no] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $out[(string) $choice->no] = array_map(
                        fn($xy) => array_map('intval', explode(',', $xy)), explode(';', $value));
                }
                return $out ?: null;

            case 'ordering':
                $value = (string) ($response[$question->get_response_fieldname()] ?? '');
                return $value === '' ? null : explode(',', $value);

            case 'multianswer':
                $out = [];
                foreach ($question->subquestions as $index => $sub) {
                    $prefix = 'sub' . $index . '_';
                    $subresponse = [];
                    foreach ($response as $name => $value) {
                        if (strpos($name, $prefix) === 0) {
                            $subresponse[substr($name, strlen($prefix))] = $value;
                        }
                    }
                    $value = $this->answer_from($sub, $qa, $subresponse);
                    if ($value !== null) {
                        $out[(string) $index] = $value;
                    }
                }
                return $out ?: null;
        }
        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Stored HTML → text + image URLs. $question null = no file area to resolve.
     */
    private function html($html, ?\question_definition $question, string $component, string $filearea, int $itemid): array {
        return ($this->content)((string) $html, $question ? (int) $question->contextid : 0, $component, $filearea, $itemid,
            $question ? (int) $question->id : 0);
    }

    /** Short label HTML (a choice, a drop label) → plain text. */
    private function plain($html): string {
        return trim(\html_to_text((string) $html, 0, false));
    }

    /**
     * A piece of text between two gaps: plain text, keeping the single space on either
     * side so "I " + gap + " home" does not collapse into "Igaphome".
     */
    private function fragment($html): string {
        $raw = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(\html_to_text((string) $html, 0, false));
        if ($text === '') {
            return trim($raw) === '' && $raw !== '' ? ' ' : '';
        }
        return (preg_match('/^\s/u', $raw) ? ' ' : '') . $text . (preg_match('/\s$/u', $raw) ? ' ' : '');
    }

    private function int($value, string $type): int {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
            return (int) $value;
        }
        throw self::invalid($type, 'expected a whole number');
    }

    private function text($value, string $type): string {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }
        throw self::invalid($type, 'expected text');
    }

    private function map($value, string $type): array {
        if (!is_array($value)) {
            throw self::invalid($type, 'expected an object');
        }
        return $value;
    }

    private static function invalid(string $type, string $why): \moodle_exception {
        return new \moodle_exception('invalidquizanswer', 'local_academy', '', $type . ': ' . $why);
    }
}
