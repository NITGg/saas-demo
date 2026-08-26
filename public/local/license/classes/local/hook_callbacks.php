<?php
namespace local_license\local;

use local_license\license;
use local_license\enforcer;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforcement callbacks: lock an expired academy, and block creating more than
 * the tier allows — all from a single early hook, so no Moodle core is touched.
 */
class hook_callbacks {

    /**
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $SCRIPT;

        // Never interfere with CLI, AJAX or web-service requests.
        if (CLI_SCRIPT
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)) {
            return;
        }

        // Resolve the current script path from every source Moodle populates —
        // $SCRIPT can be empty this early on some entry points, and if it is the
        // exemption below would fail and even /local/license/expired.php would be
        // redirected to itself (ERR_TOO_MANY_REDIRECTS). SCRIPT_NAME/PHP_SELF are
        // reliable because the Moodle docroot is public/.
        $paths = [
            (string) ($SCRIPT ?? ''),
            (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
            (string) ($_SERVER['PHP_SELF'] ?? ''),
        ];

        // Always let these through so an admin can still recover / upgrade / log
        // in, and so ANYONE can see the suspended/expired notice itself.
        foreach (['/login/', '/admin/', '/local/license/'] as $allow) {
            foreach ($paths as $p) {
                if ($p !== '' && strpos($p, $allow) !== false) {
                    return;
                }
            }
        }

        // The tier-enforcement checks below key off the exact script path. Use the
        // first non-empty resolved path (normally $SCRIPT).
        $script = '';
        foreach ($paths as $p) {
            if ($p !== '') { $script = $p; break; }
        }

        // 0) Admin suspend — locks the whole academy regardless of tier enforcement.
        if (license::is_suspended()) {
            redirect(new \moodle_url('/local/license/expired.php', ['reason' => 'suspended']));
        }

        // Everything below is tier enforcement — only when the master switch is on.
        if (!license::is_enforced()) {
            return;
        }

        // 1) Expiry lock — send everyone to the upgrade page.
        if (license::is_expired()) {
            redirect(new \moodle_url('/local/license/expired.php'));
        }

        // 2) Quantity guards at the creation entry points.
        if ($script === '/course/modedit.php') {
            $add = optional_param('add', '', PARAM_ALPHANUMEXT);
            if ($add !== '') {
                $courseid = optional_param('course', SITEID, PARAM_INT);
                $courseurl = new \moodle_url('/course/view.php', ['id' => $courseid]);

                // Feature-gated module type (e.g. DRM video is Professional-only).
                $reqfeature = license::module_requires_feature($add);
                if ($reqfeature && !license::has_feature($reqfeature)) {
                    self::block($courseurl, get_string('feature_locked', 'local_license', [
                        'type' => $add,
                        'tier' => license::tiername(),
                    ]));
                }

                // Video source — the DRM/VdoCipher activity needs a tier whose
                // video source allows it (external YouTube/Vimeo links are gated
                // in the app, which Moodle can't distinguish at add-time).
                if (!license::video_allowed($add)) {
                    self::block($courseurl, get_string('video_source_locked', 'local_license', [
                        'type'   => $add,
                        'source' => license::video_source(),
                        'tier'   => license::tiername(),
                    ]));
                }

                // Quantity limit for this activity type (per course).
                if (!enforcer::can_add_activity($add, $courseid)) {
                    self::block($courseurl, get_string('limit_activity', 'local_license', [
                        'type' => $add,
                        'tier' => license::tiername(),
                    ]));
                }
            }
        } else if ($script === '/course/edit.php') {
            // A new course form has no course id (id=0); editing an existing one
            // sets id. Block the new-course form whenever we're at the cap — do
            // NOT also require a category in the URL, because several entry points
            // open /course/edit.php without one (and the category is then chosen
            // in the form), which used to slip past the guard.
            $id = optional_param('id', 0, PARAM_INT);
            if (!$id && !enforcer::can_add_course()) {
                self::block(
                    new \moodle_url('/course/management.php'),
                    get_string('limit_course', 'local_license', license::tiername())
                );
            }
        }
    }

    /**
     * Redirect back with an "upgrade to add more" message.
     *
     * @param \moodle_url $to
     * @param string $message
     */
    protected static function block(\moodle_url $to, string $message): void {
        redirect($to, $message, null, \core\output\notification::NOTIFY_ERROR);
    }

    /**
     * Front-end lock for the teacher cap: when the academy is already at its
     * teacher limit, grey out the "Teacher" option in the enrol/assign role
     * pickers on the participants page so an admin can't pick it in the first
     * place. This is a UX nicety only — the {@see observers::role_assigned}
     * backstop still reverts any teacher role that slips through (e.g. scripted
     * or from an older cached page), so the limit is enforced either way.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook
     */
    public static function before_standard_footer(
        \core\hook\output\before_standard_footer_html_generation $hook): void {
        global $SCRIPT, $DB;

        if (CLI_SCRIPT
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)) {
            return;
        }
        if (!license::is_enforced()) {
            return;
        }
        // Only the participants page hosts the enrol / assign-role pickers.
        if ((string) ($SCRIPT ?? '') !== '/user/index.php') {
            return;
        }
        $max = license::max_teachers();
        if ($max < 0 || enforcer::count_teachers() < $max) {
            return; // unlimited, or seats still free — nothing to lock.
        }
        $teacherroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$teacherroleid) {
            return;
        }

        $rid = json_encode($teacherroleid, JSON_HEX_TAG | JSON_HEX_AMP);
        $msg = json_encode(
            get_string('limit_teacher', 'local_license', ['tier' => license::tiername(), 'max' => $max]),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        // Disable any "Teacher" <option> in role-picker selects (enrol modal loads
        // async, so also watch the DOM). Scoped to role-named selects to avoid
        // touching unrelated dropdowns that happen to share the numeric value.
        $js = <<<JS
<script>
(function(){
  var RID=$rid, MSG=$msg;
  function patch(){
    document.querySelectorAll('select[name*="role" i],select[id*="role" i]').forEach(function(sel){
      var changed=false;
      Array.prototype.forEach.call(sel.options,function(o){
        if(String(o.value)===String(RID)&&!o.dataset.licLocked){
          o.dataset.licLocked='1'; o.disabled=true; o.title=MSG;
          o.text=o.text+' (max reached)';
          changed=true;
        }
      });
      if(changed&&String(sel.value)===String(RID)){
        for(var i=0;i<sel.options.length;i++){ if(!sel.options[i].disabled){ sel.selectedIndex=i; break; } }
      }
    });
  }
  document.addEventListener('DOMContentLoaded',patch);
  try{ new MutationObserver(patch).observe(document.documentElement,{childList:true,subtree:true}); }catch(e){}
  patch();
})();
</script>
JS;
        $hook->add_html($js);
    }
}
