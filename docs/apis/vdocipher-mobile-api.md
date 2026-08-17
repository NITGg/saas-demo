# VdoCipher secure video — Flutter integration

Secure DRM video for course activities. Videos are hosted on **VdoCipher**; the
Moodle server holds the secret and mints a **short-lived, watermarked OTP** per
play. The app never sees the API secret — only an OTP + `playbackInfo` pair that
the VdoCipher SDK consumes.

**Guarantees (enforced by VdoCipher + the SDK, not by us):**
- **No download** — Widevine (Android) / FairPlay (iOS) DRM; the file is never handed over decrypted.
- **No screen recording** — the SDK sets `FLAG_SECURE` on Android and blanks the frame on iOS screen capture.
- **User watermark** — the viewer's `full name · email` is burned onto the video as a moving overlay, generated server-side, so it can't be forged or stripped.

---

## 1. Add the SDK

VdoCipher's official Flutter plugin:

```yaml
# pubspec.yaml
dependencies:
  vdocipher_flutter: ^latest
```

- **Android**: `minSdkVersion 21`+. DRM works on real devices (emulators often lack Widevine L3 for some content).
- **iOS**: FairPlay requires a **real device** (the simulator can't play DRM). Enable the VdoCipher **iOS DRM Config** in your dashboard.

---

## 2. Detect a VdoCipher activity

The course structure (`GET /local/multitopics/getalltopics.php?courseid=…&wstoken=…`)
now returns these fields on each activity. A **VdoCipher Video** activity
(`modname: "vdocipher"`) carries the secure video:

```jsonc
{
  "id": "1234",
  "modname": "vdocipher",
  "name": "Lesson 3 — Kinematics",
  "mediatype": "vdocipher",      // ← play with the VdoCipher SDK
  "isvdocipher": true,           // ← boolean flag to branch on
  "videoid": "abc123def456…",    // VdoCipher video id
  "otpurl": "https://…/local/vdocipher/api.php?function=get_playback&cmid=1234&token=<wstoken>",
  "fileurl": "",                 // empty — do NOT open a webview/file
  "resourcetype": "video/vdocipher"
}
```

Branch on **`isvdocipher == true`** (or `mediatype == "vdocipher"`). When true,
ignore `fileurl` and use the flow below. Every other `mediatype`
(`video`/`audio`/`image`/`pdf`/`document`) is unchanged.

---

## 3. Fetch the OTP right before playback

The OTP is **short-lived (~5 min)**. Fetch it the moment the user taps play —
don't cache it. Just GET the `otpurl` from the activity (the token is already in
it):

```
GET {otpurl}
```

**Success:**
```json
{
  "status": "success",
  "data": {
    "videoid": "abc123def456…",
    "otp": "20160313versASE323…",
    "playbackInfo": "eyJ2aWRlb0lkIjoiYWJj…",
    "watermark": "Ahmed Mohamed · ahmed@example.com",
    "ttl": 300
  }
}
```

**Failure** (not enrolled, no access, or no video attached):
```json
{ "status": "fail", "error": "You do not have access to this video." }
```

> You may also build the URL yourself instead of using `otpurl`:
> `POST /local/vdocipher/api.php?function=get_playback` with body `token=<wstoken>&cmid=<cmid>`.

---

## 4. Play

```dart
import 'package:vdocipher_flutter/vdocipher_flutter.dart';

final res = await http.get(Uri.parse(activity.otpurl));
final data = jsonDecode(res.body)['data'];

final controller = VdoPlayerController();
final embedInfo = EmbedInfo.streaming(
  otp: data['otp'],
  playbackInfo: data['playbackInfo'],
);

VdoPlayer(
  embedInfo: embedInfo,
  onPlayerCreated: (c) => controller = c,
  onError: (e) { /* show retry; OTP may have expired — refetch and retry */ },
);
```

- If playback errors with an expired/invalid OTP, **re-GET the `otpurl`** and retry — OTPs are single-play and short-lived by design.
- The watermark and DRM are applied automatically; the app does nothing extra for them.

---

## 5. Teacher CRUD (optional — dashboard/admin apps only)

All require the teacher's own token and the `local/vdocipher:manage` capability.

| Function | Method | Params | Returns |
|----------|--------|--------|---------|
| `create_upload` | POST | `title`, `courseid`, `cmid?` | `{videoid, rowid, upload:{…S3 fields, uploadLink}}` |
| `video_status` | GET | `videoid` | `{videoid, status, length, title}` |
| `list_videos` | GET | `courseid?` | `[{id, videoid, cmid, courseid, title, status, length}]` |
| `attach_video` | POST | `videoid`, `cmid` | `{videoid, cmid, courseid}` |
| `delete_video` | POST | `videoid` | `{deleted: true}` |

**Upload flow:** call `create_upload` → POST the file bytes straight to
`upload.uploadLink` (multipart: the returned policy fields first, then `file`
last, plus `success_action_status=201`) → poll `video_status` until
`status == "ready"`. The bytes never pass through Moodle.

---

## Quick checklist

- [ ] `isvdocipher == true` → use SDK, ignore `fileurl`.
- [ ] GET `otpurl` **at tap time**, not earlier.
- [ ] Pass `otp` + `playbackInfo` to `EmbedInfo.streaming`.
- [ ] On OTP error → refetch `otpurl` and retry once.
- [ ] Test DRM on **real devices** (iOS simulator can't play FairPlay).
