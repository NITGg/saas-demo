<?php
/**
 * The edit form for a Vimeo Video activity.
 *
 * Video files are uploaded straight from the browser to Vimeo using a resumable
 * (tus) link — the bytes never pass through PHP, so there is no memory/timeout
 * limit and large files work. The upload fills the read-only "video id" field,
 * which is what the form saves. A teacher can also paste an existing Vimeo id or
 * URL (and, for an unlisted video, its privacy hash) instead of uploading.
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_vimeo_mod_form extends moodleform_mod {

    public function definition() {
        global $COURSE;

        $mform = $this->_form;

        // ── General ──────────────────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Video source ─────────────────────────────────────────────────────
        $mform->addElement('header', 'videosource', get_string('videosource', 'vimeo'));
        $mform->setExpanded('videosource', true);

        // Browser → Vimeo (tus) uploader (no bytes through PHP).
        $credurl  = (new moodle_url('/local/vimeo/upload_credentials.php'))->out(false);
        $courseid = (int) $COURSE->id;
        $sesskey  = sesskey();

        $uploaderhtml = '
<div id="vimeo-uploader" data-credurl="' . s($credurl) . '" data-courseid="' . $courseid . '" data-sesskey="' . s($sesskey) . '"
     style="border:1px dashed var(--bs-border-color,#adb5bd);border-radius:.5rem;padding:1rem;margin:.25rem 0;">
  <input type="file" id="vimeo-file" accept="video/*" style="display:block;margin-bottom:.5rem;">
  <div style="background:var(--bs-secondary-bg,#e9ecef);border-radius:.25rem;height:8px;overflow:hidden;margin:.5rem 0;">
    <div id="vimeo-bar" style="height:8px;width:0;background:var(--bs-primary,#0d6efd);transition:width .2s;"></div>
  </div>
  <div id="vimeo-status" style="font-size:.9em;color:var(--bs-secondary-color,#6c757d);">Choose a video to upload it to Vimeo.</div>
</div>';
        $mform->addElement('static', 'videouploader', get_string('videofile', 'vimeo'), $uploaderhtml);
        $mform->addHelpButton('videouploader', 'videofile', 'vimeo');

        // The video id — filled by the uploader, or pasted manually (id or URL).
        $mform->addElement('text', 'videoid', get_string('videoid', 'vimeo'), ['size' => 48]);
        $mform->setType('videoid', PARAM_TEXT); // may be a URL; lib.php normalises to digits
        $mform->addHelpButton('videoid', 'videoid', 'vimeo');

        // Optional privacy hash for unlisted videos (?h=…).
        $mform->addElement('text', 'videohash', get_string('videohash', 'vimeo'), ['size' => 32]);
        $mform->setType('videohash', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('videohash', 'videohash', 'vimeo');

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
            $errors['videoid'] = get_string('err_novideosource', 'vimeo');
        }
        return $errors;
    }

    /**
     * Self-contained uploader: file → tus link (create_upload) → PATCH bytes → set id.
     *
     * @return string
     */
    protected function uploader_script(): string {
        return <<<'HTML'
<script>
(function () {
    var box = document.getElementById('vimeo-uploader');
    if (!box) { return; }
    var input  = document.getElementById('vimeo-file');
    var status = document.getElementById('vimeo-status');
    var bar    = document.getElementById('vimeo-bar');
    var field  = document.querySelector('input[name="videoid"]');
    var credurl  = box.getAttribute('data-credurl');
    var courseid = box.getAttribute('data-courseid');
    var sesskey  = box.getAttribute('data-sesskey');

    function say(msg) { status.textContent = msg; }

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { return; }
        bar.style.width = '0';
        say('Requesting upload link…');

        var nameEl = document.querySelector('input[name="name"]');
        var title = (nameEl && nameEl.value.trim()) ? nameEl.value.trim() : file.name;

        var cred = new FormData();
        cred.append('sesskey', sesskey);
        cred.append('courseid', courseid);
        cred.append('title', title);
        cred.append('size', file.size);

        fetch(credurl, { method: 'POST', body: cred, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error || !d.videoId || !d.uploadLink) {
                    throw new Error(d.error || 'No upload link returned');
                }
                // tus: PATCH the whole file at offset 0 to the pre-signed link.
                var xhr = new XMLHttpRequest();
                xhr.open('PATCH', d.uploadLink, true);
                xhr.setRequestHeader('Tus-Resumable', '1.0.0');
                xhr.setRequestHeader('Upload-Offset', '0');
                xhr.setRequestHeader('Content-Type', 'application/offset+octet-stream');
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
                xhr.send(file);
            })
            .catch(function (err) { say('Error: ' + err.message); });
    });
})();
</script>
HTML;
    }
}
