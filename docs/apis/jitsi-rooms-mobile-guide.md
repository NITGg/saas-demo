# Live rooms (Jitsi) in the app: "students see the teacher only", waiting room, attendance

**For:** the Flutter developer. **Date:** 11/10/2026.
**Why:** on the website a student in a live room sees **only the teacher** (camera or screen share), waits in a
waiting room until the teacher lets them in, and every entry/exit is logged. In the app, rooms open with the native
Jitsi Meet SDK, which does none of this yet, so students on the phone still see each other.

**Recommendation:** open the room's **web page inside a WebView** (option A). It is the same page the website
uses, so every rule works the same on the phone with no extra logic in the app. Option B (keep the native SDK) is
described at the end. It cannot fully meet the requirement because of SDK limits.

---

## Answers to the app team (11/10, after your option-B build)

Thanks for the detailed report. Your assumptions are right except where noted. **One new requirement: a heartbeat
(Q5).**

**Q1. `room_presence`: shape.** Correct.
- Call: `POST /local/academysessions/api.php?function=room_presence`, form-encoded `token` (or `wstoken`),
  `cmid`, `present` = `1`/`0`.
- Success: `{"status":"success","data":{"cmid":27,"present":true}}`.
- Error: `{"status":"fail","error":"…","errorcode":"…"}`.
- `present=1` is only recorded while the room lets the user in (otherwise it is ignored, still `success`).

**Q2. `isolate_students`.** A JSON boolean (`true` / `false`). The same for `auto_admit` and `lobby`.

**Q3. `room_presence` vs `set_teacher_present`.** They do different jobs. **Keep calling both** (and keep
`join_session` / `leave_session` for students).

| Call | What it feeds | Rooms | Who |
|---|---|---|---|
| `set_teacher_present` | lesson rooms only: opens / closes the students' "waiting for the teacher" gate, the teacher's first-join (lateness) and last-leave | lesson rooms | the session's teacher |
| `join_session` / `leave_session` | the lesson's attendance (live monitoring, reports) | lesson rooms | students |
| `room_presence` | the room's **Attendance log** page, and "is a host in the call" (course rooms with a waiting room only let students in while one is) | every room | everyone |

They write to different tables, so nothing is counted twice. Repeating `room_presence(present=1)` never opens a
second stretch (it is the heartbeat, Q5).

**Q4. The other fields.**

| Field | Type | What for | Option B (native SDK) |
|---|---|---|---|
| `available` + `jwt` | bool + string | **This decides entry.** Join only when `available` is true and `jwt` is not empty | use |
| `access_code` | string | Why the user cannot enter yet. Use it for the message and whether to poll. It is **not** needed to enter | use |
| `teacher_names` | string[] | The teachers' display names in order, for pinning the teacher | ignore (the SDK can't pin) |
| `teacher_name` | string | The first of `teacher_names` | ignore |
| `host_names` | string[] | Hosts' names: a host lets these out of the Jitsi lobby at once | ignore (the SDK can't answer knocks) |
| `auto_admit` | bool | The user is a host and should auto-admit knockers | ignore (same reason) |
| `lobby` | bool | Course room with a waiting room: a host turns the Jitsi lobby on when joining | see note below |

What to do with each `access_code`:
- `waitingforteacher`: poll every 5 s.
- `sessionnotavailable`: opens later; show the time.
- `sessionended`, `notallowed`, `unavailable`: stop.
- `ok`: join.

⚠️ **Note on `lobby` with the native SDK.** The Flutter SDK has no command to turn the lobby on. So if the only host
in a course room is a teacher **on the app**, nobody turns the lobby on, and students enter without being let in. It
works when the teacher is on the web, or when the teacher turns the lobby on by hand (security options in the
meeting).

**Q5. Leaves that never arrive: now handled on the server, but you must send a heartbeat.**
- While the user is in the conference, call `room_presence(cmid, present=1)` **every 60 seconds** (the same call as
  on join). The web page now does the same.
- If no heartbeat arrives for **3 minutes** (app killed, signal lost), a server task (every 2 min) closes the
  stretch **at the last heartbeat**. For a lesson room's teacher it also closes the student gate and records the
  teacher's leave, as `set_teacher_present(0)` would have.
- Ending a room or a session still closes everyone at once.
- Keep sending `present=0` when you can; it is just more exact.

**Q6. Server-side isolation: no date.** It needs SSH access to the shared Jitsi server, plus a decision, and the
designs that work need the client to cooperate:
- "**camera rooms**" (each student's camera in a separate room) needs the client to be in **two conferences** at
  once. The native SDK supports only one, so it would not help the app.
- **Changing the Jitsi bridge itself** (a fork) is expensive to build and to keep up.
- **Zoom Focus Mode** needs a Zoom account and a test first.

So server-side isolation will not fix the native SDK soon.

### Recommendation: students in a WebView, teachers can stay native

The requirement is only about what **students** see. Open the room for **students** with option A (WebView, below)
and keep the native SDK for **teachers** (they see the whole class anyway). That removes all of the "known limits" you
listed for students:
- no more "whoever speaks is shown";
- the "teacher not in the lesson" cover works;
- the heartbeat and leave are handled by the page.

The only remaining gap is that a teacher on the app must press Admit for an admin.

`MOBILE_API_V5.md` (which you don't have yet) is in the repository at `docs/MOBILE_API_V5.md`; §3.5 is the part about
rooms. Ask for the file if you can't reach the repository.

---

## What the room must do (same on web and app)

| Rule | Who |
|---|---|
| A student sees **only the teacher**: their camera, or their screen while they share it. Never another student's camera or screen | student |
| No tile view, no thumbnail strip (filmstrip) for students | student |
| When no teacher is in the call, the student's video is covered by "The teacher is not in the lesson right now" | student |
| **Course rooms** ("students wait for the teacher" on): the student can open the room only while a teacher/admin is in it. Then they wait in Jitsi's waiting room, and the teacher lets them in **inside the meeting** (Admit / Reject) | student, teacher |
| Teachers, managers and admins **never wait** for each other | hosts |
| Every join / leave is logged (the room's "Attendance log" page) | everyone |

---

## Option A (recommended): open the web room page in a WebView

### A1. Which URL

```
https://<site>/mod/jitsi/view.php?id=<cmid>
```

- **`cmid`:**
  - From a session row (`get_teacher_sessions`, `get_student_sessions`, `join_session`) → the `cmid` field.
  - From the course contents → the `jitsi` module's `id`.
- Lesson rooms (Flex) → `get_lesson_join` already returns `cmid`.

### A2. Open it signed in (the app already does this for quizzes and checkout)

1. Call `tool_mobile_get_autologin_key` with the user's `privatetoken` → `{ key, autologinurl }`.
2. Load:
   ```
   <autologinurl>?userid=<userid>&key=<key>&urltogo=<URL-encoded room URL from A1>
   ```
3. Core Moodle allows **one autologin key per user every 6 minutes**. A second call within 6 minutes fails with
   `autologinkeygenerationlockout`.
   - Keep **one persistent WebView cookie store** for the whole app.
   - If the key call is locked out, open the room URL directly: the WebView session from the previous autologin
     is still valid.
   - Only show an error if the page then lands on the login screen.

### A3. WebView settings (`flutter_inappwebview`)

```dart
InAppWebView(
  initialUrlRequest: URLRequest(url: WebUri(autologinUrl)),
  initialSettings: InAppWebViewSettings(
    javaScriptEnabled: true,
    mediaPlaybackRequiresUserGesture: false,   // Android: start audio/video without a tap
    allowsInlineMediaPlayback: true,           // iOS: video plays inside the page, not fullscreen
    iframeAllow: "camera; microphone; display-capture; autoplay; clipboard-write",
    iframeAllowFullscreen: true,
    useHybridComposition: true,                // Android
    sharedCookiesEnabled: true,                // iOS: keep the Moodle session
  ),
  // The Jitsi meeting runs in an <iframe> of the page and asks for camera/mic.
  onPermissionRequest: (controller, request) async {
    return PermissionResponse(resources: request.resources,
        action: PermissionResponseAction.GRANT);
  },
)
```

- **Before opening the room**, ask the OS for camera + microphone (`permission_handler`). If the user refuses, the
  page still works with camera/mic off.
- **Android** `AndroidManifest.xml`:
  - `android.permission.CAMERA`
  - `android.permission.RECORD_AUDIO`
  - `android.permission.MODIFY_AUDIO_SETTINGS`
  - `android.permission.INTERNET`
- **iOS** `Info.plist`: `NSCameraUsageDescription`, `NSMicrophoneUsageDescription`. WebRTC in WKWebView needs
  **iOS 14.3+**.
- Allow the Jitsi host (`academy2026.nitg-eg.com:8443`) and the site host to load. Don't open them in an external
  browser.

### A4. Leaving the room

When the user closes the room screen, **first load `about:blank`** in the WebView (or `goBack()` out of the
page), and only then dispose it. That fires the page's `pagehide`, which records the leave (attendance log, the
teacher's "teacher left", the student's time in the lesson). Disposing the WebView directly may skip it.

### A5. What you get without writing any logic

- **Waiting page:** "Waiting for the teacher…". It reloads itself and enters by itself.
- **Teacher only:** the student sees only the teacher. Tile view, the filmstrip and other students' screens are
  blocked by the page.
- **Waiting room:** the student knocks automatically. The teacher sees Jitsi's "… wants to join" with
  Admit / Reject, inside the meeting.
- **Hosts:** teachers and admins are let in at once. The teacher's own page approves them.
- **Logs:** the attendance log and the live monitoring dashboard are filled.
- **Recordings:** the recordings list after the lesson.

**Limits on phones:**
- A phone browser can't share its screen (`getDisplayMedia`). This is normal; the button just won't appear.
- The page shows the site header above the room. If you want a full-screen room page with no header for the app,
  ask for it (a small server change).

### A6. Test checklist (two phones or phone + PC)

1. **Course room, waiting room on:** the student opens it before the teacher → "Waiting for the teacher". The
   teacher opens it on the web → within 5 s the student's page goes into the meeting and knocks. The teacher
   presses Admit → the student is in.
2. **Teacher only:**
   - Two students and a teacher, cameras on: each student sees only the teacher.
   - A student shares their screen (from a PC): the other student still sees the teacher.
   - The teacher shares their screen: students see the teacher's screen.
3. The teacher leaves → the students see "The teacher is not in the lesson right now".
4. **Hosts don't wait:** the admin opens the room while the teacher is in → the admin goes straight in, no
   approval.
5. Close the room screen in the app → the attendance log (web, "Attendance log" in the activity menu) shows the
   leave time.

---

## Option B (not recommended): keep the native Jitsi Meet SDK

If you must stay on `jitsi_meet_flutter_sdk`, `mod_jitsi_get_session_info` returns everything you need:
`isolate_students`, `teacher_names`, `lobby`, `host_names`, `auto_admit`, `access_code`, `jwt`.
See `MOBILE_API_V5.md` §3.5.

**What you can do with the SDK:**

```dart
JitsiMeetConferenceOptions(
  serverURL: info.serverUrl, room: info.room, token: info.jwt,
  configOverrides: {
    "prejoinConfig.enabled": false,
    "readOnlyName": true,
    "lobby.autoKnock": true,
    if (info.isolateStudents) ...{
      "channelLastN": 1,
      "disableTileView": true,
      "filmstrip.disabled": true,
      "autoPinLatestScreenShare": false,
    },
  },
  featureFlags: {
    if (info.isolateStudents) ...{
      "tile-view.enabled": false,
      "filmstrip.enabled": false,
    },
  },
)
```

**Also call these** (`/local/academysessions/api.php`, token):
- `room_presence(cmid, present=1)` on `conferenceJoined`, **and again every 60 s** while in the conference
  (heartbeat, see Q5).
- `room_presence(cmid, present=0)` on `conferenceTerminated`.
- Without them the attendance log stays empty. And in a course room with a waiting room, students on other
  devices keep waiting, because the server does not know a teacher on the app is in the call.

**What the SDK cannot do, so the requirement is NOT met with option B:**

| Needed | Native SDK |
|---|---|
| Pin the teacher on the student's screen | ❌ no pin method. The main video follows **whoever is speaking**, so when another student talks, the student sees them |
| Show the teacher's screen share, not a student's | ❌ same reason |
| Cover the video while no teacher is in | ⚠️ only with your own Flutter overlay, driven by `participantJoined` / `participantLeft` (role `moderator`) |
| Hosts let other hosts out of the lobby automatically | ❌ no answer-knocking method. A teacher on the app must press Admit for an admin too |

So with option B a student who speaks can still be seen by other students. **Use option A.**

---

## Server side (already done, nothing to ask for)

- Room page: `mod/jitsi/view.php`.
- WS: `mod_jitsi_get_session_info` (fields above).
- Attendance: `room_presence`.
- Docs: `MOBILE_API_V5.md` §3.5.
- ⚠️ The "teacher only" rule is enforced by the room page (and the app), not by the Jitsi server. A student with a
  modified client could still request other videos. Server-side isolation is planned (`CAMERA-PRIVACY-STUDY.md`).
