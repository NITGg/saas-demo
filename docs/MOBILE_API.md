# NIT Academy — Mobile API Reference (SaaS)

This is the API surface for the **multi-tenant NIT Academy platform**. Each academy
is its own Moodle instance on its own subdomain, so the **base URL is per‑academy**:

```
https://<slug>.academy2026.nitg-eg.com
```
e.g. `https://ziad.academy2026.nitg-eg.com`. The app should let the user pick / enter
their academy (or deep-link to it) and use that host as the base for every call below.

> **Compatibility:** the endpoints are the **same as the legacy single‑tenant Academy
> app** — same auth, same Moodle web services, same live‑session API, same Jitsi JWT
> endpoint. Existing mobile code keeps working; only the base URL is now per‑academy,
> plus one new convenience endpoint (`mod_jitsi_get_session_info`) and Vimeo playback.

All authenticated requests use a **`wstoken`** (Moodle web‑service token) — the custom
endpoints accept it as `token=` and the standard ones as `wstoken=`. It is one and the
same token.

---

## 1. Authentication

```
POST /login/token.php
Content-Type: application/x-www-form-urlencoded

username=<user>&password=<pass>&service=moodle_mobile_app
```
**Response:** `{ "token": "abc123...", "privatetoken": "" }` → use `token` as `wstoken`.

**Sign in with Google** is enabled per academy (OAuth2). Email self‑registration is on.

---

## 2. Standard Moodle Web Services (REST)

All calls:
```
GET/POST /webservice/rest/server.php?wstoken={token}&moodlewsrestformat=json&wsfunction={fn}
```

| Purpose | wsfunction | Key params |
|---|---|---|
| Current user + site info | `core_webservice_get_site_info` | — |
| My enrolled courses | `core_enrol_get_users_courses` | `userid` |
| Course sections + activities | `core_course_get_contents` | `courseid` |
| One/many courses | `core_course_get_courses` | `options[ids][0]` |
| User profile | `core_user_get_users_by_field` | `field=id&values[0]=` |
| Unread notification count | `message_popup_get_unread_popup_notification_count` | `useridto` |
| Messages / notifications | `core_message_get_messages` | `useridto,type=notifications,read=0` |

In `core_course_get_contents`, each module's `id` is the **course‑module id (cmid)** —
use it for the Jitsi endpoints. Jitsi activities have `"modname": "jitsi"`.

---

## 3. App configuration & branding (per academy)

The app's per‑academy look/config (name, logo, colors, legal links, app store links,
video/watermark settings) is provisioned into each academy and exposed to the app by
the platform plugins (`local_multitopics`, `theme_nit`). Read them with the standard
`tool_mobile_get_config` / public‑config calls, or the site‑info call above
(`core_webservice_get_site_info` returns `sitename`, logo URLs, etc.).

---

## 4. Live Sessions API (`local_academysessions`)

Base: `/local/academysessions/api.php` · auth: `token={wstoken}` · **unchanged from the
Academy app.**

### Teacher
| Function | Params |
|---|---|
| `create_session` | `courseid,title,starttime,studentids(csv),duration,jitsiid` |
| `get_teacher_sessions` | `courseid` (optional) |
| `update_session` | `sessionid,title?,starttime?,duration?` |
| `end_session` | `sessionid` |
| `delete_session` | `sessionid` |
| `get_attendance` | `sessionid` |

### Student
| Function | Params |
|---|---|
| `get_student_sessions` | — (upcoming sessions; `link_visible` opens 30 min before start) |
| `join_session` | `sessionid` (records attendance; call before joining the video) |

Response envelope: `{ "status": "success", "data": … }` or `{ "status": "fail", "error": … }`.

> Live sessions are optional. A Jitsi activity can be used standalone (no scheduled
> session) — just open its cmid and join.

---

## 5. Jitsi live video

Live sessions (Jitsi) are a **Professional‑plan** feature. Two ways to get join details:

### 5a. Single call (recommended) — `mod_jitsi_get_session_info`
```
GET /webservice/rest/server.php?wstoken={token}&moodlewsrestformat=json
    &wsfunction=mod_jitsi_get_session_info&cmid={cmid}
```
**Response**
```json
{
  "cmid": 1964,
  "name": "Week 1 Live",
  "available": true,
  "available_info": "",
  "is_teacher": false,
  "server_url": "https://academy2026.nitg-eg.com:8443",
  "room": "nit_ziad_1964_a3f2b1c0",
  "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "subject": "Week 1 Live",
  "whiteboard_url": "https://academy2026.nitg-eg.com/whiteboard/#room=academy_wb_jitsi_1964",
  "recordings": []
}
```

### 5b. Legacy token endpoint — `/mod/jitsi/api_token.php`
```
GET /mod/jitsi/api_token.php?wstoken={token}&id={cmid}
```
Returns `server_url, room, jwt, is_moderator, subject, display_name, email`.
Error JSON: `{ "error": "…" }` with 401/403.

**Rules:** the JWT expires in 2h; moderators (`is_teacher: true`) can record via the
native Jitsi UI; the room is shared across web + mobile for the same activity.

### Flutter join (jitsi_meet_flutter_sdk)
```dart
final d = await ws('mod_jitsi_get_session_info', {'cmid': cmId});
await JitsiMeet().join(JitsiMeetConferenceOptions(
  serverURL: d['server_url'],
  room: d['room'],
  token: d['jwt'],
  configOverrides: {'subject': d['subject'], 'startWithAudioMuted': true},
  featureFlags: {FeatureFlags.recordingEnabled: d['is_teacher']},
  userInfo: JitsiMeetUserInfo(displayName: /* fullname */ '', email: /* email */ ''),
));
```

---

## 6. Video playback

Course videos and Jitsi **session recordings** are hosted on **Vimeo** (private,
domain‑restricted). The app plays them with the Vimeo player:
```
https://player.vimeo.com/video/<videoId>?h=<privacyHash>
```
The `videoId` (+ hash) is returned by the activity's web service / content. DRM /
watermarked playback is applied server‑side. (No MinIO/Bunny — those were retired for
the SaaS.)

---

## 7. Errors

| Source | Shape |
|---|---|
| `api.php` | `{ "status": "fail", "error": "…" }` |
| `api_token.php` | `{ "error": "…" }` (HTTP 401/403/400) |
| Standard WS | `{ "exception": "…", "errorcode": "…", "message": "…" }` |

---

## 8. Quick reference

| What | Endpoint | Auth |
|---|---|---|
| Login | `POST /login/token.php` | user+pass |
| My info | `…?wsfunction=core_webservice_get_site_info` | wstoken |
| My courses | `…?wsfunction=core_enrol_get_users_courses` | wstoken |
| Course contents | `…?wsfunction=core_course_get_contents` | wstoken |
| Jitsi join (1 call) | `…?wsfunction=mod_jitsi_get_session_info&cmid=` | wstoken |
| Jitsi JWT (legacy) | `GET /mod/jitsi/api_token.php?id=` | wstoken |
| Student sessions | `GET /local/academysessions/api.php?function=get_student_sessions` | token |
| Join session | `GET /local/academysessions/api.php?function=join_session` | token |
| Teacher sessions | `GET /local/academysessions/api.php?function=get_teacher_sessions` | token |

Everything except the **per‑academy base URL**, the **`mod_jitsi_get_session_info`**
convenience call, and **Vimeo** playback is identical to the Academy app — so existing
integrations port over with only a base‑URL change.
