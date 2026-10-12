# Files that run on the shared Jitsi server (not in Moodle)

They reach the server with the repo (`git pull`); copy them into the Jitsi containers.

- `jitsi/nit-rooms.js` — appended to `/config/custom-config.js` (and the live
  `/config/config.js`) in `academy_jitsi_web`. In Jibri's window, in `nit_*` rooms only, the
  recording shows the teacher (or their screen share, or the whiteboard while they have it on) and never a student; and, for the academies it lists, the room uses the site's colours, logo, font and icons (`mod/jitsi/branding.php`).
- `jibri/finalize.sh` — `/config/finalize.sh` in `academy_jibri` (sends each recording to
  its academy's `mod/jitsi/record_notify.php`). Academies not at `https://<slug>.academy2026.nitg-eg.com`
  are listed in `/config/saas-urls.conf` as `<slug> <wwwroot>`.

## The whiteboard (Jitsi's own Excalidraw)

Set up in `/var/www/html/academy/docker-compose.yml` (not in this repo):

- service `whiteboard`: `jitsi/excalidraw-backend:21` (matches Jitsi stable-9823), container
  `academy_jitsi_whiteboard`, on the `meet.jitsi` network with the alias `whiteboard.meet.jitsi`;
- on `jitsi-web`: `WHITEBOARD_COLLAB_SERVER_URL_BASE=http://whiteboard.meet.jitsi` (Jitsi's nginx
  then serves it at `/socket.io/`) and `WHITEBOARD_COLLAB_SERVER_PUBLIC_URL=https://academy2026.nitg-eg.com:8443`.

Check: `curl -s https://academy2026.nitg-eg.com:8443/config.js | grep -A3 "config.whiteboard ="` shows
`enabled: true`. The teacher opens it from the room's dock (`mod/jitsi/dock.js`).
