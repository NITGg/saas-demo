# NIT Academy — Mobile API: updates since `MOBILE_API.md`

This file lists **only** what changed in the project after [`MOBILE_API.md`](MOBILE_API.md) was last
updated (2026-10-05 16:38, commit `b2fb1837c`). Everything not listed here is exactly as described in
`MOBILE_API.md`.

**Summary:** no new endpoints, no new functions, no removed or renamed fields. There are two behaviour
changes the app must follow.

| # | Area | Affects | App change needed |
|---|---|---|---|
| 1 | Video progress — resume rule for short videos | `local/nit_videoprogress/api.php` → `get_progress`, `save_progress`, `get_course_progress`, `get_overview` (`resume_position`) | Yes, if the app computes the resume point itself |
| 2 | Vimeo playback — privacy hash in the player URL | `local/vimeo/api.php` → `get_playback` (`embedurl`), and the Vimeo lessons from `getalltopics.php` | Yes, if the app builds the player URL from `videoid` |

---

## 1. Video progress: resume margins scale with the video length

**Where:** `MOBILE_API.md` §5.2 (the slices protocol, step 1, and every `resume_position` field).

**Before:** `resume_position` was `0` (start over) when the saved position was **under 5 s**, or
**within 10 s of the end**. On short videos that left almost nothing to resume. For example, a
16-second video could only resume between second 5 and second 6.

**Now:** both margins are capped at **one tenth of the video** (at least 1 s):

- start margin = `min(5, max(1, floor(duration / 10)))` seconds
- end margin = `min(10, max(1, floor(duration / 10)))` seconds
- `resume_position` = `0` when `position < start margin`, or when `position >= duration − end margin`
  (finished: start over). Otherwise it is `position`.
- `duration` unknown (`0`): start margin 5 s, no end check.

| duration | resume from second | `0` (start over) when position is |
|---|---|---|
| 16 s | 1 – 14 | < 1 or ≥ 15 |
| 62 s | 5 – 55 | < 5 or ≥ 56 |
| 600 s (and longer) | 5 – 589 | < 5 or ≥ 590 (same as before) |

**App action:**
- Seek to `resume_position` exactly as the server returns it. Do not re-apply the old 5 s / 10 s rule
  on the device.
- If the player checks "near the end" locally (for example after a seek), use the same end margin
  formula as above.
- Long videos (100 s and more) behave exactly as before.

Example (`get_progress`, unchanged shape):
```json
{"status":"success","data":{"cmid":34,"courseid":4,"provider":"vimeo","name":"درس فيديو تجريبي",
  "started":true,"percent":42,"position":55,"duration":62,"resume_position":55,"…":"…"}}
```
(Before this change the same row returned `"resume_position":0`.)

---

## 2. Vimeo: `embedurl` can now carry the privacy hash (`?h=…`)

**Where:** `MOBILE_API.md` §4.3 (`get_playback`) and §2.4 (`getalltopics.php`, Vimeo lessons).

**What changed:** Vimeo may make an uploaded video **"unlisted"**, depending on the Vimeo account's
plan. An unlisted video plays only when its player URL carries the privacy hash. Without it, Vimeo shows
"Sorry, this video does not exist". When a teacher saves a Vimeo lesson, the server now asks Vimeo for
that hash and stores it. `get_playback` then returns it inside `embedurl`.

Before (still possible for videos that have no hash):
```json
{"status":"success","data":{"videoid":"76979871","embedurl":"https://player.vimeo.com/video/76979871"}}
```
Now, for an unlisted video:
```json
{"status":"success","data":{"videoid":"76979871","embedurl":"https://player.vimeo.com/video/76979871?h=abc123"}}
```

**App action:**
- **Always load `embedurl` exactly as returned.** Do not build the player URL yourself from `videoid`
  (`https://player.vimeo.com/video/<videoid>`): that drops `?h=` and unlisted videos will not play.
- If you use a native Vimeo SDK that takes an id, pass the `h` value from `embedurl` as the
  "unlisted hash" parameter.
- The hash is saved when the lesson is created or saved. Older lessons get it the next time a teacher
  saves them.
- `getalltopics.php` does not change: Vimeo lessons still point their `embedurl` to `get_playback`, so
  the hash arrives when you call it right before playback.
- Session recordings (`get_session_recordings`, `mod_jitsi_get_session_info` → `recordings`) are not
  affected.
