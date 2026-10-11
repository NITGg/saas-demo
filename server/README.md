# Files that run on the shared Jitsi server (not in Moodle)

They reach the server with the repo (`git pull`); copy them into the Jitsi containers.

- `jitsi/recorder-teacher-only.js` — appended to `/config/custom-config.js` (and the live
  `/config/config.js`) in `academy_jitsi_web`. In Jibri's window, in `nit_*` rooms only, the
  recording shows the teacher (or their screen share) and never a student.
- `jibri/finalize.sh` — `/config/finalize.sh` in `academy_jibri` (sends each recording to
  its academy's `mod/jitsi/record_notify.php`). Academies not at `https://<slug>.academy2026.nitg-eg.com`
  are listed in `/config/saas-urls.conf` as `<slug> <wwwroot>`.
