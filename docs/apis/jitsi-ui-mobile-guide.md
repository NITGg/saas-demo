# The new live-room look in the app (Jitsi UI)

**For:** the mobile (Flutter) team. **Date:** 14/10/2026.
**Read with:** [`jitsi-rooms-mobile-guide.md`](jitsi-rooms-mobile-guide.md) (room rules: students see the teacher
only, waiting room, attendance) and [`../MOBILE_API_V5.md`](../MOBILE_API_V5.md) §3.11.

The web room page no longer shows Jitsi's own toolbar. It has **its own controls in the site's style**: a floating
glass dock, two-tone icons, the site's blue and navy, a live timer. This guide says what changed on the web, what
the app can reuse, and the three ways to give the app the same look, from "no native work" to "most native".

---

## 1. What the web room looks like now

| Part | What it is |
|---|---|
| Call area | Navy gradient background, 24 px rounded corners. The call stops above the dock, so Jitsi's notifications are never hidden. |
| Status strip (top) | Glass chips: green dot + **"مباشر"** + elapsed time (`mm:ss`); a red **"جارٍ التسجيل"** chip while recording. Jitsi's own title and timer are hidden. |
| Dock (bottom) | Floating glass bar. Square buttons (48 px, 17 px radius) with a label under each. **Leave** is a red pill at the end. |
| Button states | **Off** (mic / camera off): red gradient + the slashed icon. **On** (sharing, hand up, chat open, grid view): site blue gradient with a glow. |
| Badges | Cyan pill on the icon: unread chat messages; for the host, people in the call. It turns **yellow and pulses** when a student is waiting to be let in. |
| Leave (host) | Opens a menu: **"مغادرة"** (leave) or **"إنهاء الحصة للجميع"** (end for everyone). |
| Settings | Pop-up: microphone / camera / speaker lists, "تمويه الخلفية" (blur), "إزالة الضوضاء" (noise suppression). |
| Phones (< 640 px) | Icons only (no labels), 42 px buttons. |

**Buttons per role** (same order on web):

| Role | Buttons |
|---|---|
| Host (teacher / admin) | mic, camera, screen share, chat, raise hand, **whiteboard** ("السبورة"), **participants**, **grid view**, **mute all**, **record** (on / off, to start it again if it stopped), settings, full screen, leave |
| Student | mic, camera, screen share, chat, raise hand, settings, full screen, leave (no grid view: they see the teacher only) |

Web files, if you want to read them: `mod/jitsi/templates/dock.mustache` (markup), `mod/jitsi/dock.js` (behaviour),
`mod/jitsi/styles.css` (look), `jitsi_dock_context()` in `mod/jitsi/lib.php` (which buttons).

---

## 2. Design tokens (use these in any Flutter screen of the room)

| Token | Value | Used for |
|---|---|---|
| Blue (primary) | `#0080FF` | "on" buttons, links |
| Blue light | `#0694FF` | gradient start of "on" buttons |
| Brand blue dark | `#0861C5` | pressed |
| Navy | `#0E335D` | call background, glass tint `rgba(14,51,93,.55)` |
| Ink | `#061A33` | background gradient end, pop-ups `rgba(6,26,51,.92)` |
| Cyan | `#60FEFF` | badges, the light layer of the icons, focus ring |
| Red | `#FF4D67` → `#E0284A` | off buttons, leave, recording dot |
| Green | `#4BF7A1` | "live" dot |
| Glass border | `rgba(255,255,255,.16)` | dock, chips, pop-ups (with a blur behind) |
| Font | **Almarai** (then Tajawal) | all text |
| Radii | dock 28, buttons 17, chips / leave pill 9999 (pill), pop-ups 20 | |

**Icons:** Phosphor Icons, **duotone** style (MIT license, `mod/jitsi/branding/dock/LICENSE`). Each SVG has two
paths: the light layer has `class="duo"` (draw it cyan `#60FEFF` at 55% opacity, or white at 30–35% on a coloured
button), the other is the white outline. They are public files, for example
`https://<site>/mod/jitsi/branding/dock/microphone.svg`:

| Button | Icon file(s) |
|---|---|
| mic on / off | `microphone.svg` / `microphone-slash.svg` |
| camera on / off | `video-camera.svg` / `video-camera-slash.svg` |
| screen share | `screencast.svg` |
| chat | `chat-circle-dots.svg` |
| raise hand | `hand-palm.svg` |
| whiteboard | `chalkboard-simple.svg` |
| participants | `users-three.svg` |
| grid view | `squares-four.svg` |
| mute all | `speaker-simple-slash.svg` |
| settings | `sliders-horizontal.svg` |
| full screen | `corners-out.svg` (exit: `corners-in.svg`) |
| leave / end for all | `phone-disconnect.svg` / `power.svg` |
| recording | `record.svg` |
| blur, noise | `drop-half.svg`, `waveform.svg` |

In Flutter: `flutter_svg` (`SvgPicture.string` / `.network`), set the colours yourself per path (or bundle the files
as assets).

**Strings:** the labels are the Moodle strings `dock_*` in `mod/jitsi/lang/ar/jitsi.php` (Arabic) and
`lang/en/jitsi.php`. Use the same texts so web and app say the same thing.

---

## 3. The one limit you need to know

The Flutter plugin (`jitsi_meet_flutter_sdk`, native SDK 13.2.0) opens the call as **its own full-screen native
screen**. Flutter widgets **cannot be drawn on top of it**. So the web approach ("hide Jitsi's toolbar, draw our own
dock over the call") is not possible with the native SDK as it is. That is why there are three paths:

| | Path | Same look as web? | Native work | Kept on Jitsi updates? |
|---|---|---|---|---|
| **A** | Open the web room page in a WebView | **Yes, identical** (it *is* the web page) | none in the call | yes (it is our page) |
| **B** | Native SDK, settings only | No: Jitsi's own toolbar, fewer buttons, our logo/colours | flags + config, one line in our SDK build | yes |
| **C** | Native SDK, our own toolbar inside our SDK build | Close | large (React Native code in the SDK) | **no**: re-apply on every SDK upgrade |

**Recommendation:** **A** if the identical look matters most, **B** if staying native matters most. Avoid **C**.

---

## 4. Path A: the web room in a WebView (identical look)

Everything in [`jitsi-rooms-mobile-guide.md` → Option A](jitsi-rooms-mobile-guide.md) (URL, signing in, WebView
settings, leaving, test list) applies unchanged. The page brings the dock, branding, isolation, waiting room,
attendance and auto-recording with it.

- **URL:** `https://<site>/mod/jitsi/view.php?id=<cmid>&join=1`. `join=1` goes straight into the call;
  **without** it the page shows the "غرفة الجلسة جاهزة" card with a **"انضم إلى الجلسة"** button
  (opening the room is not joining it).
- The dock is already built for phones (icons only, smaller). Full screen works on the room area.
- ⚠️ **Screen sharing does not work inside a WebView** on Android or iOS (no screen capture API there). The button
  is still shown. If you go with path A, tell us and we'll hide it when the page is opened by the app.
- Camera / microphone: grant the WebView's permission request (see Option A3 of the other guide).

---

## 5. Path B: native SDK, styled with settings (stay native)

### B1. Remove what we don't use (feature flags)

```dart
featureFlags: {
  'meeting-name.enabled': false,        // no Jitsi title (we show ours in the app bar / pre-join)
  'conference-timer.enabled': false,
  'invite.enabled': false,
  'add-people.enabled': false,
  'live-streaming.enabled': false,
  'recording.enabled': isHost,          // it starts by itself; a host can start it again if it stops
  'security-options.enabled': false,
  'help.enabled': false,
  'calendar.enabled': false,
  'car-mode.enabled': false,
  'speakerstats.enabled': false,
  'breakout-rooms.enabled': false,
  'video-share.enabled': false,
  'reactions.enabled': false,
  'welcomepage.enabled': false,
  'prejoinpage.enabled': false,         // our own pre-join is in Flutter (B4)
  // students only (isolate_students = true):
  'tile-view.enabled': !isolateStudents,
  'filmstrip.enabled': !isolateStudents,
  // hosts only:
  'kick-out.enabled': isHost,
  'participants.enabled': isHost,
  'lobby-mode.enabled': false,          // the lobby is set by the rules, not by hand
},
configOverrides: {
  'hideConferenceSubject': true,
  'hideConferenceTimer': true,
  // + the isolation / lobby overrides from jitsi-rooms-mobile-guide.md
},
```

### B2. Our logo, colours and icons inside the native call (dynamic branding)

The site serves Jitsi's own **dynamic branding** JSON (public, no token):
`GET https://<site>/mod/jitsi/branding.php`

```json
{
  "backgroundColor": "#0E335D",
  "avatarBackgrounds": ["#0080FF", "#0861C5", "#0E335D", "#0694FF"],
  "logoClickUrl": "https://<site>",
  "logoImageUrl": "https://<site>/pluginfile.php/1/core_admin/logocompact/300x300/…",
  "customIcons": { "IconMic": "https://<site>/mod/jitsi/branding.php?icon=IconMic", "…": "…" },
  "customTheme": { "palette": { "action01": "#0080FF", "action01Hover": "#0694FF", "…": "…" } }
}
```

- `customIcons` replaces Jitsi's icons **by Jitsi's icon name** (`IconMic`, `IconHangup`, `IconRaiseHand`, …)
  with icons in the site's style (Phosphor, `mod/jitsi/branding/icons/`).
- The SDK reads it from the config key **`dynamicBrandingUrl`**. In SDK 13.2.0 that key is **not** in the list
  of keys an app may override, so add **one line** to your own SDK build (the one from section C of the other guide):
  in `react/features/base/config/configWhitelist.ts`, add `'dynamicBrandingUrl',` to the array. Then pass it per call:

  ```dart
  configOverrides: { 'dynamicBrandingUrl': '$siteUrl/mod/jitsi/branding.php' },
  ```
- ⚠️ **Check on a device:** we confirmed in the SDK source that the native app fetches this JSON, but not yet that
  it applies every field (icons and theme especially). Logo, background and avatar colours are the safest. If an
  icon or colour does not change, it just stays Jitsi's: nothing breaks.

### B3. What path B cannot do

Jitsi's toolbar stays Jitsi's toolbar (its layout, its button shapes); you only choose which buttons exist and
the colours / icons above. The status strip and the glass dock of the web are not possible natively (section 3).

### B4. The screens before the call are yours (Flutter)

Everything **before** `JitsiMeet().join()` is a Flutter screen, so give it the web look with the tokens of §2:
- **Ready:** title "غرفة الجلسة جاهزة", text "لن تدخل الغرفة ولن تُستخدم الكاميرا أو الميكروفون إلا بعد الضغط على
  "انضم إلى الجلسة"", button **"انضم إلى الجلسة"**. Host: add "يبدأ تسجيل الجلسة تلقائياً بعد دخولك."
- **Waiting** (`get_session_info` → `available=false`): show the message for `access_code`
  (`waitingforteacher`: "المعلم ليس في الحصة الآن. ستدخل تلقائياً عند انضمامه.", and in a room with a waiting room
  "بعدها ينتظر طلبك حتى يسمح لك المعلم بالدخول."), poll every 5 s, join on `available=true`.
- **Ended:** "انتهت هذه الجلسة." with the recordings list (`recordings` from `get_session_info`).
The exact strings are `prejoin_*`, `waiting_*`, `sessionended` in `mod/jitsi/lang/ar/jitsi.php`.

---

## 6. Path C (not recommended): our own toolbar inside our SDK build

Possible because we already build the SDK from source: hide the toolbox (`'toolbox.enabled': false`) and add a
React Native toolbar component to the SDK, with our icons and colours, calling the same actions as the web dock.
**Why not:** it changes Jitsi's own code, so every SDK upgrade means re-applying and re-testing it (the opposite of
what we did on the web, where everything is our own page plus Jitsi's public API). Talk to us before choosing it.

---

## 7. Rules that must keep working (whatever the path)

From [`jitsi-rooms-mobile-guide.md`](jitsi-rooms-mobile-guide.md) and `MOBILE_API_V5.md`:
- students see the teacher only (`isolate_students`), the teacher's screen share while they share;
- waiting room (`lobby`): **now also in lesson (Flex) rooms** (§3.11 of V5): the student knocks, the teacher admits;
- `room_presence(present=1)` on join and every 60 s, `present=0` on leave;
- never join with an empty `jwt`.

## 8. Test checklist

- [ ] Teacher + 2 students: every button works (mic, camera, chat, hand, participants, grid, mute all, leave / end for all).
- [ ] A student never sees another student (main video, filmstrip, grid).
- [ ] Lesson room: the student waits until the teacher lets them in.
- [ ] Logo and colours (path A or B2) on Android and iOS, light and dark phone theme.
- [ ] Arabic and English.
- [ ] After the call: the recording appears on the lesson card / recordings tab.
