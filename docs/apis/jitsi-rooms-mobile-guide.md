# Live rooms (Jitsi) in the app: "students see the teacher only", waiting room, attendance

**For:** the Flutter developer. **Date:** 11/10/2026.
**Why:** on the website a student in a live room sees **only the teacher** (camera or screen share), waits in a
waiting room until the teacher lets them in, and every entry/exit is logged. In the app, rooms open with the native
Jitsi Meet SDK, which does none of this yet, so students on the phone still see each other.

**Decision (11/10): keep the native SDK, built by us with a small addition** → **section C** (at the end). The
stock SDK (option B) cannot pin the teacher or admit people, and section C adds exactly that. Option A (WebView)
stays as a fallback.

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

| Field | Type | What for | In the app (section C) |
|---|---|---|---|
| `available` + `jwt` | bool + string | **This decides entry.** Join only when `available` is true and `jwt` is not empty | use |
| `access_code` | string | Why the user cannot enter yet. Use it for the message and whether to poll. It is **not** needed to enter | use |
| `teacher_names` | string[] | The teachers' display names in order, for pinning the teacher | → `nitTeacherNames` |
| `teacher_name` | string | The first of `teacher_names` | not needed |
| `host_names` | string[] | Hosts' names: a host lets these out of the Jitsi lobby at once | → `nitHostNames` (hosts only) |
| `auto_admit` | bool | The user is a host and should auto-admit knockers | send `nitHostNames` / `nitLobby` only when true |
| `lobby` | bool | Course room with a waiting room: a host turns the Jitsi lobby on when joining | → `nitLobby` (hosts only) |

What to do with each `access_code`:
- `waitingforteacher`: poll every 5 s.
- `sessionnotavailable`: opens later; show the time.
- `sessionended`, `notallowed`, `unavailable`: stop.
- `ok`: join.

⚠️ **Note on `lobby`.** The stock Flutter SDK has no command to turn the lobby on, so a teacher alone on the app
would let students in without the waiting room. Section C turns it on by itself.

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

### Decision: stay on the native SDK, with our own build of it (section C)

The "known limits" you listed come from what the stock SDK is missing (pin, admit). Section C adds them to our own
build of the Jitsi Meet SDK; the server already sends everything needed.

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

## Option A (fallback): open the web room page in a WebView

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

## Option B: the stock native Jitsi Meet SDK (what the app has now)

Keep everything in this section (config, feature flags, `room_presence`), and add section C on top.

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

So with the stock SDK a student who speaks can still be seen by other students. **Section C fixes this.**

---

## Section C (chosen): our own build of the Jitsi Meet SDK

**Idea:** the Jitsi Meet SDK is open source. We take the exact version the Flutter plugin uses, add **one file**
with the classroom rules (the same rules as the website), build it for Android and iOS, and point the Flutter plugin
at our build. The server and Moodle need no change.

| Needed | How our build does it |
|---|---|
| The student sees only the teacher | Pins the teacher: their screen share while sharing, else their camera. Re-pins whenever someone joins, leaves, or starts/stops sharing |
| Nobody else can be pinned by the student | Any other pin is replaced by the teacher |
| No teacher in the call | The student sees only themselves (no other student) |
| Hosts (`host_names`) let other hosts out of the lobby at once | Auto-admit when the local user is moderator |
| A course room's waiting room (`lobby`) | When the local user becomes moderator, the lobby is turned on (or off for lesson rooms) |

> We wrote the code below by reading the SDK source at tag **`mobile-sdk-13.2.0`** (the version
> `jitsi_meet_flutter_sdk` 13.2.0 uses). **We have not compiled or run it.** Build it and run the test list in C6.
> It may need small fixes.

### C1. What you need

- **Android build:** any machine with JDK 17 and the Android SDK/NDK versions in jitsi-meet's `android/build.gradle`.
- **iOS build:** a Mac with Xcode and CocoaPods.
- **Both:** Node **24+** and npm **11+**, as required by jitsi-meet's `package.json`.

### C2. Get the source at the right version

```bash
mkdir jitsi-sdk && cd jitsi-sdk
git clone --branch mobile-sdk-13.2.0 --depth 1 https://github.com/jitsi/jitsi-meet.git
git clone https://github.com/jitsi/jitsi-meet-ios-sdk-releases.git     # iOS only, must sit next to jitsi-meet
cd jitsi-meet && npm install
```

The tag must match the native SDK version in the plugin you use: `android/build.gradle`
(`org.jitsi.react:jitsi-meet-sdk:13.2.0`) and `ios/jitsi_meet_flutter_sdk.podspec` (`JitsiMeetSDK 13.2.0`). When the
plugin is upgraded later, redo C2–C4 on the matching `mobile-sdk-X.Y.Z` tag.

### C3. The code change (3 files in jitsi-meet)

**1. New file `react/features/nit-classroom/middleware.ts`:**

```ts
/**
 * NIT classroom rules (the same as the website's room page), driven by config overrides from the app:
 * - nitIsolateStudents: the student sees only the teacher (their screen share first, else their camera), or only
 *   themselves while no teacher is in. Nobody else can be pinned.
 * - nitTeacherNames: the teachers' display names, in order. Without a match, any moderator counts as the teacher.
 * - nitHostNames: a moderator lets these names out of the lobby at once.
 * - nitLobby: when the local user becomes moderator, turn the lobby on (true) or off (false).
 */
import { AnyAction } from 'redux';

import { IReduxState, IStore } from '../app/types';
import { PIN_PARTICIPANT } from '../base/participants/actionTypes';
import { pinParticipant } from '../base/participants/actions';
import { PARTICIPANT_ROLE } from '../base/participants/constants';
import {
    getLocalParticipant,
    getPinnedParticipant,
    getRemoteParticipants,
    getVirtualScreenshareParticipantByOwnerId,
    isLocalParticipantModerator
} from '../base/participants/functions';
import MiddlewareRegistry from '../base/redux/MiddlewareRegistry';
import StateListenerRegistry from '../base/redux/StateListenerRegistry';
import { setKnockingParticipantApproval, toggleLobbyMode } from '../lobby/actions.any';
import { KNOCKING_PARTICIPANT_ARRIVED_OR_UPDATED } from '../lobby/actionTypes';

const nitConfig = (state: IReduxState) => state['features/base/config'] as any;

/**
 * A config flag (it may arrive as a boolean or a string).
 *
 * @param {unknown} value - The config value.
 * @returns {boolean|undefined}
 */
function flag(value: unknown): boolean | undefined {
    if (value === true || value === 'true') {
        return true;
    }
    if (value === false || value === 'false') {
        return false;
    }

    return undefined;
}

/**
 * A list of names (Android sends an empty list as the string "[]").
 *
 * @param {unknown} value - The config value.
 * @returns {string[]}
 */
function names(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map(String);
    }
    if (typeof value === 'string' && value !== '' && value !== '[]') {
        return [ value ];
    }

    return [];
}

/**
 * Who the student must see: the teacher's screen share or camera, or the student while no teacher is in.
 * Undefined when the rule is off (teachers, admins, rooms without isolation).
 *
 * @param {IReduxState} state - The redux state.
 * @returns {string|undefined}
 */
function stageId(state: IReduxState): string | undefined {
    const config = nitConfig(state);

    if (!flag(config.nitIsolateStudents)) {
        return undefined;
    }
    const people = Array.from(getRemoteParticipants(state).values()).filter(p => !p.fakeParticipant);
    const teacher = names(config.nitTeacherNames).map(n => people.find(p => p.name === n)).find(Boolean)
        ?? people.find(p => p.role === PARTICIPANT_ROLE.MODERATOR);

    if (!teacher) {
        return getLocalParticipant(state)?.id;
    }

    return getVirtualScreenshareParticipantByOwnerId(state, teacher.id)?.id ?? teacher.id;
}

/**
 * A moderator lets the hosts (nitHostNames) out of the lobby.
 *
 * @param {IStore} store - The redux store.
 * @returns {void}
 */
function admitHosts({ dispatch, getState }: IStore) {
    const state = getState();

    if (!isLocalParticipantModerator(state)) {
        return;
    }
    const hosts = names(nitConfig(state).nitHostNames);

    for (const p of state['features/lobby'].knockingParticipants) {
        if (p.name && hosts.includes(p.name)) {
            dispatch(setKnockingParticipantApproval(p.id, true));
        }
    }
}

// Keep the teacher pinned as people join, leave, change role or start/stop sharing.
StateListenerRegistry.register(
    state => stageId(state),
    (id, { dispatch, getState }) => {
        if (id && getPinnedParticipant(getState())?.id !== id) {
            dispatch(pinParticipant(id));
        }
    });

// When the local user becomes moderator: set the lobby as the room wants it, admit hosts already knocking.
StateListenerRegistry.register(
    state => isLocalParticipantModerator(state),
    (moderator, store) => {
        if (!moderator) {
            return;
        }
        const lobby = flag(nitConfig(store.getState()).nitLobby);

        if (lobby !== undefined && lobby !== store.getState()['features/lobby'].lobbyEnabled) {
            store.dispatch(toggleLobbyMode(lobby));
        }
        admitHosts(store);
    });

MiddlewareRegistry.register(store => next => (action: AnyAction) => {
    switch (action.type) {
    case PIN_PARTICIPANT: {
        // A student cannot pin anyone else (a tap on the video, "pin" in the participants list...).
        const id = stageId(store.getState());

        if (id && action.participant?.id !== id) {
            return next(pinParticipant(id));
        }
        break;
    }
    case KNOCKING_PARTICIPANT_ARRIVED_OR_UPDATED: {
        const result = next(action);

        admitHosts(store);

        return result;
    }
    }

    return next(action);
});
```

**2. Register it.** In `react/features/app/middlewares.native.ts`, add one line next to the other imports:

```ts
import '../nit-classroom/middleware';
```

**3. Let the new config keys through.** A release build drops config overrides that are not whitelisted.
Replace the content of `react/features/base/config/extraConfigWhitelist.ts` (Jitsi's file for exactly this) with:

```ts
/**
 * Deploy-specific configuration whitelists.
 */
export default [
    'nitHostNames',
    'nitIsolateStudents',
    'nitLobby',
    'nitTeacherNames'
];
```

Why it works:
- The SDK shows the **pinned** participant in the main video, and asks the video server only for that video
  (with `channelLastN: 1`).
- With the filmstrip and tile view off, the student has no other place to see anyone.

### C4. Build it

Use the same version label everywhere, e.g. **`13.2.0-nit1`**. Raise it (`-nit2`…) on every rebuild, or caches keep
the old build.

**Android** (from `jitsi-sdk/jitsi-meet`):
```bash
mkdir -p ../android-repo && git -C ../android-repo init
OVERRIDE_SDK_VERSION=13.2.0-nit1 ./android/scripts/release-sdk.sh ../android-repo
```
- This builds the SDK and publishes it, with its dependencies, into the `android-repo` folder (a local Maven repo).
  The script commits there, which is why the folder is a git repo.
- Put that folder where the app build can reach it: in the app repo (it is large, so Git LFS is better), or on an
  HTTPS file server.

**iOS** (on a Mac, from `jitsi-sdk/jitsi-meet`):
```bash
cd ios && pod install && cd ..
OVERRIDE_SDK_VERSION=13.2.0-nit1 ./ios/scripts/release-sdk.sh
```
- This writes `JitsiMeetSDK.xcframework` (and `hermesvm.xcframework`) plus `JitsiMeetSDK.podspec` into
  `jitsi-sdk/jitsi-meet-ios-sdk-releases`.
- Keep that folder with the app, the same way as `android-repo`.

### C5. Use it from Flutter

**1. Take a local copy of the plugin.** Copy `jitsi_meet_flutter_sdk` 13.2.0 (from the pub cache or GitHub) into the
app, e.g. `packages/jitsi_meet_flutter_sdk`, and in `pubspec.yaml`:
```yaml
dependency_overrides:
  jitsi_meet_flutter_sdk:
    path: packages/jitsi_meet_flutter_sdk
```

**2. Android.** Edit `packages/jitsi_meet_flutter_sdk/android/build.gradle`:
- Add our repo **first** in `rootProject.allprojects { repositories { … } }`:
  `maven { url "file:///<path>/android-repo" }` (or the HTTPS URL).
- Change `org.jitsi.react:jitsi-meet-sdk:13.2.0` to `org.jitsi.react:jitsi-meet-sdk:13.2.0-nit1`.

**3. iOS.**
- In `packages/jitsi_meet_flutter_sdk/ios/jitsi_meet_flutter_sdk.podspec`, change
  `s.dependency 'JitsiMeetSDK', '13.2.0'` to `'13.2.0-nit1'`.
- In the app's `ios/Podfile`, inside `target 'Runner'`, add:
  `pod 'JitsiMeetSDK', :path => '<path>/jitsi-meet-ios-sdk-releases'`
- Then run `pod install`.

**4. Pass the server's fields** from `mod_jitsi_get_session_info` (add to what you already send in option B):
```dart
configOverrides: {
  // ...the option B keys...
  "nitIsolateStudents": info.isolateStudents,
  if (info.teacherNames.isNotEmpty) "nitTeacherNames": info.teacherNames,
  if (info.autoAdmit && info.hostNames.isNotEmpty) "nitHostNames": info.hostNames,
  if (info.autoAdmit) "nitLobby": info.lobby,
},
```
- The display name must be the one from the JWT (`readOnlyName: true`, as you do now). Names are matched exactly.
- Keep `room_presence` with the 60 s heartbeat, `set_teacher_present`, `join_session` / `leave_session` as they are.

### C6. Test list (two phones + a PC)

1. **Teacher only:** a teacher (web or app) and two students on the app, all cameras on.
   - Each student sees only the teacher, also while the other student talks.
   - Tapping or pinning anyone else snaps back to the teacher.
2. **Screen share:**
   - The teacher shares their screen: students see the screen, and the camera again when sharing stops.
   - A student shares (from a PC): the other student still sees the teacher.
3. **No teacher:** the teacher leaves, and the students see only themselves. The teacher comes back and is shown
   again.
4. **Course room with a waiting room, teacher on the app:**
   - The lobby turns on by itself when the teacher joins.
   - A student knocks, and the teacher sees "… wants to join" and admits them.
   - An admin opens the room and goes straight in, with no Admit.
5. **Lesson room** (live session): students enter without a lobby, as before.
6. **Attendance:** kill the app during a call. Within ~5 minutes, the room's Attendance log shows the user left at
   their last heartbeat.

### C7. Keep in mind

- **Upgrades:** each new plugin/SDK version means re-applying the 3 files on the new tag and rebuilding (C2–C5).
  The change is small and lives in a new folder, so it usually applies as is.
- **Not server-enforced:** like the website, this rule is enforced by the app, not by the Jitsi server.

---

## Server side (already done, nothing to ask for)

- Room page: `mod/jitsi/view.php`.
- WS: `mod_jitsi_get_session_info` (fields above).
- Attendance: `room_presence`.
- Docs: `MOBILE_API_V5.md` §3.5.
- ⚠️ The "teacher only" rule is enforced by the room page (and the app), not by the Jitsi server. A student with a
  modified client could still request other videos. Server-side isolation is planned (`CAMERA-PRIVACY-STUDY.md`).
