<?php
/**
 * The edit form for a VdoCipher Video activity.
 *
 * Video files are uploaded straight from the browser to VdoCipher's S3 (see the
 * inline uploader below) — the bytes never pass through PHP, so there is no
 * memory/timeout limit and large files work. The upload fills the read-only
 * "video id" field, which is all the form actually saves. A teacher can also
 * paste an existing id instead.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_vdocipher_mod_form extends moodleform_mod {

    public function definition() {
        global $COURSE, $CFG;

        $mform = $this->_form;

        // ── General ──────────────────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Video source ─────────────────────────────────────────────────────
        $mform->addElement('header', 'videosource', get_string('videosource', 'vdocipher'));
        $mform->setExpanded('videosource', true);

        // Browser → S3 uploader (no bytes through PHP).
        $credurl = (new moodle_url('/local/vdocipher/upload_credentials.php'))->out(false);
        $courseid = (int) $COURSE->id;
        $sesskey  = sesskey();
        $struploading = get_string('videofile', 'vdocipher');

        $uploaderhtml = '
<div id="vdo-uploader" data-credurl="' . s($credurl) . '" data-courseid="' . $courseid . '" data-sesskey="' . s($sesskey) . '"
     style="border:1px dashed var(--bs-border-color,#adb5bd);border-radius:.5rem;padding:1rem;margin:.25rem 0;">
  <input type="file" id="vdo-file" accept="video/*" style="display:block;margin-bottom:.5rem;">
  <div style="background:var(--bs-secondary-bg,#e9ecef);border-radius:.25rem;height:8px;overflow:hidden;margin:.5rem 0;">
    <div id="vdo-bar" style="height:8px;width:0;background:var(--bs-primary,#0d6efd);transition:width .2s;"></div>
  </div>
  <div id="vdo-status" style="font-size:.9em;color:var(--bs-secondary-color,#6c757d);">Choose a video to upload it securely to VdoCipher.</div>
</div>';
        $mform->addElement('static', 'videouploader', get_string('videofile', 'vdocipher'), $uploaderhtml);
        $mform->addHelpButton('videouploader', 'videofile', 'vdocipher');

        // The video id — filled by the uploader, or pasted manually.
        $mform->addElement('text', 'videoid', get_string('videoid', 'vdocipher'), ['size' => 48]);
        $mform->setType('videoid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('videoid', 'videoid', 'vdocipher');

        // ── Standard elements ────────────────────────────────────────────────
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        // Wire up the uploader (vanilla JS; no AMD build step needed).
        $mform->addElement('html', $this->uploader_script());
    }

    /**
     * Require a video id (uploaded or pasted), unless editing keeps the existing one.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $hasid   = !empty(trim($data['videoid'] ?? ''));
        $editing = !empty($this->_instance);
        if (!$hasid && !$editing) {
            $errors['videoid'] = get_string('err_novideosource', 'vdocipher');
        }
        return $errors;
    }

    /**
     * Self-contained uploader script: file → credentials → direct S3 POST → set id.
     *
     * @return string
     */
    protected function uploader_script(): string {
        return <<<'HTML'
<script>
(function () {
    var box = document.getElementById('vdo-uploader');
    if (!box) { return; }
    var input  = document.getElementById('vdo-file');
    var status = document.getElementById('vdo-status');
    var bar    = document.getElementById('vdo-bar');
    var field  = document.querySelector('input[name="videoid"]');
    var credurl  = box.getAttribute('data-credurl');
    var courseid = box.getAttribute('data-courseid');
    var sesskey  = box.getAttribute('data-sesskey');

    function say(msg) { status.textContent = msg; }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { return; }
        bar.style.width = '0';
        say('Requesting secure upload…');

        var nameEl = document.querySelector('input[name="name"]');
        var title = (nameEl && nameEl.value.trim()) ? nameEl.value.trim() : file.name;

        var cred = new FormData();
        cred.append('sesskey', sesskey);
        cred.append('courseid', courseid);
        cred.append('title', title);

        fetch(credurl, { method: 'POST', body: cred, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error || !d.videoId || !d.clientPayload || !d.clientPayload.uploadLink) {
                    throw new Error(d.error || 'No upload credentials returned');
                }
                var p = d.clientPayload;
                var s3 = new FormData();
                Object.keys(p).forEach(function (k) {
                    if (k !== 'uploadLink') { s3.append(k, p[k]); }
                });
                if (!('success_action_redirect' in p)) { s3.append('success_action_redirect', ''); }
                if (!('success_action_status' in p)) { s3.append('success_action_status', '201'); }
                s3.append('file', file);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', p.uploadLink, true);
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round(e.loaded / e.total * 100);
                        bar.style.width = pct + '%';
                        say('Uploading… ' + pct + '%');
                    }
                };
                xhr.onload = function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        field.value = d.videoId;
                        bar.style.width = '100%';
                        say('Uploaded ✓ — video id set. Click Save to finish.');
                    } else {
                        say('Upload failed (HTTP ' + xhr.status + '). Try again or paste a video id.');
                    }
                };
                xhr.onerror = function () { say('Upload error (network/CORS). Try again or paste a video id.'); };
                xhr.send(s3);
            })
            .catch(function (err) { say('Error: ' + err.message); });
    });
})();
</script>
HTML;
    }
}
