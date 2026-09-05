<?php
/**
 * Library of interface functions for mod_vimeo.
 *
 * A "Vimeo Video" activity: one Vimeo-hosted video per instance. The video is
 * hosted on Vimeo (shared platform account); we store its id (+ optional privacy
 * hash) and keep the shared local_vimeo_videos mapping (keyed by course-module
 * id) in sync so the embed endpoint and the mobile course API resolve it.
 *
 * Mirrors mod_vdocipher, but Vimeo playback is a domain-private embed (no OTP).
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare which features the module supports.
 *
 * @param string $feature
 * @return mixed
 */
function vimeo_supports($feature) {
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
 * Create a new Vimeo Video instance.
 *
 * @param stdClass $data form data (includes ->coursemodule = cmid)
 * @param mod_vimeo_mod_form|null $mform
 * @return int new instance id
 */
function vimeo_add_instance($data, $mform = null) {
    global $DB;

    $data->videoid      = vimeo_resolve_videoid($data);
    $data->videohash    = trim($data->videohash ?? '');
    $data->timemodified = time();
    $data->intro        = $data->intro ?? '';
    $data->introformat  = $data->introformat ?? FORMAT_HTML;

    $id = $DB->insert_record('vimeo', $data);

    vimeo_sync_mapping((int) $data->coursemodule, (int) $data->course, $data->videoid, $data->videohash, $data->name);
    return $id;
}

/**
 * Update an existing Vimeo Video instance.
 *
 * @param stdClass $data form data (includes ->coursemodule = cmid, ->instance)
 * @param mod_vimeo_mod_form|null $mform
 * @return bool
 */
function vimeo_update_instance($data, $mform = null) {
    global $DB;

    $data->id           = $data->instance;
    $data->videoid      = vimeo_resolve_videoid($data);
    $data->videohash    = trim($data->videohash ?? '');
    $data->timemodified = time();

    $DB->update_record('vimeo', $data);

    vimeo_sync_mapping((int) $data->coursemodule, (int) $data->course, $data->videoid, $data->videohash, $data->name);
    return true;
}

/**
 * Delete a Vimeo Video instance: the row, the mapping, and (best-effort) the
 * video on Vimeo itself.
 *
 * @param int $id instance id
 * @return bool
 */
function vimeo_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('vimeo', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    // Remove the shared mapping row(s) for this activity's course module.
    if ($cm = get_coursemodule_from_instance('vimeo', $id, 0, false, IGNORE_MISSING)) {
        $DB->delete_records('local_vimeo_videos', ['cmid' => $cm->id]);
    }

    // Best-effort: delete the video from Vimeo so it doesn't orphan.
    if (!empty($instance->videoid) && \local_vimeo\api_client::is_configured()) {
        try {
            (new \local_vimeo\api_client())->delete_video($instance->videoid);
        } catch (\Throwable $e) {
            debugging('mod_vimeo: could not delete remote video ' . $instance->videoid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    $DB->delete_records('vimeo', ['id' => $id]);
    return true;
}

/**
 * Resolve the video id for a form submission.
 *
 * The file is uploaded directly from the browser to Vimeo (see mod_form), which
 * fills the videoid field — so here we just take that id. No bytes pass through
 * PHP, avoiding memory/timeout limits on large videos.
 *
 * @param stdClass $data
 * @return string
 */
function vimeo_resolve_videoid($data): string {
    // Accept a bare numeric id or a full Vimeo URL the teacher pasted.
    $raw = trim($data->videoid ?? '');
    if ($raw === '') {
        return '';
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $raw, $m)) {
        return $m[1];
    }
    return preg_replace('/\D+/', '', $raw); // keep digits only
}

/**
 * Upsert the shared local_vimeo_videos mapping for a course module.
 *
 * @param int $cmid
 * @param int $courseid
 * @param string $videoid
 * @param string $videohash
 * @param string $title
 */
function vimeo_sync_mapping(int $cmid, int $courseid, string $videoid, string $videohash, string $title): void {
    global $DB, $USER;

    if ($videoid === '') {
        // No video set — clear any stale mapping.
        $DB->delete_records('local_vimeo_videos', ['cmid' => $cmid]);
        return;
    }

    $now = time();
    // videoid is UNIQUE. The upload step (local_vimeo/upload_credentials.php →
    // video_service::create_upload) already inserted a PENDING row for this
    // videoid with cmid=0. Claim that row here instead of inserting a duplicate
    // (which fails with "Duplicate entry … for key …vid_uix"). Fall back to the
    // cmid's current row (the activity's video was changed), else insert fresh.
    $row = $DB->get_record('local_vimeo_videos', ['videoid' => $videoid]);
    if (!$row) {
        $row = $DB->get_record('local_vimeo_videos', ['cmid' => $cmid]);
    }
    if ($row) {
        $row->videoid      = $videoid;
        $row->videohash    = $videohash;
        $row->cmid         = $cmid;
        $row->courseid     = $courseid;
        $row->title        = core_text::substr($title, 0, 255);
        $row->usermodified = (int) $USER->id;
        $row->timemodified = $now;
        $DB->update_record('local_vimeo_videos', $row);
        $keepid = (int) $row->id;
    } else {
        $keepid = (int) $DB->insert_record('local_vimeo_videos', (object) [
            'videoid'      => $videoid,
            'videohash'    => $videohash,
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
    // Drop any OTHER row still attached to this cmid (e.g. after the activity's
    // video was swapped) so a course module maps to exactly one video.
    $DB->delete_records_select('local_vimeo_videos', 'cmid = ? AND id <> ?', [$cmid, $keepid]);
}
