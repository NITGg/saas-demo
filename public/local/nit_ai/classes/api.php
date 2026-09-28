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

namespace local_nit_ai;

/**
 * Storage and status for video transcripts.
 *
 * Everything is keyed on cmid, never on a video provider: mod_vdocipher is one
 * possible source, not the shape of the data. Swapping provider means writing a
 * new player adapter in JS, not touching this.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** @var string Table holding one transcript per course module. */
    public const TABLE = 'local_nit_ai_transcripts';

    /** @var string File area the uploaded transcript is kept in. */
    public const FILEAREA = 'transcript';

    /** @var string The AI placement that carries the site-wide switch for this feature. */
    public const PLACEMENT = 'aiplacement_nit_videoassist';

    /** @var int Tolerated gap, in seconds, between the last cue and the video's end. */
    public const LENGTH_TOLERANCE = 120;

    /**
     * Fetch the transcript row for a course module.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    public static function get(int $cmid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['cmid' => $cmid]) ?: null;
    }

    /**
     * Save the assistant settings and, when a new file was uploaded, its parsed
     * transcript.
     *
     * Uploading a new transcript clears the approval: whoever uploads reviews.
     *
     * @param int $cmid
     * @param int $courseid
     * @param string $component owning module, e.g. mod_vdocipher
     * @param string $sourceref provider video id at the time of saving
     * @param int $sourcelength video duration in seconds (0 when unknown)
     * @param bool $enabled teacher wants the assistant on this activity
     * @param bool $hasonscreen the transcript includes on-screen text
     * @param string|null $content raw transcript file, or null to keep the stored one
     * @param string $filename original name, used as a format hint
     * @return void
     */
    public static function save(
        int $cmid,
        int $courseid,
        string $component,
        string $sourceref,
        int $sourcelength,
        bool $enabled,
        bool $hasonscreen,
        ?string $content = null,
        string $filename = ''
    ): void {
        global $DB, $USER;

        $now = time();
        $existing = self::get($cmid);

        $record = $existing ?: (object) [
            'cmid'         => $cmid,
            'timecreated'  => $now,
            'approved'     => 0,
            'approvedby'   => 0,
        ];

        $record->courseid     = $courseid;
        $record->component    = $component;
        $record->enabled      = (int) $enabled;
        $record->hasonscreen  = (int) $hasonscreen;
        $record->timemodified = $now;

        if ($content !== null) {
            $parsed = transcript_parser::parse($content, $filename);

            $record->format        = $parsed['format'];
            $record->lang          = $parsed['lang'];
            $record->hastimestamps = (int) $parsed['hastimestamps'];
            $record->segmentcount  = count($parsed['cues']);
            $record->lasttimestamp = $parsed['lasttimestamp'];
            $record->segments      = json_encode($parsed['cues'], JSON_UNESCAPED_UNICODE);
            $record->plaintext     = $parsed['plaintext'];

            // A new transcript describes the video as it is right now, and needs
            // a fresh review before students see it.
            $record->sourceref    = $sourceref;
            $record->sourcelength = $sourcelength;
            $record->approved     = 0;
            $record->approvedby   = 0;
            $record->usermodified = (int) $USER->id;
        } else if ($existing && $sourcelength > 0 && (int) $existing->sourcelength === 0) {
            // The provider only learns the duration once it finishes processing;
            // fill it in so the length check can run on the next look.
            $record->sourcelength = $sourcelength;
        }

        if ($existing) {
            $DB->update_record(self::TABLE, $record);
        } else if ($content !== null) {
            $DB->insert_record(self::TABLE, $record);
        }
        // No existing row and no file: nothing worth storing yet.
    }

    /**
     * Take everything the activity form collected and store it.
     *
     * The one call a module makes from its add/update instance hook, after its
     * own record and any provider mapping are already written — the video id
     * and duration are read back from there.
     *
     * @param object $cm object with id, modname and instance
     * @param \stdClass $data submitted form data
     * @return void
     */
    public static function save_from_module(object $cm, \stdClass $data): void {
        // The form did not ask, so there is nothing to store — and running on
        // anyway would be destructive: a missing filemanager reads as an empty
        // draft area, which file_save_draft_area_files() takes as "delete every
        // file", and a missing checkbox reads as "switch it off".
        if (ui::FORM_HIDDEN) {
            return;
        }

        $context = \context_module::instance((int) $cm->id);
        $change = helper::save_draft_and_detect_change($context, (int) ($data->nitai_transcript ?? 0));
        $describe = source::describe($cm);

        self::save(
            cmid: (int) $cm->id,
            courseid: (int) ($data->course ?? 0),
            component: 'mod_' . $cm->modname,
            sourceref: $describe['ref'],
            sourcelength: $describe['length'],
            enabled: !empty($data->nitai_enabled),
            hasonscreen: !empty($data->nitai_onscreen),
            content: $change['content'] ?? null,
            filename: $change['filename'] ?? ''
        );
    }

    /**
     * Mark the transcript reviewed, which is what lets students see the assistant.
     *
     * @param int $cmid
     * @return void
     */
    public static function approve(int $cmid): void {
        global $DB, $USER;

        $record = self::get($cmid);
        if (!$record) {
            return;
        }
        $record->approved     = 1;
        $record->approvedby   = (int) $USER->id;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Remove everything we hold for a course module.
     *
     * @param int $cmid
     * @return void
     */
    public static function delete(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);

        // The generation runs go with it. They only describe this video, and a
        // quiz they produced stands on its own from here on.
        quizgen\run::delete_for_cm($cmid);

        try {
            $context = \context_module::instance($cmid);
        } catch (\Throwable $e) {
            return; // Module context already gone.
        }
        get_file_storage()->delete_area_files($context->id, 'local_nit_ai', self::FILEAREA);
    }

    /**
     * The cues, decoded.
     *
     * @param \stdClass $record
     * @return array list of {s,e,t}
     */
    public static function cues(\stdClass $record): array {
        $cues = json_decode((string) $record->segments, true);
        return is_array($cues) ? $cues : [];
    }

    /**
     * Has the video been replaced since this transcript was uploaded?
     *
     * The provider hands out a new id for a new upload, so this is a definite
     * answer rather than a guess at timestamps.
     *
     * @param \stdClass $record
     * @param string $currentsourceref
     * @return bool
     */
    public static function is_stale(\stdClass $record, string $currentsourceref): bool {
        return $currentsourceref !== '' && $record->sourceref !== '' && $record->sourceref !== $currentsourceref;
    }

    /**
     * Everything the teacher's review panel needs: what we read out of the file,
     * and every check that could catch a wrong or truncated transcript.
     *
     * @param int $cmid
     * @param string $currentsourceref
     * @param int $currentlength video duration in seconds, 0 when the provider has not reported one
     * @return array|null null when no transcript has been uploaded
     */
    public static function status(int $cmid, string $currentsourceref, int $currentlength): ?array {
        $record = self::get($cmid);
        if (!$record) {
            return null;
        }

        $stale = self::is_stale($record, $currentsourceref);
        $problems = [];
        $lengthcheck = 'skipped';

        if ($stale) {
            $problems[] = get_string('check_stale', 'local_nit_ai');
        }

        if (!$record->hastimestamps) {
            $problems[] = get_string('check_notimestamps', 'local_nit_ai');
        }

        if (!$record->segmentcount && trim((string) $record->plaintext) === '') {
            $problems[] = get_string('check_empty', 'local_nit_ai');
        }

        // Everything below is switched off somewhere else on the site. Each one
        // looks identical from the activity — the assistant simply never appears
        // — so each gets its own line rather than one vague "not working".
        if (!self::placement_enabled()) {
            $problems[] = get_string('check_placementoff', 'local_nit_ai');
        } else if (!self::provider_ready()) {
            $problems[] = get_string('check_noprovider', 'local_nit_ai');
        }

        try {
            if (!self::allowed_in_context(\context_module::instance($cmid))) {
                $problems[] = get_string('check_contextoff', 'local_nit_ai');
            }
        } catch (\Throwable $e) {
            debugging('local_nit_ai: no module context for cmid ' . $cmid, DEBUG_DEVELOPER);
        }

        if ($record->hastimestamps && $currentlength > 0) {
            $gap = $currentlength - (int) $record->lasttimestamp;
            if (abs($gap) > self::LENGTH_TOLERANCE) {
                $lengthcheck = 'failed';
                $problems[] = get_string('check_lengthmismatch', 'local_nit_ai', (object) [
                    'transcript' => helper::timecode((int) $record->lasttimestamp),
                    'video'      => helper::timecode($currentlength),
                ]);
            } else {
                $lengthcheck = 'passed';
            }
        }

        return [
            'record'      => $record,
            'stale'       => $stale,
            'lengthcheck' => $lengthcheck,
            'problems'    => $problems,
            'ready'       => $record->enabled && $record->approved && !$stale && empty($problems),
        ];
    }

    /**
     * Should this user see the assistant on this activity?
     *
     * Every gate lives here on purpose. Today the entitlement question always
     * answers yes — when the assistant becomes a paid-tier feature, this is the
     * one place that changes.
     *
     * @param \cm_info|\stdClass $cm
     * @param \context $context
     * @param string $currentsourceref
     * @return bool
     */
    public static function is_available(object $cm, \context $context, string $currentsourceref = ''): bool {
        if (!has_capability('local/nit_ai:use', $context)) {
            return false;
        }
        if (!self::is_entitled($context)) {
            return false;
        }

        $record = self::get((int) $cm->id);
        if (!$record || !$record->enabled || !$record->approved) {
            return false;
        }
        if (self::is_stale($record, $currentsourceref)) {
            return false;
        }

        return self::placement_enabled()
            && self::provider_ready()
            && self::allowed_in_context($context);
    }

    /**
     * Is the feature switched on for the site?
     *
     * This is the administrator's switch on Site administration > AI > AI
     * placements, plus the per-action toggle underneath it.
     *
     * Takes the placement to ask about, because our features are separate
     * switches: the chat and the quiz generator share a transcript but not an
     * audience or a cost, and an administrator may well want one without the
     * other.
     *
     * @param string $placement placement component, defaults to the video assistant
     * @return bool
     */
    public static function placement_enabled(string $placement = self::PLACEMENT): bool {
        try {
            [$type, $name] = explode('_', \core_component::normalize_componentname($placement), 2);
            $plugininfo = \core_plugin_manager::resolve_plugininfo_class($type);

            if (!$plugininfo::is_plugin_enabled($name)) {
                return false;
            }

            return \core\di::get(\core_ai\manager::class)
                ->is_action_enabled($placement, \core_ai\aiactions\generate_text::class);
        } catch (\Throwable $e) {
            debugging('local_nit_ai: placement check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Is there an AI provider that can actually answer a question?
     *
     * @return bool
     */
    public static function provider_ready(): bool {
        try {
            return \core\di::get(\core_ai\manager::class)
                ->is_action_available(\core_ai\aiactions\generate_text::class);
        } catch (\Throwable $e) {
            debugging('local_nit_ai: AI subsystem unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Has AI been left on for this course and activity?
     *
     * Reads the two "AI tools" switches directly rather than asking
     * \core_ai\manager::is_action_enabled_in_context(). That method also
     * requires generate_text to appear in the activity's enabledaiactions
     * list, and core builds that list from the courseassist and editor
     * placements alone (see course/modlib.php) — ours never contributes to
     * it. So an activity saved while either of those was on ends up with a
     * list holding no generate_text, and the assistant is blocked for good:
     * no setting an administrator can reach undoes it, because the two
     * placements that write the list are exactly the ones we do not use.
     *
     * The enableaitools columns are the half of that setting that really
     * means "AI here, yes or no", so they are the half we honour. Null means
     * never set, which core reads as allowed; only an explicit 0 blocks.
     *
     * @param \context $context
     * @return bool
     */
    public static function allowed_in_context(\context $context): bool {
        global $DB;

        try {
            // The course-level switch covers every AI feature, ours included.
            if (!\core_ai\manager::is_ai_tools_enabled_in_course($context)) {
                return false;
            }

            if ($context->contextlevel == CONTEXT_MODULE) {
                $record = $DB->get_record(
                    table: 'course_modules',
                    conditions: ['id' => $context->instanceid],
                    fields: 'enableaitools',
                    strictness: MUST_EXIST,
                );
                if (!is_null($record->enableaitools) && !$record->enableaitools) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            debugging('local_nit_ai: context check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * The subscription gate, deliberately isolated and deliberately open.
     *
     * @param \context $context
     * @return bool
     */
    public static function is_entitled(\context $context): bool {
        // Phase 1: available to everyone. Wire this to the subscription tier when
        // that decision is made — nothing else needs to change.
        unset($context);
        return true;
    }
}
