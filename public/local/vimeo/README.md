# local_vimeo

A Vimeo-backed video plugin for the Academy platform (upload · manage · playback),
mirroring `local_vdocipher` but using Vimeo instead of VdoCipher.

## Credentials model

The plugin uses a **shared, platform-level Vimeo account** — a single access token,
read from plugin config (`local_vimeo/access_token`), exactly as `local_vdocipher`
reads `local_vdocipher/apisecret`. The token is **provisioned per package by the
nit2 provisioner** at academy-create / tier-change time (alongside the per-package
Kashier and VdoCipher defaults). You only need this plugin so that setting
`local_vimeo/access_token` (+ `local_vimeo/apibase`) makes upload and playback work;
the nit2 push is handled separately, in the nit2 repo (`D:\NIT\nit2`).

The token is sent server-side only, as `Authorization: bearer <token>`. It is never
exposed to a browser or the mobile app.

> **This is a video plugin, not a payments provider.** There is no
> `local_payments_providers`-style enable row to insert — nothing to toggle in a
> payments table. Configure the token and it works.

## How it differs from VdoCipher

| | VdoCipher | Vimeo (this plugin) |
|---|---|---|
| Auth header | `Authorization: Apisecret <key>` | `Authorization: bearer <token>` |
| Upload | S3 form-POST (signed policy) | resumable **tus** (`POST /me/videos` → PATCH bytes to `upload_link`) |
| Playback | short-lived, watermarked **OTP** per view | **domain-private embed** — no OTP, no watermark |
| Access control | OTP + DRM | Moodle enrolment/capability **+** Vimeo embed-whitelist on the academy domain |
| Delete | `DELETE /videos?videos=<ids>` | `DELETE /videos/<id>` |

On upload the video is created private (`view: disable`, `embed: whitelist`) and the
academy domain is added to its embed whitelist (`PUT /videos/<id>/privacy/domains/<domain>`),
so the embed only plays when served from this academy.

## Files

- `version.php`, `settings.php`, `db/{install.xml,access.php,upgrade.php}`, `lang/en/local_vimeo.php`
- `api.php` — token-authed JSON API (mirrors `local_academy` envelopes: `{status, data|error, errorcode}`; 401 only for dead tokens). Functions: `get_playback`, `create_upload`, `video_status`, `list_videos`, `attach_video`, `delete_video`. CRUD requires `local/vimeo:manage`.
- `classes/api_client.php` — thin Vimeo REST client (`\curl`, bearer auth, tus PATCH).
- `classes/video_service.php` — CRUD (create/refresh_status/list/attach/delete), capability-guarded.
- `classes/playback_service.php` — resolves the embed URL after an access check.
- `player.php` — session-authed embeddable iframe player.
- `diagnose.php` — admin connection smoke-test (lists videos).
- `upload_credentials.php` — session-authed AJAX: create video + return tus `uploadLink`.
- `cli/upload_test.php` — CLI end-to-end upload + status poll.

## App wiring

`local/multitopics/getalltopics.php` sets `isvimeo` + `embedurl` for Vimeo-backed
`resource2` (and `vimeo`) activities, pointing at
`/local/vimeo/api.php?function=get_playback&cmid=...`. VdoCipher takes precedence
over Vimeo, which takes precedence over a plain uploaded file.

## Quick test

```bash
php local/vimeo/cli/upload_test.php --file=/path/to/video.mp4 --title="Smoke test"
```

Or via the token API (needs a valid `local_academy` wstoken):

```bash
curl -X POST "https://<academy>/local/vimeo/api.php?function=create_upload&token=<wstoken>" \
     -d "title=Test&size=<bytes>&courseid=<id>"
# → PATCH the file bytes to the returned upload_link (tus), then:
curl "https://<academy>/local/vimeo/api.php?function=video_status&token=<wstoken>&videoid=<id>"
curl "https://<academy>/local/vimeo/api.php?function=get_playback&token=<wstoken>&cmid=<cmid>"
```
