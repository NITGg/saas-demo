<?php
/**
 * Library of interface functions for mod_vdocipher.
 *
 * A "VdoCipher Video" activity: one secure DRM video per instance. The video is
 * hosted on VdoCipher; we store its id and keep the shared local_vdocipher_videos
 * mapping (keyed by course-module id) in sync so the playback OTP endpoint and the
 * mobile course API resolve it.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare which features the module supports.
 *
 * @param string $feature
 * @return mixed
 */
function vdocipher_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false; // backup/restore not implemented in this version
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_CONTENT') ? MOD_PURPOSE_CONTENT : null;
        default:
            return null;
    }
}

/**
 * Create a new VdoCipher Video instance.
 *
 * @param stdClass $data form data (includes ->coursemodule = cmid)
 * @param mod_vdocipher_mod_form|null $mform
 * @return int new instance id
 */
function vdocipher_add_instance($data, $mform = null) {
    global $DB;

    $data->videoid      = vdocipher_resolve_videoid($data);
    $data->timemodified = time();
    $data->intro        = $data->intro ?? '';
    $data->introformat  = $data->introformat ?? FORMAT_HTML;

    $id = $DB->insert_record('vdocipher', $data);

    vdocipher_sync_mapping((int) $data->coursemodule, (int) $data->course, $data->videoid, $data->name);
    return $id;
}

/**
 * Update an existing VdoCipher Video instance.
 *
 * @param stdClass $data form data (includes ->coursemodule = cmid, ->instance)
 * @param mod_vdocipher_mod_form|null $mform
 * @return bool
 */
function vdocipher_update_instance($data, $mform = null) {
    global $DB;

    $data->id           = $data->instance;
    $data->videoid      = vdocipher_resolve_videoid($data);
    $data->timemodified = time();

    $DB->update_record('vdocipher', $data);

    vdocipher_sync_mapping((int) $data->coursemodule, (int) $data->course, $data->videoid, $data->name);
    return true;
}

/**
 * Delete a VdoCipher Video instance: the row, the mapping, and (best-effort) the
 * video on VdoCipher itself.
 *
 * @param int $id instance id
 * @return bool
 */
function vdocipher_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('vdocipher', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    // Remove the shared mapping row(s) for this activity's course module.
    if ($cm = get_coursemodule_from_instance('vdocipher', $id, 0, false, IGNORE_MISSING)) {
        $DB->delete_records('local_vdocipher_videos', ['cmid' => $cm->id]);
    }

    // Best-effort: delete the video from VdoCipher so it doesn't orphan.
    if (!empty($instance->videoid) && \local_vdocipher\api_client::is_configured()) {
        try {
            (new \local_vdocipher\api_client())->delete_videos([$instance->videoid]);
        } catch (\Throwable $e) {
            debugging('mod_vdocipher: could not delete remote video ' . $instance->videoid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    $DB->delete_records('vdocipher', ['id' => $id]);
    return true;
}

/**
 * Resolve the video id for a form submission.
 *
 * The file is uploaded directly from the browser to VdoCipher (see mod_form),
 * which fills the videoid field — so here we just take that id. No bytes pass
 * through PHP, avoiding memory/timeout limits on large videos.
 *
 * @param stdClass $data
 * @return string
 */
function vdocipher_resolve_videoid($data): string {
    return trim($data->videoid ?? '');
}

/**
 * Upsert the shared local_vdocipher_videos mapping for a course module.
 *
 * @param int $cmid
 * @param int $courseid
 * @param string $videoid
 * @param string $title
 */
function vdocipher_sync_mapping(int $cmid, int $courseid, string $videoid, string $title): void {
    global $DB, $USER;

    if ($videoid === '') {
        // No video set — clear any stale mapping.
        $DB->delete_records('local_vdocipher_videos', ['cmid' => $cmid]);
        return;
    }

    $now = time();
    $existing = $DB->get_record('local_vdocipher_videos', ['cmid' => $cmid]);
    if ($existing) {
        $existing->videoid      = $videoid;
        $existing->courseid     = $courseid;
        $existing->title        = core_text::substr($title, 0, 255);
        $existing->usermodified = (int) $USER->id;
        $existing->timemodified = $now;
        $DB->update_record('local_vdocipher_videos', $existing);
    } else {
        $DB->insert_record('local_vdocipher_videos', (object) [
            'videoid'      => $videoid,
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'title'        => core_text::substr($title, 0, 255),
            'status'       => 'Processing',
            'length'       => 0,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
}
