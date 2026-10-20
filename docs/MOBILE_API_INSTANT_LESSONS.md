# NIT Academy — Mobile API: Instant lessons ("Get a tutor now")

For the mobile developer. Read on top of [`MOBILE_API.md`](MOBILE_API.md) … [`MOBILE_API_V5.md`](MOBILE_API_V5.md):
same base URL, same token, same envelope. Product background: [`instant-lessons-design.md`](instant-lessons-design.md).

**In one paragraph.** A student picks a subject and asks for a tutor *now*. Online teachers of that subject get a
prompt (in waves of a few at a time). The first teacher who accepts starts a normal live lesson at once — the same
Flex lesson the app already knows (`get_lesson`, `get_lesson_join`, complete, absence, reviews), with
`type = "instant"`. Nothing is charged until a teacher accepts; cancel / timeout / "nobody accepted" are free.

---

## 1. Calling convention

```
GET|POST {site}/local/nit_lessons/api.php?function=instant_<action>&token=<user token>[&lang=ar|en]
```

- Envelope (as everywhere): `{"status":"success","data":{…}}` or `{"status":"fail","errorcode":"…","error":"<message in lang>"}`.
- `GET` for: `instant_subjects`, `instant_status`, `instant_presence`, `instant_poll`, `instant_reliability`.
  **`POST` for everything else** (`instant_request`, `instant_cancel`, `instant_online`, `instant_offline`,
  `instant_accept`, `instant_decline`, `instant_watch`).
- `instant_request` with a photo: `multipart/form-data`, file field **`photo`**.
- All times are unix seconds; all countdowns (`secondsleft`) are seconds from *now* on the server — count down
  locally, re-sync on the next poll.
- The feature can be switched off by the admin: `instant_subjects` → `enabled: false` (hide the entry points).

## 2. Student flow

```
instant_subjects ──► (pick subject, optional note / photo / quick) ──► instant_request
        ▲                                                                   │
        │                                     poll instant_status every 2 s │
        │                ┌───────────────────────────────────────────────────┤
        │      status = "matched" ──► get_lesson_join(lessonid) ──► open the room (existing Jitsi flow)
        │      status = "expired" | "cancelled" ──► "Try again" / "Notify me" (instant_watch) / normal booking
        └───── status = "searching" ──► keep waiting (instant_cancel to give up)
```

### `instant_subjects` — GET
What the "Get a tutor now" screen needs. Call when the screen opens (and after a failed request).

```json
{
  "enabled": true,
  "subjects": [ {"subject": "{mlang ar}الفيزياء{mlang}{mlang en}Physics{mlang}", "label": "Physics", "teachers": 2} ],
  "all":      [ {"subject": "…raw…", "label": "Physics"}, {"subject": "…", "label": "Chemistry"} ],
  "open": null,
  "trial": false, "trialflex": 1,
  "quick": true, "quickminutes": 15, "quickflex": 1,
  "minutes": 30,
  "peak": false,
  "bookurl": "https://…/local/nit_lessons/student.php?tab=book"
}
```
- `subjects` = subjects with **at least one teacher online now** (`teachers` = how many). Show `label`; **send `subject`
  back exactly as received** (it is the raw value, may contain `{mlang}` — never display it).
- `all` = every subject of the academy (for "Notify me" when nobody is online).
- `open` = the student's request that is still searching (same shape as `instant_status`) — if not null, go straight
  to the "Finding a tutor…" screen (e.g. the app was closed during a search).
- `trial: true` → the next instant lesson is the student's **trial**: show "Your first lesson is a trial:
  `trialflex` Flex" and hide the quick option.
- `quick: true` → offer "Quick question (`quickminutes` min · `quickflex` Flex)" next to "Full lesson (`minutes` min)".
- `peak: true` → show "Peak hours: instant lessons cost a little more now".

### `instant_request` — POST
| param | required | notes |
|---|---|---|
| `subject` | yes | raw `subject` from `instant_subjects` |
| `note` | no | what the student needs help with (shown to the teacher; an AI one-line summary may be added) |
| `mode` | no | `normal` (default) or `quick` |
| `photo` | no | image file (jpg/png/webp/gif, ≤ 5 MB), multipart field `photo` |

Returns the request status (below). Errors:

| errorcode | when | show |
|---|---|---|
| `noinstanttutor` | nobody can take it right now | the message + "Notify me" / "Book a lesson" |
| `instantopen` | the student already has a request searching | go to the searching screen (`instant_subjects.open`) |
| `noflex` | no active Flex package | send to Flex packages |
| `instantoff` | the admin switched instant lessons off | hide the feature |
| `instantphoto` | bad photo | the message |
| `subjectrequired`, `badaction` | bad input / quick questions off | the message |

### `instant_status` — GET `requestid`
```json
{"id": 41, "status": "searching", "subject": "Physics", "secondsleft": 97, "teachers": 3,
 "lessonid": 0, "mode": "normal", "trial": false, "duration": 30}
```
- `status`: `searching` → keep polling (every **2 s**; every 10 s if you use the realtime socket, §5);
  `matched` → `lessonid` is set: call **`get_lesson_join(lessonid)`** and open the room exactly like a booked lesson;
  `expired` (nobody accepted) / `cancelled` → show "No tutor accepted this time — nothing was charged" with
  "Try again", "Notify me when a tutor is online" (`instant_watch`) and "Book a lesson".
- `teachers` = how many teachers are being asked right now ("asking 3 tutors").
- A request can go back from `matched` to `searching` **once**: if the teacher accepted but never opened the room
  within 3 minutes, the lesson is cancelled, the Flex returned, the student gets a notification and the request
  is sent to other teachers. If the app is in the room screen and the lesson becomes `cancelled_teacher`, return to
  the searching screen with `instant_subjects` (its `open` will be the reopened request).

### `instant_cancel` — POST `requestid`
Free while `searching`. Returns the status (`cancelled`).

### `instant_watch` — POST `subject`
"Notify me when a tutor is online": the first teacher of that subject who goes online sends the student one
notification (bell + push, provider `local_nit_lessons` / `lessonupdate`). Forgotten after one alert or a day.
Returns `{"watching": true}`.

### Photos
The teacher sees the photo; the student's own request has it too. URL = `photourl` in the teacher's offer (§3); it is a
`pluginfile.php` URL — append `?token=<token>` (`webservice/pluginfile.php` form) like other files in the app.

## 3. Teacher flow

```
instant_online ──► poll instant_poll every 4 s (it is also the heartbeat) ──► offers[] ──► show prompt
     │                                                    accept ──► instant_accept ──► get_lesson_join ──► room
     └── instant_offline (or just stop polling: offline after 60 s)        decline ──► instant_decline
```

Only teachers students can book may go online (bookings on, ≥ 1 subject, approved tutor) — else errorcode
`instantnotbookable`.

### `instant_online` / `instant_offline` — POST
Switch "Available now for instant lessons". Returns presence `{"online": true, "busy": false}`.

### `instant_presence` — GET
`{"online": bool, "busy": bool}` — `busy` = in a lesson now, or a booked lesson starts before an instant one would end.

### `instant_poll` — GET (the heartbeat)
**Keep calling it while the teacher is available** — every 4 s in the foreground. A teacher whose last poll is older
than **60 s** counts as offline and gets no prompts (so background the app = offline; that is intended — push below
brings them back).
```json
{"online": true, "busy": false, "offers": [
  {"requestid": 41, "subject": "Physics", "studentname": "Omar", "duration": 30, "mode": "normal",
   "trial": false, "flex": 2, "secondsleft": 24,
   "note": "Newton's 2nd law with friction", "aisummary": "Newton's 2nd law with friction, grade 10",
   "photourl": "https://…/pluginfile.php/1/local_nit_lessons/instantphoto/41/problem.jpg"}
]}
```
Show each offer as a full-screen / modal prompt with a sound, a countdown from `secondsleft`, and Accept / Decline.
- `flex` = what the lesson is worth (the teacher's share is the usual percentage of it).
- `trial: true` → label it "Trial lesson — paid to the academy" (teacher share 0% by policy).
- `mode: "quick"` → "Quick question (15 min)".
- `aisummary` may be empty at first and appear on a later poll (it is generated in the background).
- An offer that disappears from `offers` before the teacher answers was taken by someone else, rang out, or was
  cancelled — close the prompt silently.

### `instant_accept` — POST `requestid`
```json
{"lesson": { …the normal lesson object, "type": "instant", "status": "in_progress", "trial": false… },
 "joinurl": "https://…/local/nit_lessons/join.php?id=812"}
```
In the app: call **`get_lesson_join(lesson.id)`** and open the room — **within 3 minutes**, or the lesson is
cancelled as a teacher no-show (Flex back to the student, counts against the teacher's reliability).
Errors: `instanttaken` ("Another tutor took this request, or it has ended" — close the prompt),
`timeconflict` (the teacher is busy now), `notenoughflex` (the student's Flex ran out — close the prompt).

### `instant_decline` — POST `requestid`
Returns `{"ok": true}`. The request moves on to the next teachers.

### `instant_reliability` — GET `[teacherid]`
`{"show": true, "prompts": 12, "acceptance": 83, "noshows": 0, "avgresponse": 9}` — for a "Answers 83% of instant
requests" chip on the teacher profile (show only when `show` is true; it needs 5 prompts in 90 days).

## 4. Push notifications (FCM, existing setup)

| provider (`component` / `name`) | to | when | tap → |
|---|---|---|---|
| `local_nit_lessons` / `instantoffer` | teacher | a request was sent to them | open the app → `instant_online` if needed → `instant_poll` → prompt |
| `local_nit_lessons` / `lessonupdate` | student | the instant lesson started (`started`), the teacher did not join (refund / retry), a watched subject's tutor is online | the lessons screen / `instant_subjects` |

A teacher who is not polling (app in background) is **not asked** — presence lapses after 60 s. So the push is for a
teacher who just left the app: tapping it brings them back, `instant_poll` revives presence, and the next wave can
include them. For "always reachable" teachers, keep the poll alive while the app is foregrounded.

## 5. Realtime (optional, web socket)

When the server has the notify-ws relay configured, `instant_subjects` and `instant_presence` also return
`"ws": {"url": "https://…", "path": "/notify-ws/socket.io", "token": "<userid>.<exp>.<hmac>"}` (the token is valid
6 h — call either again to refresh it); otherwise `"ws": null`. Connect with a Socket.IO 4 client:
`io(ws.url, {path: ws.path, auth: {token: ws.token}})` and listen to `notification` events with `type == "instant"`:

| `what` | to | do |
|---|---|---|
| `offer` | teacher | call `instant_poll` now (a new prompt, or an AI summary arrived) |
| `closed` | teacher | close the prompt for `requestid` |
| `status` | student | call `instant_status(requestid)` now |

Keep polling slowly as a safety net (teacher heartbeat ≤ every 20 s — presence needs one within 60 s; student 10 s).
Polling alone is fully supported; the socket is an optimisation.

## 6. Lesson objects

Every lesson (`get_my_lessons`, `get_lesson`, `instant_accept`) now also has:
- `type`: `scheduled` | `instant`
- `trial`: `true` for the student's trial lesson (whole value to the academy).

Instant lessons use the usual lifecycle after accept: `in_progress` → `complete_lesson` / absence reports; if the
teacher never completes it, it auto-completes 72 h after its end (only if the student joined the room).

## 7. Admin settings that change app behaviour

Live lesson settings (web admin): instant on/off, waiting time (120 s), lesson length (30 min), teachers per wave (3),
ring time per wave (30 s), teacher no-show (3 min), extra Flex, peak hours + peak extra, quick question length / price,
trial on/off + price, AI summary on/off, guardians' WhatsApp alert. The app should never hard-code these: everything it
needs comes in `instant_subjects`, `instant_status` and the offers.
